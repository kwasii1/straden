<?php

namespace App\Ai\Tools;

use App\Models\Script;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateScriptTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Test $test) {}

    public function description(): Stringable|string
    {
        return 'Create a new k6 test script for the current test. Writes the entry point script.js file and optional additional helper/config files to the script directory. This is a destructive operation that creates files on disk.';
    }

    public function handle(Request $request): Stringable|string
    {
        $name = $request->string('name');
        $description = $request->string('description', '');
        $entryPointContent = $request->string('entry_point_content');
        $additionalFiles = $request->array('additional_files', []);

        $script = Script::create([
            'test_id' => $this->test->id,
            'name' => $name,
            'description' => $description,
            'disk' => 'local',
            'script_path' => '',
        ]);

        $basePath = 'scripts/'.$this->test->id.'/'.$script->id;

        Storage::disk('local')->makeDirectory($basePath);

        Storage::disk('local')->put($basePath.'/script.js', $entryPointContent);

        foreach ($additionalFiles as $file) {
            $filePath = $basePath.'/'.ltrim($file['path'], '/');
            $dir = dirname($filePath);

            if ($dir !== '.' && ! Storage::disk('local')->directoryExists($dir)) {
                Storage::disk('local')->makeDirectory($dir);
            }

            Storage::disk('local')->put($filePath, $file['content']);
        }

        $script->update([
            'script_path' => $basePath.'/script.js',
        ]);

        $filesCreated = 1 + count($additionalFiles);

        return "Created script '{$name}' with ID {$script->id} at {$basePath}/. Wrote {$filesCreated} file(s).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('A descriptive name for the test script.')
                ->required(),
            'description' => $schema->string()
                ->description('Optional description of what this script tests.'),
            'entry_point_content' => $schema->string()
                ->description('The full k6 JavaScript content for the script.js entry point file. Must include imports from k6/http and k6 packages, an export default function, and proper checks/assertions.')
                ->required(),
            'additional_files' => $schema->array()
                ->description('Optional array of additional files to create alongside the entry point.'),
        ];
    }

    protected function needsApproval(Request $request): bool
    {
        return true;
    }
}
