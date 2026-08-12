<?php

namespace App\Services;

use App\Models\Connector;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class InfluxDbService
{
    public function __construct(private readonly Connector $connector) {}

    private function baseUrl(): string
    {
        return sprintf(
            '%s://%s:%s',
            $this->connector->ssl_enabled ? 'https' : 'http',
            $this->connector->host,
            $this->connector->port,
        );
    }

    public function query(string $influxQl): array
    {
        $response = Http::timeout($this->connector->timeout ?? 5)
            ->withOptions(['verify' => $this->connector->verify_ssl])
            ->get($this->baseUrl().'/query', [
                'db' => $this->connector->database,
                'q' => $influxQl,
                'epoch' => 'ms',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('InfluxDB query failed: '.$response->body());
        }

        $json = $response->json();

        return $json['results'] ?? [];
    }

    public function showDatabases(): array
    {
        $results = $this->query('SHOW DATABASES');

        if (empty($results[0]['series'][0]['values'])) {
            return [];
        }

        return array_column($results[0]['series'][0]['values'], 0);
    }

    public function write(array $points, ?string $precision = null): void
    {
        $body = implode("\n", $points);

        $queryParams = ['db' => $this->connector->database];
        if ($precision) {
            $queryParams['precision'] = $precision;
        }

        $response = Http::timeout($this->connector->timeout ?? 5)
            ->withOptions(['verify' => $this->connector->verify_ssl])
            ->withBody($body, 'text/plain')
            ->post($this->baseUrl().'/write?'.http_build_query($queryParams));

        if ($response->failed()) {
            throw new \RuntimeException('InfluxDB write failed: '.$response->body());
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout($this->connector->timeout ?? 5)
                ->withOptions(['verify' => $this->connector->verify_ssl])
                ->get($this->baseUrl().'/ping');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function fromInfluxDbConnector(): self
    {
        return new self(Connector::influxDb());
    }

    /**
     * Build a time-range clause for a run's execution window. Without explicit
     * bounds, `GROUP BY time(...) fill(0)` queries extend to `now()`, returning
     * thousands of empty buckets for old runs (huge payloads and stretched
     * chart axes), so every time-series query is scoped to when the run ran.
     */
    public static function runTimeRange(?CarbonInterface $start, ?CarbonInterface $end): ?array
    {
        if ($start === null) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end ?? now(),
        ];
    }

    public function metricsForRun(string $runId, ?string $endpoint = null, ?array $timeRange = null): array
    {
        $perEndpoint = $endpoint !== null;

        return [
            'vus' => $perEndpoint ? ['labels' => [], 'values' => []] : $this->vusOverTime($runId, $timeRange),
            'request_rate' => $this->requestRateOverTime($runId, $endpoint, $timeRange),
            'response_time' => $this->responseTimeOverTime($runId, $endpoint, $timeRange),
            'error_rate' => $this->errorRateOverTime($runId, $endpoint, $timeRange),
            'checks' => $perEndpoint ? ['labels' => [], 'passed' => [], 'failed' => []] : $this->checksOverTime($runId, $timeRange),
            'data_transfer' => $perEndpoint ? ['labels' => [], 'sent' => [], 'received' => []] : $this->dataTransferOverTime($runId, $timeRange),
            'response_codes' => $this->responseCodesOverTime($runId, $endpoint, $timeRange),
        ];
    }

    public function endpointsForRun(string $runId): array
    {
        $result = $this->query(
            sprintf('SHOW TAG VALUES FROM "http_reqs" WITH KEY = "name" WHERE "run_id"=\'%s\'', $runId)
        );

        $values = $result[0]['series'][0]['values'] ?? [];

        return array_values(array_map(fn (array $row) => $row[1], $values));
    }

    public function endpointSummary(string $runId, string $endpoint): array
    {
        $requests = $this->query(
            sprintf('SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\' AND "name"=\'%s\'', $runId, $endpoint)
        );

        $latency = $this->query(
            sprintf('SELECT percentile("value", 95) AS "p95", percentile("value", 99) AS "p99" FROM "http_req_duration" WHERE "run_id"=\'%s\' AND "name"=\'%s\'', $runId, $endpoint)
        );

        $errors = $this->query(
            sprintf('SELECT mean("value") * 100 AS "error_rate" FROM "http_req_failed" WHERE "run_id"=\'%s\' AND "name"=\'%s\'', $runId, $endpoint)
        );

        $requestsRow = $requests[0]['series'][0]['values'][0] ?? null;
        $latencyRow = $latency[0]['series'][0]['values'][0] ?? null;
        $errorRow = $errors[0]['series'][0]['values'][0] ?? null;

        return [
            'total_requests' => $requestsRow !== null ? (int) $requestsRow[1] : 0,
            'p95_ms' => $latencyRow !== null ? round((float) $latencyRow[1], 2) : null,
            'p99_ms' => $latencyRow !== null ? round((float) $latencyRow[2], 2) : null,
            'error_rate_percent' => $errorRow !== null ? round((float) $errorRow[1], 2) : null,
        ];
    }

    public function vusOverTime(string $runId, ?array $timeRange = null): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT max("value") FROM "vus" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(none)', $runId, $this->timeRangeClause($timeRange))
        );
    }

    public function requestRateOverTime(string $runId, ?string $endpoint = null, ?array $timeRange = null): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\'%s%s GROUP BY time(5s) fill(0)', $runId, $this->endpointClause($endpoint), $this->timeRangeClause($timeRange))
        );
    }

    public function responseTimeOverTime(string $runId, ?string $endpoint = null, ?array $timeRange = null): array
    {
        $q = sprintf(
            'SELECT percentile("value", 95) AS "p95", percentile("value", 99) AS "p99" FROM "http_req_duration" WHERE "run_id"=\'%s\'%s%s GROUP BY time(5s) fill(none)',
            $runId,
            $this->endpointClause($endpoint),
            $this->timeRangeClause($timeRange)
        );

        return $this->queryMultiSeries($q, ['p95', 'p99']);
    }

    public function errorRateOverTime(string $runId, ?string $endpoint = null, ?array $timeRange = null): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT mean("value") * 100 FROM "http_req_failed" WHERE "run_id"=\'%s\'%s%s GROUP BY time(5s) fill(0)', $runId, $this->endpointClause($endpoint), $this->timeRangeClause($timeRange))
        );
    }

    public function checksOverTime(string $runId, ?array $timeRange = null): array
    {
        $total = $this->queryTimeSeries(
            sprintf('SELECT count("value") FROM "checks" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->timeRangeClause($timeRange))
        );

        $passed = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "checks" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->timeRangeClause($timeRange))
        );

        $labels = $total['labels'] ?: $passed['labels'];
        $passedValues = $passed['values'];
        $failedValues = [];

        foreach ($total['values'] as $i => $t) {
            $p = $passedValues[$i] ?? 0;
            $failedValues[] = max(0, $t - $p);
        }

        return [
            'labels' => $labels,
            'passed' => $passedValues,
            'failed' => $failedValues,
        ];
    }

    public function dataTransferOverTime(string $runId, ?array $timeRange = null): array
    {
        $sent = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "data_sent" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->timeRangeClause($timeRange))
        );

        $received = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "data_received" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->timeRangeClause($timeRange))
        );

        return [
            'labels' => $sent['labels'] ?: $received['labels'],
            'sent' => $sent['values'],
            'received' => $received['values'],
        ];
    }

    public function responseCodeBreakdown(string $runId, ?string $endpoint = null): array
    {
        $result = $this->query(
            sprintf('SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\'%s GROUP BY "status"', $runId, $this->endpointClause($endpoint))
        );

        $statusCounts = [];

        foreach ($result[0]['series'] ?? [] as $series) {
            $status = $series['tags']['status'] ?? null;
            $count = $series['values'][0][1] ?? 0;

            if ($status === null) {
                continue;
            }

            $group = $this->statusGroup((int) $status);
            $statusCounts[$group] = ($statusCounts[$group] ?? 0) + (int) $count;
        }

        $total = (int) array_sum($statusCounts);

        $groups = [];
        foreach (['2xx', '3xx', '4xx', '5xx'] as $group) {
            $count = $statusCounts[$group] ?? 0;
            $groups[$group] = [
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 2) : 0,
            ];
        }

        return [
            'total' => $total,
            'groups' => $groups,
        ];
    }

    public function responseCodesOverTime(string $runId, ?string $endpoint = null, ?array $timeRange = null): array
    {
        $prefixes = ['2', '3', '4', '5'];
        $datasets = [];
        $allLabels = [];

        foreach ($prefixes as $prefix) {
            $result = $this->queryTimeSeries(
                sprintf(
                    'SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\' AND "status" =~ /^%s/%s%s GROUP BY time(5s) fill(0)',
                    $runId,
                    $prefix,
                    $this->endpointClause($endpoint),
                    $this->timeRangeClause($timeRange)
                )
            );

            $key = $prefix.'xx';

            if (! empty($result['labels'])) {
                $allLabels = $result['labels'];
            }

            $datasets[$key] = $result['values'];
        }

        return array_merge(
            ['labels' => $allLabels],
            $datasets
        );
    }

    private function statusGroup(int $code): string
    {
        $prefix = (int) floor($code / 100);

        return match ($prefix) {
            2 => '2xx',
            3 => '3xx',
            4 => '4xx',
            5 => '5xx',
            default => 'other',
        };
    }

    public function queryTimeSeries(string $influxQl): array
    {
        $result = $this->query($influxQl);
        $series = $result[0]['series'][0] ?? null;

        if (! $series || empty($series['values'])) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];

        foreach ($series['values'] as $row) {
            $labels[] = date('H:i:s', (int) ($row[0] / 1000));
            $values[] = $row[1] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function queryMultiSeries(string $influxQl, array $columns): array
    {
        $result = $this->query($influxQl);
        $series = $result[0]['series'][0] ?? null;

        if (! $series || empty($series['values'])) {
            return array_merge(
                ['labels' => []],
                array_fill_keys($columns, [])
            );
        }

        $labels = [];
        $datasets = array_fill_keys($columns, []);

        $colIndexes = array_flip($series['columns']);

        foreach ($series['values'] as $row) {
            $labels[] = date('H:i:s', (int) ($row[0] / 1000));

            foreach ($columns as $col) {
                $idx = $colIndexes[$col] ?? null;
                $datasets[$col][] = $idx !== null ? ($row[$idx] ?? 0) : 0;
            }
        }

        return array_merge(['labels' => $labels], $datasets);
    }

    private function endpointClause(?string $endpoint): string
    {
        return $endpoint !== null
            ? " AND \"name\"='".$endpoint."'"
            : '';
    }

    private function timeRangeClause(?array $timeRange): string
    {
        $clauses = [];

        if (isset($timeRange['start'])) {
            $clauses[] = 'time >= \''.$timeRange['start']->copy()->utc()->format('Y-m-d\TH:i:s\Z').'\'';
        }

        if (isset($timeRange['end'])) {
            $clauses[] = 'time <= \''.$timeRange['end']->copy()->utc()->format('Y-m-d\TH:i:s\Z').'\'';
        }

        return $clauses === [] ? '' : ' AND '.implode(' AND ', $clauses);
    }
}
