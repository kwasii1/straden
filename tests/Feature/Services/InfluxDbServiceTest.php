<?php

use App\Models\Connector;
use App\Models\Run;
use App\Services\InfluxDbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeInfluxQueryResponses(): void
{
    Http::fake(fn () => Http::response([
        'results' => [[
            'series' => [[
                'columns' => ['time', 'value'],
                'values' => [[1690000000000, 1]],
            ]],
        ]],
    ]));
}

function capturedInfluxQueries(): string
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0]['q'] ?? '')
        ->implode("\n");
}

test('time-series queries are bounded to the run execution window', function () {
    Connector::factory()->influxDb()->create();

    $run = Run::factory()->passed()->create([
        'started_at' => now()->subMinutes(10),
        'completed_at' => now()->subMinutes(9),
    ]);

    fakeInfluxQueryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->metricsForRun($run->id, null, InfluxDbService::runTimeRange($run->started_at, $run->completed_at));

    $queries = capturedInfluxQueries();

    expect($queries)
        ->toContain("time >= '".$run->started_at->utc()->format('Y-m-d\TH:i:s\Z')."'")
        ->toContain("time <= '".$run->completed_at->utc()->format('Y-m-d\TH:i:s\Z')."'");
});

test('time-series queries are unbounded when no start time is known', function () {
    Connector::factory()->influxDb()->create();

    fakeInfluxQueryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->metricsForRun('some-run-id');

    expect(capturedInfluxQueries())->not->toContain('time >=');
});

test('running runs bound the end of the query to now', function () {
    Connector::factory()->influxDb()->create();

    $run = Run::factory()->running()->create(['started_at' => now()->subMinute()]);

    fakeInfluxQueryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $range = InfluxDbService::runTimeRange($run->started_at, $run->completed_at);
    $service->metricsForRun($run->id, null, $range);

    $queries = capturedInfluxQueries();

    expect($queries)
        ->toContain("time >= '".$run->started_at->utc()->format('Y-m-d\TH:i:s\Z')."'")
        ->toContain("time <= '".$range['end']->utc()->format('Y-m-d\TH:i:s\Z')."'");
});
