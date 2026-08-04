<?php

namespace App\Ai\Tools;

use App\Models\Script;
use App\Services\InfluxDbService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ScriptInsightsTool implements Tool
{
    public function __construct(public Script $script) {}

    public function description(): Stringable|string
    {
        return 'Fetch recent run results and InfluxDB performance metrics for this script. Returns per-run summary metrics, which k6 thresholds failed in recent runs, and InfluxDB time-series aggregates (average latency, max VUs, request rate, error rate, checks). Use this before editing the script to understand how it performed and to decide whether thresholds or test parameters should be adjusted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $runs = $this->script->runs()->latest()->limit(5)->get();

        return json_encode([
            'script' => $this->scriptContext(),
            'recent_runs' => $runs->map(fn ($run) => $this->runContext($run))->values()->all(),
            'threshold_analysis' => $this->thresholdAnalysis($runs),
            'influx' => $this->influxInsights($runs),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private function scriptContext(): array
    {
        return [
            'id' => $this->script->id,
            'name' => $this->script->name,
            'description' => $this->script->description,
            'test_name' => $this->script->test->name,
            'target_url' => $this->script->test->target_url,
            'base_path' => 'scripts/'.$this->script->test_id.'/'.$this->script->id,
        ];
    }

    private function runContext($run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'triggered_by' => $run->triggered_by,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'duration_seconds' => $run->duration_seconds,
            'vus_max' => $run->vus_max,
            'requests_total' => $run->requests_total,
            'requests_per_second' => $run->requests_per_second,
            'req_duration_p95_ms' => $run->req_duration_p95_ms,
            'req_duration_p99_ms' => $run->req_duration_p99_ms,
            'error_rate' => $run->error_rate,
            'checks_total' => $run->checks_total,
            'checks_failed' => $run->checks_failed,
            'thresholds_passed' => $run->thresholds_passed,
            'thresholds_summary' => $run->thresholds_summary,
            'exit_code' => $run->exit_code,
            'error_message' => $run->error_message,
        ];
    }

    private function thresholdAnalysis(iterable $runs): array
    {
        $failed = [];

        foreach ($runs as $run) {
            foreach ($run->thresholds_summary ?? [] as $threshold) {
                if (empty($threshold['ok'])) {
                    $failed[$threshold['name']][] = $run->id;
                }
            }
        }

        $failedThresholds = array_map(
            fn (string $name, array $runIds) => [
                'name' => $name,
                'run_ids' => $runIds,
                'run_count' => count($runIds),
            ],
            array_keys($failed),
            $failed
        );

        return [
            'failed_thresholds' => array_values($failedThresholds),
            'note' => 'Thresholds listed as failed exceeded their limits in at least one recent run. Consider raising them, fixing the underlying performance issue, or removing them if no longer relevant.',
        ];
    }

    private function influxInsights(iterable $runs): array
    {
        try {
            $service = InfluxDbService::fromInfluxDbConnector();
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'error' => 'InfluxDB is not configured: '.$e->getMessage(),
            ];
        }

        $perRun = [];

        foreach ($runs as $run) {
            try {
                $metrics = $service->metricsForRun($run->id);

                $perRun[] = [
                    'run_id' => $run->id,
                    'vus_max' => $this->maxOf($metrics['vus']['values'] ?? []),
                    'avg_p95_ms' => $this->roundNullable($this->averageOf($metrics['response_time']['p95'] ?? [])),
                    'avg_p99_ms' => $this->roundNullable($this->averageOf($metrics['response_time']['p99'] ?? [])),
                    'total_requests' => (int) array_sum($metrics['request_rate']['values'] ?? []),
                    'avg_error_rate_percent' => $this->roundNullable($this->averageOf($metrics['error_rate']['values'] ?? [])),
                    'checks_passed' => (int) array_sum($metrics['checks']['passed'] ?? []),
                    'checks_failed' => (int) array_sum($metrics['checks']['failed'] ?? []),
                ];
            } catch (\Throwable $e) {
                $perRun[] = [
                    'run_id' => $run->id,
                    'error' => 'InfluxDB query failed: '.$e->getMessage(),
                ];
            }
        }

        return [
            'available' => true,
            'runs' => $perRun,
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

    private function maxOf(array $values): ?int
    {
        $values = array_values(array_filter($values, fn ($v) => is_numeric($v)));

        if (empty($values)) {
            return null;
        }

        return (int) max($values);
    }

    private function roundNullable(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}
