<?php

use App\Ai\Agents\TestAgent;
use App\Ai\Tools\CreateScriptTool;
use App\Ai\Tools\ScanContextTool;
use App\Ai\Tools\UpdateScriptTool;
use App\Ai\Tools\ValidateScriptTool;
use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('test agent has correct configuration attributes', function () {
    $test = Test::factory()->create();

    $agent = new TestAgent($test);

    expect($agent)->toBeInstanceOf(TestAgent::class);
});

test('test agent instructions include test name and target url', function () {
    $test = Test::factory()->create([
        'name' => 'API Load Test',
        'target_url' => 'https://api.example.com',
    ]);

    $agent = new TestAgent($test);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('API Load Test')
        ->toContain('https://api.example.com')
        ->toContain('ScanContextTool')
        ->toContain('CreateScriptTool')
        ->toContain('ValidateScriptTool')
        ->toContain('Read the room first');
});

test('test agent provides all tools including file storage', function () {
    $test = Test::factory()->create();

    $agent = new TestAgent($test);

    $tools = collect($agent->tools());

    expect($tools->count())->toBeGreaterThan(4);
    expect($tools->first())->toBeInstanceOf(ScanContextTool::class);
    expect($tools->slice(1, 1)->first())->toBeInstanceOf(CreateScriptTool::class);
    expect($tools->slice(2, 1)->first())->toBeInstanceOf(UpdateScriptTool::class);
    expect($tools->slice(3, 1)->first())->toBeInstanceOf(ValidateScriptTool::class);
});

test('scan context tool returns all context sections', function () {
    $test = Test::factory()->create();

    $tool = new ScanContextTool($test);
    $request = new Request([]);

    $result = (string) $tool->handle($request);
    $context = json_decode($result, true);

    expect($context)->toHaveKey('test');
    expect($context)->toHaveKey('project');
    expect($context)->toHaveKey('connectors');
    expect($context)->toHaveKey('repositories');
    expect($context)->toHaveKey('scripts');
    expect($context)->toHaveKey('runs');
    expect($context['test']['name'])->toBe($test->name);
    expect($context['test']['target_url'])->toBe($test->target_url);
});

test('scan context tool does not expose sensitive connector fields', function () {
    $test = Test::factory()->create();

    Connector::create([
        'project_id' => $test->project_id,
        'name' => 'Test DB',
        'type' => ConnectorType::Database,
        'host' => 'db.example.com',
        'port' => 5432,
        'database' => 'testdb',
        'username' => 'secret_user',
        'password' => 'secret_pass',
        'token' => 'secret_token',
    ]);

    $tool = new ScanContextTool($test);
    $request = new Request([]);

    $result = (string) $tool->handle($request);
    $context = json_decode($result, true);

    $connector = $context['connectors'][0];
    expect($connector)->toHaveKey('name');
    expect($connector)->toHaveKey('host');
    expect($connector)->toHaveKey('type');
    expect($connector)->not->toHaveKey('username');
    expect($connector)->not->toHaveKey('password');
    expect($connector)->not->toHaveKey('token');
});

test('create script tool validates input schema', function () {
    $test = Test::factory()->create();

    $tool = new CreateScriptTool($test);

    $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory)))->toSchema();

    expect($schema['properties'])->toHaveKeys(['name', 'entry_point_content', 'additional_files']);

    // Gemini rejects array schemas without an explicit items definition.
    $items = $schema['properties']['additional_files']['items'] ?? null;

    expect($items)->not->toBeNull()
        ->and($items['type'])->toBe('object')
        ->and($items['properties'])->toHaveKeys(['path', 'content']);
});

test('update script tool files schema declares items for gemini', function () {
    $test = Test::factory()->create();

    $tool = new UpdateScriptTool($test);

    $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory)))->toSchema();

    $items = $schema['properties']['files']['items'] ?? null;

    expect($items)->not->toBeNull()
        ->and($items['type'])->toBe('object')
        ->and($items['properties'])->toHaveKeys(['path', 'content']);
});

test('create script tool requires approval', function () {
    $test = Test::factory()->create();

    $tool = new CreateScriptTool($test);

    expect($tool)->toBeInstanceOf(Approvable::class);
});

