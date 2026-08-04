<?php

use App\Ai\Tools\RunInfluxMetricsTool;
use App\Models\Connector;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function makeInfluxRun(): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->passed()->create(['script_id' => $script->id]);
}

function fakeInfluxSeries(): void
{
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';
        $t = 1690000000000;

        $series = match (true) {
            str_contains($q, 'http_req_duration') => [
                'columns' => ['time', 'p95', 'p99'],
                'values' => [[$t, 100, 200], [$t + 5000, 900, 1500]],
            ],
            str_contains($q, '"vus"') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 5], [$t + 5000, 10]],
            ],
            str_contains($q, '"http_reqs"') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 20], [$t + 5000, 30]],
            ],
            str_contains($q, '"http_req_failed"') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 1], [$t + 5000, 5]],
            ],
            str_contains($q, '"checks"') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 2], [$t + 5000, 2]],
            ],
            str_contains($q, 'data_sent') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 10], [$t + 5000, 20]],
            ],
            str_contains($q, 'data_received') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 30], [$t + 5000, 50]],
            ],
            default => null,
        };

        if ($series === null) {
            return Http::response(['results' => [['series' => []]]]);
        }

        return Http::response(['results' => [['series' => [$series]]]]);
    });
}

test('run influx metrics tool returns aggregates for the run', function () {
    fakeInfluxSeries();

    Connector::factory()->influxDb()->create();
    $run = makeInfluxRun();

    $result = json_decode((string) (new RunInfluxMetricsTool($run))->handle(new Request([])), true);

    expect($result['available'])->toBeTrue();
    expect($result['run_id'])->toBe($run->id);

    $aggregates = $result['aggregates'];

    expect($aggregates['vus_max'])->toBe(10);
    expect($aggregates['total_requests'])->toBe(50);
    expect($aggregates['avg_p95_ms'])->toEqual(500.0);
    expect($aggregates['avg_p99_ms'])->toEqual(850.0);
    expect($aggregates['max_p95_ms'])->toEqual(900.0);
    expect($aggregates['max_p99_ms'])->toEqual(1500.0);
    expect($aggregates['avg_error_rate_percent'])->toEqual(3.0);
    expect($aggregates['max_error_rate_percent'])->toEqual(5.0);
    expect($aggregates['checks_passed'])->toBe(4);
    expect($aggregates['checks_failed'])->toBe(0);
    expect($aggregates['data_sent_bytes'])->toBe(30);
    expect($aggregates['data_received_bytes'])->toBe(80);
});

test('run influx metrics tool identifies peak latency and error windows', function () {
    fakeInfluxSeries();

    Connector::factory()->influxDb()->create();
    $run = makeInfluxRun();

    $result = json_decode((string) (new RunInfluxMetricsTool($run))->handle(new Request([])), true);

    expect($result['peaks']['slowest_latency']['value'])->toEqual(900.0);
    expect($result['peaks']['highest_error_rate']['value'])->toEqual(5.0);
    expect($result['peaks']['peak_vus']['value'])->toEqual(10.0);
});

test('run influx metrics tool downsamples long trends', function () {
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';
        $t = 1690000000000;

        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $values[] = [$t + ($i * 5000), 10];
        }

        $series = ['columns' => ['time', 'value'], 'values' => $values];

        $columns = match (true) {
            str_contains($q, 'http_req_duration') => ['time', 'p95', 'p99'],
            default => ['time', 'value'],
        };

        if (str_contains($q, 'http_req_duration')) {
            $durationValues = [];

            foreach ($values as [$time, $v]) {
                $durationValues[] = [$time, 100, 200];
            }

            $series = ['columns' => $columns, 'values' => $durationValues];
        }

        return Http::response(['results' => [['series' => [$series]]]]);
    });

    Connector::factory()->influxDb()->create();
    $run = makeInfluxRun();

    $result = json_decode((string) (new RunInfluxMetricsTool($run))->handle(new Request([])), true);

    expect(count($result['trend']['vus']))->toBeLessThanOrEqual(30);
    expect(count($result['trend']['p95_ms']))->toBeLessThanOrEqual(30);
});

test('run influx metrics tool degrades gracefully without a connector', function () {
    $run = makeInfluxRun();

    $result = json_decode((string) (new RunInfluxMetricsTool($run))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('InfluxDB');
});
