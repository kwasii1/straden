<?php

use App\Models\Connector;
use App\Services\DatabaseMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('test connection fails gracefully when the database is unreachable', function () {
    $connector = Connector::factory()->mysql()->create([
        'host' => '127.0.0.1',
        'port' => 1,
        'timeout' => 1,
    ]);

    expect((new DatabaseMetricsService($connector))->testConnection())->toBeFalse();
});

test('mysql connections build a dsn from the connector', function () {
    $connector = Connector::factory()->mysql()->create([
        'host' => 'db.example.com',
        'port' => 3306,
        'database' => 'app_db',
    ]);

    $dsn = (new ReflectionClass(DatabaseMetricsService::class))
        ->getMethod('dsn')
        ->invoke(new DatabaseMetricsService($connector));

    expect($dsn)->toContain('mysql:host=db.example.com;port=3306;dbname=app_db');
});

test('mongodb connectors have no database driver', function () {
    $connector = Connector::factory()->mongodb()->create();

    expect((new DatabaseMetricsService($connector))->testConnection())->toBeFalse();
});
