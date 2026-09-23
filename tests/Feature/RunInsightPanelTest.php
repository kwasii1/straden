<?php

use App\Jobs\GenerateRunInsightJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunInsight;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Services\AiCredentialManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makePanelRun(string $status = 'passed'): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->{$status}()->create(['script_id' => $script->id]);
}

test('panel shows the generate button for a completed run without an insight', function () {
    $user = User::factory()->create();
    $run = makePanelRun();

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('Generate AI Insights');
});

test('panel generate creates a queued insight and dispatches the job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $run = makePanelRun();

    app(AiCredentialManager::class)->put('openai', ['OPENAI_API_KEY' => 'sk-test-1234']);
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->call('generate');

    $insight = $run->insight;

    expect($insight)->not->toBeNull();
    expect($insight->status)->toBe('queued');

    Queue::assertPushed(GenerateRunInsightJob::class, function ($job) use ($run, $insight) {
        return $job->runId === $run->id && $job->runInsightId === $insight->id;
    });
});

test('panel refuses to generate while the run is active', function () {
    Queue::fake();

    $user = User::factory()->create();
    $run = makePanelRun('running');

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('Run in progress')
        ->call('generate');

    expect($run->insight)->toBeNull();
    Queue::assertNotPushed(GenerateRunInsightJob::class);
});

test('panel displays a completed insight report', function () {
    $user = User::factory()->create();
    $run = makePanelRun();

    RunInsight::factory()->completed()->create([
        'run_id' => $run->id,
        'report' => [
            'summary' => 'The checkout endpoint degraded under load.',
            'overall_health' => 'poor',
            'what_is_slow' => 'P95 latency spiked to 900ms during the ramp-up.',
            'key_findings' => [
                ['title' => 'Latency threshold exceeded', 'severity' => 'high', 'detail' => 'P95 crossed 200ms.'],
            ],
            'recommendations' => [
                ['title' => 'Add caching', 'impact' => 'Reduces latency', 'detail' => 'Cache product data at the edge.'],
            ],
            'script_observations' => 'Thresholds were appropriate for the SLA.',
        ],
    ]);

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('The checkout endpoint degraded under load.')
        ->assertSee("What's Slow", false)
        ->assertSee('Latency threshold exceeded')
        ->assertSee('Add caching')
        ->assertSee('Regenerate');
});

test('panel shows an error state for a failed insight', function () {
    $user = User::factory()->create();
    $run = makePanelRun();

    RunInsight::factory()->failed()->create([
        'run_id' => $run->id,
        'error' => 'AI provider unavailable.',
    ]);

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('Generation failed')
        ->assertSee('AI provider unavailable.')
        ->assertSee('Retry');
});

test('panel exports a completed insight report as markdown', function () {
    $user = User::factory()->create();
    $run = makePanelRun();

    RunInsight::factory()->completed()->create([
        'run_id' => $run->id,
        'report' => [
            'summary' => 'The checkout endpoint degraded under load.',
            'overall_health' => 'poor',
            'what_is_slow' => 'P95 latency spiked to 900ms during the ramp-up.',
            'key_findings' => [
                ['title' => 'Latency threshold exceeded', 'severity' => 'high', 'detail' => 'P95 crossed 200ms.'],
            ],
            'recommendations' => [
                ['title' => 'Add caching', 'impact' => 'Reduces latency', 'detail' => 'Cache product data at the edge.'],
            ],
            'script_observations' => 'Thresholds were appropriate for the SLA.',
        ],
    ]);

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->call('export')
        ->assertFileDownloaded('insight-'.$run->slug.'.md');
});

test('panel does not export when no completed insight exists', function () {
    $user = User::factory()->create();
    $run = makePanelRun();

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->call('export')
        ->assertNoFileDownloaded();
});
