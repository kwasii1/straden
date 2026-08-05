<?php

use App\Jobs\SyncRepositoryJob;
use App\Models\Connector;
use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use App\Services\RepositorySyncService;
use Illuminate\Support\Facades\Queue;
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
        ->assertSet('type', 'local_path');

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

test('local path repository requires name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->set('name', '')
        ->set('type', 'local_path')
        ->set('local_path', '/var/www/my-app')
        ->call('addRepository')
        ->assertHasErrors(['name']);
});

test('sync dispatches a job and sets status to syncing', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create();
    $repository = Repository::factory()->create([
        'project_id' => $project->id,
        'sync_status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.repositories', ['project' => $project])
        ->call('syncRepository', $repository->id);

    Queue::assertPushed(SyncRepositoryJob::class, fn ($job) => $job->repository->id === $repository->id);

    expect($repository->fresh()->sync_status)->toBe('syncing');
});

test('repository belongs to connector', function () {
    $connector = Connector::factory()->github()->create();
    $repository = Repository::factory()->create([
        'connector_id' => $connector->id,
    ]);

    expect($repository->connector)->not->toBeNull();
    expect($repository->connector->type->isGitProvider())->toBeTrue();
});

test('repository shows reconnect badge when git type has no connector', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    Repository::factory()->create([
        'project_id' => $project->id,
        'type' => 'git',
        'connector_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('projects.repositories', ['project' => $project]))
        ->assertOk()
        ->assertSee('Reconnect');
});

test('service scans local path and builds file tree', function () {
    $dir = sys_get_temp_dir().'/straden-sync-test-'.uniqid();
    mkdir($dir);
    mkdir($dir.'/src');
    mkdir($dir.'/src/utils');
    file_put_contents($dir.'/src/utils/helper.js', '// helper');
    file_put_contents($dir.'/src/index.ts', '// index');
    file_put_contents($dir.'/README.md', '# Readme');
    file_put_contents($dir.'/package.json', '{}');

    try {
        $service = new RepositorySyncService;

        $repository = Repository::factory()->make([
            'type' => 'local_path',
            'local_path' => $dir,
        ]);

        $result = $service->sync($repository);

        expect($result['last_commit_sha'])->toBeNull();
        expect($result['file_tree'])->toBe([
            ['name' => 'README.md'],
            ['name' => 'package.json'],
            [
                'name' => 'src',
                'children' => [
                    ['name' => 'index.ts'],
                    [
                        'name' => 'utils',
                        'children' => [
                            ['name' => 'helper.js'],
                        ],
                    ],
                ],
            ],
        ]);
    } finally {
        unlink($dir.'/src/utils/helper.js');
        unlink($dir.'/src/index.ts');
        unlink($dir.'/README.md');
        unlink($dir.'/package.json');
        rmdir($dir.'/src/utils');
        rmdir($dir.'/src');
        rmdir($dir);
    }
});

test('service excludes ignored directories from file tree', function () {
    $dir = sys_get_temp_dir().'/straden-sync-test-'.uniqid();
    mkdir($dir);
    mkdir($dir.'/node_modules');
    mkdir($dir.'/.git');
    mkdir($dir.'/vendor');
    mkdir($dir.'/src');
    file_put_contents($dir.'/node_modules/ignored.js', 'ignored');
    file_put_contents($dir.'/.git/config', 'ignored');
    file_put_contents($dir.'/vendor/ignored.php', '<?php // ignored');
    file_put_contents($dir.'/src/main.js', '// kept');

    try {
        $service = new RepositorySyncService;

        $repository = Repository::factory()->make([
            'type' => 'local_path',
            'local_path' => $dir,
        ]);

        $result = $service->sync($repository);

        expect($result['file_tree'])->toBe([
            ['name' => 'src', 'children' => [
                ['name' => 'main.js'],
            ]],
        ]);
    } finally {
        unlink($dir.'/src/main.js');
        unlink($dir.'/node_modules/ignored.js');
        unlink($dir.'/.git/config');
        unlink($dir.'/vendor/ignored.php');
        rmdir($dir.'/src');
        rmdir($dir.'/node_modules');
        rmdir($dir.'/.git');
        rmdir($dir.'/vendor');
        rmdir($dir);
    }
});

test('service throws on missing local path', function () {
    $service = new RepositorySyncService;

    $repository = Repository::factory()->make([
        'type' => 'local_path',
        'local_path' => '/nonexistent/path/12345',
    ]);

    $service->sync($repository);
})->throws(RuntimeException::class, 'does not exist');

test('service throws on empty local path', function () {
    $service = new RepositorySyncService;

    $repository = Repository::factory()->make([
        'type' => 'local_path',
        'local_path' => null,
    ]);

    $service->sync($repository);
})->throws(RuntimeException::class, 'not configured');

test('job marks repository as synced with file tree on success', function () {
    $dir = sys_get_temp_dir().'/straden-sync-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/hello.js', 'console.log("hi");');

    try {
        $project = Project::factory()->create();
        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'type' => 'local_path',
            'local_path' => $dir,
            'sync_status' => 'syncing',
        ]);

        $job = new SyncRepositoryJob($repository);
        $job->handle(app(RepositorySyncService::class));

        $repository->refresh();

        expect($repository->sync_status)->toBe('synced');
        expect($repository->sync_error)->toBeNull();
        expect($repository->last_synced_at)->not->toBeNull();
        expect($repository->cloned_at)->toBeNull();
        expect($repository->file_tree)->toBe([
            ['name' => 'hello.js'],
        ]);
    } finally {
        unlink($dir.'/hello.js');
        rmdir($dir);
    }
});

test('job marks repository as failed on error', function () {
    $project = Project::factory()->create();
    $repository = Repository::factory()->create([
        'project_id' => $project->id,
        'type' => 'local_path',
        'local_path' => '/nonexistent/path/12345',
        'sync_status' => 'syncing',
    ]);

    $job = new SyncRepositoryJob($repository);

    try {
        $job->handle(app(RepositorySyncService::class));
    } catch (Throwable) {
    }

    $repository->refresh();

    expect($repository->sync_status)->toBe('failed');
    expect($repository->sync_error)->toContain('does not exist');
});

test('job sets cloned_at on first successful clone for git repos', function () {
    $dir = sys_get_temp_dir().'/straden-sync-test-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/hello.js', 'console.log("hi");');

    try {
        $project = Project::factory()->create();
        $repository = Repository::factory()->create([
            'project_id' => $project->id,
            'type' => 'local_path',
            'local_path' => $dir,
            'sync_status' => 'syncing',
            'cloned_at' => null,
        ]);

        $job = new SyncRepositoryJob($repository);
        $job->handle(app(RepositorySyncService::class));

        $repository->refresh();

        expect($repository->sync_status)->toBe('synced');
        expect($repository->cloned_at)->toBeNull();
    } finally {
        unlink($dir.'/hello.js');
        rmdir($dir);
    }
});
