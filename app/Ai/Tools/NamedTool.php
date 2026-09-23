<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class NamedTool implements Tool
{
    public function __construct(
        private Tool $inner,
        private string $name,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->inner->description();
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->inner->handle($request);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->inner->schema($schema);
    }
}
