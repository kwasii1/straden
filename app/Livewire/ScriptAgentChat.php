<?php

namespace App\Livewire;

use App\Ai\Agents\ScriptAgent;
use App\Ai\Providers\AvailableModelMap;
use App\Events\ConversationErrored;
use App\Events\ConversationUpdated;
use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Notifications\ScriptGenerationCompleted;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Livewire\Component;

class ScriptAgentChat extends Component
{
    public Project $project;

    public Test $test;

    public Script $script;

    public string $input = '';

    public ?string $conversationId = null;

    public array $displayMessages = [];

    public bool $awaitingApproval = false;

    /**
     * Accumulated user decisions for pending approvals, keyed by tool call ID.
     * Values: 'approve' or 'reject'
     *
     * @var array<string, string>
     */
    public array $pendingDecisions = [];

    public bool $isProcessing = false;

    public ?string $error = null;

    public ?string $selectedProvider = null;

    public ?string $selectedModel = null;

    /**
     * Available providers as [key => label].
     *
     * @var array<string, string>
     */
    public array $availableProviders = [];

    /**
     * Available models for the selected provider.
     *
     * @var array<string>
     */
    public array $availableModels = [];

    /**
     * Previous conversations for this script.
     *
     * @var array<int, array{id: string, title: string, created_at: string}>
     */
    public array $conversations = [];

    public function mount(Project $project, Test $test, Script $script): void
    {
        $this->project = $project;
        $this->test = $test;
        $this->script = $script;

        $this->buildAvailableProviders();
        $this->selectedProvider = 'deepseek';
        $this->buildAvailableModels();
        $this->selectedModel = 'deepseek-v4-flash';
        $this->loadConversations();

        $existingConversation = Conversation::query()
            ->where('participant_type', $script->getMorphClass())
            ->where('participant_id', $script->getKey())
            ->latest('updated_at')
            ->first();

        if ($existingConversation) {
            $this->conversationId = $existingConversation->id;
            $this->loadConversationMessages();
            $this->detectApprovalState();
        }
    }

    public function updatedSelectedProvider(?string $value): void
    {
        $this->buildAvailableModels();

        if (! in_array($this->selectedModel, $this->availableModels, true)) {
            $this->selectedModel = $this->availableModels[0] ?? null;
        }
    }

    public function updatedConversationId(?string $value): void
    {
        if ($value) {
            $this->loadConversationMessages();
            $this->detectApprovalState();
            $this->awaitingApproval = false;
            $this->pendingDecisions = [];
            $this->error = null;
            $this->dispatch('chat-scroll-bottom');
        } else {
            $this->newConversation();
        }
    }

    public function submitMessage(): void
    {
        $this->validate([
            'input' => ['required', 'string', 'max:2000'],
        ]);

        $this->isProcessing = true;
        $this->error = null;
        $this->awaitingApproval = false;
        $this->pendingDecisions = [];

        $userInput = trim($this->input);
        $this->input = '';

        $testId = $this->test->id;
        $scriptId = $this->script->id;
        $userId = auth()->id();

        $agent = new ScriptAgent($this->script);

        if ($this->conversationId) {
            $agent->continue($this->conversationId, as: $this->script);
        } else {
            $agent->forParticipant($this->script);
        }

        $agent->queue(
            $userInput,
            provider: $this->selectedProvider ? Lab::from($this->selectedProvider) : null,
            model: $this->selectedModel,
        )
            ->then(function () use ($testId, $scriptId, $userId) {
                event(new ConversationUpdated($testId));

                if ($userId) {
                    User::find($userId)?->notify(new ScriptGenerationCompleted($scriptId, $testId));
                }
            })
            ->catch(function (\Throwable $e) use ($testId) {
                event(new ConversationErrored($testId, $e->getMessage()));
            });

        $this->dispatch('chat-scroll-bottom');
    }

    public function reloadMessages(): void
    {
        if (! $this->conversationId) {
            $conversation = Conversation::query()
                ->where('participant_type', $this->script->getMorphClass())
                ->where('participant_id', $this->script->getKey())
                ->latest('updated_at')
                ->first();

            if ($conversation) {
                $this->conversationId = $conversation->id;
            }
        }

        $this->loadConversations();
        $this->loadConversationMessages();
        $this->detectApprovalState();
        $this->isProcessing = false;
        $this->dispatch('chat-scroll-bottom');
    }

