<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Notifications\RunCompleted;
use App\Services\RunResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function writeRunArtifacts(Run $run, ?int $exitCode, ?array $summary): void
{
    if ($exitCode !== null) {
        file_put_contents(RunResultService::exitCodeFilePath($run->id), (string) $exitCode);
    }

    if ($summary !== null) {
        file_put_contents(RunResultService::summaryFilePath($run->id), json_encode($summary));
    }
}

function k6Summary(array $overrides = []): array
{
    $summary = [
        'metrics' => [
            'vus_max' => ['value' => 50],
            'http_reqs' => ['count' => 5000, 'rate' => 150.5],
            'http_req_duration' => [
                'p(95)' => 250.5,
                'p(99)' => 400.2,
                'thresholds' => ['p(95)<500' => true],
            ],
            'http_req_failed' => ['passes' => 50, 'fails' => 4950],
        ],
        'root_group' => [
            'checks' => [['passes' => 100, 'fails' => 5]],
        ],
    ];

    return array_replace_recursive($summary, $overrides);
}

test('finalize marks a successful run as passed with parsed metrics', function () {
    $this->travel(-1)->minute();
    $run = Run::factory()->running()->create(['started_at' => now()]);
    writeRunArtifacts($run, 0, k6Summary());
    $this->travelBack();

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('passed');
    expect($run->exit_code)->toBe(0);
    expect($run->vus_max)->toBe(50);
    expect($run->requests_total)->toBe(5000);
    expect($run->requests_per_second)->toEqual(150.5);
    expect($run->req_duration_p95_ms)->toEqual(250.5);
    expect($run->req_duration_p99_ms)->toEqual(400.2);
    expect($run->error_rate)->toEqual(1.0);
    expect($run->checks_total)->toBe(105);
    expect($run->checks_failed)->toBe(5);
    expect($run->thresholds_passed)->toBeTrue();
    expect($run->thresholds_summary)->toBe([
        [
            'name' => 'http_req_duration',
            'condition' => 'p(95)<500',
            'ok' => true,
            'value' => 250.5,
        ],
    ]);
    expect($run->completed_at)->not->toBeNull();
    expect($run->duration_seconds)->toBe(60);

    expect(file_exists(RunResultService::summaryFilePath($run->id)))->toBeFalse();
    expect(file_exists(RunResultService::exitCodeFilePath($run->id)))->toBeFalse();
});

test('finalize marks a threshold failure as failed', function () {
    $run = Run::factory()->running()->create();
    writeRunArtifacts($run, 99, k6Summary([
        'metrics' => [
            'http_req_duration' => [
                'p(95)' => 800.5,
                'thresholds' => ['p(95)<500' => false],
            ],
        ],
    ]));

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('failed');
    expect($run->thresholds_passed)->toBeFalse();
    expect($run->thresholds_summary[0]['name'])->toBe('http_req_duration');
    expect($run->thresholds_summary[0]['condition'])->toBe('p(95)<500');
    expect($run->thresholds_summary[0]['ok'])->toBeFalse();
    expect($run->thresholds_summary[0]['value'])->toEqual(800.5);
});

test('thresholds are self-evaluated against the summary metric values', function () {
    expect(RunResultService::evaluateThreshold('count>100', ['count' => 324]))->toBeTrue();
    expect(RunResultService::evaluateThreshold('count>100', ['count' => 50]))->toBeFalse();
    expect(RunResultService::evaluateThreshold('rate<1', ['value' => 0.188]))->toBeTrue();
    expect(RunResultService::evaluateThreshold('rate<1', ['value' => 1.5]))->toBeFalse();
    expect(RunResultService::evaluateThreshold('p(95)<5000', ['p(95)' => 257.11]))->toBeTrue();
    expect(RunResultService::evaluateThreshold('p(95)<500', ['p(95)' => 800.5]))->toBeFalse();
    expect(RunResultService::evaluateThreshold('avg<=1', ['avg' => 1]))->toBeTrue();
    expect(RunResultService::evaluateThreshold('max>=100', ['max' => 100]))->toBeTrue();
});

test('thresholds support OR and AND combined conditions', function () {
    expect(RunResultService::evaluateThreshold(
        'p(95)<500||p(99)<1000',
        ['p(95)' => 800, 'p(99)' => 900],
    ))->toBeTrue();

    expect(RunResultService::evaluateThreshold(
        'count>5&&rate<1000',
        ['count' => 10, 'rate' => 2000],
    ))->toBeFalse();

    expect(RunResultService::evaluateThreshold(
        'count>5&&rate<3000',
        ['count' => 10, 'rate' => 2000],
    ))->toBeTrue();
});

test('finalize maps signal termination to aborted', function () {
    $run = Run::factory()->running()->create();
    writeRunArtifacts($run, 143, null);

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('aborted');
});

test('finalize marks a script error with a message', function () {
    $run = Run::factory()->running()->create();
    writeRunArtifacts($run, 108, null);

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('error');
    expect($run->error_message)->toContain('108');
});

test('finalize marks an error when the exit code file is missing', function () {
    $run = Run::factory()->running()->create();

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('error');
    expect($run->exit_code)->toBeNull();
});

test('finalize surfaces the k6 log tail when the exit code file is missing', function () {
    $run = Run::factory()->running()->create();
    file_put_contents(RunResultService::logFilePath($run->id), "syntax error near unexpected token\n");

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('error');
    expect($run->error_message)->toContain('syntax error near unexpected token');
    expect(file_exists(RunResultService::logFilePath($run->id)))->toBeFalse();
});

