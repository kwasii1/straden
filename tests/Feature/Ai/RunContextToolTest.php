<?php

use App\Ai\Tools\RunContextTool;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function makeRunContextRun(array $attributes = []): Run
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

    return Run::factory()->failed()->create(array_merge([
        'script_id' => $script->id,
        'vus_max' => 50,
        'req_duration_p95_ms' => 250.5,
        'req_duration_p99_ms' => 900.25,
        'error_rate' => 12.5,
        'thresholds_passed' => false,
        'thresholds_summary' => [
            ['name' => 'http_req_duration[p(95)<200]', 'ok' => false],
        ],
        'run_config' => ['vus' => 50, 'duration' => '2m'],
    ], $attributes));
}

test('run context tool returns run, script, and test context', function () {
    $run = makeRunContextRun();

    $result = json_decode((string) (new RunContextTool($run))->handle(new Request([])), true);

    expect($result['run']['id'])->toBe($run->id);
    expect($result['run']['slug'])->toBe($run->slug);
    expect($result['run']['status'])->toBe('failed');
    expect($result['run']['vus_max'])->toBe(50);
    expect($result['run']['req_duration_p95_ms'])->toEqual(250.5);
    expect($result['run']['req_duration_p99_ms'])->toEqual(900.25);
    expect($result['run']['error_rate'])->toEqual(12.5);
    expect($result['run']['thresholds_passed'])->toBeFalse();
    expect($result['run']['thresholds_summary'][0]['name'])->toBe('http_req_duration[p(95)<200]');
    expect($result['run']['run_config'])->toBe(['vus' => 50, 'duration' => '2m']);
});

test('run context tool includes script and test details', function () {
    $run = makeRunContextRun();

    $result = json_decode((string) (new RunContextTool($run))->handle(new Request([])), true);

    expect($result['script']['name'])->toBe('checkout-load');
    expect($result['script']['base_path'])->toBe('scripts/'.$run->script->test_id.'/'.$run->script->id);
    expect($result['test']['name'])->toBe('Checkout API');
    expect($result['test']['target_url'])->toBe('https://checkout.example.com');
});
