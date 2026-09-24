<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\DeleteScriptFileTool;
use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a file or folder from a script directory. The script.js entry point cannot be deleted.')]
class DeleteScriptFile extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        return $this->delegate(new DeleteScriptFileTool($this->script($request)), $request->except('script_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script.')->required(),
            ...(new DeleteScriptFileTool(new Script))->schema($schema),
        ];
    }
}
