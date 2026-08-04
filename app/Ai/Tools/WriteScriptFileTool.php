<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

class WriteScriptFileTool extends ScriptFileTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Create a new file or overwrite an existing file inside the current script directory. The path must be relative to the script directory (e.g. "script.js", "lib/helpers.js"). Parent directories are created automatically. This is a destructive operation that writes to disk and requires approval. Writing the entry point "script.js" automatically triggers k6 validation of the script.';
    }

    public function handle(Request $request): Stringable|string
    {
        $path = $this->resolvePath($request->string('path'));
        $content = $request->string('content', '');

        $existed = $this->fm()->exists($path);
        $basePath = $this->basePath();

        $dir = dirname($path);

        if ($dir !== '.' && ! Storage::disk('local')->directoryExists($basePath.'/'.$dir)) {
            Storage::disk('local')->makeDirectory($basePath.'/'.$dir);
        }

        Storage::disk('local')->put($basePath.'/'.$path, $content);

        if ($path === $this->fm()->entryPointPath()) {
            $this->script->update(['script_path' => $basePath.'/'.$path]);
        }

        $validation = null;

        if ($this->fm()->exists($this->fm()->entryPointPath())) {
            $validation = $this->validateScript();
        }

        return json_encode([
            'status' => $existed ? 'overwritten' : 'created',
            'path' => $path,
            'is_entry_point' => $path === $this->fm()->entryPointPath(),
            'message' => ($existed ? 'Overwrote' : 'Created')." '{$path}' in the script directory.",
            'validation' => $validation,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Relative path of the file within the script directory (e.g. "script.js", "lib/helpers.js").')
                ->required(),
            'content' => $schema->string()
                ->description('The full file content to write. Providing empty content creates an empty file.')
                ->required(),
        ];
    }

    protected function needsApproval(Request $request): bool
    {
        return true;
    }

    private function validateScript(): ?array
    {
        try {
            $result = (new ValidateScriptTool)->handle(new Request(['script_id' => $this->script->id]));

            return json_decode((string) $result, true);
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => 'k6 validation failed to run: '.$e->getMessage(),
            ];
        }
    }
}
