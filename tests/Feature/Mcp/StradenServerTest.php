<?php

use App\Jobs\RunTestJob;
use App\Mcp\Servers\StradenServer;
use App\Mcp\Tools\CancelRun;
use App\Mcp\Tools\CreateScript;
use App\Mcp\Tools\GetRunStatus;
use App\Mcp\Tools\GetTestContext;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListRuns;
use App\Mcp\Tools\ReadScriptFile;
use App\Mcp\Tools\StartRun;
use App\Mcp\Tools\UpdateScript;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['name' => 'Checkout']);
    $this->test = Test::factory()->create(['project_id' => $this->project->id, 'name' => 'Checkout API']);
});

test('the mcp endpoint rejects unauthenticated requests', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertUnauthorized();
});

test('the mcp endpoint accepts a sanctum bearer token', function () {
    $token = $this->user->createToken('agent', ['mcp:read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->assertSee('list-projects');
});

test('list projects returns projects and tests', function () {
    StradenServer::actingAs($this->user)
        ->tool(ListProjects::class)
        ->assertOk()
        ->assertSee(['Checkout', 'Checkout API', $this->test->id]);
});

test('get test context returns the test', function () {
    StradenServer::actingAs($this->user)
        ->tool(GetTestContext::class, ['test_id' => $this->test->id])
        ->assertOk()
        ->assertSee($this->test->target_url);
});

test('missing models return an error', function () {
    StradenServer::actingAs($this->user)
        ->tool(GetTestContext::class, ['test_id' => 'nope'])
        ->assertHasErrors(['Test not found.']);
});

test('scripts can be created, updated and read', function () {
    Sanctum::actingAs($this->user, ['mcp:read', 'mcp:write']);

    StradenServer::actingAs($this->user)
        ->tool(CreateScript::class, [
            'test_id' => $this->test->id,
            'name' => 'Smoke',
            'entry_point_content' => 'export default function () {}',
        ])
        ->assertOk()
        ->assertSee("Created script 'Smoke'");

    $script = Script::where('name', 'Smoke')->firstOrFail();

    StradenServer::actingAs($this->user)
        ->tool(UpdateScript::class, [
            'script_id' => $script->id,
            'files' => [['path' => 'lib/helpers.js', 'content' => 'export const x = 1;']],
        ])
        ->assertOk()
        ->assertSee('lib/helpers.js');

    StradenServer::actingAs($this->user)
        ->tool(ReadScriptFile::class, ['script_id' => $script->id, 'path' => 'lib/helpers.js'])
        ->assertOk()
        ->assertSee('export const x = 1;');
});

test('tools enforce token abilities', function () {
    Sanctum::actingAs($this->user, ['mcp:read']);

    StradenServer::actingAs($this->user)
        ->tool(CreateScript::class, [
            'test_id' => $this->test->id,
            'name' => 'Smoke',
            'entry_point_content' => 'export default function () {}',
        ])
        ->assertHasErrors(["This token is missing the 'mcp:write' ability required by this tool."]);

    expect(Script::count())->toBe(0);
});

test('runs can be started, listed, polled and cancelled', function () {
    Queue::fake();
    Sanctum::actingAs($this->user, ['mcp:read', 'mcp:run']);

    $script = Script::factory()->create(['test_id' => $this->test->id]);

    StradenServer::actingAs($this->user)
        ->tool(StartRun::class, ['script_id' => $script->id])
        ->assertOk()
        ->assertSee('Run queued.');

    $run = Run::firstOrFail();

    expect($run->status)->toBe('queued')
        ->and($run->triggered_by)->toBe('mcp')
        ->and($run->triggered_by_user_id)->toBe($this->user->id);

    Queue::assertPushed(RunTestJob::class);

    StradenServer::actingAs($this->user)
        ->tool(StartRun::class, ['script_id' => $script->id])
        ->assertHasErrors();

    StradenServer::actingAs($this->user)
        ->tool(ListRuns::class, ['test_id' => $this->test->id])
        ->assertOk()
        ->assertSee($run->id);

    StradenServer::actingAs($this->user)
        ->tool(CancelRun::class, ['run_id' => $run->id])
        ->assertOk();

    StradenServer::actingAs($this->user)
        ->tool(GetRunStatus::class, ['run_id' => $run->id])
        ->assertSee('aborted');
});
