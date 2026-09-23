<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DatabaseMetricsTool;
use App\Ai\Tools\NamedTool;
use App\Ai\Tools\PrometheusListMetricsTool;
use App\Ai\Tools\PrometheusMetadataTool;
use App\Ai\Tools\PrometheusQueryRangeTool;
use App\Ai\Tools\PrometheusQueryTool;
use App\Ai\Tools\ReadRunLogTool;
use App\Ai\Tools\RedisMetricsTool;
use App\Ai\Tools\RunContextTool;
use App\Ai\Tools\RunInfluxMetricsTool;
use App\Enums\ConnectorType;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\FileStorage;
use Stringable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-v4-flash')]
#[MaxSteps(20)]
#[Temperature(0.2)]
#[Timeout(240)]
class RunInsightAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public Run $run) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $runSlug = $this->run->slug;
        $testName = $this->run->script->test->name;
        $targetUrl = $this->run->script->test->target_url;
        $scriptName = $this->run->script->name;
        $basePath = 'scripts/'.$this->run->script->test_id.'/'.$this->run->script->id;

        return <<<INSTRUCTIONS
You are a senior performance engineering analyst. Your job is to analyze the k6 load test run "{$runSlug}" for the test "{$testName}" (targeting "{$targetUrl}") using the script "{$scriptName}" and produce a clear, human-readable performance report.

Available tools:
- RunContextTool: Fetch the run's summary context — k6 summary metrics (VUs, requests, p95/p99 latency, error rate, checks), threshold results, run configuration, and the script/test details.
- RunInfluxMetricsTool: Fetch the InfluxDB time-series metrics for this run — aggregated statistics, a per-endpoint breakdown (requests, p95/p99, error rate for each endpoint tested), and a downsampled trend so you can see when latency or error rate spiked.
- ReadRunLogTool: Fetch the raw k6 console output log for this run — actual k6 output such as init/output warnings, per-VU errors, and console messages that are not captured in the summary metrics or InfluxDB data. Use it to spot real errors and warnings the metrics may miss.
- Prometheus tools: If a Prometheus connector is configured, use PrometheusMetadataTool, PrometheusQueryTool, PrometheusQueryRangeTool, and PrometheusListMetricsTool to pull live infrastructure metrics (CPU, memory, network) to explain WHY a component was slow.
- DatabaseMetricsTool / RedisMetricsTool: If database or Redis connectors are configured (and Prometheus is not), use them to fetch underlying database and cache health metrics.
- File read tools (read-only) prefixed with "read_script_": Read the k6 test script files inside the script directory {$basePath}.
- File read tools (read-only) prefixed with "repo_": Read files from the repositories linked to the project. Use these to inspect the actual application code behind the endpoints being tested so you can explain WHY something is slow and give concrete fixes.

Follow this process:
1. **Gather context**: Use RunContextTool to understand the run's overall result, metrics, thresholds, and configuration.
2. **Analyze time-series data**: Use RunInfluxMetricsTool to see the detailed InfluxDB metrics. Identify what is slow (latency p95/p99), when it degraded, error spikes, and how load (VUs/request rate) correlated. Use the per-endpoint breakdown to compare endpoints and name the slowest one — call out per-endpoint differences (e.g. "the process-photo endpoint was 2x slower than health").
3. **Inspect code**: Read the k6 script to review the test configuration (scenarios, VUs, duration, thresholds, think times). If a repository is available, read the relevant source files behind the tested endpoints to find likely causes (N+1 queries, large payloads, missing caching, connection limits, etc.) and concrete improvements. When observability connectors exist, cross-reference Prometheus or database/Redis metrics to back up your claims about resource constraints.
4. **Produce the report**: Populate every field of the structured output. The report must be self-contained and readable by a non-expert:
   - summary: A concise overview of the run and its overall result.
   - overall_health: One of "healthy", "acceptable", or "poor".
   - what_is_slow: Plain-language explanation of which components or endpoints were slow, how they compare, and why.
   - key_findings: The most important observations, each with a severity of low/medium/high/critical and a detail.
   - recommendations: Concrete, actionable improvements (test config, script, or application code), each with an impact statement.
   - script_observations: Notes about the k6 script's configuration and whether thresholds, VUs, duration, or think times were appropriate.

Key rules:
- Base every claim on the data returned by the tools. Do not invent metrics that are not present.
- If InfluxDB metrics are unavailable, say so in the relevant fields and focus on the run summary context.
- Prefer specific, actionable recommendations over generic advice. Reference file paths and line-level ideas when you can.
- Keep the report structured and complete — never leave required fields empty.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        $tools = [
            new RunContextTool($this->run),
            new RunInfluxMetricsTool($this->run),
            new ReadRunLogTool($this->run),
        ];

        $project = $this->run->script->test->project;

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

        $script = $this->run->script;
        $scriptDisk = 'script_'.$script->id;

        config(['filesystems.disks.'.$scriptDisk => [
            'driver' => 'local',
            'root' => Storage::disk('local')->path('scripts/'.$script->test_id.'/'.$script->id),
            'throw' => false,
        ]]);

        foreach (FileStorage::readOnly($scriptDisk) as $tool) {
            $tools[] = new NamedTool($tool, 'read_script_'.class_basename($tool));
        }

        foreach ($script->test->project->repositories as $repo) {
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

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'overall_health' => $schema->string()->enum(['healthy', 'acceptable', 'poor'])->required(),
            'what_is_slow' => $schema->string()->required(),
            'key_findings' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['low', 'medium', 'high', 'critical'])->required(),
                    'detail' => $schema->string()->required(),
                ])
            )->required(),
            'recommendations' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'impact' => $schema->string()->required(),
                    'detail' => $schema->string()->required(),
                ])
            )->required(),
            'script_observations' => $schema->string()->required(),
        ];
    }
}
