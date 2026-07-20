<?php

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from repositories page', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.repositories', ['project' => $project]))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login from repository browse page', function () {
    $project = Project::factory()->create();
    $repository = Repository::factory()->create(['project_id' => $project->id]);

    $this->get(route('projects.repository-browse', ['project' => $project, 'repository' => $repository]))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the repositories page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.repositories', ['project' => $project]))
        ->assertOk();
});

test('authenticated users can visit the repository browse page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->synced()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repository-browse', ['project' => $project, 'repository' => $repository]))
        ->assertOk();
});

test('user can add a git repository', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->set('name', 'My Git Repo')
        ->set('type', 'git')
        ->set('git_url', 'https://github.com/user/repo.git')
        ->set('git_branch', 'main')
        ->set('git_auth_type', 'none')
        ->call('addRepository')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('type', 'git');

    expect($project->repositories()->count())->toBe(1);
    expect($project->repositories()->first()->name)->toBe('My Git Repo');
});

test('user can add a local path repository', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->set('name', 'My Local Repo')
        ->set('type', 'local_path')
        ->set('local_path', '/var/www/my-app')
        ->call('addRepository')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('type', 'git');

    expect($project->repositories()->count())->toBe(1);
    expect($project->repositories()->first()->name)->toBe('My Local Repo');
});

test('user can delete a repository', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->call('deleteRepository', $repository->id);

    expect($project->repositories()->count())->toBe(0);
});

test('repositories page shows empty state when no repositories', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.repositories', ['project' => $project]))
        ->assertOk()
        ->assertSee('No repositories connected');
});

test('repositories page lists existing repositories', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->synced()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repositories', ['project' => $project]))
        ->assertOk()
        ->assertSee($repository->name);
});

test('browse page shows empty state when file tree is null', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->create([
        'project_id' => $project->id,
        'file_tree' => null,
    ]);

    $this->actingAs($user)
        ->get(route('projects.repository-browse', ['project' => $project, 'repository' => $repository]))
        ->assertOk()
        ->assertSee('No file tree available');
});

test('browse page shows file tree when populated', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->synced()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('projects.repository-browse', ['project' => $project, 'repository' => $repository]))
        ->assertOk()
        ->assertSee('src')
        ->assertSee('README.md');
});

test('adding a git repository requires valid url', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->set('name', 'Bad Repo')
        ->set('type', 'git')
        ->set('git_url', 'not-a-url')
        ->call('addRepository')
        ->assertHasErrors(['git_url']);
});

test('adding a local path repository requires path', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->set('name', 'Bad Path')
        ->set('type', 'local_path')
        ->set('local_path', '')
        ->call('addRepository')
        ->assertHasErrors(['local_path']);
});
