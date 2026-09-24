<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Straden')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
Straden is a k6 load-testing platform. Projects contain tests (each with a target URL); tests contain k6 scripts; running a script produces a run whose metrics are stored in InfluxDB.

Typical workflow:
1. `list-projects` to find the project and test IDs, then `get-test-context` for the target URL, connectors, existing scripts and recent runs.
2. `create-script` (or `update-script` / `write-script-file` for an existing script), then `validate-script` and fix any issues.
3. `start-run`, then poll `get-run-status` every ~10 seconds until the status is `completed`, `failed` or `aborted`.
4. Analyse with `get-run-context`, `get-run-metrics`, `read-run-log` and `get-script-insights`. Correlate with server-side metrics via the Prometheus, database and Redis tools.
5. Update the script or the application under test based on the findings and repeat.

k6 scripts must be ES modules that import from `k6/http` and `k6`, export a default function and use `check()` assertions; declare load profile and thresholds in `export const options`. Target the test's `target_url`.
MARKDOWN)]
class StradenServer extends Server
{
    protected array $tools = [
        Tools\ListProjects::class,
        Tools\GetTestContext::class,
        Tools\CreateScript::class,
        Tools\UpdateScript::class,
        Tools\ValidateScript::class,
        Tools\ListScriptFiles::class,
        Tools\ReadScriptFile::class,
        Tools\WriteScriptFile::class,
        Tools\DeleteScriptFile::class,
        Tools\RenameScriptFile::class,
        Tools\GetScriptInsights::class,
        Tools\StartRun::class,
        Tools\GetRunStatus::class,
        Tools\CancelRun::class,
        Tools\ListRuns::class,
        Tools\GetRunContext::class,
        Tools\GetRunMetrics::class,
        Tools\ReadRunLog::class,
        Tools\GetRunInsight::class,
        Tools\PrometheusQuery::class,
        Tools\PrometheusQueryRange::class,
        Tools\PrometheusListMetrics::class,
        Tools\PrometheusMetadata::class,
        Tools\DatabaseMetrics::class,
        Tools\RedisMetrics::class,
    ];
}
