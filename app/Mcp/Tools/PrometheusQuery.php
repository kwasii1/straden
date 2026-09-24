<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\PrometheusQueryTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Run an instant PromQL query against the project Prometheus connector.')]
class PrometheusQuery extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new PrometheusQueryTool($this->prometheusConnector($this->project($request))), $request->except('project_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('The UUID of the project whose connectors should be queried.')->required(),
            ...(new PrometheusQueryTool(null))->schema($schema),
        ];
    }
}
