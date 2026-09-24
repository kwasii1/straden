<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
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
     * Durably record the user's prompt, reusing the active conversation when one
     * is given so the placeholder is visible to the subsequent reload.
     *
     * @return array{conversation_id: string, message_id: string, is_new_conversation: bool}|null
     */
    public static function storeUserPrompt(object $participant, string $agentClass, string $text, ?string $existingConversationId = null): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $participantType = Conversation::participantType($participant);
        $participantKey = Conversation::participantKey($participant);

        $conversation = null;
        $isNewConversation = true;

        if ($existingConversationId) {
            $conversation = Conversation::query()
                ->where('id', $existingConversationId)
                ->where('participant_type', $participantType)
                ->where('participant_id', $participantKey)
                ->first();

            if ($conversation) {
                $isNewConversation = false;
                $conversation->touch();
            }
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'id' => (string) Str::uuid7(),
                'participant_type' => $participantType,
                'participant_id' => $participantKey,
                'title' => Str::limit($text, 50, preserveWords: true),
            ]);
            $isNewConversation = true;
        }

        $message = ConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'participant_type' => $conversation->participant_type,
            'participant_id' => $conversation->participant_id,
            'agent' => $agentClass,
            'role' => 'user',
            'content' => $text,
            'attachments' => [],
            'steps' => [],
            'usage' => [],
            'meta' => [],
            'status' => MessageStatus::Completed,
        ]);

        return [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'is_new_conversation' => $isNewConversation,
        ];
    }

    /**
     * Append an optimistic user bubble so the sent message is visible before
     * the queued agent run completes and triggers a reload from storage.
     */
    protected function pushOptimisticUserMessage(string $text): void
    {
        $this->displayMessages[] = [
            'role' => 'user',
            'content' => $text,
            'tool_calls' => [],
            'tool_results' => [],
            'is_approval_pause' => false,
            'pending_call_ids' => [],
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Remove the placeholder message once the SDK has persisted the real one.
     *
     * When the placeholder reused an existing conversation only the single
     * message is removed; a freshly created placeholder conversation is
     * removed entirely. Placeholders written before the reuse flag existed
     * fall back to the previous behaviour.
     *
     * @param  array{conversation_id: string, message_id: string, is_new_conversation?: bool}|null  $placeholder
     */
    public static function forgetPlaceholder(?array $placeholder): void
    {
        if ($placeholder === null) {
            return;
        }

        if (($placeholder['is_new_conversation'] ?? true) === false) {
            ConversationMessage::where('id', $placeholder['message_id'])->delete();

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
