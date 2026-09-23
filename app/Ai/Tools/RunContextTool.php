<?php

namespace App\Ai\Tools;

use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunContextTool implements Tool
{
    public function __construct(public Run $run) {}

    public function description(): Stringable|string
    {
        return 'Fetch the summary context for this specific load test run, including the k6 summary metrics, threshold results, run configuration, script details, and target URL. Use this first to understand how the run performed before analyzing InfluxDB time-series data.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode([
            'run' => $this->runContext(),
            'script' => $this->scriptContext(),
            'test' => $this->testContext(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    private function runContext(): array
    {
        return [
            'id' => $this->run->id,
            'slug' => $this->run->slug,
            'status' => $this->run->status,
            'triggered_by' => $this->run->triggered_by,
            'started_at' => $this->run->started_at?->toIso8601String(),
            'completed_at' => $this->run->completed_at?->toIso8601String(),
            'duration_seconds' => $this->run->duration_seconds,
            'vus_max' => $this->run->vus_max,
            'requests_total' => $this->run->requests_total,
            'requests_per_second' => $this->run->requests_per_second,
            'req_duration_p95_ms' => $this->run->req_duration_p95_ms,
            'req_duration_p99_ms' => $this->run->req_duration_p99_ms,
            'error_rate' => $this->run->error_rate,
            'checks_total' => $this->run->checks_total,
            'checks_failed' => $this->run->checks_failed,
            'thresholds_passed' => $this->run->thresholds_passed,
            'thresholds_summary' => $this->run->thresholds_summary,
            'run_config' => $this->run->run_config,
            'exit_code' => $this->run->exit_code,
            'error_message' => $this->run->error_message,
        ];
    }

    /** @return array<string, mixed> */
    private function scriptContext(): array
    {
        return [
            'id' => $this->run->script->id,
            'name' => $this->run->script->name,
            'description' => $this->run->script->description,
            'base_path' => 'scripts/'.$this->run->script->test_id.'/'.$this->run->script->id,
            'is_default' => $this->run->script->is_default,
        ];
    }

    /** @return array<string, mixed> */
    private function testContext(): array
    {
        return [
            'id' => $this->run->script->test->id,
            'name' => $this->run->script->test->name,
            'target_url' => $this->run->script->test->target_url,
            'description' => $this->run->script->test->description,
        ];
    }
}
