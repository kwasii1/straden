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
        return 'Fetch the InfluxDB time-series performance metrics recorded for this specific run. Returns aggregated statistics (max VUs, total requests, average/max p95 and p99 latency, average/max error rate, checks passed/failed, data transfer) plus a downsampled trend so you can spot when latency or errors spiked. Use this to identify what is slow and when the degradation happened.';
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

        return json_encode($this->summarize($metrics), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
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
