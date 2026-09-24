<?php

namespace App\Mcp\Tools;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class StradenTool extends Tool
{
    /**
     * The token ability required to call this tool.
     */
    protected string $ability = 'mcp:read';

    abstract protected function execute(Request $request): Response;

    public function handle(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && ! $token->can($this->ability)) {
            return Response::error("This token is missing the '{$this->ability}' ability required by this tool.");
        }

        try {
            return $this->execute($request);
        } catch (ModelNotFoundException $e) {
            return Response::error(class_basename($e->getModel()).' not found.');
        } catch (ValidationException $e) {
            return Response::error($e->getMessage());
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * Run an internal Straden agent tool and wrap its output as an MCP response.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function delegate(AiTool $tool, array $arguments): Response
    {
        return Response::text((string) $tool->handle(new AiRequest($arguments)));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function json(array $data): Response
    {
        return Response::text(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    protected function project(Request $request): Project
    {
        return Project::findOrFail((string) $request->get('project_id'));
    }

    protected function test(Request $request): Test
    {
        return Test::findOrFail((string) $request->get('test_id'));
    }

    protected function script(Request $request): Script
    {
        return Script::findOrFail((string) $request->get('script_id'));
    }

    protected function run(Request $request): Run
    {
        return Run::findOrFail((string) $request->get('run_id'));
    }

    protected function prometheusConnector(Project $project): ?Connector
    {
        return $project->connectors()
            ->where('type', ConnectorType::Prometheus->value)
            ->first();
    }
}
