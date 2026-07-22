<?php

namespace Database\Seeders;

use App\Enums\ConnectorType;
use App\Models\Connector;
use Illuminate\Database\Seeder;

class InfluxDbConnectorSeeder extends Seeder
{
    public function run(): void
    {
        Connector::updateOrCreate(
            [
                'type' => ConnectorType::InfluxDb,
                'is_system' => true,
            ],
            [
                'project_id' => null,
                'name' => 'InfluxDB (built-in)',
                'host' => env('INFLUXDB_HOST', '127.0.0.1'),
                'port' => env('INFLUXDB_PORT', 8086),
                'database' => env('INFLUXDB_DB', 'k6'),
                'ssl_enabled' => false,
                'verify_ssl' => false,
                'timeout' => 5,
                'username' => null,
                'password' => null,
            ]
        );
    }
}
