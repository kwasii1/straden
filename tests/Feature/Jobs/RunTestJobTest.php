<?php

use App\Jobs\RunTestJob;
use App\Models\Run;
use App\Services\RunProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('run process manager includes p99 in the k6 summary trend stats', function () {
    $run = Run::factory()->queued()->create();

    $command = (new RunProcessManager)->buildK6Command($run);

    expect($command)
        ->toContain('--summary-export=')
        ->toContain("--summary-trend-stats='avg,min,med,max,p(90),p(95),p(99)'")
        ->toContain('--summary-mode=full');
});

test('run test job starts k6 in the background and records the pid', function () {
    $this->mock(RunProcessManager::class, function ($mock) {
        $mock->shouldReceive('start')->once()->andReturn(['pid' => 4242, 'running' => true]);
    });

    $run = Run::factory()->queued()->create();

    (new RunTestJob($run))->handle();

    $run->refresh();

    expect($run->status)->toBe('running');
    expect($run->pid)->toBe(4242);
    expect($run->started_at)->not->toBeNull();
});

test('run test job finalizes immediately when the process already exited', function () {
    $this->mock(RunProcessManager::class, function ($mock) {
        $mock->shouldReceive('start')->once()->andReturn(['pid' => 4242, 'running' => false]);
    });

    $run = Run::factory()->queued()->create();

    (new RunTestJob($run))->handle();

    $run->refresh();

    expect($run->pid)->toBe(4242);
    expect($run->status)->not->toBe('running');
});

test('run test job marks the run as error when starting fails', function () {
    $this->mock(RunProcessManager::class, function ($mock) {
        $mock->shouldReceive('start')->once()->andThrow(new RuntimeException('k6 not found'));
    });

    $run = Run::factory()->queued()->create();

    expect(fn () => (new RunTestJob($run))->handle())->toThrow(RuntimeException::class);

    $run->refresh();

    expect($run->status)->toBe('error');
    expect($run->error_message)->toContain('k6 not found');
});

test('run test job skips starting when the run was cancelled while queued', function () {
    $this->mock(RunProcessManager::class, function ($mock) {
        $mock->shouldReceive('start')->never();
    });

    $run = Run::factory()->create(['status' => 'aborted', 'pid' => null]);

    (new RunTestJob($run))->handle();

    $run->refresh();

    expect($run->status)->toBe('aborted');
    expect($run->pid)->toBeNull();
});
