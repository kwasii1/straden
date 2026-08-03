<?php

namespace App\Ai\Tools;

use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\Process\Process;

class ValidateScriptTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Validate a k6 test script for syntax errors and structural correctness. Runs k6 inspect and checks for required patterns like imports, export default function, and proper k6 API usage. Use this after creating or modifying a script.';
    }

    public function handle(Request $request): Stringable|string
    {
        $scriptId = $request->string('script_id');

        $script = Script::findOrFail($scriptId);

        if (! $script->script_path || ! Storage::disk($script->disk)->exists($script->script_path)) {
            return json_encode([
                'valid' => false,
                'error' => "Script file not found at {$script->script_path}.",
            ]);
        }

        $content = Storage::disk($script->disk)->get($script->script_path);

        $syntaxIssues = $this->checkSyntax($content);
        $k6InspectResult = $this->runK6Inspect($script);
        $patternIssues = $this->checkPatterns($content);

        return json_encode([
            'valid' => empty($syntaxIssues) && $k6InspectResult['valid'] && empty($patternIssues),
            'syntax_issues' => $syntaxIssues,
            'k6_inspect' => $k6InspectResult,
            'pattern_issues' => $patternIssues,
            'script_id' => $scriptId,
            'script_path' => $script->script_path,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()
                ->description('The UUID of the script to validate.')
                ->required(),
        ];
    }

    private function checkSyntax(string $content): array
    {
        $issues = [];

        $lines = explode("\n", $content);

        foreach ($lines as $i => $line) {
            $lineNum = $i + 1;

            if (preg_match('/\bvar\b/', $line) && ! preg_match('/\bvar\s+\w+\s*=/', $line)) {
                $issues[] = [
                    'line' => $lineNum,
                    'message' => "Use 'const' or 'let' instead of 'var'.",
                    'severity' => 'warning',
                ];
            }

            if (preg_match('/console\.(log|error|warn)/', $line)) {
                $issues[] = [
                    'line' => $lineNum,
                    'message' => "Avoid console.log in k6 scripts; use 'check' with logging metadata instead.",
                    'severity' => 'warning',
                ];
            }
        }

        return $issues;
    }

    private function runK6Inspect(Script $script): array
    {
        $disk = $script->disk;
        $scriptPath = $script->script_path;

        $fullPath = Storage::disk($disk)->path($scriptPath);

        if (! file_exists($fullPath)) {
            return [
                'valid' => false,
                'error' => 'File does not exist on disk.',
                'output' => '',
            ];
        }

        $process = new Process(['k6', 'inspect', $fullPath]);
        $process->run();

        return [
            'valid' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => Str::limit($process->getOutput(), 5000),
            'error' => $process->isSuccessful() ? null : Str::limit($process->getErrorOutput(), 5000),
        ];
    }

    private function checkPatterns(string $content): array
    {
        $issues = [];

        if (! preg_match('/import\s+.*from\s+[\'"]k6\/http[\'"]/', $content)) {
            $issues[] = [
                'pattern' => 'k6/http import',
                'message' => 'Missing import from k6/http. Required for HTTP request testing.',
                'severity' => 'error',
            ];
        }

        if (! preg_match('/export\s+default\s+function/', $content)) {
            $issues[] = [
                'pattern' => 'export default function',
                'message' => 'Missing export default function. k6 requires a default export as the entry point.',
                'severity' => 'error',
            ];
        }

        if (! preg_match('/check\(/', $content)) {
            $issues[] = [
                'pattern' => 'check()',
                'message' => 'No check() calls found. Add assertions to validate responses.',
                'severity' => 'warning',
            ];
        }

        return $issues;
    }
}
