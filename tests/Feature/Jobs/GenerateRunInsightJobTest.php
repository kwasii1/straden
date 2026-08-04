<?php

use App\Ai\Agents\RunInsightAgent;
use App\Jobs\GenerateRunInsightJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunInsight;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeJobRun(string $status = 'failed'): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->{$status}()->create(['script_id' => $script->id]);
}

test('job persists the structured report and marks the insight completed', function () {
    $run = makeJobRun();

    RunInsightAgent::fake([[
        'summary' => 'Latency degraded under load.',
        'overall_health' => 'poor',
        'what_is_slow' => 'P95 spiked.',
        'key_findings' => [],
        'recommendations' => [],
        'script_observations' => 'Thresholds were appropriate.',
    ]]);

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    $insight->refresh();

    expect($insight->status)->toBe('completed');
    expect($insight->error)->toBeNull();
    expect($insight->report['summary'])->toContain('Latency degraded');
    expect($insight->report['overall_health'])->toBe('poor');
});

test('job creates an insight record when none exists', function () {
    $run = makeJobRun();

    RunInsightAgent::fake();

    (new GenerateRunInsightJob($run->id))->handle();

    $insight = $run->insight;

    expect($insight)->not->toBeNull();
    expect($insight->status)->toBe('completed');
    expect($insight->report)->not->toBeNull();
});

test('job marks the insight failed when the agent throws', function () {
    $run = makeJobRun();

    RunInsightAgent::fake(fn () => throw new RuntimeException('AI provider down'));

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    $insight->refresh();

    expect($insight->status)->toBe('failed');
    expect($insight->error)->toContain('AI provider down');
});

test('job fails the insight when the run is still active', function () {
    $run = makeJobRun('running');

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    $insight->refresh();

    expect($insight->status)->toBe('failed');
    expect($insight->error)->toContain('still active');
});

test('job does nothing when the insight is already completed', function () {
    $run = makeJobRun();

    RunInsightAgent::fake();

    $insight = RunInsight::factory()->completed()->create(['run_id' => $run->id]);
    $report = $insight->report;

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    $insight->refresh();

    expect($insight->status)->toBe('completed');
    expect($insight->report)->toBe($report);
});

test('job is dispatched to the queue by the panel', function () {
    Queue::fake();

    $run = makeJobRun();
    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    GenerateRunInsightJob::dispatch($run->id, $insight->id);

    Queue::assertPushed(GenerateRunInsightJob::class, function ($job) use ($run, $insight) {
        return $job->runId === $run->id && $job->runInsightId === $insight->id;
    });
});
