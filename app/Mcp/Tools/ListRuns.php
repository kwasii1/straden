<?php

namespace App\Mcp\Tools;

use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List recent runs for a script or a test, newest first, with their status and summary metrics.')]
class ListRuns extends StradenTool
{
    protected function execute(Request $request): Response
    {
        $validated = $request->validate([
            'script_id' => ['required_without:test_id', 'nullable', 'string'],
            'test_id' => ['required_without:script_id', 'nullable', 'string'],
            'status' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = filled($validated['script_id'] ?? null)
            ? $this->script($request)->runs()
            : $this->test($request)->runs();

        $runs = $query
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('runs.status', $status))
            ->latest('runs.created_at')
            ->limit($validated['limit'] ?? 20)
            ->get();

        return $this->json($runs->map(fn (Run $run) => GetRunStatus::summary($run))->values()->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('Filter by script UUID. Either script_id or test_id is required.'),
            'test_id' => $schema->string()->description('Filter by test UUID.'),
            'status' => $schema->string()->enum(['queued', 'running', 'completed', 'failed', 'aborted'])->description('Optional status filter.'),
            'limit' => $schema->integer()->description('Maximum number of runs to return (default 20, max 100).'),
        ];
    }
}
