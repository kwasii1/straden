<?php

use App\Ai\Agents\TestAgent;
use App\Ai\Tools\NamedTool;
use App\Ai\Tools\ScanContextTool;
use App\Mcp\Servers\StradenServer;
use App\Mcp\Tools\SetTestRepositories;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Test;
use App\Models\User;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->test = Test::factory()->create(['project_id' => $this->project->id]);
    $this->api = Repository::factory()->create(['project_id' => $this->project->id, 'name' => 'api']);
    $this->web = Repository::factory()->create(['project_id' => $this->project->id, 'name' => 'web']);
});

test('sync repositories only links repositories from the test project', function () {
    $foreign = Repository::factory()->create();

    $this->test->syncRepositories([$this->api->id, $foreign->id, 'not-a-uuid']);

    expect($this->test->repositories()->pluck('repositories.id')->all())->toBe([$this->api->id]);
});

test('context repositories are the linked ones, falling back to the whole project', function () {
    expect($this->test->contextRepositories()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->api->id, $this->web->id])->sort()->values()->all());

    $this->test->syncRepositories([$this->web->id]);

    expect($this->test->contextRepositories()->pluck('id')->all())->toBe([$this->web->id]);
});

test('the test agent only gets file tools for linked repositories', function () {
    $this->test->syncRepositories([$this->api->id]);

    $repoToolNames = collect((new TestAgent($this->test))->tools())
        ->filter(fn ($tool) => $tool instanceof NamedTool)
        ->map(fn (NamedTool $tool) => $tool->name())
        ->filter(fn (string $name) => str_starts_with($name, 'repo_'));

    expect($repoToolNames)->not->toBeEmpty()
        ->and($repoToolNames->every(fn (string $name) => str_starts_with($name, 'repo_'.$this->api->id.'_')))->toBeTrue();
});

test('scan context reports linked repositories and their scope', function () {
    $this->test->syncRepositories([$this->api->id]);

    $context = json_decode((string) (new ScanContextTool($this->test))->handle(new Request([])), true);

    expect($context['repositories_scope'])->toBe('linked')
        ->and(collect($context['repositories'])->pluck('name')->all())->toBe(['api']);
});

test('new test form links the selected repositories', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.new-test', ['project' => $this->project])
        ->set('name', 'Checkout')
        ->set('target_endpoint', 'https://shop.example.com')
        ->set('repositoryIds', [$this->api->id, $this->web->id])
        ->call('submit')
        ->assertHasNoErrors();

    $created = Test::where('name', 'Checkout')->sole();

    expect($created->repositories()->count())->toBe(2);
});

test('updating a test changes its linked repositories', function () {
    $this->test->syncRepositories([$this->api->id]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard.view-test', ['project' => $this->project, 'test' => $this->test])
        ->assertSet('testRepositories', [$this->api->id])
        ->call('startUpdate')
        ->set('testRepositories', [$this->web->id])
        ->call('updateTest')
        ->assertHasNoErrors();

    expect($this->test->repositories()->pluck('repositories.id')->all())->toBe([$this->web->id]);
});

test('the mcp server can link repositories to a test', function () {
    StradenServer::actingAs(User::factory()->create())
        ->tool(SetTestRepositories::class, [
            'test_id' => $this->test->id,
            'repository_ids' => [$this->web->id],
        ])
        ->assertOk()
        ->assertSee(['"scope": "linked"', 'web']);

    expect($this->test->repositories()->pluck('repositories.id')->all())->toBe([$this->web->id]);
});
