<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Test;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all Straden projects with their tests. Start here to discover the project and test IDs used by the other tools.')]
class ListProjects extends StradenTool
{
    protected function execute(Request $request): Response
    {
        $projects = Project::query()->with('tests')->orderBy('name')->get();

        return $this->json($projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'tests' => $project->tests->map(fn (Test $test) => [
                'id' => $test->id,
                'name' => $test->name,
                'target_url' => $test->target_url,
            ])->values()->all(),
        ])->values()->all());
    }
}
