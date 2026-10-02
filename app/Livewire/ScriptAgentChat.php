<?php

namespace App\Livewire;

use App\Ai\Agents\ScriptAgent;
use App\Ai\Providers\AvailableModelMap;
use App\Jobs\ChatAgentJob;
use App\Livewire\Concerns\PersistsChatMessages;
use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Illuminate\Contracts\View\View;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ScriptAgentChat extends Component
{
    use PersistsChatMessages;

    public Project $project;

    public Test $test;

    public Script $script;

    public string $input = '';

    public ?string $conversationId = null;

    /** @var array<int, array<string, mixed>> */
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
        $this->selectedModel = in_array('deepseek-flash', $this->availableModels, true)
            ? 'deepseek-flash'
            : ($this->availableModels[0] ?? null);
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
        $this->selectedProvider = $value !== '' ? $value : null;
        $this->buildAvailableModels();

        if ($this->selectedProvider === null) {
            $this->selectedModel = null;
        } elseif (! AvailableModelMap::isKnownModel($this->selectedProvider, $this->selectedModel)) {
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

            if (! AvailableModelMap::isKnownModel($this->selectedProvider, $this->selectedModel)) {
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

        if (! AvailableModelMap::isKnownModel($this->selectedProvider, $this->selectedModel)) {
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

        $testId = $this->test->id;
        $scriptId = $this->script->id;
        $userId = auth()->id();

        $placeholder = PersistsChatMessages::storeUserPrompt($this->script, ScriptAgent::class, $userInput, $this->conversationId);

        if ($placeholder) {
            $this->conversationId = $placeholder['conversation_id'];
            $this->pushOptimisticUserMessage($userInput);
            $this->loadConversations();
            $this->dispatch('chat-optimistic-sent');
        }

        $agent = new ScriptAgent($this->script);

        if ($this->conversationId) {
            $agent->continue($this->conversationId, as: $this->script);
        } else {
            $agent->forParticipant($this->script);
        }

        $this->dispatchAgent($agent, $userInput, $placeholder, notifyUserId: $userId !== null ? (string) $userId : null, notifyScriptId: $scriptId);

        $this->dispatch('chat-scroll-bottom');
    }

    /**
     * @param  array{conversation_id: string, message_id: string, is_new_conversation: bool}|null  $placeholder
     */
    private function dispatchAgent(Agent $agent, Decisions|string $prompt, ?array $placeholder, ?string $notifyUserId = null, ?string $notifyScriptId = null): void
    {
        $model = $this->selectedModel !== '' ? $this->selectedModel : null;

        if (ScriptAgent::isFaked()) {
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
            notifyUserId: $notifyUserId,
            notifyScriptId: $notifyScriptId,
        );
    }

    public function reloadMessages(): void
    {
        if (! $this->conversationId) {
            $this->conversationId = PersistsChatMessages::latestConversationId($this->script);
        }

        $this->loadConversations();
        $this->loadConversationMessages();
        $this->detectApprovalState();
        $this->isProcessing = false;
        $this->dispatch('chat-scroll-bottom');
    }

    /** @param array<string, mixed> $payload */
    public function reloadError(array $payload): void
    {
        $this->error = $payload['error'] ?? 'An unknown error occurred.';

        if (! $this->conversationId) {
            $this->conversationId = PersistsChatMessages::latestConversationId($this->script);
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

        $agent = (new ScriptAgent($this->script))
            ->continue($this->conversationId, as: $this->script);

        $this->dispatchAgent($agent, Decisions::from($decisions), null);

        $this->pendingDecisions = [];
        $this->dispatch('chat-scroll-bottom');
    }

    /**
     * Permanently delete one of this chat's past conversations.
     */
    public function deleteConversation(string $conversationId): void
    {
        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('participant_type', $this->script->getMorphClass())
            ->where('participant_id', $this->script->getKey())
            ->first();

        if (! $conversation) {
            return;
        }

        $conversation->messages()->delete();
        $conversation->delete();

        if ($this->conversationId === $conversationId) {
            $this->newConversation();
        }

        $this->loadConversations();
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

    /** @return array<int, string> */
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

        $this->displayMessages = $messages->map(function (ConversationMessage $message) {
            // tool_calls/tool_results are read-only accessors derived from
            // the stored steps. A call with an approval_reason but no result
            // is still awaiting a decision.
            $toolCalls = $message->tool_calls;
            $toolResults = $message->tool_results;

            $pendingCallIds = [];

            foreach ($toolCalls as $call) {
                if (is_array($call) && PendingApproval::isPending($call)) {
                    $pendingCallIds[] = $call['id'];
                }
            }

            return [
                'role' => $message->role,
                'content' => $message->content,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
                'is_approval_pause' => $pendingCallIds !== [],
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

    /** @param array<string, mixed> $message */
    public function reasoningHeader(array $message): string
    {
        $content = $message['content'] ?? '';
        $toolCalls = $message['tool_calls'] ?? [];

        if ($content === '' && empty($toolCalls)) {
            return 'Processing...';
        }

        $firstSentence = $this->extractFirstSentence($content);

        if ($firstSentence !== '') {
            return $firstSentence;
        }

        $toolNames = array_map(fn ($call) => $call['name'] ?? $call['function']['name'] ?? '', $toolCalls);
        $toolNames = array_filter($toolNames);

        if (! empty($toolNames)) {
            return 'Calling '.implode(', ', array_map(fn ($n) => $this->formatToolName($n), $toolNames));
        }

        return 'Processing...';
    }

    public function toolLabel(string $toolName): string
    {
        return $this->formatToolName($toolName);
    }

    private function extractFirstSentence(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        $sentence = preg_split('/[.。!！\n]+/', $content, 2)[0] ?? '';

        $sentence = trim($sentence);

        if ($sentence === '') {
            return mb_substr($content, 0, 60).(mb_strlen($content) > 60 ? '...' : '');
        }

        $sentence = mb_substr($sentence, 0, 80).(mb_strlen($sentence) > 80 ? '...' : '');

        return $this->cleanupHeader($sentence);
    }

    private function cleanupHeader(string $text): string
    {
        $text = preg_replace('/^(i\'ll|i will|let me|now|first|next|finally|here\'s|this is|ok|okay|alright|well|so|and)\s+/i', '', $text);
        $text = preg_replace('/^(i( am| have| need| want| see| notice| observe| recommend| suggest))\s+/i', '', $text);
        $text = preg_replace('/^(the|a|an)\s+/i', '', $text);

        return ucfirst(trim($text));
    }

    private function formatToolName(string $name): string
    {
        return match ($name) {
            'ListScriptFilesTool' => 'listing files',
            'ReadScriptFileTool' => 'reading file',
            'WriteScriptFileTool' => 'writing file',
            'DeleteScriptFileTool' => 'deleting file',
            'RenameScriptFileTool' => 'renaming file',
            'ValidateScriptTool' => 'validating script',
            'ScriptInsightsTool' => 'checking insights',
            default => strtolower(str_replace('Tool', '', $name)),
        };
    }

    /**
     * Every searchable model for the selected provider.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function allModels(): array
    {
        return $this->selectedProvider
            ? AvailableModelMap::allModelsFor($this->selectedProvider)
            : [];
    }

    public function render(): View
    {
        return view('livewire.script-agent-chat');
    }
}
