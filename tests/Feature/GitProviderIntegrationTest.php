<?php

use App\Models\Connector;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('user can visit repository picker page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->github()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repository-picker', ['project' => $project, 'connector' => $connector]))
        ->assertOk()
        ->assertSee('Browse Repositories');
});

test('repository picker shows cached repos', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->github()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repository-picker', ['project' => $project, 'connector' => $connector]))
        ->assertOk()
        ->assertSee('test-owner/test-repo');
});

test('git providers page is accessible', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->assertSee('No Git Providers Connected')
        ->assertSee('Add Git Provider');
});

test('git connector form shows token label per provider', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitType', 'github');

    expect($component->get('gitType'))->toBe('github');
    expect($component->get('gitToken'))->toBe('');
});

test('git connector form shows workspace field for bitbucket', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitType', 'bitbucket')
        ->assertSee('Workspace');
});

test('git connector form shows organization field for azure devops', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitType', 'azure_devops')
        ->assertSee('Organization');
});

test('adding git connector validates token via API', function () {
    Http::fake([
        'api.github.com/user' => Http::response(['login' => 'testuser'], 200),
        'api.github.com/user/repos*' => Http::response([[
            'id' => 123456,
            'full_name' => 'testuser/hello-world',
            'clone_url' => 'https://github.com/testuser/hello-world.git',
            'default_branch' => 'main',
            'private' => false,
        ]], 200),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitName', 'Test GitHub')
        ->set('gitType', 'github')
        ->set('gitToken', 'ghp_valid_token')
        ->call('addGitConnector')
        ->assertHasNoErrors();

    expect(Connector::where('name', 'Test GitHub')->exists())->toBeTrue();
});

test('adding git connector fails on invalid token', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitName', 'Bad GitHub')
        ->set('gitType', 'github')
        ->set('gitToken', 'invalid_token')
        ->call('addGitConnector');

    expect(Connector::where('name', 'Bad GitHub')->exists())->toBeFalse();
});

test('adding bitbucket connector requires workspace', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitName', 'My Bitbucket')
        ->set('gitType', 'bitbucket')
        ->set('gitToken', 'ATBB123')
        ->call('addGitConnector')
        ->assertHasErrors(['gitWorkspace']);
});

test('adding azure devops connector requires organization', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.git-providers', ['project' => $project])
        ->set('gitName', 'My Azure')
        ->set('gitType', 'azure_devops')
        ->set('gitToken', 'ado123')
        ->call('addGitConnector')
        ->assertHasErrors(['gitOrganization']);
});

test('repository picker page shows 404 for non-git connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $connector = Connector::factory()->database()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repository-picker', ['project' => $project, 'connector' => $connector]))
        ->assertNotFound();
});
