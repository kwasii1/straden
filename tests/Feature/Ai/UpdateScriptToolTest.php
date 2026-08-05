<?php

use App\Ai\Agents\TestAgent;
use App\Ai\Tools\UpdateScriptTool;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('update script tool reports existing files', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', 'import http from "k6/http";');
    Storage::disk('local')->put($basePath.'/config.js', 'const BASE_URL = "https://example.com";');
    Storage::disk('local')->makeDirectory($basePath.'/lib');
    Storage::disk('local')->put($basePath.'/lib/helper.js', 'export function foo() {}');

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [],
    ]);

    $result = (string) $tool->handle($request);

    expect($result)->toContain("Script '{$script->name}'");
    expect($result)->toContain('Existing files: [config.js, lib/helper.js, script.js]');
    expect($result)->toContain('No files updated.');
});

test('update script tool writes new file content', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', 'import http from "k6/http";');
    Storage::disk('local')->put($basePath.'/config.js', 'const BASE_URL = "https://old.example.com";');

    $newContent = 'const BASE_URL = "https://fakerforge.com/api/mock/lv3nx6gxyqiyc0lk/face-pipeline";';

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [
            ['path' => 'config.js', 'content' => $newContent],
        ],
    ]);

    $result = (string) $tool->handle($request);

    expect($result)->toContain('Updated 1 file(s): [config.js]');

    $writtenContent = Storage::disk('local')->get($basePath.'/config.js');
    expect($writtenContent)->toBe($newContent);

    $scriptJsContent = Storage::disk('local')->get($basePath.'/script.js');
    expect($scriptJsContent)->toBe('import http from "k6/http";');
});

test('update script tool creates a new file in the script directory', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', 'import http from "k6/http";');

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [
            ['path' => 'helpers.js', 'content' => 'export function generateToken() { return "abc"; }'],
        ],
    ]);

    $result = (string) $tool->handle($request);

    expect($result)->toContain('Updated 1 file(s): [helpers.js]');

    Storage::disk('local')->assertExists($basePath.'/helpers.js');
    expect(Storage::disk('local')->get($basePath.'/helpers.js'))->toBe('export function generateToken() { return "abc"; }');
});

test('update script tool creates subdirectories as needed', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', 'import http from "k6/http";');

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [
            ['path' => 'utils/math.js', 'content' => 'export function add(a, b) { return a + b; }'],
        ],
    ]);

    (string) $tool->handle($request);

    Storage::disk('local')->assertExists($basePath.'/utils/math.js');
    expect(Storage::disk('local')->get($basePath.'/utils/math.js'))->toBe('export function add(a, b) { return a + b; }');
});

test('update script tool updates script name and description', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create([
        'test_id' => $test->id,
        'name' => 'Old Name',
        'description' => 'Old description',
    ]);

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->makeDirectory($basePath);
    Storage::disk('local')->put($basePath.'/script.js', 'import http from "k6/http";');

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [],
        'name' => 'New Name',
        'description' => 'New description',
    ]);

    (string) $tool->handle($request);

    $script->refresh();

    expect($script->name)->toBe('New Name');
    expect($script->description)->toBe('New description');
});

test('update script tool rejects scripts not belonging to the test', function () {
    $test = Test::factory()->create();
    $otherTest = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $otherTest->id]);

    $tool = new UpdateScriptTool($test);

    $request = new Request([
        'script_id' => $script->id,
        'files' => [],
    ]);

    $tool->handle($request);
})->throws(ModelNotFoundException::class);

test('update script tool requires approval', function () {
    $test = Test::factory()->create();

    $tool = new UpdateScriptTool($test);

    expect($tool)->toBeInstanceOf(Approvable::class);
});

test('update script tool validates input schema', function () {
    $test = Test::factory()->create();

    $tool = new UpdateScriptTool($test);

    $mockSchema = Mockery::mock(JsonSchema::class);
    $mockSchema->shouldReceive('string')->andReturn(Mockery::mock()->shouldReceive('description', 'required')->andReturnSelf()->getMock());
    $mockSchema->shouldReceive('array')->andReturn(Mockery::mock()->shouldReceive('description', 'required')->andReturnSelf()->getMock());

    $schema = $tool->schema($mockSchema);

    expect($schema)->toHaveKey('script_id');
    expect($schema)->toHaveKey('name');
    expect($schema)->toHaveKey('description');
    expect($schema)->toHaveKey('files');
});

test('update script tool is registered in test agent', function () {
    $test = Test::factory()->create();

    $agent = new TestAgent($test);

    $tools = collect($agent->tools());

    $updateTools = $tools->filter(fn ($tool) => $tool instanceof UpdateScriptTool);

    expect($updateTools)->toHaveCount(1);
});
