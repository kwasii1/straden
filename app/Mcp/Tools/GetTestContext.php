<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ScanContextTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the full context of a test: target URL, project, connectors, repositories, scripts (with their recent runs) and the latest runs. Use this before creating or updating scripts.')]
class GetTestContext extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new ScanContextTool($this->test($request)), []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_id' => $schema->string()->description('The UUID of the test.')->required(),
        ];
    }
}
