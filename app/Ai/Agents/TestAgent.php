<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateScriptTool;
use App\Ai\Tools\NamedTool;
use App\Ai\Tools\ScanContextTool;
use App\Ai\Tools\ValidateScriptTool;
use App\Models\Test;
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
use Laravel\Ai\Tools\FileStorage;
use Stringable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-v4-flash')]
#[MaxSteps(20)]
#[Temperature(0.2)]
#[Timeout(240)]
class TestAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public Test $test) {}

    public function instructions(): Stringable|string
    {
        $testName = $this->test->name;
        $targetUrl = $this->test->target_url;

        return <<<INSTRUCTIONS
You are a k6 test planning and scripting agent. Your job is to help create performance and load testing scripts for the test named "{$testName}" targeting "{$targetUrl}".

Follow this process strictly:

1. **Scan Context**: Use the ScanContextTool to understand the test environment — what connectors are available, what repositories exist, what previous scripts and runs look like.

2. **Propose a Plan**: After scanning, describe a test plan in plain text. Include:
   - Which endpoints or services to test
   - What k6 scenarios to create (e.g., smoke, load, stress, soak)
   - What checks and assertions to include
   - What thresholds to set
   - How many VUs and what duration
   Do NOT write any code yet. Wait for the human to approve the plan.

3. **Create Scripts**: After plan approval, use the CreateScriptTool to write k6 scripts. The entry_point_content must be valid k6 JavaScript with:
   - Imports from k6/http, k6/metrics, k6/html, etc.
   - An export default function
   - Proper check() assertions on responses
   - Custom metrics if needed
   - Thresholds in the options block
   Provide additional_files for helpers, utilities, config, or data files.

4. **Validate Scripts**: After creation, use the ValidateScriptTool to verify correctness. Fix any issues found.

Key rules:
- Always scan context before proposing a plan.
- Always propose a plan and wait for approval before writing code.
- Always validate scripts after creation.
- Use k6 best practices: checks, thresholds, proper error handling, realistic think times.
- Output scripts as clean, well-structured JavaScript.
INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        $scriptsDisk = 'scripts_'.$this->test->id;

        config(['filesystems.disks.'.$scriptsDisk => [
            'driver' => 'local',
            'root' => storage_path('app/scripts/'.$this->test->id),
            'throw' => false,
        ]]);

        $tools = [
            new ScanContextTool($this->test),
            (new CreateScriptTool($this->test))->requireApproval('Creating scripts writes files to disk. Review the proposed content before approving.'),
            new ValidateScriptTool,
            ...FileStorage::all($scriptsDisk),
        ];

        foreach ($this->test->project->repositories as $repo) {
            $repoDisk = 'repo_'.$repo->id;

            config(['filesystems.disks.'.$repoDisk => [
                'driver' => 'local',
                'root' => storage_path('app/repositories/'.$repo->id),
                'throw' => false,
            ]]);

            foreach (FileStorage::readOnly($repoDisk) as $tool) {
                $tools[] = new NamedTool($tool, 'repo_'.$repo->id.'_'.class_basename($tool));
            }
        }

        return $tools;
    }
}
