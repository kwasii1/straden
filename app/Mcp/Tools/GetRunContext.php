<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\RunContextTool;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the full context of a run: summary metrics, thresholds, k6 config, the script content, the test and target URL.')]
class GetRunContext extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new RunContextTool($this->run($request)), $request->except('run_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run.')->required(),
            ...(new RunContextTool(new Run))->schema($schema),
        ];
    }
}
