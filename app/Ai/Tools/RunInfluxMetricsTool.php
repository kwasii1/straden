<?php

namespace App\Ai\Tools;

use App\Models\Run;
use App\Services\InfluxDbService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunInfluxMetricsTool implements Tool
{
    public function __construct(public Run $run) {}

    public function description(): Stringable|string
    {
        return 'Fetch the InfluxDB time-series performance metrics recorded for this specific run. Returns aggregated statistics (max VUs, total requests, average/max p95 and p99 latency, average/max error rate, checks passed/failed, data transfer), a per-endpoint breakdown (requests, p95/p99, error rate for each endpoint tested), and a downsampled trend so you can spot when latency or errors spiked. Use this to identify what is slow, which endpoint is slowest, and when the degradation happened.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $service = InfluxDbService::fromInfluxDbConnector();
            $metrics = $service->metricsForRun($this->run->id);
        } catch (\Throwable $e) {
            return json_encode([
                'available' => false,
                'error' => 'InfluxDB is not configured or the metrics are unavailable: '.$e->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode(
            array_merge($this->summarize($metrics), [
                'per_endpoint' => $this->perEndpointBreakdown($service),
            ]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private function perEndpointBreakdown(InfluxDbService $service): array
    {
        $runId = $this->run->id;

        try {
            $requests = $this->groupByEndpoint($service->query(
                sprintf('SELECT count("value") FROM "http_reqs" WHERE "run_id"=\'%s\' GROUP BY "name"', $runId)
            ), 1);

            $latency = $this->groupByEndpoint($service->query(
                sprintf('SELECT percentile("value", 95) AS "p95", percentile("value", 99) AS "p99" FROM "http_req_duration" WHERE "run_id"=\'%s\' GROUP BY "name"', $runId)
            ), 1);

            $errors = $this->groupByEndpoint($service->query(
                sprintf('SELECT mean("value") * 100 AS "error_rate" FROM "http_req_failed" WHERE "run_id"=\'%s\' GROUP BY "name"', $runId)
            ), 1);
        } catch (\Throwable) {
            return [];
        }

        $totalRequests = (int) array_sum(array_column($requests, 'count'));

        $breakdown = [];

        foreach ($requests as $endpoint => $requestRow) {
            $latencyRow = $latency[$endpoint] ?? null;
            $errorRow = $errors[$endpoint] ?? null;

            $breakdown[] = [
                'name' => $endpoint,
                'total_requests' => (int) $requestRow['count'],
                'requests_share_percent' => $totalRequests > 0 ? round($requestRow['count'] / $totalRequests * 100, 2) : 0,
                'p95_ms' => $this->roundNullable(($latencyRow['p95'] ?? null)),
                'p99_ms' => $this->roundNullable(($latencyRow['p99'] ?? null)),
                'error_rate_percent' => $this->roundNullable(($errorRow['error_rate'] ?? null)),
            ];
        }

        usort($breakdown, fn (array $a, array $b) => $b['total_requests'] <=> $a['total_requests']);

        return $breakdown;
    }

    /**
     * Map InfluxDB GROUP BY "name" results into a [endpoint => column => value] map.
     */
    private function groupByEndpoint(array $results, int $valueOffset): array
    {
        $map = [];

        foreach ($results[0]['series'] ?? [] as $series) {
            $endpoint = $series['tags']['name'] ?? null;
            $row = $series['values'][0] ?? null;

            if ($endpoint === null || $row === null) {
                continue;
            }

            $map[$endpoint] = array_merge(
                ['count' => $row[$valueOffset] ?? 0],
                $this->mapColumns($series['columns'] ?? [], $row)
            );
        }

        return $map;
    }

    private function mapColumns(array $columns, array $row): array
    {
        $mapped = [];

        foreach ($columns as $index => $column) {
            if ($column === 'time') {
                continue;
            }

            $mapped[$column] = $row[$index] ?? null;
        }

        return $mapped;
    }

    private function summarize(array $metrics): array
    {
        $vus = $metrics['vus']['values'] ?? [];
        $requestRate = $metrics['request_rate']['values'] ?? [];
        $responseTime = $metrics['response_time'] ?? [];
        $errorRate = $metrics['error_rate']['values'] ?? [];
        $checks = $metrics['checks'] ?? [];
        $dataSent = $metrics['data_transfer']['sent'] ?? [];
        $dataReceived = $metrics['data_transfer']['received'] ?? [];

        $p95 = $responseTime['p95'] ?? [];
        $p99 = $responseTime['p99'] ?? [];

        return [
            'available' => true,
            'run_id' => $this->run->id,
            'aggregates' => [
                'vus_max' => $this->maxOf($vus),
                'total_requests' => (int) array_sum($requestRate),
                'avg_p95_ms' => $this->roundNullable($this->averageOf($p95)),
                'avg_p99_ms' => $this->roundNullable($this->averageOf($p99)),
                'max_p95_ms' => $this->roundNullable($this->maxOf($p95)),
                'max_p99_ms' => $this->roundNullable($this->maxOf($p99)),
                'avg_error_rate_percent' => $this->roundNullable($this->averageOf($errorRate)),
                'max_error_rate_percent' => $this->roundNullable($this->maxOf($errorRate)),
                'checks_passed' => (int) array_sum($checks['passed'] ?? []),
                'checks_failed' => (int) array_sum($checks['failed'] ?? []),
                'data_sent_bytes' => (int) array_sum($dataSent),
                'data_received_bytes' => (int) array_sum($dataReceived),
            ],
            'peaks' => [
                'slowest_latency' => $this->peak($responseTime['labels'] ?? [], $p95, 'p95'),
                'highest_error_rate' => $this->peak($metrics['error_rate']['labels'] ?? [], $errorRate, 'error_rate'),
                'peak_vus' => $this->peak($metrics['vus']['labels'] ?? [], $vus, 'vus'),
            ],
            'trend' => [
                'vus' => $this->downsample($metrics['vus']['labels'] ?? [], $vus),
                'request_rate' => $this->downsample($metrics['request_rate']['labels'] ?? [], $requestRate),
                'p95_ms' => $this->downsample($responseTime['labels'] ?? [], $p95),
                'p99_ms' => $this->downsample($responseTime['labels'] ?? [], $p99),
                'error_rate' => $this->downsample($metrics['error_rate']['labels'] ?? [], $errorRate),
            ],
        ];
    }

    /**
     * Reduce a time series to at most $limit evenly-spaced points.
     */
    private function downsample(array $labels, array $values, int $limit = 30): array
    {
        $count = count($labels);

        if ($count <= $limit) {
            return $this->pairs($labels, $values);
        }

        $step = $count / $limit;
        $sampled = [];

        for ($i = 0; $i < $limit; $i++) {
            $index = (int) floor($i * $step);
            $sampled[] = [$labels[$index] ?? '', $values[$index] ?? 0];
        }

        return $sampled;
    }

    private function pairs(array $labels, array $values): array
    {
        $pairs = [];

        foreach ($labels as $i => $label) {
            $pairs[] = [$label, $values[$i] ?? 0];
        }

        return $pairs;
    }

    private function peak(array $labels, array $values, string $name): array
    {
        $maxIndex = null;
        $maxValue = null;

        foreach ($values as $i => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            if ($maxValue === null || (float) $value > (float) $maxValue) {
                $maxValue = $value;
                $maxIndex = $i;
            }
        }

        if ($maxIndex === null) {
            return ['metric' => $name, 'value' => null, 'at' => null];
        }

        return [
            'metric' => $name,
            'value' => $maxValue,
            'at' => $labels[$maxIndex] ?? null,
        ];
    }

    private function averageOf(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => is_numeric($v)));

        if (empty($values)) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private function maxOf(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => is_numeric($v)));

        if (empty($values)) {
            return null;
        }

        return (float) max($values);
    }

    private function roundNullable(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}
