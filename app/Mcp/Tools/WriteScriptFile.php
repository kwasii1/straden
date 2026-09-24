<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\WriteScriptFileTool;
use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create or overwrite a file in a script directory. Writing script.js automatically validates it and returns the result.')]
class WriteScriptFile extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        return $this->delegate(new WriteScriptFileTool($this->script($request)), $request->except('script_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script.')->required(),
            ...(new WriteScriptFileTool(new Script))->schema($schema),
        ];
    }
}
