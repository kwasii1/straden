<?php

namespace App\Mcp\Tools;

use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the current status and summary metrics of a run. Poll this after start-run until the status is no longer "queued" or "running".')]
class GetRunStatus extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->json(self::summary($this->run($request)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run.')->required(),
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(Run $run): array
    {
        return [
            'id' => $run->id,
            'script_id' => $run->script_id,
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
            'exit_code' => $run->exit_code,
            'error_message' => $run->error_message,
        ];
    }
}
