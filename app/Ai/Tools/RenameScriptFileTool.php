<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

class RenameScriptFileTool extends ScriptFileTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Rename or move a file or folder inside the current script directory. The source path is relative to the script directory; the new name must be a single file or folder name (no slashes). This is a destructive operation that requires approval. The entry point "script.js" cannot be renamed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $source = $this->resolvePath($request->string('source_path'));
        $newName = trim($request->string('new_name'));

        if ($source === $this->fm()->entryPointPath()) {
            return json_encode([
                'renamed' => false,
                'error' => 'The entry point script.js cannot be renamed.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (! $this->itemExists($source)) {
            return json_encode([
                'renamed' => false,
                'error' => "Item '{$source}' does not exist in the script directory.",
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($newName === '' || str_contains($newName, '/') || str_contains($newName, '\\') || $newName === '.' || $newName === '..') {
            return json_encode([
                'renamed' => false,
                'error' => 'The new name must be a single file or folder name, not a path.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $destination = $this->fm()->rename($source, $newName);

        if ($destination === null) {
            return json_encode([
                'renamed' => false,
                'error' => 'Rename failed: the destination already exists or the name is unchanged.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode([
            'renamed' => true,
            'source' => $source,
            'destination' => $destination,
            'message' => "Renamed '{$source}' to '{$destination}'.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_path' => $schema->string()
                ->description('Relative path of the file or folder to rename within the script directory.')
                ->required(),
            'new_name' => $schema->string()
                ->description('The new single file or folder name (no slashes).')
                ->required(),
        ];
    }

    protected function needsApproval(Request $request): bool
    {
        return true;
    }
}
