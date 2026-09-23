<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadScriptFileTool extends ScriptFileTool
{
    public function description(): Stringable|string
    {
        return 'Read the full contents of a file in the current script directory. The path must be relative to the script directory (e.g. "script.js", "lib/helpers.js").';
    }

    public function handle(Request $request): Stringable|string
    {
        $path = $this->resolvePath($request->string('path'));

        $content = $this->fm()->readFile($path);

        if ($content === null) {
            return json_encode([
                'found' => false,
                'path' => $path,
                'error' => 'File does not exist in the script directory.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return json_encode([
            'found' => true,
            'path' => $path,
            'content' => $content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Relative path of the file within the script directory (e.g. "script.js", "lib/helpers.js").')
                ->required(),
        ];
    }
}