test('finalize appends the k6 log tail to a script error message', function () {
    $run = Run::factory()->running()->create();
    writeRunArtifacts($run, 108, null);
    file_put_contents(RunResultService::logFilePath($run->id), "level=error msg=\"bad script\"\n");

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('error');
    expect($run->error_message)->toContain('108');
    expect($run->error_message)->toContain('bad script');
});

test('finalize ignores runs that are not running', function () {
    $run = Run::factory()->queued()->create();
    writeRunArtifacts($run, 0, k6Summary());

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('queued');
});

test('cancel signals the running k6 process', function () {
    $process = Process::quietly()
        ->forever()
        ->options(['create_new_console' => true])
        ->start(['sleep', '60']);

    $k6Pid = $process->id();
    $run = Run::factory()->running()->create(['pid' => $k6Pid]);
    file_put_contents(RunResultService::k6PidFilePath($run->id), (string) $k6Pid);

    try {
        expect(RunResultService::isProcessAlive($k6Pid))->toBeTrue();

        RunResultService::cancel($run);

        $deadline = microtime(true) + 2;
        while (RunResultService::isProcessAlive($k6Pid) && microtime(true) < $deadline) {
            usleep(50000);
        }

        expect(RunResultService::isProcessAlive($k6Pid))->toBeFalse();
    } finally {
        @posix_kill($k6Pid, SIGKILL);
        @unlink(RunResultService::k6PidFilePath($run->id));
    }
});

test('cancel aborts a queued run immediately', function () {
    $run = Run::factory()->queued()->create();

    RunResultService::cancel($run);
    $run->refresh();

    expect($run->status)->toBe('aborted');
    expect($run->completed_at)->not->toBeNull();
});

test('finalize notifies the triggering user about the completed run', function () {
    Notification::fake();

    $user = User::factory()->create();
    $run = Run::factory()->running()->create(['triggered_by_user_id' => $user->id]);
    writeRunArtifacts($run, 0, k6Summary());

    RunResultService::finalize($run);
    $run->refresh();

    expect($run->status)->toBe('passed');
    Notification::assertSentTo($user, RunCompleted::class);
});

test('finalize does not notify when no user triggered the run', function () {
    Notification::fake();

    $run = Run::factory()->running()->create();
    writeRunArtifacts($run, 0, k6Summary());

    RunResultService::finalize($run);

    Notification::assertNothingSent();
});

test('cancel notifies the triggering user when a queued run is aborted', function () {
    Notification::fake();

    $user = User::factory()->create();
    $run = Run::factory()->queued()->create(['triggered_by_user_id' => $user->id]);

    RunResultService::cancel($run);

    Notification::assertSentTo($user, RunCompleted::class);
});

function makeRunnableRun(bool $persistRunLogs): Run
{
    $project = Project::factory()->create(['persist_run_logs' => $persistRunLogs]);
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->running()->create(['script_id' => $script->id]);
}

test('finalize persists the log when the project enables log persistence', function () {
    Storage::fake('local');

    $run = makeRunnableRun(true);
    writeRunArtifacts($run, 0, null);
    file_put_contents(RunResultService::logFilePath($run->id), "hello k6\n");

    RunResultService::finalize($run);

    expect(Storage::disk('local')->exists('run-logs/'.$run->id.'.log'))->toBeTrue();
    expect(Storage::disk('local')->get('run-logs/'.$run->id.'.log'))->toBe("hello k6\n");
    expect(file_exists(RunResultService::logFilePath($run->id)))->toBeFalse();
});

test('finalize discards the log when the project disables log persistence', function () {
    Storage::fake('local');

    $run = makeRunnableRun(false);
    writeRunArtifacts($run, 0, null);
    file_put_contents(RunResultService::logFilePath($run->id), "hello k6\n");

    RunResultService::finalize($run);

    expect(Storage::disk('local')->exists('run-logs/'.$run->id.'.log'))->toBeFalse();
    expect(file_exists(RunResultService::logFilePath($run->id)))->toBeFalse();
});

test('readLogChunk returns new bytes and advances the offset', function () {
    $run = Run::factory()->running()->create();
    file_put_contents(RunResultService::logFilePath($run->id), "line1\nline2\n");

    $chunk = RunResultService::readLogChunk($run->id, 0);

    expect($chunk['content'])->toBe("line1\nline2\n");
    expect($chunk['nextOffset'])->toBe(12);
    expect($chunk['eof'])->toBeTrue();

    $next = RunResultService::readLogChunk($run->id, $chunk['nextOffset']);

    expect($next['content'])->toBe('');
    expect($next['eof'])->toBeTrue();
});

test('readLogChunk falls back to the persisted log after finalize', function () {
    Storage::fake('local');

    $run = Run::factory()->running()->create();
    Storage::disk('local')->put('run-logs/'.$run->id.'.log', "persisted\n");

    $chunk = RunResultService::readLogChunk($run->id, 0);

    expect($chunk['content'])->toBe("persisted\n");
});

test('hasLog detects live and persisted logs', function () {
    Storage::fake('local');

    $run = Run::factory()->running()->create();

    expect(RunResultService::hasLog($run->id))->toBeFalse();

    file_put_contents(RunResultService::logFilePath($run->id), 'live');
    expect(RunResultService::hasLog($run->id))->toBeTrue();
    @unlink(RunResultService::logFilePath($run->id));

    Storage::disk('local')->put('run-logs/'.$run->id.'.log', 'persisted');
    expect(RunResultService::hasLog($run->id))->toBeTrue();
});
