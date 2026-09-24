<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\RunInfluxMetricsTool;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get detailed InfluxDB metrics for a run: aggregated latency/throughput/error metrics, a per-endpoint breakdown and a downsampled trend over time.')]
class GetRunMetrics extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new RunInfluxMetricsTool($this->run($request)), $request->except('run_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->string()->description('The UUID of the run.')->required(),
            ...(new RunInfluxMetricsTool(new Run))->schema($schema),
        ];
    }
}
