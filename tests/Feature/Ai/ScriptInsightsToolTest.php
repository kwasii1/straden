<?php

use App\Ai\Tools\ScriptInsightsTool;
use App\Models\Connector;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('script insights tool returns script context and recent runs', function () {
    $test = Test::factory()->create([
        'name' => 'Search API',
        'target_url' => 'https://search.example.com',
    ]);
    $script = Script::factory()->create([
        'test_id' => $test->id,
        'name' => 'search-load',
    ]);
    Run::factory()->passed()->create([
        'script_id' => $script->id,
        'req_duration_p95_ms' => 120.5,
    ]);

    $tool = new ScriptInsightsTool($script);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    expect($result['script']['name'])->toBe('search-load');
    expect($result['script']['test_name'])->toBe('Search API');
    expect($result['script']['target_url'])->toBe('https://search.example.com');
    expect($result['recent_runs'])->toHaveCount(1);
    expect($result['recent_runs'][0]['req_duration_p95_ms'])->toEqual(120.5);
});

test('script insights tool reports failed thresholds across runs', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    Run::factory()->failed()->create([
        'script_id' => $script->id,
        'thresholds_passed' => false,
        'thresholds_summary' => [
            ['name' => 'http_req_duration[p(95)<500]', 'ok' => false],
            ['name' => 'http_req_failed[rate<0.01]', 'ok' => true],
        ],
    ]);

    Run::factory()->passed()->create([
        'script_id' => $script->id,
        'thresholds_passed' => true,
        'thresholds_summary' => [
            ['name' => 'http_req_duration[p(95)<500]', 'ok' => false],
        ],
    ]);

    $tool = new ScriptInsightsTool($script);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    $failed = $result['threshold_analysis']['failed_thresholds'];

    expect($failed)->toHaveCount(1);
    expect($failed[0]['name'])->toBe('http_req_duration[p(95)<500]');
    expect($failed[0]['run_count'])->toBe(2);
});

test('script insights tool degrades gracefully without an influx connector', function () {
    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);

    $tool = new ScriptInsightsTool($script);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    expect($result['influx']['available'])->toBeFalse();
    expect($result['influx']['error'])->toContain('InfluxDB');
});

test('script insights tool returns influx aggregates for recent runs', function () {
    Http::fake(function ($request) {
        $q = $request['q'] ?? '';
        $t = 1690000000000;

        $series = match (true) {
            str_contains($q, 'http_req_duration') => [
                'columns' => ['time', 'p95', 'p99'],
                'values' => [[$t, 100, 200], [$t + 5000, 110, 220]],
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
                'values' => [[$t, 1], [$t + 5000, 3]],
            ],
            str_contains($q, '"checks"') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 2], [$t + 5000, 2]],
            ],
            str_contains($q, 'data_sent') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 10], [$t + 5000, 10]],
            ],
            str_contains($q, 'data_received') => [
                'columns' => ['time', 'value'],
                'values' => [[$t, 20], [$t + 5000, 20]],
            ],
            default => null,
        };

        if ($series === null) {
            return Http::response(['results' => [['series' => []]]]);
        }

        return Http::response(['results' => [['series' => [$series]]]]);
    });

    Connector::factory()->influxDb()->create();

    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id]);
    $run = Run::factory()->passed()->create(['script_id' => $script->id]);

    $tool = new ScriptInsightsTool($script);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    expect($result['influx']['available'])->toBeTrue();
    expect($result['influx']['runs'])->toHaveCount(1);

    $influx = $result['influx']['runs'][0];

    expect($influx['run_id'])->toBe($run->id);
    expect($influx['vus_max'])->toBe(10);
    expect($influx['avg_p95_ms'])->toEqual(105.0);
    expect($influx['avg_p99_ms'])->toEqual(210.0);
    expect($influx['total_requests'])->toBe(50);
    expect($influx['avg_error_rate_percent'])->toEqual(2.0);
    expect($influx['checks_passed'])->toBe(4);
    expect($influx['checks_failed'])->toBe(0);
});
