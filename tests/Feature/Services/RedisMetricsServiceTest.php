<?php

use App\Models\Connector;
use App\Services\RedisMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('test connection fails gracefully when redis is unreachable', function () {
    $connector = Connector::factory()->redis()->create([
        'host' => '127.0.0.1',
        'port' => 1,
        'timeout' => 1,
    ]);

    expect((new RedisMetricsService($connector))->testConnection())->toBeFalse();
});
