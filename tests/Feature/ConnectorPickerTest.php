<?php

use App\Livewire\ConnectorPicker;
use App\Models\Connector;
use App\Models\Project;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makePickerProject(): Project
{
    $project = Project::factory()->create();

    Connector::factory()->influxDb()->create();
    Connector::factory()->prometheus()->create(['project_id' => $project->id]);
    Connector::factory()->database()->create(['project_id' => $project->id]);

    return $project;
}

test('picker excludes influx from options but exposes it as locked', function () {
    $user = User::factory()->create();
    $project = makePickerProject();

    $component = Livewire::actingAs($user)
        ->test(ConnectorPicker::class, ['project' => $project]);

    $options = $component->get('options');

    expect($options)->toHaveCount(2)
        ->and(collect($options)->pluck('type'))->not->toContain('InfluxDB')
        ->and($component->get('influxConnectorId'))->not->toBeNull()
        ->and($component->get('selected'))->toBe([]);
});

test('picker toggles connectors but refuses influx and unknown ids', function () {
    $user = User::factory()->create();
    $project = makePickerProject();
    $optionId = Connector::query()
        ->where('project_id', $project->id)
        ->value('id');

    $component = Livewire::actingAs($user)
        ->test(ConnectorPicker::class, ['project' => $project])
        ->call('toggle', (string) $optionId)
        ->assertSet('selected', [(string) $optionId])
        ->call('toggle', (string) $optionId)
        ->assertSet('selected', []);

    $influxId = $component->get('influxConnectorId');

    $component
        ->call('toggle', (string) $influxId)
        ->assertSet('selected', [])
        ->call('toggle', '00000000-0000-0000-0000-000000000000')
        ->assertSet('selected', [])
        ->call('remove', (string) $influxId)
        ->assertSet('selected', []);
});

test('new test attaches selection plus influx and ignores foreign ids', function () {
    $user = User::factory()->create();
    $project = makePickerProject();
    $foreign = Connector::factory()->prometheus()->create();

    $optionId = (string) Connector::query()
        ->where('project_id', $project->id)
        ->value('id');

    Livewire::actingAs($user)
        ->test('pages::dashboard.new-test', ['project' => $project])
        ->set('name', 'Load Test')
        ->set('target_endpoint', 'https://api.example.com')
        ->set('connectors', [$optionId, (string) $foreign->id, 'not-a-uuid'])
        ->call('submit')
        ->assertHasNoErrors();

    $test = Test::query()->where('project_id', $project->id)->firstOrFail();
    $attached = $test->connectors()->pluck('connectors.id')->map(strval(...))->all();

    expect($attached)->toContain($optionId)
        ->and($attached)->toContain(Connector::query()->where('type', 'influxdb')->value('id'))
        ->and($attached)->not->toContain((string) $foreign->id)
        ->and($attached)->toHaveCount(2);
});

test('view test sync persists adds and removals while keeping influx', function () {
    $user = User::factory()->create();
    $project = makePickerProject();
    $test = Test::factory()->create(['project_id' => $project->id]);

    $ids = Connector::query()->where('project_id', $project->id)->pluck('id')->map(strval(...))->all();

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.view-test', ['project' => $project, 'test' => $test])
        ->assertSet('testConnectors', []);

    $component->call('startUpdate')->set('testConnectors', $ids)->call('updateTest')
        ->assertHasNoErrors();

    $attached = $test->connectors()->pluck('connectors.id')->map(strval(...))->all();

    expect($attached)->toContain(...$ids)
        ->and($attached)->toContain((string) Connector::query()->where('type', 'influxdb')->value('id'));

    $component->call('startUpdate')->set('testConnectors', [])->call('updateTest')
        ->assertHasNoErrors();

    expect($test->connectors()->pluck('connectors.id')->map(strval(...))->all())
        ->toBe([(string) Connector::query()->where('type', 'influxdb')->value('id')]);
});

function makeViewTest(): Test
{
    $project = makePickerProject();

    return Test::factory()->create(['project_id' => $project->id]);
}

test('update modal pre-fills and saves fields plus connectors', function () {
    $user = User::factory()->create();
    $test = makeViewTest();
    $project = $test->project;

    $optionId = (string) Connector::query()->where('project_id', $project->id)->value('id');
    $influxId = (string) Connector::query()->where('type', 'influxdb')->value('id');

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test', ['project' => $project, 'test' => $test])
        ->call('startUpdate')
        ->assertSet('testName', $test->name)
        ->assertSet('targetUrl', $test->target_url)
        ->set('testName', 'Renamed Test')
        ->set('targetUrl', 'https://staging.example.com')
        ->set('testDescription', 'Updated description')
        ->set('testConnectors', [$optionId])
        ->call('updateTest')
        ->assertHasNoErrors();

    $test->refresh();

    expect($test->name)->toBe('Renamed Test')
        ->and($test->target_url)->toBe('https://staging.example.com')
        ->and($test->description)->toBe('Updated description')
        ->and($test->connectors()->pluck('connectors.id')->map(strval(...))->sort()->values()->all())
        ->toBe(collect([$optionId, $influxId])->sort()->values()->all());
});

test('update test rejects invalid fields', function () {
    $user = User::factory()->create();
    $test = makeViewTest();

    Livewire::actingAs($user)
        ->test('pages::dashboard.view-test', ['project' => $test->project, 'test' => $test])
        ->call('startUpdate')
        ->set('testName', '')
        ->set('targetUrl', 'not-a-url')
        ->call('updateTest')
        ->assertHasErrors(['testName', 'targetUrl']);

    expect($test->refresh()->name)->not->toBe('');
});

test('connectors card reports the test connectors', function () {
    $user = User::factory()->create();
    $test = makeViewTest();

    $optionId = (string) Connector::query()->where('project_id', $test->project_id)->value('id');
    $test->syncConnectors([$optionId]);

    $this->actingAs($user)
        ->get(route('projects.view-test', ['project' => $test->project, 'test' => $test]))
        ->assertOk()
        ->assertSee('2 connectors', false);
});
