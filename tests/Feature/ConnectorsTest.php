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
        ->set('type', 'postgres')
        ->set('host', 'localhost')
        ->set('port', 5432)
        ->set('database', 'my_db')
        ->set('username', 'user')
        ->set('password', 'pass')
        ->call('addConnector')
        ->assertHasNoErrors();

    expect(Connector::where('name', 'My Database')->exists())->toBeTrue();
});

test('user can add each observability connector type', function (string $type, int $port) {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', "My {$type}")
        ->set('type', $type)
        ->set('host', 'localhost')
        ->set('port', $port)
        ->set('database', $type === 'redis' ? '0' : 'my_db')
        ->call('addConnector')
        ->assertHasNoErrors();

    expect(Connector::where('name', "My {$type}")->exists())->toBeTrue();
})->with([
    ['prometheus', 9090],
    ['mysql', 3306],
    ['postgres', 5432],
    ['mongodb', 27017],
    ['redis', 6379],
]);

test('host is required for connection-based connectors', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'Missing Host')
        ->set('type', 'prometheus')
        ->call('addConnector')
        ->assertHasErrors(['host']);
});

test('selecting a type pre-fills its default port', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('type', 'redis');

    $component->assertSet('port', 6379);
});

test('redis database index must be an integer', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.connectors', ['project' => $project])
        ->set('name', 'Bad Redis')
        ->set('type', 'redis')
        ->set('host', 'localhost')
        ->set('port', 6379)
        ->set('database', 'not-an-index')
        ->call('addConnector')
        ->assertHasErrors(['database']);
});

test('observability connector types appear in the form', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.connectors', ['project' => $project]))
        ->assertOk()
        ->assertSee('Prometheus')
        ->assertSee('MongoDB')
        ->assertSee('Redis');
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

test('git providers page is accessible', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.git-providers', ['project' => $project]))
        ->assertOk()
        ->assertSee('Git Providers');
});

test('ai providers page is accessible', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.ai-integrations'))
        ->assertOk()
        ->assertSee('AI Integrations');
});

test('git providers list shows empty state', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->assertSee('No Git Providers Connected');
});

test('git providers list shows connected providers', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    Connector::factory()->github()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->assertSee('GitHub');
});

test('user can delete git provider connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->github()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->call('deleteGitConnector', $connector->id);

    expect(Connector::find($connector->id))->toBeNull();
});
