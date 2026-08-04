<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListScriptFilesTool extends ScriptFileTool
{
    public function description(): Stringable|string
    {
        return 'List all files and folders in the current script directory (scripts/{test_id}/{script_id}). Use this first to see what files exist before reading or editing them.';
    }

    public function handle(Request $request): Stringable|string
    {
        $files = $this->fm()->fileTree();

        return json_encode([
            'script_id' => $this->script->id,
            'script_name' => $this->script->name,
            'base_path' => $this->basePath(),
            'entry_point' => $this->fm()->entryPointPath(),
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
