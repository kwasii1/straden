<?php

use App\Jobs\CheckRunningTestsJob;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

function spawnSleepProcess(): int
{
    $process = Process::quietly()
        ->forever()
        ->options(['create_new_console' => true])
        ->start(['sleep', '60']);

    return $process->id();
}

test('check running tests job ignores runs whose process is alive', function () {
    $pid = spawnSleepProcess();
    $run = Run::factory()->running()->create(['pid' => $pid]);

    try {
        (new CheckRunningTestsJob)->handle();

        $run->refresh();

        expect($run->status)->toBe('running');
        expect($run->completed_at)->toBeNull();
    } finally {
        @posix_kill($pid, SIGKILL);
    }
});

test('check running tests job finalizes runs whose process is gone', function () {
    $pid = spawnSleepProcess();
    $run = Run::factory()->running()->create(['pid' => $pid]);

    try {
        posix_kill($pid, SIGKILL);
        usleep(100000);

        (new CheckRunningTestsJob)->handle();

        $run->refresh();

        expect($run->status)->toBe('error');
        expect($run->completed_at)->not->toBeNull();
    } finally {
        @posix_kill($pid, SIGKILL);
    }
});

test('check running tests job ignores runs without a pid', function () {
    $run = Run::factory()->running()->create(['pid' => null]);

    (new CheckRunningTestsJob)->handle();

    $run->refresh();

    expect($run->status)->toBe('running');
});
