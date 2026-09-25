<?php

namespace App\Mcp\Tools;

use App\Models\Repository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Link repositories to a test. Straden\'s AI agents only read code from a test\'s linked repositories; pass an empty list to fall back to every repository in the project. Repository IDs are listed by get-test-context.')]
class SetTestRepositories extends StradenTool
{
    protected string $ability = 'mcp:write';

    protected function execute(Request $request): Response
    {
        $validated = $request->validate([
            'test_id' => ['required', 'string'],
            'repository_ids' => ['present', 'array'],
            'repository_ids.*' => ['string'],
        ]);

        $test = $this->test($request);
        $test->syncRepositories($validated['repository_ids']);

        $linked = $test->repositories()->get();

        return $this->json([
            'test_id' => $test->id,
            'scope' => $linked->isEmpty() ? 'project' : 'linked',
            'repositories' => $linked->map(fn (Repository $repo) => ['id' => $repo->id, 'name' => $repo->name])->values()->all(),
            'ignored_ids' => array_values(array_diff($validated['repository_ids'], $linked->pluck('id')->map(strval(...))->all())),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_id' => $schema->string()->description('The UUID of the test.')->required(),
            'repository_ids' => $schema->array()->items($schema->string())->description('UUIDs of repositories in the test\'s project. Replaces the current links.')->required(),
        ];
    }
}
