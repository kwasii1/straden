<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class PrometheusQueryRangeTool extends PrometheusTool
{
    public function description(): Stringable|string
    {
        return 'Run a PromQL range query against the configured Prometheus instance, returning a series of samples between a start and end unix timestamp at a given step size (e.g. "15s", "1m"). Use this to see how an observability metric trended over time.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = $this->service();

        if ($service === null) {
            return $this->unavailable();
        }

        try {
            $response = $service->queryRange(
                $request->string('query'),
                $request->integer('start'),
                $request->integer('end'),
                $request->string('step'),
            );

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
            'query' => $schema->string()
                ->description('The PromQL expression to evaluate (e.g. "rate(http_requests_total[5m])").')
                ->required(),
            'start' => $schema->integer()
                ->description('Start of the query window as a unix timestamp.')
                ->required(),
            'end' => $schema->integer()
                ->description('End of the query window as a unix timestamp.')
                ->required(),
            'step' => $schema->string()
                ->description('Resolution step between samples, e.g. "15s", "1m", "1h".')
                ->required(),
        ];
    }
}
