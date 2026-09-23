<?php

use App\Ai\Tools\PrometheusListMetricsTool;
use App\Ai\Tools\PrometheusMetadataTool;
use App\Ai\Tools\PrometheusQueryRangeTool;
use App\Ai\Tools\PrometheusQueryTool;
use App\Models\Connector;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function prometheusToolConnector(): Connector
{
    return Connector::factory()->prometheus()->create([
        'project_id' => Project::factory()->create(),
        'host' => 'localhost',
        'port' => 9090,
    ]);
}

test('metadata tool returns available metrics metadata', function () {
    Http::fake(['*/api/v1/metadata*' => Http::response(['data' => ['up' => [['type' => 'gauge', 'help' => 'Whether the target is up', 'unit' => '']]]])]);

    $result = json_decode((string) (new PrometheusMetadataTool(prometheusToolConnector()))->handle(new Request([])), true);

    expect($result['available'])->toBeTrue();
    expect($result['data'])->toHaveKey('up');
});

test('query tool runs an instant query', function () {
    Http::fake(['*/api/v1/query*' => Http::response(['data' => ['resultType' => 'vector', 'result' => []]])]);

    $result = json_decode((string) (new PrometheusQueryTool(prometheusToolConnector()))->handle(new Request(['query' => 'up'])), true);

    expect($result['available'])->toBeTrue();

    Http::assertSent(fn ($request) => $request['query'] === 'up');
});

test('query range tool forwards start, end, and step', function () {
    Http::fake(['*/api/v1/query_range*' => Http::response(['data' => ['resultType' => 'matrix', 'result' => []]])]);

    $result = json_decode((string) (new PrometheusQueryRangeTool(prometheusToolConnector()))->handle(
        new Request(['query' => 'up', 'start' => 1, 'end' => 100, 'step' => '15s'])
    ), true);

    expect($result['available'])->toBeTrue();

    Http::assertSent(fn ($request) => $request['start'] == 1 && $request['end'] == 100 && $request['step'] === '15s');
});

test('list metrics tool returns metric names', function () {
    Http::fake(['*/api/v1/label/__name__/values*' => Http::response(['data' => ['up', 'go_goroutines']])]);

    $result = json_decode((string) (new PrometheusListMetricsTool(prometheusToolConnector()))->handle(new Request([])), true);

    expect($result['count'])->toBe(2);
    expect($result['metrics'])->toBe(['up', 'go_goroutines']);
});

test('prometheus tools degrade gracefully without a connector', function () {
    $result = json_decode((string) (new PrometheusMetadataTool(null))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('Prometheus');
});
