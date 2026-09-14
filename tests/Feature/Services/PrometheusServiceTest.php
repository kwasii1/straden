<?php

use App\Models\Connector;
use App\Models\Project;
use App\Services\PrometheusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function prometheusService(): PrometheusService
{
    $connector = Connector::factory()->prometheus()->create([
        'project_id' => Project::factory()->create(),
        'host' => 'localhost',
        'port' => 9090,
    ]);

    return new PrometheusService($connector);
}

test('query sends an instant query to the prometheus api', function () {
    Http::fake(['*/api/v1/query*' => Http::response(['status' => 'success', 'data' => []])]);

    prometheusService()->query('up');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://localhost:9090/api/v1/query') && $request['query'] === 'up');
});

test('query range sends start, end, and step', function () {
    Http::fake(['*/api/v1/query_range*' => Http::response(['status' => 'success', 'data' => []])]);

    prometheusService()->queryRange('up', 1000, 2000, '15s');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/query_range')
        && $request['query'] === 'up'
        && $request['start'] == 1000
        && $request['end'] == 2000
        && $request['step'] === '15s');
});

test('list metrics hits the name label endpoint and respects limit', function () {
    Http::fake(['*/api/v1/label/__name__/values*' => Http::response(['data' => ['up', 'go_goroutines', 'http_requests_total']])]);

    $metrics = prometheusService()->listMetrics(2);

    expect($metrics)->toBe(['up', 'go_goroutines']);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/label/__name__/values'));
});

test('metadata filters by metric name', function () {
    Http::fake(['*/api/v1/metadata*' => Http::response(['data' => ['up' => []]])]);

    prometheusService()->metadata('up');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/metadata') && $request['metric'] === 'up');
});

test('test connection succeeds when buildinfo responds', function () {
    Http::fake(['*/api/v1/status/buildinfo*' => Http::response(['status' => 'success', 'data' => []]), '*' => Http::response(status: 500)]);

    expect(prometheusService()->testConnection())->toBeTrue();
});

test('test connection fails on a non-successful response', function () {
    Http::fake(['*' => Http::response(status: 500)]);

    expect(prometheusService()->testConnection())->toBeFalse();
});

test('for project resolves the prometheus connector', function () {
    $project = Project::factory()->create();
    Connector::factory()->prometheus()->create(['project_id' => $project->id]);

    expect(PrometheusService::forProject($project))->not->toBeNull();
});

test('for project returns null when no prometheus connector exists', function () {
    $project = Project::factory()->create();

    expect(PrometheusService::forProject($project))->toBeNull();
});
