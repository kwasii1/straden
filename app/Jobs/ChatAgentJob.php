<?php

namespace App\Jobs;

use App\Livewire\Concerns\PersistsChatMessages;
use App\Models\User;
use App\Notifications\ScriptGenerationCompleted;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Attributes\WithoutBroadcasting;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Throwable;

/**
 * Queue an agent run and broadcast its stream events live over WebSockets.
 *
 * Unlike the SDK's BroadcastAgent, failures here broadcast the real exception
 * message (so the UI can surface why the run failed) and leave the durably
 * pre-written user prompt intact so it is never lost.
 */
class ChatAgentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public string $invocationId;

    /**
     * @param  array{conversation_id: string, message_id: string}|null  $placeholder
     */
    public function __construct(
        public Agent $agent,
        public Decisions|string $prompt,
        public string $testId,
        public ?array $placeholder = null,
        public array $attachments = [],
        public Lab|array|string|null $provider = null,
        public ?string $model = null,
        public ?string $notifyUserId = null,
        public ?string $notifyScriptId = null,
    ) {
        $this->invocationId = (string) Str::uuid7();
    }

    public function handle(): void
    {
        $channels = new PrivateChannel('test.'.$this->testId);
        $without = WithoutBroadcasting::eventsFor($this->agent);

        try {
            $this->agent->stream($this->prompt, $this->attachments, $this->provider, $this->model)
                ->each(function (StreamEvent $event) use ($channels, $without): void {
                    if (WithoutBroadcasting::excludes($without, $event)) {
                        return;
                    }

                    try {
                        $event->withInvocationId($this->invocationId)->broadcastNow($channels);
                    } catch (Throwable $broadcastError) {
                        // Tool calls can carry full script contents that exceed
                        // the broadcaster's payload limit. Skipping the live
                        // event is harmless: the persisted conversation (which
                        // the UI reloads from on completion) still holds the
                        // full content. Never let one oversized event kill the
                        // whole run — the tiny completion/approval signals sent
                        // afterwards must still go out.
                        if (! str_contains($broadcastError->getMessage(), 'too large')) {
                            report($broadcastError);
                        }
                    }
                })
                ->then(function (StreamedAgentResponse $response): void {
                    // The SDK's RememberConversation middleware persists the
                    // conversation messages before this callback runs, so the
                    // UI only reloads once the final content is actually stored.
                    PersistsChatMessages::forgetPlaceholder($this->placeholder);
                    // $this->notifyCompletion();

                    $this->broadcastSignal(
                        $response->hasPendingApprovals()
                            ? 'agent_approval_request'
                            : 'agent_completed'
                    );
                });
        } catch (Throwable $e) {
            report($e);

            $this->broadcastError($e);
        }
    }

    /**
     * Broadcast a post-persistence signal so the chat UI reloads the stored
     * conversation. Sent only after the middleware has written the messages.
     */
    protected function broadcastSignal(string $type): void
    {
        try {
            Broadcast::on(new PrivateChannel('test.'.$this->testId))
                ->as($type)
                ->with(['type' => $type])
                ->sendNow();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Send the completion notification when a user triggered the run.
     */
    protected function notifyCompletion(): void
    {
        if (! $this->notifyUserId || ! $this->notifyScriptId) {
            return;
        }

        $user = User::find($this->notifyUserId);

        $user?->notify(new ScriptGenerationCompleted($this->notifyScriptId, $this->testId));

        // Broadcast a simple Bell event that requires zero model restoration,
        // bypassing the serialization problems of BroadcastNotificationCreated.
        try {
            Broadcast::on(new PrivateChannel('App.Models.User.'.$this->notifyUserId))
                ->as('NotificationSent')
                ->with(['type' => 'notification_sent'])
                ->sendNow();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Broadcast a detailed error so the chat UI can surface why the run failed.
     */
    protected function broadcastError(Throwable $e): void
    {
        $message = $e->getMessage();

        if ($e instanceof RequestException && $e->response) {
            $body = mb_substr(trim((string) $e->response->body()), 0, 500);

            if ($body !== '') {
                $message .= ' Response: '.$body;
            }
        }

        (new Error(
            id: (string) Str::uuid7(),
            type: 'request_error',
            message: mb_substr($message, 0, 1000),
            recoverable: false,
            timestamp: time(),
        ))->withInvocationId($this->invocationId)
            ->broadcastNow(new PrivateChannel('test.'.$this->testId));
    }
}
