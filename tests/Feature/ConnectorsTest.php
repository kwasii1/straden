<?php

use App\Models\Connector;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.connectors', ['project' => $project]))
        ->assertRedirect(route('login'));
});

test('authenticated users can view connectors page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', ['project' => $project]))
        ->assertOk();
});

test('empty state shows when no connectors configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', ['project' => $project]))
        ->assertOk()
        ->assertSee('No connectors');
});

test('seeded influxdb connector is shown as system', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    Connector::factory()->influxDb()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', ['project' => $project]))
        ->assertOk()
        ->assertSee('System')
        ->assertSee('InfluxDB');
});

test('user can add a database connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'My Database')
        ->set('type', 'database')
        ->set('host', 'localhost')
        ->set('port', 5432)
        ->set('database', 'my_db')
        ->set('username', 'user')
        ->set('password', 'pass')
        ->call('addConnector')
        ->assertHasNoErrors();

    expect(Connector::where('name', 'My Database')->exists())->toBeTrue();
});

test('connector type must be valid', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'Bad Connector')
        ->set('type', 'invalid')
        ->call('addConnector')
        ->assertHasErrors(['type']);
});

test('user cannot delete system connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->influxDb()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->call('deleteConnector', $connector->id);

    expect(Connector::find($connector->id))->not->toBeNull();
});

test('user can delete non-system connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->database()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->call('deleteConnector', $connector->id);

    expect(Connector::find($connector->id))->toBeNull();
});

test('settings page shows git providers tab', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.settings', ['project' => $project]))
        ->assertOk()
        ->assertSee('Git Providers')
        ->assertSee('AI Providers');
});

test('git providers list shows empty state', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.settings', ['project' => $project])
        ->set('activeTab', 'git-providers')
        ->assertSee('No git providers');
});

test('git providers list shows connected providers', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    Connector::factory()->github()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.settings', ['project' => $project])
        ->set('activeTab', 'git-providers')
        ->assertSee('GitHub');
});

test('user can delete git provider connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->github()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.settings', ['project' => $project])
        ->call('deleteGitConnector', $connector->id);

    expect(Connector::find($connector->id))->toBeNull();
});
