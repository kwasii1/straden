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
        ->assertSee('Search endpoints', false)
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
        ->assertSee('Total requests')
        ->assertSee('100 ms')
        ->assertSee('200 ms')
        ->assertSee('are run-level only')
        ->assertDontSee('Active VUs');
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
        ->assertSee('Data transfer');
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

function fakeGroupedRunPageInflux(): void
{
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';
        $t = 1690000000000;

        if (str_contains($q, 'SHOW TAG VALUES')) {
            return Http::response(['results' => [['series' => [['columns' => ['key', 'value'], 'values' => [
                ['name', 'https://api.example.com/v1/users'],
                ['name', 'https://api.example.com/todos/1'],
                ['name', 'https://api.example.com/todos/2'],
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

test('dynamic endpoints collapse into one grouped filter row', function () {
    fakeGroupedRunPageInflux();

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    $this->actingAs($user)
        ->get(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]))
        ->assertOk()
        ->assertSee('{id}', false)
        ->assertSee('Search endpoints', false);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run]);

    expect($component->instance()->endpoints)->toBe([
        [
            'pattern' => 'https://api.example.com/v1/users',
            'label' => 'v1/users',
            'count' => 1,
            'names' => ['https://api.example.com/v1/users'],
        ],
        [
            'pattern' => 'https://api.example.com/todos/{id}',
            'label' => 'todos/{id}',
            'count' => 2,
            'names' => [
                'https://api.example.com/todos/1',
                'https://api.example.com/todos/2',
            ],
        ],
    ]);
});

test('selecting a grouped pattern scopes queries across every raw url', function () {
    fakeGroupedRunPageInflux();

    Connector::factory()->influxDb()->create();
    $user = User::factory()->create();
    $run = makeFilteredRun();

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-run', ['project' => $run->script->test->project, 'run' => $run])
        ->set('selectedEndpoint', 'https://api.example.com/todos/{id}')
        ->assertSee('Total requests');

    // One anchored regex covers the whole group — no per-URL OR enumeration.
    Http::assertSent(function ($request) {
        $q = $request['q'] ?? '';

        return str_contains($q, '"name" =~') && str_contains($q, 'todos');
    });

    foreach (Http::recorded() as $pair) {
        expect($pair[0]['q'] ?? '')->not->toContain(' OR ');
    }
});
