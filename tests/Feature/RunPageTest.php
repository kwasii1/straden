<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
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
