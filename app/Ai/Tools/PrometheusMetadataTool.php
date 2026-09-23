<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class PrometheusMetadataTool extends PrometheusTool
{
    public function description(): Stringable|string
    {
        return 'Get the metrics available on the configured Prometheus instance along with their metadata (metric type, help text, and unit). Optionally filter to a single metric name. Use this to discover which observability metrics you can query and how to interpret them.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = $this->service();

        if ($service === null) {
            return $this->unavailable();
        }

        try {
            $response = $service->metadata((string) $request->string('metric') ?: null);

            return json_encode([
                'available' => true,
                'data' => $response['data'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()
                ->description('Optional metric name to filter metadata by. Omit to list all metrics.'),
        ];
    }
}
