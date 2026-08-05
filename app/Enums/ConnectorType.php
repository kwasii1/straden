<?php

namespace App\Enums;

enum ConnectorType: string
{
    case InfluxDb = 'influxdb';
    case Database = 'database';
    case Repository = 'repository';
    case Grafana = 'grafana';
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case AzureDevOps = 'azure_devops';

    public function label(): string
    {
        return match ($this) {
            self::InfluxDb => 'InfluxDB',
            self::Database => 'Database',
            self::Repository => 'Repository',
            self::Grafana => 'Grafana',
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::AzureDevOps => 'Azure DevOps',
        };
    }

    public function isAlwaysSystem(): bool
    {
        return $this === self::InfluxDb;
    }

    public function isGitProvider(): bool
    {
        return in_array($this, [
            self::GitHub,
            self::GitLab,
            self::Bitbucket,
            self::AzureDevOps,
        ], true);
    }
}
