<?php

namespace App\Services;

use App\Enums\ConnectorType;
use App\Models\Connector;
use PDO;
use PDOStatement;

class DatabaseMetricsService
{
    public function __construct(private readonly Connector $connector) {}

    /** @return 'mysql'|'pgsql' */
    private function driver(): string
    {
        return match ($this->connector->type) {
            ConnectorType::MySQL => 'mysql',
            ConnectorType::Postgres => 'pgsql',
            default => throw new \InvalidArgumentException("No database driver for connector type [{$this->connector->type->value}]."),
        };
    }

    private function pdo(): PDO
    {
        return new PDO(
            $this->dsn(),
            $this->connector->username,
            $this->connector->password,
            $this->options(),
        );
    }

    private function dsn(): string
    {
        return match ($this->driver()) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->connector->connectionHost(),
                $this->connector->port,
                $this->connector->database,
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d',
                $this->connector->connectionHost(),
                $this->connector->port,
                $this->connector->database,
                $this->connector->timeout ?? 5,
            ),
        };
    }

    /** @return array<int, mixed> */
    private function options(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ];

        if ($this->driver() === 'mysql') {
            $options[PDO::ATTR_TIMEOUT] = $this->connector->timeout ?? 5;
        }

        return $options;
    }

    public function testConnection(): bool
    {
        // PHP has no PDO driver for MongoDB (and ext-mongodb isn't installed),
        // so the best available check is that the server accepts connections.
        if ($this->connector->type === ConnectorType::MongoDB) {
            return $this->canOpenSocket();
        }

        try {
            $this->pdo()->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function canOpenSocket(): bool
    {
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->connector->connectionHost(), $this->connector->port ?? 27017),
            timeout: (float) ($this->connector->timeout ?? 5),
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * Run a query, throwing instead of returning false on failure.
     *
     * @throws \RuntimeException
     */
    private function query(PDO $pdo, string $sql): PDOStatement
    {
        $statement = $pdo->query($sql);

        if ($statement === false) {
            throw new \RuntimeException("Database query failed: {$sql}");
        }

        return $statement;
    }

    /**
     * Collect a normalized snapshot of database health and performance metrics.
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $pdo = $this->pdo();

        return $this->connector->type === ConnectorType::Postgres
            ? $this->postgresMetrics($pdo)
            : $this->mysqlMetrics($pdo);
    }

    /** @return array<string, mixed> */
    private function mysqlMetrics(PDO $pdo): array
    {
        $status = $this->statusVariables($pdo, [
            'Uptime',
            'Threads_connected',
            'Threads_running',
            'Max_used_connections',
            'Connections',
            'Queries',
            'Slow_queries',
            'Aborted_connects',
            'Innodb_buffer_pool_read_requests',
            'Innodb_buffer_pool_reads',
        ]);

        $maxConnections = $this->maxConnections($pdo);
        $activeQueries = $this->activeQueryCount($pdo);

        $readRequests = (int) ($status['Innodb_buffer_pool_read_requests'] ?? 0);
        $diskReads = (int) ($status['Innodb_buffer_pool_reads'] ?? 0);
        $totalReads = $readRequests + $diskReads;

        return [
            'engine' => 'mysql',
            'uptime_seconds' => $this->intOrNull($status['Uptime'] ?? null),
            'connections' => [
                'current' => $this->intOrNull($status['Threads_connected'] ?? null),
                'running' => $this->intOrNull($status['Threads_running'] ?? null),
                'peak_used' => $this->intOrNull($status['Max_used_connections'] ?? null),
                'max' => $maxConnections,
                'utilization_percent' => $this->percent($status['Threads_connected'] ?? null, $maxConnections),
                'active_queries' => $activeQueries,
            ],
            'throughput' => [
                'total_connections' => $this->intOrNull($status['Connections'] ?? null),
                'total_queries' => $this->intOrNull($status['Queries'] ?? null),
                'slow_queries' => $this->intOrNull($status['Slow_queries'] ?? null),
                'aborted_connects' => $this->intOrNull($status['Aborted_connects'] ?? null),
            ],
            'buffer_pool' => [
                'cache_hit_ratio_percent' => $totalReads > 0 ? round($readRequests / $totalReads * 100, 2) : null,
                'read_requests' => $readRequests,
                'disk_reads' => $diskReads,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function postgresMetrics(PDO $pdo): array
    {
        $row = $this->query(
            $pdo,
            'SELECT datname, numbackends, xact_commit, xact_rollback, blks_read, blks_hit, conflicts, deadlocks, temp_files FROM pg_stat_database WHERE datname = current_database()'
        )->fetch();

        $row = $row ?: (object) [];

        $blksHit = (int) ($row->blks_hit ?? 0);
        $blksRead = (int) ($row->blks_read ?? 0);
        $total = $blksHit + $blksRead;

        $states = collect($this->query(
            $pdo,
            'SELECT state, count(*) AS count FROM pg_stat_activity GROUP BY state'
        )->fetchAll())->pluck('count', 'state')->map(fn ($count) => (int) $count)->all();

        return [
            'engine' => 'postgres',
            'database' => $row->datname ?? $this->connector->database,
            'connections' => [
                'current' => (int) ($row->numbackends ?? 0),
                'by_state' => $states,
            ],
            'cache' => [
                'hit_ratio_percent' => $total > 0 ? round($blksHit / $total * 100, 2) : null,
                'blks_hit' => $blksHit,
                'blks_read' => $blksRead,
            ],
            'transactions' => [
                'committed' => (int) ($row->xact_commit ?? 0),
                'rolled_back' => (int) ($row->xact_rollback ?? 0),
                'rollback_percent' => $this->percent($row->xact_rollback ?? null, ($row->xact_commit ?? 0) + ($row->xact_rollback ?? 0)),
            ],
            'contention' => [
                'conflicts' => (int) ($row->conflicts ?? 0),
                'deadlocks' => (int) ($row->deadlocks ?? 0),
                'temp_files' => (int) ($row->temp_files ?? 0),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function statusVariables(PDO $pdo, array $keys): array
    {
        $result = $this->query(
            $pdo,
            "SHOW GLOBAL STATUS WHERE Variable_name IN ('".implode("','", $keys)."')"
        )->fetchAll();

        $values = [];

        foreach ($result as $row) {
            $values[$row->Variable_name] = $row->Value;
        }

        return $values;
    }

    private function maxConnections(PDO $pdo): ?int
    {
        $row = $this->query($pdo, "SHOW GLOBAL VARIABLES LIKE 'max_connections'")->fetch();

        return isset($row->Value) ? (int) $row->Value : null;
    }

    private function activeQueryCount(PDO $pdo): int
    {
        $row = $this->query(
            $pdo,
            "SELECT count(*) AS count FROM information_schema.processlist WHERE command != 'Sleep'"
        )->fetch();

        return (int) ($row->count ?? 0);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function percent(mixed $value, mixed $total): ?float
    {
        $total = $this->intOrNull($total);

        if ($total === null || $total <= 0) {
            return null;
        }

        return round($this->intOrNull($value) / $total * 100, 2);
    }
}
