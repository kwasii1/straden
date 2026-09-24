<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ValidateScriptTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Validate a k6 script with k6 inspect plus syntax and k6 API pattern checks. Run this after creating or modifying a script and before starting a run.')]
class ValidateScript extends StradenTool
{
    protected function execute(Request $request): Response
    {
        return $this->delegate(new ValidateScriptTool, ['script_id' => $this->script($request)->id]);
    }

    public function schema(JsonSchema $schema): array
    {
        return (new ValidateScriptTool)->schema($schema);
    }
}
