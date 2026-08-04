<?php

use App\Ai\Agents\ScriptAgent;
use App\Ai\Tools\DeleteScriptFileTool;
use App\Ai\Tools\ListScriptFilesTool;
use App\Ai\Tools\ReadScriptFileTool;
use App\Ai\Tools\RenameScriptFileTool;
use App\Ai\Tools\ScriptInsightsTool;
use App\Ai\Tools\ValidateScriptTool;
use App\Ai\Tools\WriteScriptFileTool;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Prompts\AgentPrompt;

uses(RefreshDatabase::class);

test('script agent can be instantiated with a script', function () {
    $script = Script::factory()->create();

    $agent = new ScriptAgent($script);

    expect($agent)->toBeInstanceOf(ScriptAgent::class);
    expect($agent->script->is($script))->toBeTrue();
});

test('script agent instructions include script, test, and tool names', function () {
    $test = Test::factory()->create([
        'name' => 'Payment API',
        'target_url' => 'https://pay.example.com',
    ]);
    $script = Script::factory()->create([
        'test_id' => $test->id,
        'name' => 'load-test',
    ]);

    $instructions = (string) (new ScriptAgent($script))->instructions();

    expect($instructions)->toContain('load-test')
        ->toContain('Payment API')
        ->toContain('https://pay.example.com')
        ->toContain('ListScriptFilesTool')
        ->toContain('ReadScriptFileTool')
        ->toContain('WriteScriptFileTool')
        ->toContain('DeleteScriptFileTool')
        ->toContain('RenameScriptFileTool')
        ->toContain('ValidateScriptTool')
        ->toContain('ScriptInsightsTool')
        ->toContain('scripts/'.$test->id.'/'.$script->id)
        ->toContain('script.js');
});

test('script agent instructions mandate validation after changes', function () {
    $script = Script::factory()->create();

    $instructions = (string) (new ScriptAgent($script))->instructions();

    expect($instructions)->toContain('Always validate')
        ->toContain('passes validation');
});

test('script agent provides all script tools', function () {
    $script = Script::factory()->create();

    $agent = new ScriptAgent($script);

    $tools = collect($agent->tools());

    expect($tools->filter(fn ($tool) => $tool instanceof ListScriptFilesTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof ReadScriptFileTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof WriteScriptFileTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof DeleteScriptFileTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof RenameScriptFileTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof ValidateScriptTool))->toHaveCount(1);
    expect($tools->filter(fn ($tool) => $tool instanceof ScriptInsightsTool))->toHaveCount(1);
});

test('script agent write, delete, and rename tools require approval', function () {
    $script = Script::factory()->create();

    $tools = collect((new ScriptAgent($script))->tools());

    foreach ([WriteScriptFileTool::class, DeleteScriptFileTool::class, RenameScriptFileTool::class] as $class) {
        $tool = $tools->first(fn ($t) => $t instanceof $class);

        expect($tool)->toBeInstanceOf(Approvable::class);
    }
});

test('script agent can be faked for a simple prompt', function () {
    $script = Script::factory()->create();

    ScriptAgent::fake(['I updated the thresholds and validated the script.']);

    $agent = (new ScriptAgent($script))->forParticipant($script);

    $response = $agent->prompt('Update the thresholds based on recent runs');

    expect($response->text)->toContain('updated the thresholds');
});

test('script agent fake assertions work', function () {
    $script = Script::factory()->create();

    ScriptAgent::fake(['Response']);

    (new ScriptAgent($script))->forParticipant($script)->prompt('Edit the script');

    ScriptAgent::assertPrompted('Edit the script');
    ScriptAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->contains('script');
    });
});
