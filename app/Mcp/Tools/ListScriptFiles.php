<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ListScriptFilesTool;
use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all files in a script directory.')]
class ListScriptFiles extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new ListScriptFilesTool($this->script($request)), $request->except('script_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script.')->required(),
            ...(new ListScriptFilesTool(new Script))->schema($schema),
        ];
    }
}
