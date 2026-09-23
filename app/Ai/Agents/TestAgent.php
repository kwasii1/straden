<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateScriptTool;
use App\Ai\Tools\DatabaseMetricsTool;
use App\Ai\Tools\NamedTool;
use App\Ai\Tools\PrometheusListMetricsTool;
use App\Ai\Tools\PrometheusMetadataTool;
use App\Ai\Tools\PrometheusQueryRangeTool;
use App\Ai\Tools\PrometheusQueryTool;
use App\Ai\Tools\RedisMetricsTool;
use App\Ai\Tools\ScanContextTool;
use App\Ai\Tools\UpdateScriptTool;
use App\Ai\Tools\ValidateScriptTool;
use App\Enums\ConnectorType;
use App\Models\Test;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\WithoutBroadcasting;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Streaming\Events\ToolResult;
use Laravel\Ai\Tools\FileStorage;
use Stringable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-v4-flash')]
#[MaxSteps(35)]
#[Temperature(0.2)]
#[Timeout(240)]
#[WithoutBroadcasting(ToolResult::class)]
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

0. **Read the room first**: If the user sends a greeting, thanks you, or makes a casual remark that does not ask you to do anything (e.g. "hello", "thanks", "good morning"), just reply conversationally and stop. Do NOT scan context, propose a plan, or create anything unless the user explicitly asks you to build or modify a load test.

1. **Scan Context**: Use the ScanContextTool to understand the test environment — what connectors are available, what repositories exist, what previous scripts and runs look like. If observability connectors (Prometheus, MySQL, PostgreSQL, Redis) are configured, you can use their metrics tools to ground recommendations in live infrastructure data.

2. **Propose a Plan**: After scanning, describe a test plan in plain text. Include:
   - Which endpoints or services to test
   - What k6 scenarios to create (e.g., smoke, load, stress, soak). Each distinct test type should be proposed as a separate script. Only combine multiple scenarios into one script if the user explicitly requests it.
   - What checks and assertions to include
   - What thresholds to set
   - How many VUs and what duration
   Do NOT write any code yet. Wait for the human to approve the plan.

3. **Create Scripts**: After plan approval, use the CreateScriptTool once per script — one script per scenario type. Each script should contain exactly one k6 scenario focused on a single test purpose. For example, if the user asks for a smoke test and a load test, create two separate scripts: "smoke-test" and "load-test". Only combine multiple scenarios into a single script if the user explicitly asked for that.
   The entry_point_content must be valid k6 JavaScript with:
   - Imports from k6/http, k6/metrics, k6/html, etc.
   - An export default function
   - Proper check() assertions on responses
   - Custom metrics if needed
   - Thresholds in the options block
   Provide additional_files for helpers, utilities, config, or data files.

4. **Validate Scripts**: After creating each script, use the ValidateScriptTool to verify correctness. If validation fails:
   a. Analyze the reported issues (syntax issues, pattern issues, k6 inspect errors)
   b. Use the UpdateScriptTool to apply fixes to the failing files
   c. Validate again with ValidateScriptTool
   d. Repeat this fix-and-validate loop up to 5 times per script
   e. If a script still fails after 5 retries, inform the user of the persistent issues and recommend manual review

Key rules:
- Always scan context before proposing a plan.
- Always propose a plan and wait for approval before writing code.
- Always validate each script after creation.
- Create separate scripts for different test types by default. One script = one scenario type. Do not combine multiple scenarios (e.g., smoke + load) into a single script unless the user explicitly asks to combine them.
- When validation fails, do not give up. Retry the fix-and-validate cycle up to 5 times per script before reporting a persistent issue to the user.
- Use k6 best practices: checks, thresholds, proper error handling, realistic think times.
- Lifecycle budgets: if a script defines setup() or teardown() that performs HTTP requests, always set setupTimeout/teardownTimeout in export const options (e.g. setupTimeout: '3m', teardownTimeout: '5m') — k6 kills them after 60s by default. Never issue serial per-item requests inside setup/teardown; batch with http.batch() in bounded chunks instead, since the target is usually still saturated when cleanup runs.
- Output scripts as clean, well-structured JavaScript.
INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        $scriptsDisk = 'scripts_'.$this->test->id;

        config(['filesystems.disks.'.$scriptsDisk => [
            'driver' => 'local',
            'root' => Storage::disk('local')->path('scripts/'.$this->test->id),
            'throw' => false,
        ]]);

        $tools = [
            new ScanContextTool($this->test),
            (new CreateScriptTool($this->test))->requireApproval('Creating scripts writes files to disk. Review the proposed content before approving.'),
            (new UpdateScriptTool($this->test))->requireApproval('Updating scripts writes files to disk. Review the proposed changes before approving.'),
            new ValidateScriptTool,
            ...FileStorage::all($scriptsDisk),
        ];

        $project = $this->test->project;

        $prometheus = $project->connectors()
            ->where('type', ConnectorType::Prometheus->value)
            ->first();

        array_push($tools,
            new PrometheusMetadataTool($prometheus),
            new PrometheusQueryTool($prometheus),
            new PrometheusQueryRangeTool($prometheus),
            new PrometheusListMetricsTool($prometheus),
            new DatabaseMetricsTool($project),
            new RedisMetricsTool($project),
        );

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
