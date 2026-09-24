<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ScriptInsightsTool;
use App\Models\Script;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get performance insights for a script across its recent runs: trends, failed thresholds and aggregated InfluxDB metrics.')]
class GetScriptInsights extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new ScriptInsightsTool($this->script($request)), $request->except('script_id'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'script_id' => $schema->string()->description('The UUID of the script.')->required(),
            ...(new ScriptInsightsTool(new Script))->schema($schema),
        ];
    }
}
