<?php

namespace App\Services;

use App\Models\Connector;
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

    public function metricsForRun(string $runId, ?string $endpoint = null): array
    {
        $perEndpoint = $endpoint !== null;

        return [
            'vus' => $perEndpoint ? ['labels' => [], 'values' => []] : $this->vusOverTime($runId),
            'request_rate' => $this->requestRateOverTime($runId, $endpoint),
            'response_time' => $this->responseTimeOverTime($runId, $endpoint),
            'error_rate' => $this->errorRateOverTime($runId, $endpoint),
            'checks' => $perEndpoint ? ['labels' => [], 'passed' => [], 'failed' => []] : $this->checksOverTime($runId),
            'data_transfer' => $perEndpoint ? ['labels' => [], 'sent' => [], 'received' => []] : $this->dataTransferOverTime($runId),
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

    public function vusOverTime(string $runId): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT max("value") FROM "vus" WHERE "run_id"=\'%s\' GROUP BY time(5s) fill(none)', $runId)
        );
    }

    public function requestRateOverTime(string $runId, ?string $endpoint = null): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->endpointClause($endpoint))
        );
    }

    public function responseTimeOverTime(string $runId, ?string $endpoint = null): array
    {
        $q = sprintf(
            'SELECT percentile("value", 95) AS "p95", percentile("value", 99) AS "p99" FROM "http_req_duration" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(none)',
            $runId,
            $this->endpointClause($endpoint)
        );

        return $this->queryMultiSeries($q, ['p95', 'p99']);
    }

    public function errorRateOverTime(string $runId, ?string $endpoint = null): array
    {
        return $this->queryTimeSeries(
            sprintf('SELECT mean("value") * 100 FROM "http_req_failed" WHERE "run_id"=\'%s\'%s GROUP BY time(5s) fill(0)', $runId, $this->endpointClause($endpoint))
        );
    }

    public function checksOverTime(string $runId): array
    {
        $total = $this->queryTimeSeries(
            sprintf('SELECT count("value") FROM "checks" WHERE "run_id"=\'%s\' GROUP BY time(5s) fill(0)', $runId)
        );

        $passed = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "checks" WHERE "run_id"=\'%s\' GROUP BY time(5s) fill(0)', $runId)
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

    public function dataTransferOverTime(string $runId): array
    {
        $sent = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "data_sent" WHERE "run_id"=\'%s\' GROUP BY time(5s) fill(0)', $runId)
        );

        $received = $this->queryTimeSeries(
            sprintf('SELECT sum("value") FROM "data_received" WHERE "run_id"=\'%s\' GROUP BY time(5s) fill(0)', $runId)
        );

        return [
            'labels' => $sent['labels'] ?: $received['labels'],
            'sent' => $sent['values'],
            'received' => $received['values'],
        ];
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
}
