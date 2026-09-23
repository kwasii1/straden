<?php

namespace App\Livewire;

use App\Ai\Agents\TestAgent;
use App\Ai\Providers\AvailableModelMap;
use App\Jobs\ChatAgentJob;
use App\Livewire\Concerns\PersistsChatMessages;
use App\Models\Project;
use App\Models\Test;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Livewire\Component;

class AgentChat extends Component
{
    use PersistsChatMessages;

    public Project $project;

    public Test $test;

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
     * Previous conversations for this test.
     *
     * @var array<int, array{id: string, title: string, created_at: string}>
     */
    public array $conversations = [];

    public function mount(Project $project, Test $test): void
    {
        $this->project = $project;
        $this->test = $test;

        $this->buildAvailableProviders();
        $this->selectedProvider = 'deepseek';
        $this->buildAvailableModels();
        $this->selectedModel = in_array('deepseek-flash', $this->availableModels, true)
            ? 'deepseek-flash'
            : ($this->availableModels[0] ?? null);
        $this->loadConversations();

        $existingConversation = Conversation::query()
            ->where('participant_type', $test->getMorphClass())
            ->where('participant_id', $test->getKey())
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
        $this->selectedProvider = $value !== '' ? $value : null;
        $this->buildAvailableModels();

        if ($this->selectedProvider === null) {
            $this->selectedModel = null;
        } elseif (! in_array($this->selectedModel, $this->availableModels, true)) {
            $this->selectedModel = $this->availableModels[0] ?? null;
        }

        $this->dispatch('agent-provider-changed', provider: $this->selectedProvider);
        $this->dispatch('agent-model-changed', model: $this->selectedModel);
    }

    public function updatedSelectedModel(?string $value): void
    {
        $this->selectedModel = $value !== '' ? $value : null;
        $this->dispatch('agent-model-changed', model: $this->selectedModel);
    }

    public function restoreSelection(?string $provider, ?string $model): void
    {
        if ($provider === '') {
            $this->selectedProvider = null;
            $this->availableModels = [];
            $this->selectedModel = null;
        } elseif ($provider !== null && isset($this->availableProviders[$provider])) {
            $this->selectedProvider = $provider;
            $this->buildAvailableModels();

            if (! in_array($this->selectedModel, $this->availableModels, true)) {
                $this->selectedModel = $this->availableModels[0] ?? null;
            }
        }

        if ($model !== null && $model !== '' && in_array($model, $this->availableModels, true)) {
            $this->selectedModel = $model;
        } elseif ($model === '') {
            $this->selectedModel = null;
        }
    }

    /**
     * Guard against stale provider/model combinations (e.g. restored from
     * localStorage, or left over from a provider switch) so a run never
     * sends a model that does not belong to the selected provider.
     */
    private function ensureValidSelection(): void
    {
        if (! $this->selectedProvider) {
            $this->selectedModel = null;

            return;
        }

        if ($this->selectedModel === null) {
            return;
        }

        $models = AvailableModelMap::modelsFor($this->selectedProvider);

        if (! in_array($this->selectedModel, $models, true)) {
            $this->selectedModel = $models[0] ?? null;
            $this->dispatch('agent-model-changed', model: $this->selectedModel);
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

        $this->ensureValidSelection();
        $this->isProcessing = true;
        $this->error = null;
        $this->awaitingApproval = false;
        $this->pendingDecisions = [];

        $userInput = trim($this->input);
        $this->input = '';

        $placeholder = PersistsChatMessages::storeUserPrompt($this->test, TestAgent::class, $userInput, $this->conversationId);

        if ($placeholder) {
            $this->conversationId = $placeholder['conversation_id'];
            $this->pushOptimisticUserMessage($userInput);
            $this->loadConversations();
            $this->dispatch('chat-optimistic-sent');
        }

        $agent = new TestAgent($this->test);

        if ($this->conversationId) {
            $agent->continue($this->conversationId, as: $this->test);
        } else {
            $agent->forParticipant($this->test);
        }

        $this->dispatchAgent($agent, $userInput, $placeholder);

        $this->dispatch('chat-scroll-bottom');
    }

    private function dispatchAgent(Agent $agent, Decisions|string $prompt, ?array $placeholder): void
    {
        $model = $this->selectedModel !== '' ? $this->selectedModel : null;

        if (TestAgent::isFaked()) {
            $agent->queue(
                $prompt,
                provider: $this->selectedProvider ? Lab::from($this->selectedProvider) : null,
                model: $model,
            );

            return;
        }

        ChatAgentJob::dispatch(
            $agent,
            $prompt,
            $this->test->id,
            placeholder: $placeholder,
            provider: $this->selectedProvider ? Lab::from($this->selectedProvider) : null,
            model: $model,
        );
    }

    public function reloadMessages(): void
    {
        if (! $this->conversationId) {
            $this->conversationId = PersistsChatMessages::latestConversationId($this->test);
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

        if (! $this->conversationId) {
            $this->conversationId = PersistsChatMessages::latestConversationId($this->test);
        }

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

    public function approveAllToolCalls(): void
    {
        foreach ($this->getAllPendingCallIds() as $callId) {
            $this->pendingDecisions[$callId] = 'approve';
        }

        $this->attemptSubmitDecisions();
    }

    public function rejectAllToolCalls(): void
    {
        foreach ($this->getAllPendingCallIds() as $callId) {
            $this->pendingDecisions[$callId] = 'reject';
        }

        $this->attemptSubmitDecisions();
    }

    private function attemptSubmitDecisions(): void
    {
        $pendingIds = $this->getAllPendingCallIds();

        if ($pendingIds === []) {
            return;
        }

        if (count(array_intersect($pendingIds, array_keys($this->pendingDecisions))) !== count($pendingIds)) {
            return;
        }

        $this->sendDecisions();
    }

    private function sendDecisions(): void
    {
        $pendingIds = $this->getAllPendingCallIds();

        if ($pendingIds === []) {
            return;
        }

        $this->ensureValidSelection();
        $this->isProcessing = true;
        $this->awaitingApproval = false;
        $this->error = null;

        $decisions = [];

        foreach ($pendingIds as $callId) {
            $decisionType = $this->pendingDecisions[$callId] ?? null;

            if ($decisionType === null) {
                return;
            }

            $decisions[$callId] = $decisionType === 'approve'
                ? Decision::approve()
                : Decision::reject('Rejected by user.');
        }

        $agent = (new TestAgent($this->test))
            ->continue($this->conversationId, as: $this->test);

        $this->dispatchAgent($agent, Decisions::from($decisions), null);

        $this->pendingDecisions = [];
        $this->dispatch('chat-scroll-bottom');
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

    private function getAllPendingCallIds(): array
    {
        $ids = [];

        foreach ($this->displayMessages as $message) {
            if (! empty($message['is_approval_pause'])) {
                foreach ($message['pending_call_ids'] ?? [] as $callId) {
                    $ids[] = $callId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function getPendingCallIds(): array
    {
        return $this->getAllPendingCallIds();
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
            ->orderBy('id', 'asc')
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
            ->where('participant_type', $this->test->getMorphClass())
            ->where('participant_id', $this->test->getKey())
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
        return view('livewire.agent-chat');
    }
}
