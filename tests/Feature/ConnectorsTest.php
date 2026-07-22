<?php

use App\Models\Connector;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.connectors', $project))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the connectors page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', $project))
        ->assertOk()
        ->assertSee('Connectors')
        ->assertSee('Connect external services');
});

test('connectors page shows empty state when no connectors exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', $project))
        ->assertOk()
        ->assertSee('No connectors configured.');
});

test('connectors page shows seeded InfluxDB connector as system', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $connector = Connector::factory()->influxDb()->create(['name' => 'InfluxDB (built-in)']);

    $this->actingAs($user)
        ->get(route('projects.connectors', $project))
        ->assertOk()
        ->assertSee('InfluxDB (built-in)')
        ->assertSee('System');
});

test('authenticated users can add a connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'My Database')
        ->set('type', 'database')
        ->set('host', '127.0.0.1')
        ->set('port', 5432)
        ->set('database', 'my_app')
        ->call('addConnector')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    expect(Connector::where('name', 'My Database')->exists())->toBeTrue();
});

test('add connector requires valid type', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'Bad Connector')
        ->set('type', 'invalid_type')
        ->call('addConnector')
        ->assertHasErrors(['type']);
});

test('system connectors cannot be deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $connector = Connector::factory()->influxDb()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->call('deleteConnector', $connector->id);

    expect(Connector::where('id', $connector->id)->exists())->toBeTrue();
});

test('non-system connectors can be deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $connector = Connector::factory()->database()->for($project)->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->call('deleteConnector', $connector->id);

    expect(Connector::where('id', $connector->id)->exists())->toBeFalse();
});

test('test connection records last tested timestamp', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $connector = Connector::factory()->influxDb()->create([
        'host' => '127.0.0.1',
        'port' => 9999,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->call('testConnection', $connector->id);

    $connector->refresh();

    expect($connector->last_tested_at)->not->toBeNull()
        ->and($connector->last_test_successful)->toBeFalse();
});
