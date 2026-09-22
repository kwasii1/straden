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

test('http timing breakdown queries each timing component', function () {
    Connector::factory()->influxDb()->create();

    $run = Run::factory()->passed()->create();

    fakeInfluxQueryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $timing = $service->httpTimingOverTime($run->id);

    $queries = capturedInfluxQueries();

    expect($queries)->toContain('"http_req_blocked"')
        ->toContain('"http_req_connecting"')
        ->toContain('"http_req_tls_handshaking"')
        ->toContain('"http_req_sending"')
        ->toContain('"http_req_waiting"')
        ->toContain('"http_req_receiving"');

    expect(array_keys($timing))->toBe(['labels', 'blocked', 'connecting', 'tls', 'sending', 'waiting', 'receiving']);
});

test('iteration duration and iterations queries hit the right measurements', function () {
    Connector::factory()->influxDb()->create();

    $run = Run::factory()->passed()->create();

    fakeInfluxQueryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->iterationDurationOverTime($run->id);
    $service->iterationsOverTime($run->id);

    $queries = capturedInfluxQueries();

    expect($queries)->toContain('"iteration_duration"')
        ->toContain('"iterations"');
});

function fakeEndpointSummaryResponses(): void
{
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';

        if (str_contains($q, 'http_req_duration')) {
            $series = ['columns' => ['time', 'p95', 'p99'], 'values' => [[1690000000000, 100, 200]]];
        } else {
            $series = ['columns' => ['time', 'value'], 'values' => [[1690000000000, 10]]];
        }

        return Http::response(['results' => [['series' => [$series]]]]);
    });
}

test('grouped endpoints match via one regex instead of or enumeration', function () {
    Connector::factory()->influxDb()->create();
    fakeEndpointSummaryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->endpointSummary('run-1', [
        'https://api.example.com/todos/1',
        'https://api.example.com/todos/2',
    ]);

    $queries = capturedInfluxQueries();

    expect($queries)->toContain('"name" =~')->not->toContain(' OR ');
});

test('mixed endpoint sets fall back to or enumeration', function () {
    Connector::factory()->influxDb()->create();
    fakeEndpointSummaryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->endpointSummary('run-1', [
        'https://api.example.com/v1/users',
        'https://api.example.com/todos/1',
    ]);

    expect(capturedInfluxQueries())
        ->toContain('"name"=\'https://api.example.com/v1/users\' OR "name"=\'https://api.example.com/todos/1\'');
});

test('single endpoint keeps an exact match', function () {
    Connector::factory()->influxDb()->create();
    fakeEndpointSummaryResponses();

    $service = new InfluxDbService(Connector::influxDb());
    $service->endpointSummary('run-1', 'https://api.example.com/v1/users');

    $queries = capturedInfluxQueries();

    expect($queries)
        ->toContain('("name"=\'https://api.example.com/v1/users\')')
        ->not->toContain('=~');
});
