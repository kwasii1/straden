<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Keep the user's prompt when an agent run fails.
 *
 * The AI SDK only persists conversation messages after a successful run
 * (inside the RememberConversation middleware's `then` callback), so a
 * provider error loses the user's prompt entirely. To guard against that we
 * durably pre-write the user message before dispatching the run and remove it
 * again once the SDK has stored the real conversation.
 */
trait PersistsChatMessages
{
    /**
     * Durably record the user's prompt in a fresh conversation.
     *
     * @return array{conversation_id: string, message_id: string}|null
     */
    public static function storeUserPrompt(object $participant, string $agentClass, string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $conversation = Conversation::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => Conversation::participantType($participant),
            'participant_id' => Conversation::participantKey($participant),
            'title' => Str::limit($text, 50, preserveWords: true),
        ]);

        $message = ConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'participant_type' => $conversation->participant_type,
            'participant_id' => $conversation->participant_id,
            'agent' => $agentClass,
            'role' => 'user',
            'content' => $text,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
            'approval_state' => null,
        ]);

        return [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ];
    }

    /**
     * Remove the placeholder conversation once the SDK has persisted the real one.
     *
     * @param  array{conversation_id: string, message_id: string}|null  $placeholder
     */
    public static function forgetPlaceholder(?array $placeholder): void
    {
        if ($placeholder === null) {
            return;
        }

        ConversationMessage::where('conversation_id', $placeholder['conversation_id'])->delete();
        Conversation::where('id', $placeholder['conversation_id'])->delete();
    }

    /**
     * Resolve the most recent conversation ID for a participant.
     */
    public static function latestConversationId(object $participant): ?string
    {
        return Conversation::query()
            ->where('participant_type', Conversation::participantType($participant))
            ->where('participant_id', Conversation::participantKey($participant))
            ->latest('updated_at')
            ->value('id');
    }
}
