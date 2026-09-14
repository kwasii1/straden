<?php

namespace App\Enums;

enum ConnectorType: string
{
    case InfluxDb = 'influxdb';
    case Database = 'database';
    case Repository = 'repository';
    case Grafana = 'grafana';
    case Prometheus = 'prometheus';
    case MySQL = 'mysql';
    case Postgres = 'postgres';
    case MongoDB = 'mongodb';
    case Redis = 'redis';
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
            self::Prometheus => 'Prometheus',
            self::MySQL => 'MySQL',
            self::Postgres => 'PostgreSQL',
            self::MongoDB => 'MongoDB',
            self::Redis => 'Redis',
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::AzureDevOps => 'Azure DevOps',
        };
    }

    /**
     * Connectors that provide observability/metrics data.
     */
    public function isObservability(): bool
    {
        return in_array($this, [
            self::InfluxDb,
            self::Prometheus,
            self::MySQL,
            self::Postgres,
            self::MongoDB,
            self::Redis,
        ], true);
    }

    /**
     * Relational/document database engines backed by the database metrics service.
     */
    public function isDatabase(): bool
    {
        return in_array($this, [
            self::MySQL,
            self::Postgres,
            self::MongoDB,
        ], true);
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Grafana => 3000,
            self::InfluxDb => 8086,
            self::Prometheus => 9090,
            self::MySQL => 3306,
            self::Postgres => 5432,
            self::MongoDB => 27017,
            self::Redis => 6379,
            default => null,
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
