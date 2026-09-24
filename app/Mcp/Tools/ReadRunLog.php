<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ReadRunLogTool;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Read the raw k6 console output for a run (warnings, per-VU errors, console.log output). Only available when the project persists run logs.')]
class ReadRunLog extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new ReadRunLogTool($this->run($request)), $request->except('run_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run.')->required(),
            ...(new ReadRunLogTool(new Run))->schema($schema),
        ];
    }
}
