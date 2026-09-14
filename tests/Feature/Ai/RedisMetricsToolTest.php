<?php

use App\Ai\Tools\RedisMetricsTool;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('redis metrics tool reports when no connector is configured', function () {
    $project = Project::factory()->create();

    $result = json_decode((string) (new RedisMetricsTool($project))->handle(new Request([])), true);

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('Redis');
});
