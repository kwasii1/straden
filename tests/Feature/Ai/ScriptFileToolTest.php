<?php

use App\Ai\Tools\DeleteScriptFileTool;
use App\Ai\Tools\ListScriptFilesTool;
use App\Ai\Tools\ReadScriptFileTool;
use App\Ai\Tools\RenameScriptFileTool;
use App\Ai\Tools\WriteScriptFileTool;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

if (! function_exists('makeScriptWithFiles')) {
    function makeScriptWithFiles(Test $test, array $files = []): Script
    {
        $script = Script::factory()->create(['test_id' => $test->id]);

        $base = 'scripts/'.$test->id.'/'.$script->id;
        Storage::disk('local')->makeDirectory($base);

        foreach ($files as $path => $content) {
            $dir = dirname($base.'/'.$path);
            if (! Storage::disk('local')->directoryExists($dir)) {
                Storage::disk('local')->makeDirectory($dir);
            }
            Storage::disk('local')->put($base.'/'.$path, $content);
        }

        return $script;
    }
}

test('list script files tool returns the file tree', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, [
        'script.js' => 'export default function () {}',
        'lib/helpers.js' => 'export function helper() {}',
    ]);

    $tool = new ListScriptFilesTool($script);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    expect($result['script_id'])->toBe($script->id);
    expect($result['entry_point'])->toBe('script.js');
    expect($result['files'])->toHaveCount(2);
});

test('read script file tool returns file content', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['config.js' => 'const BASE_URL = "https://example.com";']);

    $tool = new ReadScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request(['path' => 'config.js'])), true);

    expect($result['found'])->toBeTrue();
    expect($result['content'])->toBe('const BASE_URL = "https://example.com";');
});

test('read script file tool reports missing files', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $tool = new ReadScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request(['path' => 'nope.js'])), true);

    expect($result['found'])->toBeFalse();
});

test('write script file tool creates a new file', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $tool = new WriteScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request([
        'path' => 'helpers.js',
        'content' => 'export function token() { return "abc"; }',
    ])), true);

    expect($result['status'])->toBe('created');

    $base = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertExists($base.'/helpers.js');
    expect(Storage::disk('local')->get($base.'/helpers.js'))->toBe('export function token() { return "abc"; }');
});

test('write script file tool overwrites an existing file', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['config.js' => 'old']);

    $tool = new WriteScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request([
        'path' => 'config.js',
        'content' => 'new',
    ])), true);

    expect($result['status'])->toBe('overwritten');

    $base = 'scripts/'.$test->id.'/'.$script->id;
    expect(Storage::disk('local')->get($base.'/config.js'))->toBe('new');
});

test('write script file tool creates subdirectories as needed', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $tool = new WriteScriptFileTool($script);
    (string) $tool->handle(new Request([
        'path' => 'utils/math.js',
        'content' => 'export function add(a, b) { return a + b; }',
    ]));

    $base = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertExists($base.'/utils/math.js');
});

test('write script file tool sets script path when creating entry point', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $tool = new WriteScriptFileTool($script);
    (string) $tool->handle(new Request([
        'path' => 'script.js',
        'content' => 'export default function () {}',
    ]));

    $script->refresh();

    expect($script->script_path)->toBe('scripts/'.$test->id.'/'.$script->id.'/script.js');
});

test('write script file tool validates the script after writing the entry point', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $validK6Script = <<<'JS'
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: 10,
    duration: '30s',
    thresholds: {
        http_req_duration: ['p(95)<500'],
    },
};

export default function () {
    const res = http.get('https://httpbin.org/get');
    check(res, {
        'status is 200': (r) => r.status === 200,
    });
    sleep(1);
}
JS;

    $tool = new WriteScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request([
        'path' => 'script.js',
        'content' => $validK6Script,
    ])), true);

    expect($result['is_entry_point'])->toBeTrue();
    expect($result['validation'])->not->toBeNull();
    expect($result['validation']['valid'])->toBeTrue();
});

test('write script file tool rejects paths escaping the script directory', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $tool = new WriteScriptFileTool($script);

    expect(fn () => $tool->handle(new Request([
        'path' => '../evil.js',
        'content' => 'x',
    ])))->toThrow(InvalidArgumentException::class);
});

test('delete script file tool deletes a file', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['old.js' => 'content']);

    $tool = new DeleteScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request(['path' => 'old.js'])), true);

    expect($result['deleted'])->toBeTrue();

    $base = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertMissing($base.'/old.js');
});

test('delete script file tool refuses to delete the entry point', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['script.js' => 'export default function () {}']);

    $tool = new DeleteScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request(['path' => 'script.js'])), true);

    expect($result['deleted'])->toBeFalse();
    expect($result['error'])->toContain('cannot be deleted');

    $base = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertExists($base.'/script.js');
});

test('rename script file tool renames a file', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['old.js' => 'content']);

    $tool = new RenameScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request([
        'source_path' => 'old.js',
        'new_name' => 'new.js',
    ])), true);

    expect($result['renamed'])->toBeTrue();

    $base = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertMissing($base.'/old.js');
    Storage::disk('local')->assertExists($base.'/new.js');
});

test('rename script file tool refuses to rename the entry point', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test, ['script.js' => 'export default function () {}']);

    $tool = new RenameScriptFileTool($script);
    $result = json_decode((string) $tool->handle(new Request([
        'source_path' => 'script.js',
        'new_name' => 'main.js',
    ])), true);

    expect($result['renamed'])->toBeFalse();
    expect($result['error'])->toContain('cannot be renamed');
});

test('write, delete, and rename tools require approval', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    expect(new WriteScriptFileTool($script))->toBeInstanceOf(Approvable::class);
    expect(new DeleteScriptFileTool($script))->toBeInstanceOf(Approvable::class);
    expect(new RenameScriptFileTool($script))->toBeInstanceOf(Approvable::class);
});

test('write script file tool validates input schema', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $mockSchema = Mockery::mock(JsonSchema::class);
    $mockSchema->shouldReceive('string')->andReturn(Mockery::mock()->shouldReceive('description', 'required')->andReturnSelf()->getMock());

    $schema = (new WriteScriptFileTool($script))->schema($mockSchema);

    expect($schema)->toHaveKey('path');
    expect($schema)->toHaveKey('content');
});

test('read script file tool validates input schema', function () {
    $test = Test::factory()->create();
    $script = makeScriptWithFiles($test);

    $mockSchema = Mockery::mock(JsonSchema::class);
    $mockSchema->shouldReceive('string')->andReturn(Mockery::mock()->shouldReceive('description', 'required')->andReturnSelf()->getMock());

    $schema = (new ReadScriptFileTool($script))->schema($mockSchema);

    expect($schema)->toHaveKey('path');
});
