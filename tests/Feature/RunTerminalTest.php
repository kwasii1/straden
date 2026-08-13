<?php

use App\Livewire\RunTerminal;
use App\Models\Run;
use App\Services\RunResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('mount backfills the existing log content', function () {
    $run = Run::factory()->running()->create();
    file_put_contents(RunResultService::logFilePath($run->id), "starting k6...\n");

    $component = Livewire::test(RunTerminal::class, ['run' => $run]);

    $component->assertDispatched('log-chunk', content: "starting k6...\n");
});

test('poll appends new log lines', function () {
    $run = Run::factory()->running()->create();
    file_put_contents(RunResultService::logFilePath($run->id), "first\n");

    $component = Livewire::test(RunTerminal::class, ['run' => $run]);

    $component->assertDispatched('log-chunk', content: "first\n");

    file_put_contents(RunResultService::logFilePath($run->id), "first\nsecond\n");

    $component->call('poll');

    $component->assertDispatched('log-chunk', content: "second\n");
});

test('poll marks the terminal finished once the run ends', function () {
    $run = Run::factory()->running()->create();

    $component = Livewire::test(RunTerminal::class, ['run' => $run]);

    expect($component->instance()->finished)->toBeFalse();

    $run->update(['status' => 'passed']);

    $component->call('poll');

    expect($component->instance()->finished)->toBeTrue();
});
