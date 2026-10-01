<?php

use App\Livewire\AgentChat;
use App\Models\Test;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

test('forgetting a new-conversation placeholder keeps messages the agent stored', function () {
    $test = Test::factory()->create();

    $placeholder = AgentChat::storeUserPrompt($test, 'agent', 'hello');

    $realMessage = ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $placeholder['conversation_id'],
        'participant_type' => $test->getMorphClass(),
        'participant_id' => $test->getKey(),
        'agent' => 'agent',
        'role' => 'assistant',
        'content' => 'hi there',
        'attachments' => [],
        'steps' => [],
        'usage' => [],
        'meta' => [],
        'status' => MessageStatus::Completed,
    ]);

    AgentChat::forgetPlaceholder($placeholder);

    expect(Conversation::find($placeholder['conversation_id']))->not->toBeNull()
        ->and(ConversationMessage::find($realMessage->id))->not->toBeNull()
        ->and(ConversationMessage::find($placeholder['message_id']))->toBeNull();
});

test('forgetting a placeholder removes an otherwise empty conversation', function () {
    $test = Test::factory()->create();

    $placeholder = AgentChat::storeUserPrompt($test, 'agent', 'hello');

    AgentChat::forgetPlaceholder($placeholder);

    expect(Conversation::find($placeholder['conversation_id']))->toBeNull();
});
