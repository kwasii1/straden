<?php

use App\Models\Connector;
use App\Services\DatabaseMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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

test('mongodb connection test checks that the server accepts connections', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) Str::afterLast(stream_socket_get_name($server, false), ':');

    $reachable = Connector::factory()->mongodb()->create(['host' => '127.0.0.1', 'port' => $port, 'timeout' => 1]);
    $unreachable = Connector::factory()->mongodb()->create(['host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]);

    expect((new DatabaseMetricsService($reachable))->testConnection())->toBeTrue()
        ->and((new DatabaseMetricsService($unreachable))->testConnection())->toBeFalse();

    fclose($server);
});
