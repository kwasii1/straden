<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DeleteScriptFileTool;
use App\Ai\Tools\ListScriptFilesTool;
use App\Ai\Tools\ReadScriptFileTool;
use App\Ai\Tools\RenameScriptFileTool;
use App\Ai\Tools\ScriptInsightsTool;
use App\Ai\Tools\ValidateScriptTool;
use App\Ai\Tools\WriteScriptFileTool;
use App\Models\Script;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-v4-flash')]
#[MaxSteps(35)]
#[Temperature(0.2)]
#[Timeout(240)]
class ScriptAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public Script $script) {}

    public function instructions(): Stringable|string
    {
        $scriptName = $this->script->name;
        $testName = $this->script->test->name;
        $targetUrl = $this->script->test->target_url;
        $basePath = 'scripts/'.$this->script->test_id.'/'.$this->script->id;

        return <<<INSTRUCTIONS
You are a k6 test script coding agent. You edit and maintain the script named "{$scriptName}" belonging to the test "{$testName}" which targets "{$targetUrl}".

All file operations are scoped to the directory {$basePath}. You may only create, read, edit, rename, move, or delete files inside this directory. Never access files outside of it.

Available tools:
- ListScriptFilesTool: List all files and folders in the script directory.
- ReadScriptFileTool: Read the full contents of a file.
- WriteScriptFileTool: Create or overwrite a file (writes to disk, requires approval). Automatically validates the script when you write the entry point.
- DeleteScriptFileTool: Delete a file or folder (requires approval). The entry point script.js cannot be deleted.
- RenameScriptFileTool: Rename a file or folder (requires approval). The entry point script.js cannot be renamed.
- ValidateScriptTool: Run k6 inspect and structural checks against the script.
- ScriptInsightsTool: Fetch recent run results and InfluxDB metrics for this script to guide your edits.

Follow this process:

1. **Understand the script**: Start by listing the files with ListScriptFilesTool and reading script.js (and any helper files) with ReadScriptFileTool.

2. **Gather insights**: Before making changes, use ScriptInsightsTool to review how recent runs performed — latency (p95/p99), error rate, checks, and which thresholds failed. Use these insights to decide what to change, for example:
   - If a latency threshold keeps failing, raise it to a realistic target or investigate the underlying issue.
   - If error rates are high, review the checks and assertions.
   - If VUs/duration seem mismatched with the test goal, adjust them.

3. **Make changes**: Use WriteScriptFileTool to create or edit files, DeleteScriptFileTool to remove files, and RenameScriptFileTool to rename files. All destructive operations require human approval.

4. **Always validate**: After every change, ensure the script is valid. WriteScriptFileTool returns validation results automatically when it writes the entry point, but you MUST also confirm with ValidateScriptTool after any sequence of edits. If validation reports errors, fix them and re-validate. Never report your work as complete while the script fails validation.

Key rules:
- Stay within the {$basePath} directory at all times.
- The entry point is script.js. Never delete or rename it. If it does not exist yet, create it with WriteScriptFileTool.
- A valid k6 entry point imports from k6 packages (e.g. k6/http), exports a default function, uses check() assertions, and declares thresholds in export const options.
- Use k6 best practices: realistic think times, proper error handling, and descriptive check names.
- Only respond with your final summary after the script passes validation.
INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ListScriptFilesTool($this->script),
            new ReadScriptFileTool($this->script),
            (new WriteScriptFileTool($this->script))->requireApproval('Writing files writes to disk. Review the proposed file content before approving.'),
            (new DeleteScriptFileTool($this->script))->requireApproval('Deleting files removes them from disk permanently. Review before approving.'),
            (new RenameScriptFileTool($this->script))->requireApproval('Renaming or moving files changes the script directory structure. Review before approving.'),
            new ValidateScriptTool,
            new ScriptInsightsTool($this->script),
        ];
    }
}
