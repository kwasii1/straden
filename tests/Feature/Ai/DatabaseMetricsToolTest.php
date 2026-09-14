<?php

use App\Ai\Tools\DatabaseMetricsTool;
use App\Models\Connector;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('database metrics tool reports when no connector is configured', function () {
    $project = Project::factory()->create();

    $result = json_decode((string) (new DatabaseMetricsTool($project))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('MySQL');
});

test('database metrics tool reports the missing mongodb driver', function () {
    $project = Project::factory()->create();
    Connector::factory()->mongodb()->create(['project_id' => $project->id]);

    $result = json_decode((string) (new DatabaseMetricsTool($project))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('ext-mongodb');
});
