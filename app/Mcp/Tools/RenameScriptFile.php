<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\RenameScriptFileTool;
use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename or move a file within a script directory. The script.js entry point cannot be renamed.')]
class RenameScriptFile extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        return $this->delegate(new RenameScriptFileTool($this->script($request)), $request->except('script_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script.')->required(),
            ...(new RenameScriptFileTool(new Script))->schema($schema),
        ];
    }
}
