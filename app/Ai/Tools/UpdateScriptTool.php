<?php

namespace App\Ai\Tools;

use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateScriptTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Test $test) {}

    public function description(): Stringable|string
    {
        return 'Update an existing k6 test script. Always returns the list of existing files in the script directory. Use this to apply changes to an already-created script — unlike CreateScriptTool, this modifies the existing script in place rather than creating a new one. Pass the files you want to create or overwrite as an array of {path, content} objects. This is a destructive operation that writes files to disk.';
    }

    public function handle(Request $request): Stringable|string
    {
        $scriptId = (string) $request->string('script_id');

        $script = $this->test->scripts()->findOrFail($scriptId);

        $basePath = 'scripts/'.$this->test->id.'/'.$script->id;

        $allFiles = Storage::disk('local')->allFiles($basePath);
        $relativePaths = array_map(fn (string $fullPath) => Str::after($fullPath, $basePath.'/'), $allFiles);
        sort($relativePaths);

        if ($request->has('name')) {
            $script->update(['name' => $request->string('name')]);
        }

        if ($request->has('description')) {
            $script->update(['description' => $request->string('description')]);
        }

        $updates = $request->array('files');
        $updatedPaths = [];

        foreach ($updates as $file) {
            $path = ltrim($file['path'], '/');
            $dir = dirname($path);

            if ($dir !== '.' && ! Storage::disk('local')->directoryExists($basePath.'/'.$dir)) {
                Storage::disk('local')->makeDirectory($basePath.'/'.$dir);
            }

            Storage::disk('local')->put($basePath.'/'.$path, $file['content']);
            $updatedPaths[] = $path;
        }

        $filesUpdated = count($updatedPaths);

        return "Script '{$script->name}' (ID: {$script->id})\n"
            .'Existing files: ['.implode(', ', $relativePaths)."]\n"
            .($filesUpdated > 0
                ? "Updated {$filesUpdated} file(s): [".implode(', ', $updatedPaths).']'
                : 'No files updated.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()
                ->description('The UUID of the existing script to update.')
                ->required(),
            'name' => $schema->string()
                ->description('Optional new name for the script.'),
            'description' => $schema->string()
                ->description('Optional new description for the script.'),
            'files' => $schema->array()
                ->items($schema->object([
                    'path' => $schema->string()
                        ->description('Path of the file relative to the script root, e.g. "config.js" or "lib/helpers.js".')
                        ->required(),
                    'content' => $schema->string()
                        ->description('The full file content as a string.')
                        ->required(),
                ]))
                ->description('Array of files to create or overwrite. Each entry must have "path" (relative to script root, e.g. "config.js" or "lib/helpers.js") and "content" (the full file content as a string).')
                ->required(),
        ];
    }

    protected function needsApproval(Request $request): bool
    {
        return true;
    }
}
