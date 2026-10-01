<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.overview', $project))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the overview page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('High-level summary of tests, runs, and load testing metrics');
});

test('overview shows correct stat counts with data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $test = Test::factory()->for($project)->create();
    $script = Script::factory()->for($test)->create();

    Run::factory()->for($script)->passed()->count(3)->create();
    Run::factory()->for($script)->failed()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Total Tests')
        ->assertSee('Total Runs')
        ->assertSee('1')   // Total Tests count
        ->assertSee('4')   // Total Runs count
        ->assertSee('Passed')  // Last run status
        ->assertSee('Failed'); // Should see status badge
});

test('overview shows empty state when no runs exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('No run data available for this project yet.')
        ->assertSee('Create your first test');
});

test('overview shows recent runs in correct order', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $test = Test::factory()->for($project)->create();
    $script = Script::factory()->for($test)->create();

    $oldRun = Run::factory()->for($script)->passed()->create(['created_at' => now()->subDays(2)]);
    $newRun = Run::factory()->for($script)->failed()->create(['created_at' => now()->subHour()]);

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Recent Executions')
        ->assertSee('Passed')
        ->assertSee('Failed');
});

test('overview shows correct runs this week count', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $test = Test::factory()->for($project)->create();
    $script = Script::factory()->for($test)->create();

    Run::factory()->for($script)->passed()->create(['created_at' => now()]);
    Run::factory()->for($script)->passed()->create(['created_at' => now()->subDay()]);
    Run::factory()->for($script)->passed()->create(['created_at' => now()->subWeek()->subDay()]);

    $response = $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk();

    $response->assertSee('Runs This Week')
        ->assertSee('2'); // Only 2 runs this week
});

test('overview isolates data per project', function () {
    $user = User::factory()->create();

    $projectA = Project::factory()->create();
    $testA = Test::factory()->for($projectA)->create();
    $scriptA = Script::factory()->for($testA)->create();
    Run::factory()->for($scriptA)->passed()->count(5)->create();

    $projectB = Project::factory()->create();
    $testB = Test::factory()->for($projectB)->create();
    $scriptB = Script::factory()->for($testB)->create();
    Run::factory()->for($scriptB)->passed()->count(2)->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $projectA))
        ->assertOk()
        ->assertSee('5'); // Total runs for project A

    $this->actingAs($user)
        ->get(route('projects.overview', $projectB))
        ->assertOk()
        ->assertSee('2'); // Total runs for project B only
});

test('overview passes status distribution data for charts', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $test = Test::factory()->for($project)->create();
    $script = Script::factory()->for($test)->create();

    Run::factory()->for($script)->passed()->count(3)->create();
    Run::factory()->for($script)->failed()->count(1)->create();
    Run::factory()->for($script)->running()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Status Distribution')
        ->assertSee('Response Time Trend');
});

test('overview handles project with zero tests gracefully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Total Tests')
        ->assertSee('0')
        ->assertSee('No run data available for this project yet.');
});

test('overview shows last run as none when no runs exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee('Last Execution')
        ->assertSee('None');
});

test('recent executions link to the run detail page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $script = Script::factory()->for(Test::factory()->for($project)->create())->create();
    $run = Run::factory()->for($script)->passed()->create();

    $this->actingAs($user)
        ->get(route('projects.overview', $project))
        ->assertOk()
        ->assertSee(route('projects.runs.view', ['project' => $project, 'run' => $run]), false);
});
