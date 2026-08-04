<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteScriptFileTool extends ScriptFileTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Delete a file or folder inside the current script directory. The path must be relative to the script directory (e.g. "lib", "old-data.json"). This is a destructive operation that requires approval. The entry point "script.js" cannot be deleted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $path = $this->resolvePath($request->string('path'));

        if ($path === $this->fm()->entryPointPath()) {
            return json_encode([
                'deleted' => false,
                'error' => 'The entry point script.js cannot be deleted.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (! $this->itemExists($path)) {
            return json_encode([
                'deleted' => false,
                'error' => "Item '{$path}' does not exist in the script directory.",
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $this->fm()->delete($path);

        return json_encode([
            'deleted' => true,
            'path' => $path,
            'message' => "Deleted '{$path}' from the script directory.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Relative path of the file or folder to delete within the script directory.')
                ->required(),
        ];
    }

    protected function needsApproval(Request $request): bool
    {
        return true;
    }
}
