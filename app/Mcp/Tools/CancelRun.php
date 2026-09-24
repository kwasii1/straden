<?php

namespace App\Mcp\Tools;

use App\Services\RunResultService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Cancel a queued or running run. Queued runs are aborted immediately; running k6 processes are signalled to stop and the status updates shortly after.')]
class CancelRun extends StradenTool
{
    protected string $ability = 'mcp:run';

    protected function execute(Request $request): Response
    {
        $run = $this->run($request);

        if (! in_array($run->status, ['queued', 'running'], true)) {
            return Response::error("Run is not active (status: {$run->status}).");
        }

        RunResultService::cancel($run);

        return $this->json(['run_id' => $run->id, 'status' => $run->refresh()->status, 'message' => 'Cancellation requested.']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run to cancel.')->required(),
        ];
    }
}
