<?php

namespace App\Services;

use App\Models\Connector;
use Redis;

class RedisMetricsService
{
    public function __construct(private readonly Connector $connector) {}

    private function client(): ?Redis
    {
        if (! class_exists(Redis::class)) {
            return null;
        }

        $client = new Redis;

        $connected = $client->connect(
            $this->connector->host,
            $this->connector->port,
            $this->connector->timeout ?? 5,
        );

        if (! $connected) {
            return null;
        }

        if ($this->connector->password) {
            $client->auth($this->connector->password);
        }

        if ($this->connector->database !== null) {
            $client->select((int) $this->connector->database);
        }

        return $client;
    }

    public function testConnection(): bool
    {
        try {
            $client = $this->client();

            return $client !== null && $client->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Collect a normalized snapshot of Redis health and performance metrics.
     */
    public function metrics(): array
    {
        $client = $this->client();

        if ($client === null) {
            return ['available' => false, 'error' => 'Redis is unreachable or the phpredis extension is not installed.'];
        }

        try {
            $info = $client->info();
            $slowLog = $this->slowLog($client);

            return [
                'available' => true,
                'server' => [
                    'version' => $info['server']['redis_version'] ?? null,
                    'uptime_seconds' => $this->intOrNull($info['server']['uptime_in_seconds'] ?? null),
                    'mode' => $info['server']['redis_mode'] ?? null,
                ],
                'memory' => [
                    'used_bytes' => $this->intOrNull($info['memory']['used_memory'] ?? null),
                    'used_peak_bytes' => $this->intOrNull($info['memory']['used_memory_peak'] ?? null),
                    'fragmentation_ratio' => isset($info['memory']['mem_fragmentation_ratio'])
                        ? round((float) $info['memory']['mem_fragmentation_ratio'], 2)
                        : null,
                ],
                'clients' => [
                    'connected' => $this->intOrNull($info['clients']['connected_clients'] ?? null),
                    'blocked' => $this->intOrNull($info['clients']['blocked_clients'] ?? null),
                ],
                'stats' => [
                    'keys' => (int) $client->dbSize(),
                    'total_commands' => $this->intOrNull($info['stats']['total_commands_processed'] ?? null),
                    'total_connections' => $this->intOrNull($info['stats']['total_connections_received'] ?? null),
                    'expired_keys' => $this->intOrNull($info['stats']['expired_keys'] ?? null),
                    'evicted_keys' => $this->intOrNull($info['stats']['evicted_keys'] ?? null),
                    'hit_ratio_percent' => $this->hitRatio($info['stats'] ?? []),
                ],
                'slow_log' => $slowLog,
            ];
        } finally {
            $client->close();
        }
    }

    private function slowLog(Redis $client): array
    {
        try {
            return array_map(fn (array $entry) => [
                'id' => (int) $entry[0],
                'timestamp' => $entry[1],
                'duration_micros' => (int) $entry[2],
                'command' => implode(' ', (array) $entry[3]),
            ], $client->slowlog('GET', 10));
        } catch (\Throwable) {
            return [];
        }
    }

    private function hitRatio(array $stats): ?float
    {
        $hits = $this->intOrNull($stats['keyspace_hits'] ?? null);
        $misses = $this->intOrNull($stats['keyspace_misses'] ?? null);

        if ($hits === null || $misses === null || ($hits + $misses) === 0) {
            return null;
        }

        return round($hits / ($hits + $misses) * 100, 2);
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
