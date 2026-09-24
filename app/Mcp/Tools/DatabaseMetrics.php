<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\DatabaseMetricsTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get health metrics (connections, queries, locks, cache hit ratio) from a MySQL/PostgreSQL connector in the project.')]
class DatabaseMetrics extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new DatabaseMetricsTool($this->project($request)), $request->except('project_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('The UUID of the project whose connectors should be queried.')->required(),
            ...(new DatabaseMetricsTool(new Project))->schema($schema),
        ];
    }
}
