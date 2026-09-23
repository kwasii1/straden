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
                'host' => config('influxdb.host'),
                'port' => config('influxdb.port'),
                'database' => config('influxdb.database'),
                'ssl_enabled' => false,
                'verify_ssl' => false,
                'timeout' => 5,
                'username' => null,
                'password' => null,
            ]
        );
    }
}
