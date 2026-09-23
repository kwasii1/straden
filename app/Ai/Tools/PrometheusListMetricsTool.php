<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class PrometheusListMetricsTool extends PrometheusTool
{
    public function description(): Stringable|string
    {
        return 'List the names of all metrics currently exposed by the configured Prometheus instance (the values of the __name__ label). Use this to enumerate what is being scraped before running a query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = $this->service();

        if ($service === null) {
            return $this->unavailable();
        }

        try {
            $metrics = $service->listMetrics($request->integer('limit') ?: null);

            return json_encode([
                'available' => true,
                'count' => count($metrics),
                'metrics' => $metrics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Optional maximum number of metric names to return.'),
        ];
    }
}
