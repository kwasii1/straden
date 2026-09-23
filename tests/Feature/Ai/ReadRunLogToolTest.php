<?php

use App\Ai\Tools\ReadRunLogTool;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function makeLogRun(): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->failed()->create(['script_id' => $script->id]);
}

test('read run log tool returns persisted log content', function () {
    $run = makeLogRun();

    Storage::disk('local')->put('run-logs/'.$run->id.'.log', "starting k6...\nWARN[0001] some warning\n");

    $result = json_decode((string) (new ReadRunLogTool($run))->handle(new Request([])), true);

    expect($result['available'])->toBeTrue();
    expect($result['run_id'])->toBe($run->id);
    expect($result['truncated'])->toBeFalse();
    expect($result['content'])->toContain('WARN[0001] some warning');
});

test('read run log tool returns unavailable when no log exists', function () {
    $run = makeLogRun();

    $result = json_decode((string) (new ReadRunLogTool($run))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('No run log is available');
});

test('read run log tool truncates large logs', function () {
    $run = makeLogRun();

    $large = str_repeat('line of log output'."\n", 20000);

    Storage::disk('local')->put('run-logs/'.$run->id.'.log', $large);

    $result = json_decode((string) (new ReadRunLogTool($run))->handle(new Request([])), true);

    expect($result['available'])->toBeTrue();
    expect($result['truncated'])->toBeTrue();
    expect(strlen($result['content']))->toBeLessThan(strlen($large));
    expect($result['content'])->toStartWith('...');
});
