<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\CreateScriptTool;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a new k6 script for a test. Writes the script.js entry point plus optional helper files. Run validate-script afterwards.')]
class CreateScript extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entry_point_content' => ['required', 'string'],
            'additional_files' => ['nullable', 'array'],
            'additional_files.*.path' => ['required', 'string'],
            'additional_files.*.content' => ['present', 'string'],
        ]);

        return $this->delegate(new CreateScriptTool($this->test($request)), $request->except('test_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_id' => $schema->string()->description('The UUID of the test to add the script to.')->required(),
            ...(new CreateScriptTool(new Test))->schema($schema),
        ];
    }
}
