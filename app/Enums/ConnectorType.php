<?php

namespace App\Enums;

enum ConnectorType: string
{
    case InfluxDb = 'influxdb';
    case Database = 'database';
    case Repository = 'repository';
    case Grafana = 'grafana';

    public function label(): string
    {
        return match ($this) {
            self::InfluxDb => 'InfluxDB',
            self::Database => 'Database',
            self::Repository => 'Repository',
            self::Grafana => 'Grafana',
        };
    }

    public function isAlwaysSystem(): bool
    {
        return $this === self::InfluxDb;
    }
}
