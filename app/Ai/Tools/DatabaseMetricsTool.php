<?php

namespace App\Ai\Tools;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Services\DatabaseMetricsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DatabaseMetricsTool implements Tool
{
    public function __construct(private readonly Project $project) {}

    public function description(): Stringable|string
    {
        return 'Fetch health and performance metrics from a configured MySQL or PostgreSQL database connector (connections, cache hit ratio, throughput, slow queries, contention). Use this when Prometheus is not available and the underlying database metrics are needed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $connector = $this->resolveConnector((string) $request->string('connector_id') ?: null);

        if ($connector === null) {
            return json_encode([
                'available' => false,
                'error' => 'No MySQL or PostgreSQL connector is configured for this project. Add one on the Connectors page.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        if ($connector->type === ConnectorType::MongoDB) {
            return json_encode([
                'available' => false,
                'error' => 'MongoDB metrics require the mongodb PHP driver (ext-mongodb), which is not installed on this server.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        try {
            $metrics = (new DatabaseMetricsService($connector))->metrics();

            return json_encode([
                'available' => true,
                'connector' => $connector->name,
                ...$metrics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return json_encode([
                'available' => false,
                'error' => 'Failed to query database metrics: '.$e->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'connector_id' => $schema->string()
                ->description('Optional UUID of the database connector to query. When omitted, the first MySQL/PostgreSQL connector for the project is used.'),
        ];
    }

    private function resolveConnector(?string $connectorId): ?Connector
    {
        $query = $this->project->connectors()->whereIn('type', [
            ConnectorType::MySQL->value,
            ConnectorType::Postgres->value,
            ConnectorType::MongoDB->value,
        ]);

        return $connectorId !== null && $connectorId !== ''
            ? $query->where('id', $connectorId)->first()
            : $query->first();
    }
}
