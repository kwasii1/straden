<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\UpdateScriptTool;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update an existing script: rename it, change its description and/or create or overwrite several files at once. Returns the list of files in the script directory.')]
class UpdateScript extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        $request->validate([
            'script_id' => ['required', 'string'],
            'files' => ['nullable', 'array'],
            'files.*.path' => ['required', 'string'],
            'files.*.content' => ['present', 'string'],
        ]);

        return $this->delegate(new UpdateScriptTool($this->script($request)->test), $request->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return (new UpdateScriptTool(new Test))->schema($schema);
    }
}
