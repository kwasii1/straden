<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Services\RunResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeRun(string $status): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->{$status}()->create(['script_id' => $script->id]);
}

test('cancel run aborts a queued run', function () {
    $user = User::factory()->create();
    $run = makeRun('queued');

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run])
        ->call('cancelRun');

    $run->refresh();

    expect($run->status)->toBe('aborted');
    expect($run->completed_at)->not->toBeNull();
});

test('cancel run leaves a completed run untouched', function () {
    $user = User::factory()->create();
    $run = makeRun('passed');

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run])
        ->call('cancelRun');

    $run->refresh();

    expect($run->status)->toBe('passed');
});

test('run page renders the cancel button for running runs', function () {
    $user = User::factory()->create();
    $run = makeRun('running');

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('Cancel Run');
});

test('run page renders the AI insights trigger and flyout', function () {
    $user = User::factory()->create();
    $run = makeRun('passed');

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('AI Insights')
        ->assertSee('run-insights');
});

test('extra charts can be toggled on and off', function () {
    $user = User::factory()->create();
    $run = makeRun('passed');

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run]);

    expect($component->instance()->extraCharts)->toBe([]);

    $component->call('toggleExtraChart', 'timing')
        ->assertSet('extraCharts', ['timing']);

    $component->call('toggleExtraChart', 'iterations')
        ->assertSet('extraCharts', ['timing', 'iterations']);

    $component->call('toggleExtraChart', 'timing')
        ->assertSet('extraCharts', ['iterations']);
});

test('progress percent is computed from the run duration', function () {
    $this->travelTo(now()->startOfDay());

    $user = User::factory()->create();
    $run = makeRun('running');
    $run->update([
        'run_config' => ['duration_seconds' => 100],
        'started_at' => now()->subSeconds(40),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run]);

    expect($component->instance()->progressPercent())->toBe(40);
});

test('progress percent caps at 99 while running', function () {
    $this->travelTo(now()->startOfDay());

    $user = User::factory()->create();
    $run = makeRun('running');
    $run->update([
        'run_config' => ['duration_seconds' => 10],
        'started_at' => now()->subSeconds(120),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run]);

    expect($component->instance()->progressPercent())->toBe(99);
});

test('progress percent is null for queued and completed runs', function () {
    $user = User::factory()->create();

    $queued = makeRun('queued');
    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $queued->script->test->project, 'run' => $queued]);

    expect($component->instance()->progressPercent())->toBeNull();

    $passed = makeRun('passed');
    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $passed->script->test->project, 'run' => $passed]);

    expect($component->instance()->progressPercent())->toBeNull();
});

test('run page renders the log modal for active runs with logs', function () {
    $user = User::factory()->create();
    $run = makeRun('running');
    file_put_contents(RunResultService::logFilePath($run->id), 'log');

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('run-logs');
});

test('creating a run stamps the script last run timestamp', function () {
    $script = Script::factory()->create();

    expect($script->last_run_at)->toBeNull();

    Run::factory()->for($script)->create();

    expect($script->fresh()->last_run_at)->not->toBeNull();
});

test('error run shows collapsed error with expand chevron', function () {
    $user = User::factory()->create();
    $run = makeRun('error');
    $run->update(['error_message' => "level=error msg=\"teardown() execution timed out after 60 seconds\"\nSecond line of the log\nThird line"]);

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('x-data="{ open: false }"', false)
        ->assertSee('teardown() execution timed out', false)
        ->assertSee('teardown() execution timed out after 60 seconds', false);
});