test('validate script tool returns errors for missing k6 imports', function () {
    Storage::fake('local');

    $test = Test::factory()->create();
    $script = Script::factory()->create([
        'test_id' => $test->id,
        'disk' => 'local',
        'script_path' => 'scripts/'.$test->id.'/some-id/script.js',
    ]);

    Storage::disk('local')->makeDirectory(dirname($script->script_path));
    Storage::disk('local')->put(
        $script->script_path,
        "function test() { console.log('hello'); }\n"
    );

    $tool = new ValidateScriptTool;
    $request = new Request(['script_id' => $script->id]);

    $result = json_decode((string) $tool->handle($request), true);

    expect($result['valid'])->toBeFalse();
    expect($result['pattern_issues'])->not->toBeEmpty();
});

test('validate script tool passes for correct k6 script', function () {
    Storage::fake('local');

    $test = Test::factory()->create();
    $script = Script::factory()->create(['test_id' => $test->id, 'disk' => 'local']);

    $validK6Script = <<<'JS'
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: 10,
    duration: '30s',
    thresholds: {
        http_req_duration: ['p(95)<500'],
    },
};

export default function () {
    const res = http.get('https://httpbin.org/get');
    check(res, {
        'status is 200': (r) => r.status === 200,
    });
    sleep(1);
}
JS;

    $script->update([
        'script_path' => 'scripts/'.$test->id.'/'.$script->id.'/script.js',
    ]);

    Storage::disk('local')->makeDirectory('scripts/'.$test->id.'/'.$script->id);
    Storage::disk('local')->put($script->script_path, $validK6Script);

    $tool = new ValidateScriptTool;
    $request = new Request(['script_id' => $script->id]);

    $result = json_decode((string) $tool->handle($request), true);

    expect($result['valid'])->toBeTrue();
    expect($result['pattern_issues'])->toBeEmpty();
    expect($result['syntax_issues'])->toBeEmpty();
});

test('create script tool creates script model and writes files', function () {
    Storage::fake('local');

    $test = Test::factory()->create();

    $tool = new CreateScriptTool($test);

    $entryPointContent = <<<'JS'
import http from 'k6/http';
import { check, sleep } from 'k6';

export default function () {
    const res = http.get('https://httpbin.org/get');
    check(res, { 'status is 200': (r) => r.status === 200 });
    sleep(1);
}
JS;

    $request = new Request([
        'name' => 'Smoke Test',
        'description' => 'A basic smoke test',
        'entry_point_content' => $entryPointContent,
        'additional_files' => [
            ['path' => 'helpers.js', 'content' => 'export function generateToken() { return "test"; }'],
        ],
    ]);

    $result = (string) $tool->handle($request);

    expect($result)->toContain('Smoke Test');

    $script = Script::first();
    expect($script->name)->toBe('Smoke Test');
    expect($script->description)->toBe('A basic smoke test');

    $basePath = 'scripts/'.$test->id.'/'.$script->id;
    Storage::disk('local')->assertExists($basePath.'/script.js');
    Storage::disk('local')->assertExists($basePath.'/helpers.js');

    $writtenContent = Storage::disk('local')->get($basePath.'/script.js');
    expect($writtenContent)->toContain("import http from 'k6/http'");
    expect($writtenContent)->toContain('export default function');
});

test('agent can be faked with pending approvals', function () {
    $test = Test::factory()->create();

    TestAgent::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval(
                id: 'call_001',
                tool: 'CreateScriptTool',
                arguments: ['name' => 'Load Test', 'entry_point_content' => 'import http from "k6/http";'],
                reason: 'Creating scripts writes files to disk.',
            ),
        ]),
    ]);

    $response = (new TestAgent($test))->forParticipant($test)->prompt('Create tests');

    expect($response->hasPendingApprovals())->toBeTrue();
    expect($response->pendingApprovals)->toHaveCount(1);
    expect($response->pendingApprovals[0]->tool)->toBe('CreateScriptTool');
});

test('agent can be faked for plan-first workflow', function () {
    $test = Test::factory()->create();

    TestAgent::fake([
        'Here is my test plan for '.$test->name.'. I will test the following endpoints...',
    ]);

    $agent = (new TestAgent($test))->forParticipant($test);

    $response = $agent->prompt('Create tests for this API');

    expect($response->text)->toContain('Here is my test plan');
    expect($response->text)->toContain((string) $test->name);
});

test('agent fake assertions work', function () {
    $test = Test::factory()->create();

    TestAgent::fake(['Response']);

    (new TestAgent($test))->forParticipant($test)->prompt('Create tests');

    TestAgent::assertPrompted('Create tests');
    TestAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->contains('Create');
    });
});
