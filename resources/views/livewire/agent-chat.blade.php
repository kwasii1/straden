<div class="flex flex-col h-full">
    <div class="flex items-center justify-between shrink-0 px-3 py-1.5 border-b border-zinc-800">
        <flux:heading size="sm">AI Test Assistant</flux:heading>
        <button
            wire:click="newConversation"
            class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors"
        >
            New Chat
        </button>
    </div>

    <div class="shrink-0 border-b border-zinc-800 px-3 py-1.5 flex items-center gap-1.5">
        <div class="flex-1 min-w-0">
            <flux:select wire:model.live="selectedProvider" size="sm" placeholder="Provider...">
                <flux:select.option value="">Provider</flux:select.option>
                @foreach ($availableProviders as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex-1 min-w-0">
            <flux:select wire:model.live="selectedModel" size="sm" placeholder="Model...">
                <flux:select.option value="">Model</flux:select.option>
                @foreach ($availableModels as $model)
                    <flux:select.option value="{{ $model }}">{{ $model }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if (! empty($conversations))
            <div class="flex-[2] min-w-0">
                <flux:select wire:model.live="conversationId" size="sm" placeholder="History...">
                    <flux:select.option value="">New Conversation</flux:select.option>
                    @foreach ($conversations as $convo)
                        <flux:select.option value="{{ $convo['id'] }}">
                            {{ \Illuminate\Support\Str::limit($convo['title'], 30) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    <div
        id="chat-messages"
        class="flex-1 overflow-y-auto p-3 space-y-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        @if (empty($displayMessages) && ! $isProcessing)
            <div class="flex justify-center items-center h-full text-zinc-500 text-sm">
                <div class="text-center space-y-2">
                    <p>Describe what tests you need and I'll help create them.</p>
                    <p class="text-xs text-zinc-600">I'll scan your connectors, repositories, and existing scripts before writing a plan.</p>
                </div>
            </div>
        @endif

        @foreach ($displayMessages as $message)
            @if ($message['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-sm bg-blue-600 text-white">
                        <p class="text-xs text-blue-300 mb-1 font-medium">You</p>
                        <div class="whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                    </div>
                </div>
            @elseif ($message['role'] === 'assistant')
                @if ($message['is_approval_pause'])
                    <div class="flex justify-start">
                        <div class="max-w-[90%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-amber-900/40 border border-amber-700/50 text-amber-200">
                            <p class="text-xs text-amber-400 mb-2 font-medium">Action Required</p>

                            @foreach ($message['tool_calls'] as $call)
                                @php $callId = $call['id'] ?? ''; @endphp
                                @if (in_array($callId, $message['pending_call_ids']))
                                    <div class="space-y-2">
                                        <div class="text-xs space-y-1">
                                            <p class="text-amber-300 font-medium">{{ $call['name'] ?? $call['function']['name'] ?? 'Unknown Tool' }}</p>
                                            <div class="bg-zinc-900/50 rounded p-2 font-mono text-xs text-zinc-300 overflow-x-auto">
                                                @php
                                                    $args = $call['arguments'] ?? [];
                                                    if (is_string($args)) {
                                                        $args = json_decode($args, true) ?? [];
                                                    }
                                                @endphp
                                                @foreach ($args as $key => $value)
                                                    @if ($key === 'entry_point_content')
                                                        <div class="mb-1">
                                                            <span class="text-amber-400">{{ $key }}:</span>
                                                            <pre class="mt-1 text-zinc-400 line-clamp-4">{{ $value }}</pre>
                                                        </div>
                                                    @else
                                                        <div>
                                                            <span class="text-amber-400">{{ $key }}:</span> {{ is_string($value) ? $value : json_encode($value) }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        @if ($awaitingApproval)

                                            @php $decision = $pendingDecisions[$callId] ?? null; @endphp

                                            @if ($decision)
                                                <div class="mt-2">
                                                    <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full {{ $decision === 'approve' ? 'bg-green-900/40 text-green-300 border border-green-700/50' : 'bg-red-900/40 text-red-300 border border-red-700/50' }}">
                                                        {{ $decision === 'approve' ? 'Approved' : 'Rejected' }}
                                                    </span>
                                                </div>
                                            @else
                                                <div class="flex gap-2 mt-2">
                                                    <flux:button
                                                        wire:click="approveToolCall('{{ $callId }}')"
                                                        variant="primary"
                                                        size="sm"
                                                    >
                                                        Approve
                                                    </flux:button>
                                                    <flux:button
                                                        wire:click="rejectToolCall('{{ $callId }}')"
                                                        variant="danger"
                                                        size="sm"
                                                    >
                                                        Reject
                                                    </flux:button>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($message['content']))
                    <div class="flex justify-start">
                        <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-zinc-800 text-zinc-200">
                            <p class="text-xs text-zinc-500 mb-1 font-medium">AI Assistant</p>
                            <div class="whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                        </div>
                    </div>
                @endif

                @if (! empty($message['tool_calls']) && ! $message['is_approval_pause'])
                    @foreach ($message['tool_calls'] as $call)
                        @php $callId = $call['id'] ?? ''; @endphp
                        <div class="flex justify-start">
                            <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-zinc-700/50 text-zinc-300">
                                <p class="text-xs text-zinc-500 mb-1">
                                    Tool: {{ $call['name'] ?? $call['function']['name'] ?? 'Unknown' }}
                                </p>
                                <p class="text-xs text-zinc-400">Executed successfully</p>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if (! empty($message['tool_results']))
                    @foreach ($message['tool_results'] as $result)
                        <div class="flex justify-start">
                            <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-green-900/30 border border-green-800/50 text-green-200">
                                @php
                                    $resultContent = $result['content'] ?? $result['result'] ?? '';
                                    if (is_array($resultContent)) {
                                        $resultContent = implode("\n", array_map(fn ($r) => $r['text'] ?? '', $resultContent));
                                    }
                                @endphp
                                <p class="text-xs">{{ Str::limit($resultContent, 500) }}</p>
                            </div>
                        </div>
                    @endforeach
                @endif
            @endif
        @endforeach

        @if ($isProcessing)
            <div class="flex justify-start">
                <div class="max-w-[85%] rounded-lg rounded-bl-sm px-4 py-3 text-sm bg-zinc-800 text-zinc-400">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ $awaitingApproval ? 'Processing approval...' : 'Thinking...' }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($error)
            <div class="flex justify-start">
                <div class="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm bg-red-900/40 border border-red-700/50 text-red-200">
                    <p class="text-xs text-red-400 mb-1 font-medium">Error</p>
                    <p>{{ $error }}</p>
                </div>
            </div>
        @endif
    </div>

    <div class="shrink-0 border-t border-zinc-800 p-3">
        <div class="flex items-end gap-2">
            <textarea
                wire:model="input"
                wire:keydown.enter="submitMessage"
                rows="2"
                placeholder="Ask the AI assistant..."
                class="flex-1 bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-200 placeholder-zinc-500 resize-none focus:outline-none focus:border-zinc-600 disabled:opacity-50"
            ></textarea>
            <flux:button
                wire:click="submitMessage"
                variant="primary"
                size="sm"
                class="shrink-0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </flux:button>
        </div>
    </div>
</div>

@script
<script>
    const testId = '{{ $test->id }}';

    window.Echo.private('test.' + testId)
        .listen('.ConversationUpdated', () => {
            $wire.reloadMessages();
        })
        .listen('.ConversationErrored', (e) => {
            $wire.reloadError(e);
        });

    $wire.on('chat-scroll-bottom', () => {
        const container = document.getElementById('chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
</script>
@endscript
