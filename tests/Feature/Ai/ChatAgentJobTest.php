<?php

use App\Ai\Agents\ScriptAgent;
use App\Jobs\ChatAgentJob;
use App\Livewire\Concerns\PersistsChatMessages;
use App\Models\Script;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

uses(RefreshDatabase::class);

test('chat agent job removes the placeholder conversation after a successful run', function () {
    Bus::fake([BroadcastEvent::class]);

    $script = Script::factory()->create();

    ScriptAgent::fake(['Done']);

    $placeholder = PersistsChatMessages::storeUserPrompt($script, ScriptAgent::class, 'Hello agent');

    $job = new ChatAgentJob(
        agent: (new ScriptAgent($script))->forParticipant($script),
        prompt: 'Hello agent',
        testId: $script->test_id,
        placeholder: $placeholder,
    );

    $job->handle();

    expect(ConversationMessage::where('id', $placeholder['message_id'])->exists())->toBeFalse();
    expect(Conversation::where('id', $placeholder['conversation_id'])->exists())->toBeFalse();

    // The SDK middleware must have persisted the real conversation (with the
    // assistant reply) BEFORE the completion signal is broadcast, so the UI
    // reload always finds the final content.
    expect(ConversationMessage::query()
        ->where('participant_type', $script->getMorphClass())
        ->where('participant_id', $script->getKey())
        ->where('role', 'assistant')
        ->where('content', 'Done')
        ->exists())->toBeTrue();

    Bus::assertDispatched(BroadcastEvent::class, function (BroadcastEvent $broadcast) {
        return $broadcast->event->broadcastAs() === 'agent_completed';
    });
});

test('chat agent job broadcasts a detailed error when the run fails', function () {
    Bus::fake([BroadcastEvent::class]);

    config(['ai.providers.deepseek.key' => 'test-key']);
    Http::fake(['https://api.deepseek.com/*' => Http::response('Bad Request', 400)]);

    $script = Script::factory()->create();

    $job = new ChatAgentJob(
        agent: (new ScriptAgent($script))->forParticipant($script),
        prompt: 'Hi',
        testId: $script->test_id,
    );

    $job->handle();

    Bus::assertDispatched(BroadcastEvent::class, function (BroadcastEvent $broadcast) {
        $event = $broadcast->event;

        return $event->broadcastAs() === 'request_error'
            && str_contains($event->broadcastWith()['message'] ?? '', '400');
    });
});
