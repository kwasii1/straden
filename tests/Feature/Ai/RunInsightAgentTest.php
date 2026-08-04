<?php

use App\Ai\Agents\RunInsightAgent;
use App\Ai\Tools\NamedTool;
use App\Ai\Tools\RunContextTool;
use App\Ai\Tools\RunInfluxMetricsTool;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

uses(RefreshDatabase::class);

function makeInsightRun(): Run
{
    $project = Project::factory()->create(['name' => 'Checkout Platform']);
    $test = Test::factory()->create([
        'project_id' => $project->id,
        'name' => 'Checkout API',
        'target_url' => 'https://checkout.example.com',
    ]);
    $script = Script::factory()->create([
        'test_id' => $test->id,
        'name' => 'checkout-load',
    ]);

    return Run::factory()->failed()->create(['script_id' => $script->id]);
}

test('run insight agent can be instantiated with a run', function () {
    $run = makeInsightRun();

    $agent = new RunInsightAgent($run);

    expect($agent)->toBeInstanceOf(RunInsightAgent::class);
    expect($agent->run->is($run))->toBeTrue();
});

test('run insight agent instructions include run, test, and tool context', function () {
    $run = makeInsightRun();

    $instructions = (string) (new RunInsightAgent($run))->instructions();

    expect($instructions)->toContain($run->slug)
        ->toContain('Checkout API')
        ->toContain('https://checkout.example.com')
        ->toContain('checkout-load')
        ->toContain('RunContextTool')
        ->toContain('RunInfluxMetricsTool')
        ->toContain('read_script_')
        ->toContain('repo_')
        ->toContain('scripts/'.$run->script->test_id.'/'.$run->script->id);
});

test('run insight agent provides context, influx, and script tools', function () {
    $run = makeInsightRun();

    $tools = collect((new RunInsightAgent($run))->tools());

    expect($tools->filter(fn ($tool) => $tool instanceof RunContextTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof RunInfluxMetricsTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof NamedTool && str_starts_with($tool->name(), 'read_script_'))->count())->toBeGreaterThan(0);
});

test('run insight agent exposes read-only repository tools', function () {
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);
    $repo = Repository::factory()->create(['project_id' => $project->id]);
    $run = Run::factory()->failed()->create(['script_id' => $script->id]);

    $tools = collect((new RunInsightAgent($run))->tools());

    expect($tools->filter(
        fn ($tool) => $tool instanceof NamedTool && str_starts_with($tool->name(), 'repo_'.$repo->id.'_')
    )->count())->toBeGreaterThan(0);
});

test('run insight agent defines a complete structured schema', function () {
    $run = makeInsightRun();

    $schema = (new RunInsightAgent($run))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toContain('summary')
        ->toContain('overall_health')
        ->toContain('what_is_slow')
        ->toContain('key_findings')
        ->toContain('recommendations')
        ->toContain('script_observations');
});

test('run insight agent can be faked for a structured prompt', function () {
    $run = makeInsightRun();

    RunInsightAgent::fake([[
        'summary' => 'The checkout endpoint degraded under load.',
        'overall_health' => 'poor',
        'what_is_slow' => 'Checkout latency.',
        'key_findings' => [
            ['title' => 'P95 exceeded threshold', 'severity' => 'high', 'detail' => 'Latency spiked to 900ms.'],
        ],
        'recommendations' => [
            ['title' => 'Add caching', 'impact' => 'Reduces latency', 'detail' => 'Cache product data.'],
        ],
        'script_observations' => 'Thresholds were appropriate.',
    ]]);

    $agent = new RunInsightAgent($run);

    $response = $agent->prompt('Analyze this run');

    expect($response['summary'])->toContain('checkout endpoint degraded');
    expect($response['overall_health'])->toBe('poor');
});
