<?php

namespace App\Ai\Tools;

use App\Models\Connector;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ScanContextTool implements Tool
{
    public function __construct(private Test $test) {}

    public function description(): Stringable|string
    {
        return 'Scan the test environment context including connectors, repositories, existing scripts, and recent run results. Use this tool first to understand what endpoints and scenarios to test before creating a test plan.';
    }

    public function handle(Request $request): Stringable|string
    {
        $context = [
            'test' => $this->testContext(),
            'project' => $this->projectContext(),
            'connectors' => $this->connectorsContext(),
            'repositories' => $this->repositoriesContext(),
            'scripts' => $this->scriptsContext(),
            'runs' => $this->runsContext(),
        ];

        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    private function testContext(): array
    {
        return [
            'id' => $this->test->id,
            'name' => $this->test->name,
            'target_url' => $this->test->target_url,
            'description' => $this->test->description,
        ];
    }

    /** @return array<string, mixed> */
    private function projectContext(): array
    {
        return [
            'id' => $this->test->project->id,
            'name' => $this->test->project->name,
            'description' => $this->test->project->description,
        ];
    }

    /** @return array<int, mixed> */
    private function connectorsContext(): array
    {
        return $this->test->project->connectors->map(function (Connector $connector) {
            return [
                'id' => $connector->id,
                'name' => $connector->name,
                'type' => $connector->type->value,
                'host' => $connector->host,
                'port' => $connector->port,
                'database' => $connector->database,
                'is_system' => $connector->is_system,
                'last_tested_at' => $connector->last_tested_at?->toIso8601String(),
                'last_test_successful' => $connector->last_test_successful,
            ];
        })->values()->all();
    }

    /** @return array<int, mixed> */
    private function repositoriesContext(): array
    {
        return $this->test->project->repositories->map(function (Repository $repo) {
            return [
                'id' => $repo->id,
                'name' => $repo->name,
                'type' => $repo->type,
                'sync_status' => $repo->sync_status,
                'last_synced_at' => $repo->last_synced_at?->toIso8601String(),
                'last_commit_sha' => $repo->last_commit_sha,
                'file_tree' => $repo->file_tree,
            ];
        })->values()->all();
    }

    /** @return array<int, mixed> */
    private function scriptsContext(): array
    {
        return $this->test->scripts()->with(['runs' => function ($query) {
            $query->latest()->limit(5);
        }])->get()->map(function (Script $script) {
            return [
                'id' => $script->id,
                'name' => $script->name,
                'description' => $script->description,
                'is_default' => $script->is_default,
                'last_run_at' => $script->last_run_at?->toIso8601String(),
                'recent_runs' => $script->runs->map(function (Run $run) {
                    return [
                        'id' => $run->id,
                        'status' => $run->status,
                        'started_at' => $run->started_at?->toIso8601String(),
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
                })->values()->all(),
            ];
        })->values()->all();
    }

    /** @return array<int, mixed> */
    private function runsContext(): array
    {
        return $this->test->runs()->latest()->limit(10)->get()->map(function (Run $run) {
            return [
                'id' => $run->id,
                'script_name' => $run->script->name,
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
        })->values()->all();
    }
}
