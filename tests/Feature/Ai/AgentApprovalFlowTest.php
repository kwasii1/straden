<?php

use App\Ai\Agents\ScriptAgent;
use App\Ai\Agents\TestAgent;
use App\Livewire\AgentChat;
use App\Livewire\ScriptAgentChat;
use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedApprovalConversation(object $participant, string $agentClass, array $pauses): Conversation
{
    $conversation = Conversation::create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $participant->getMorphClass(),
        'participant_id' => $participant->getKey(),
        'title' => 'Approval test',
    ]);

    foreach ($pauses as $pause) {
        $toolCalls = [];
        $pending = [];

        foreach ($pause as $call) {
            $toolCalls[] = [
                'id' => $call['id'],
                'name' => $call['tool'] ?? 'CreateScriptTool',
                'arguments' => $call['arguments'] ?? ['name' => 'demo'],
                'reason' => $call['reason'] ?? 'Needs review.',
            ];
            $pending[$call['id']] = $call['reason'] ?? 'Needs review.';
        }

        ConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'participant_type' => $conversation->participant_type,
            'participant_id' => $conversation->participant_id,
            'agent' => $agentClass,
            'role' => 'assistant',
            'content' => '',
            'attachments' => [],
            'tool_calls' => $toolCalls,
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
            'approval_state' => ['pending' => $pending],
        ]);
    }

    return $conversation;
}

test('test agent chat waits for all pending approvals across pauses before resuming', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Done']);

    seedApprovalConversation($test, TestAgent::class, [
        [['id' => 'call_aaa'], ['id' => 'call_bbb']],
        [['id' => 'call_ccc']],
    ]);

    $component = Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->assertSet('awaitingApproval', true);

    // First decision alone must not resume the agent.
    $component->call('approveToolCall', 'call_aaa')->assertSet('isProcessing', false);

    TestAgent::assertNeverQueued();

    // Second decision still leaves one pause undecided.
    $component->call('approveToolCall', 'call_bbb')->assertSet('isProcessing', false);

    TestAgent::assertNeverQueued();

    // Final decision resumes with every pending call decided.
    $component->call('approveToolCall', 'call_ccc')->assertSet('isProcessing', true);

    TestAgent::assertQueued(function ($prompt) {
        return $prompt->hasApprovalDecisions()
            && array_keys($prompt->approvalDecisions->all()) === ['call_aaa', 'call_bbb', 'call_ccc'];
    });
});

test('test agent chat approve all decides every pending call at once', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Done']);

    seedApprovalConversation($test, TestAgent::class, [
        [['id' => 'call_aaa'], ['id' => 'call_bbb']],
        [['id' => 'call_ccc']],
    ]);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->call('approveAllToolCalls')
        ->assertSet('isProcessing', true);

    TestAgent::assertQueued(function ($prompt) {
        return $prompt->hasApprovalDecisions()
            && count($prompt->approvalDecisions->all()) === 3;
    });
});

test('test agent chat reject all decides every pending call at once', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Done']);

    seedApprovalConversation($test, TestAgent::class, [
        [['id' => 'call_aaa'], ['id' => 'call_bbb']],
    ]);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->call('rejectAllToolCalls')
        ->assertSet('isProcessing', true);

    TestAgent::assertQueued(function ($prompt) {
        return $prompt->hasApprovalDecisions()
            && count($prompt->approvalDecisions->all()) === 2;
    });
});

test('script agent chat approve all covers every pending pause', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    ScriptAgent::fake(['Done']);

    seedApprovalConversation($script, ScriptAgent::class, [
        [['id' => 'call_aaa']],
        [['id' => 'call_bbb']],
    ]);

    Livewire::actingAs($user)
        ->test(ScriptAgentChat::class, ['project' => $project, 'test' => $test, 'script' => $script])
        ->call('approveAllToolCalls')
        ->assertSet('isProcessing', true);

    ScriptAgent::assertQueued(function ($prompt) {
        return $prompt->hasApprovalDecisions()
            && count($prompt->approvalDecisions->all()) === 2;
    });
});
