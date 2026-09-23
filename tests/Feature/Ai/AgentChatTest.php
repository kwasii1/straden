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
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('script agent chat submits a message through the broadcasting queue', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    ScriptAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(ScriptAgentChat::class, ['project' => $project, 'test' => $test, 'script' => $script])
        ->set('input', 'Update the thresholds based on recent runs')
        ->call('submitMessage')
        ->assertSet('input', '')
        ->assertSet('isProcessing', true);

    ScriptAgent::assertQueued('Update the thresholds based on recent runs');
});

test('script agent chat persists the user prompt so a failure does not lose it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    ScriptAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(ScriptAgentChat::class, ['project' => $project, 'test' => $test, 'script' => $script])
        ->set('input', 'Review the script performance')
        ->call('submitMessage');

    $message = ConversationMessage::query()
        ->where('participant_type', $script->getMorphClass())
        ->where('participant_id', $script->getKey())
        ->where('role', 'user')
        ->first();

    expect($message)->not->toBeNull();
    expect($message->content)->toBe('Review the script performance');

    expect(Conversation::query()
        ->where('participant_type', $script->getMorphClass())
        ->where('participant_id', $script->getKey())
        ->count())->toBe(1);
});

test('script agent chat rejects an empty message', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    ScriptAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(ScriptAgentChat::class, ['project' => $project, 'test' => $test, 'script' => $script])
        ->set('input', '')
        ->call('submitMessage')
        ->assertHasErrors('input');
});

test('test agent chat submits a message through the broadcasting queue', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('input', 'Suggest a load test for my endpoints')
        ->call('submitMessage')
        ->assertSet('input', '')
        ->assertSet('isProcessing', true);

    TestAgent::assertQueued('Suggest a load test for my endpoints');
});

test('test agent chat persists the user prompt so a failure does not lose it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('input', 'Suggest a load test for my endpoints')
        ->call('submitMessage');

    $message = ConversationMessage::query()
        ->where('participant_type', $test->getMorphClass())
        ->where('participant_id', $test->getKey())
        ->where('role', 'user')
        ->first();

    expect($message)->not->toBeNull();
    expect($message->content)->toBe('Suggest a load test for my endpoints');
});

test('test agent chat rejects an empty message', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Response']);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('input', '')
        ->call('submitMessage')
        ->assertHasErrors('input');
});
