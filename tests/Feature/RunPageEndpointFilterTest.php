<?php

use App\Models\Connector;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeFilteredRun(): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->passed()->create(['script_id' => $script->id]);
}

function fakeRunPageInflux(): void
{
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';
        $t = 1690000000000;

        if (str_contains($q, 'SHOW TAG VALUES')) {
            return Http::response(['results' => [['series' => [['columns' => ['key', 'value'], 'values' => [
                ['name', 'https://api.example.com/v1/users'],
                ['name', 'https://api.example.com/v1/orders'],
            ]]]]]]);
        }

        $isDuration = str_contains($q, 'http_req_duration');

        if ($isDuration) {
            $series = ['columns' => ['time', 'p95', 'p99'], 'values' => [[$t, 100, 200]]];
        } else {
            $series = ['columns' => ['time', 'value'], 'values' => [[$t, 10]]];
        }

        return Http::response(['results' => [['series' => [$series]]]]);
    });
}

test('run detail page shows the endpoint filter with short labels', function () {
    fakeRunPageInflux();

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('All endpoints')
        ->assertSee('v1/users')
        ->assertSee('v1/orders')
        ->assertSee('Active VUs');
});

test('selecting an endpoint scopes the charts and shows a summary strip', function () {
    fakeRunPageInflux();

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run])
        ->set('selectedEndpoint', 'https://api.example.com/v1/users')
        ->assertSee('Total Requests')
        ->assertSee('100 ms')
        ->assertSee('200 ms')
        ->assertDontSee('Active VUs')
        ->assertDontSee('Data Transfer');
});

test('clearing the endpoint filter restores the run-level charts', function () {
    fakeRunPageInflux();

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run])
        ->set('selectedEndpoint', 'https://api.example.com/v1/users')
        ->assertDontSee('Active VUs')
        ->set('selectedEndpoint', '')
        ->assertSee('Active VUs')
        ->assertSee('Data Transfer');
});

test('run detail page hides the filter when no endpoints are available', function () {
    Http::fake(fn ($request) => Http::response(['results' => [['series' => []]]]));

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertDontSee('All endpoints');
});
