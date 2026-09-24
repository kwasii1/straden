<?php

namespace App\Mcp\Tools;

use App\Jobs\RunTestJob;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Queue a k6 load test run for a script. Returns the run ID; poll get-run-status until it finishes, then inspect results with get-run-metrics, read-run-log and get-run-context.')]
class StartRun extends StradenTool
{
    protected string $ability = 'mcp:run';

    protected function execute(Request $request): Response
    {
        $script = $this->script($request);

        $active = $script->runs()->whereIn('status', ['queued', 'running'])->latest()->first();

        if ($active !== null) {
            return Response::error("Script already has an active run ({$active->id}, status: {$active->status}). Wait for it to finish or cancel it first.");
        }

        $run = $script->runs()->create([
            'status' => 'queued',
            'triggered_by' => 'mcp',
            'triggered_by_user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        RunTestJob::dispatch($run);

        return $this->json([
            'run_id' => $run->id,
            'status' => $run->status,
            'message' => 'Run queued.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script to run.')->required(),
        ];
    }
}