    public function reloadError(array $payload): void
    {
        $this->error = $payload['error'] ?? 'An unknown error occurred.';
        $this->loadConversationMessages();
        $this->isProcessing = false;
        $this->dispatch('chat-scroll-bottom');
    }

    public function approveToolCall(string $callId): void
    {
        $this->pendingDecisions[$callId] = 'approve';
        $this->attemptSubmitDecisions();
    }

    public function rejectToolCall(string $callId): void
    {
        $this->pendingDecisions[$callId] = 'reject';
        $this->attemptSubmitDecisions();
    }

    private function attemptSubmitDecisions(): void
    {
        $pendingIds = $this->getPendingCallIds();

        if (count(array_intersect($pendingIds, array_keys($this->pendingDecisions))) !== count($pendingIds)) {
            return;
        }

        $this->sendDecisions();
    }

    private function sendDecisions(): void
    {
        $this->isProcessing = true;
        $this->error = null;

        $testId = $this->test->id;

        $decisions = [];

        foreach ($this->pendingDecisions as $callId => $decisionType) {
            $decisions[$callId] = $decisionType === 'approve'
                ? Decision::approve()
                : Decision::reject('Rejected by user.');
        }

        $agent = (new ScriptAgent($this->script))
            ->continue($this->conversationId, as: $this->script);

        $agent->queue(
            Decisions::from($decisions),
            provider: $this->selectedProvider ? Lab::from($this->selectedProvider) : null,
            model: $this->selectedModel,
        )
            ->then(function () use ($testId) {
                event(new ConversationUpdated($testId));
            })
            ->catch(function (\Throwable $e) use ($testId) {
                event(new ConversationErrored($testId, $e->getMessage()));
            });

        $this->pendingDecisions = [];
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->displayMessages = [];
        $this->awaitingApproval = false;
        $this->pendingDecisions = [];
        $this->error = null;
    }

    private function detectApprovalState(): void
    {
        foreach ($this->displayMessages as $message) {
            if ($message['is_approval_pause']) {
                $this->awaitingApproval = true;

                return;
            }
        }

        $this->awaitingApproval = false;
    }

    private function getPendingCallIds(): array
    {
        foreach ($this->displayMessages as $message) {
            if ($message['is_approval_pause']) {
                return $message['pending_call_ids'];
            }
        }

        return [];
    }

    private function loadConversationMessages(): void
    {
        if (! $this->conversationId) {
            $this->displayMessages = [];

            return;
        }

        $messages = Conversation::query()
            ->where('id', $this->conversationId)
            ->first()
            ?->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        if (! $messages) {
            $this->displayMessages = [];

            return;
        }

        $this->displayMessages = $messages->map(function ($message) {
            $toolCalls = is_array($message->tool_calls) ? $message->tool_calls : [];
            $toolResults = is_array($message->tool_results) ? $message->tool_results : [];
            $approvalState = is_array($message->approval_state) ? $message->approval_state : null;

            $isApprovalPause = false;
            $pendingCallIds = [];

            if ($approvalState && isset($approvalState['pending']) && is_array($approvalState['pending'])) {
                $isApprovalPause = ! empty($approvalState['pending']);
                $pendingCallIds = array_keys($approvalState['pending']);
            }

            return [
                'role' => $message->role,
                'content' => $message->content,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
                'is_approval_pause' => $isApprovalPause,
                'pending_call_ids' => $pendingCallIds,
                'created_at' => $message->created_at->toIso8601String(),
            ];
        })->all();
    }

    private function buildAvailableProviders(): void
    {
        $providers = config('ai.providers');
        $textProviders = AvailableModelMap::textProviders();

        foreach ($providers as $key => $config) {
            if (! in_array($key, $textProviders, true)) {
                continue;
            }

            if (empty($config['key'])) {
                continue;
            }

            $this->availableProviders[$key] = AvailableModelMap::labelFor($key);
        }

        if (empty($this->availableProviders)) {
            $this->availableProviders['deepseek'] = 'DeepSeek';
        }

        asort($this->availableProviders);
    }

    private function buildAvailableModels(): void
    {
        if (! $this->selectedProvider) {
            $this->availableModels = [];

            return;
        }

        $this->availableModels = AvailableModelMap::modelsFor($this->selectedProvider);
    }

    private function loadConversations(): void
    {
        $this->conversations = Conversation::query()
            ->where('participant_type', $this->script->getMorphClass())
            ->where('participant_id', $this->script->getKey())
            ->latest('updated_at')
            ->get()
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at->diffForHumans(),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.script-agent-chat');
    }
}
