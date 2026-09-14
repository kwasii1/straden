<?php

namespace App\Ai\Tools;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Services\RedisMetricsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RedisMetricsTool implements Tool
{
    public function __construct(private readonly Project $project) {}

    public function description(): Stringable|string
    {
        return 'Fetch health and performance metrics from a configured Redis connector (version, uptime, memory usage, connected clients, command throughput, key hit ratio, and recent slow log entries). Use this when Prometheus is not available and Redis metrics are needed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $connector = $this->resolveConnector($request->string('connector_id') ?: null);

        if ($connector === null) {
            return json_encode([
                'available' => false,
                'error' => 'No Redis connector is configured for this project. Add one on the Connectors page.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        try {
            $metrics = (new RedisMetricsService($connector))->metrics();

            return json_encode([
                'connector' => $connector->name,
                ...$metrics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return json_encode([
                'available' => false,
                'error' => 'Failed to query Redis metrics: '.$e->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'connector_id' => $schema->string()
                ->description('Optional UUID of the Redis connector to query. When omitted, the first Redis connector for the project is used.'),
        ];
    }

    private function resolveConnector(?string $connectorId): ?Connector
    {
        $query = $this->project->connectors()->where('type', ConnectorType::Redis->value);

        return $connectorId !== null && $connectorId !== ''
            ? $query->where('id', $connectorId)->first()
            : $query->first();
    }
}
