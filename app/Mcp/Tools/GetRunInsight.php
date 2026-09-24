<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the AI-generated insight report for a run, if one has been generated in Straden.')]
class GetRunInsight extends StradenTool
{
    protected function execute(Request $request): Response
    {
        $insight = $this->run($request)->insight;

        if ($insight === null) {
            return $this->json(['available' => false, 'message' => 'No insight has been generated for this run.']);
        }

        return $this->json([
            'available' => $insight->status === 'completed',
            'status' => $insight->status,
            'report' => $insight->report,
            'error' => $insight->error,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run.')->required(),
        ];
    }
}
