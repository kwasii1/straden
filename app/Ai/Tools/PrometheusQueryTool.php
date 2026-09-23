<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class PrometheusQueryTool extends PrometheusTool
{
    public function description(): Stringable|string
    {
        return 'Run an instant PromQL query against the configured Prometheus instance and return the current value of the metric (or its value at the provided unix timestamp). Use this to check the latest state of an observability metric.';
    }

    public function handle(Request $request): Stringable|string
    {
        $service = $this->service();

        if ($service === null) {
            return $this->unavailable();
        }

        try {
            $time = $request->integer('time') ?: null;
            $response = $service->query($request->string('query'), $time);

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
                ->description('The PromQL expression to evaluate (e.g. "up", "rate(http_requests_total[5m])").')
                ->required(),
            'time' => $schema->integer()
                ->description('Optional unix timestamp to evaluate the query at. Defaults to now.'),
        ];
    }
}
