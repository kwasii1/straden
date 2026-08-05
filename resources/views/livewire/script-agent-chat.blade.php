<div
    x-data="{ sidebarHovered: false, sidebarPinned: false }"
    class="relative flex flex-col h-full"
>
    {{-- Hover-expand conversation sidebar --}}
    <div
        @mouseenter="sidebarHovered = true"
        @mouseleave="if (!sidebarPinned) sidebarHovered = false"
        class="absolute left-0 top-0 bottom-0 z-30 flex transition-all duration-200 ease-out"
        :class="sidebarHovered || sidebarPinned ? 'w-64' : 'w-11'"
    >
        <div class="flex flex-col h-full bg-zinc-950/95 backdrop-blur-sm border-r border-zinc-800"
             :class="sidebarHovered || sidebarPinned ? 'w-64' : 'w-11'">
            <div class="flex flex-col items-center gap-1.5 pt-2 px-1.5">
                <button
                    wire:click="newConversation"
                    class="flex items-center gap-2 w-full rounded-md p-1.5 text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/50 transition-colors"
                    :class="sidebarHovered || sidebarPinned ? 'justify-start' : 'justify-center'"
                    title="New Chat"
                >
                    <flux:icon.plus class="size-4 shrink-0" />
                    <span x-show="sidebarHovered || sidebarPinned" class="text-xs whitespace-nowrap overflow-hidden">New Chat</span>
                </button>
                <div class="relative w-full"
                     x-data="{ historyOpen: false, leaveTimer: null }"
                >
                    <div
                        class="flex items-center gap-2 w-full rounded-md p-1.5 text-zinc-400 cursor-default"
                        :class="sidebarHovered || sidebarPinned ? 'justify-start' : 'justify-center'"
                        @mouseenter="clearTimeout(leaveTimer); historyOpen = true"
                        @mouseleave="leaveTimer = setTimeout(() => historyOpen = false, 150)"
                    >
                        <flux:icon.chat-bubble-left-right class="size-4 shrink-0" />
                        <span x-show="sidebarHovered || sidebarPinned" class="text-xs whitespace-nowrap overflow-hidden">History</span>
                    </div>
                    @if (! empty($conversations))
                        <div
                            x-show="historyOpen && (sidebarHovered || sidebarPinned)"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-x-2"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            @mouseenter="clearTimeout(leaveTimer); historyOpen = true"
                            @mouseleave="leaveTimer = setTimeout(() => historyOpen = false, 150)"
                            class="absolute left-full top-0 ml-2 w-72 max-h-96 overflow-y-auto rounded-lg border border-zinc-700 bg-zinc-900 shadow-xl"
                        >
                            <div class="px-3 py-2 border-b border-zinc-800">
                                <span class="text-xs text-zinc-500 uppercase tracking-wide">Previous Conversations</span>
                            </div>
                            @foreach ($conversations as $convo)
                                <button
                                    wire:click="$set('conversationId', '{{ $convo['id'] }}')"
                                    class="w-full text-left px-3 py-2.5 hover:bg-zinc-800/50 transition-colors {{ $conversationId === $convo['id'] ? 'bg-zinc-800/50' : '' }}"
                                >
                                    <p class="text-xs text-zinc-200 truncate">{{ $convo['title'] }}</p>
                                    <p class="text-[10px] text-zinc-500 mt-0.5">{{ $convo['created_at'] }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Messages area --}}
    <div
        id="chat-messages"
        class="flex-1 overflow-y-auto pt-14 pb-36 pl-14 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        <div class="max-w-2xl mx-auto px-4">
        @if (empty($displayMessages) && ! $isProcessing)
            <div class="flex justify-center items-center h-full text-zinc-500 text-sm">
                <div class="text-center space-y-2 max-w-md">
                    <p>Ask me to edit this k6 script.</p>
                    <p class="text-xs text-zinc-600">I can read, create, edit, and delete files in the script directory, always validate the script, and check recent run results from InfluxDB before suggesting changes.</p>
                </div>
            </div>
        @endif

        @foreach ($displayMessages as $message)
            @if ($message['role'] === 'user')
                <div class="flex justify-end mb-3">
                    <div class="max-w-[75%] rounded-xl rounded-br-sm px-4 py-2.5 bg-blue-600/90 text-white">
                        <div class="whitespace-pre-wrap break-words text-sm">{{ $message['content'] }}</div>
                    </div>
                </div>
            @elseif ($message['role'] === 'assistant')
                <div class="mb-3 space-y-2">
                    @if ($message['is_approval_pause'])
                        @foreach ($message['tool_calls'] as $call)
                            @php $callId = $call['id'] ?? ''; @endphp
                            @if (in_array($callId, $message['pending_call_ids']))
                                <div class="rounded-xl border border-amber-700/50 bg-amber-900/20 p-4 max-w-[80%]">
                                    <div class="flex items-center gap-2 mb-3">
                                        <flux:icon.exclamation-triangle class="size-4 text-amber-400" />
                                        <span class="text-sm font-medium text-amber-300">{{ $this->toolLabel($call['name'] ?? $call['function']['name'] ?? '') }}</span>
                                    </div>
                                    <div class="bg-zinc-900/50 rounded-lg p-2.5 font-mono text-xs text-zinc-300 overflow-x-auto mb-3">
                                        @php
                                            $args = $call['arguments'] ?? [];
                                            if (is_string($args)) { $args = json_decode($args, true) ?? []; }
                                        @endphp
                                        @foreach ($args as $key => $value)
                                            @if (in_array($key, ['content', 'entry_point_content']))
                                                <pre class="mt-1 text-zinc-400 line-clamp-3">{{ $value }}</pre>
                                            @else
                                                <div class="mb-0.5">
                                                    <span class="text-amber-400/70">{{ $key }}:</span> {{ is_string($value) ? $value : json_encode($value) }}
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if ($awaitingApproval)
                                        @php $decision = $pendingDecisions[$callId] ?? null; @endphp
                                        @if ($decision)
                                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $decision === 'approve' ? 'bg-green-900/40 text-green-300 border border-green-700/50' : 'bg-red-900/40 text-red-300 border border-red-700/50' }}">
                                                {{ $decision === 'approve' ? 'Approved' : 'Rejected' }}
                                            </span>
                                        @else
                                            <div class="flex gap-2">
                                                <flux:button wire:click="approveToolCall('{{ $callId }}')" variant="primary" size="sm">Approve</flux:button>
                                                <flux:button wire:click="rejectToolCall('{{ $callId }}')" variant="danger" size="sm">Reject</flux:button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    @endif

                    @if (! empty($message['content']))
                        <div x-data="{ expanded: false }" class="max-w-[80%]">
                            <button
                                @click="expanded = !expanded"
                                class="flex items-center gap-2 w-full text-left rounded-lg px-3 py-2 bg-zinc-800/60 hover:bg-zinc-800/80 transition-colors group"
                            >
                                <div class="size-1.5 rounded-full bg-zinc-500 shrink-0"></div>
                                <span class="text-xs text-zinc-300 flex-1">{{ $this->reasoningHeader($message) }}</span>
                                <flux:icon.chevron-down class="size-3.5 text-zinc-500 transition-transform duration-200" ::class="expanded ? 'rotate-180' : ''" />
                            </button>
                            <div
                                x-show="expanded"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="mt-1 rounded-lg px-3 py-2.5 bg-zinc-800/30 text-sm text-zinc-300 whitespace-pre-wrap break-words"
                            >
                                {{ $message['content'] }}
                            </div>
                        </div>
                    @endif

                    @if (! empty($message['tool_calls']) && ! $message['is_approval_pause'])
                        @foreach ($message['tool_calls'] as $call)
                            <div class="flex items-center gap-2 max-w-[80%] rounded-lg px-3 py-1.5 bg-zinc-800/20 text-xs text-zinc-400">
                                <flux:icon.wrench-screwdriver class="size-3 text-zinc-500" />
                                <span>{{ $this->toolLabel($call['name'] ?? $call['function']['name'] ?? '') }}</span>
                            </div>
                        @endforeach
                    @endif

                    @if (! empty($message['tool_results']))
                        @foreach ($message['tool_results'] as $result)
                            <div class="max-w-[80%] rounded-lg px-3 py-2 bg-green-900/10 border border-green-800/20 text-xs text-green-300/80">
                                @php
                                    $resultContent = $result['content'] ?? $result['result'] ?? '';
                                    if (is_array($resultContent)) {
                                        $resultContent = implode("\n", array_map(fn ($r) => $r['text'] ?? '', $resultContent));
                                    }
                                @endphp
                                <span class="text-zinc-500">Executed:</span> {{ Str::limit($resultContent, 200) }}
                            </div>
                        @endforeach
                    @endif
                </div>
            @endif
        @endforeach

        @if ($isProcessing)
            <div class="mb-3 max-w-[80%]">
                <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-zinc-800/60">
                    <div class="flex items-center gap-1.5">
                        <span class="flex gap-1">
                            <span class="size-1.5 rounded-full bg-zinc-500 animate-pulse"></span>
                            <span class="size-1.5 rounded-full bg-zinc-500 animate-pulse" style="animation-delay: 0.15s"></span>
                            <span class="size-1.5 rounded-full bg-zinc-500 animate-pulse" style="animation-delay: 0.3s"></span>
                        </span>
                    </div>
                    <span class="text-xs text-zinc-400">{{ $awaitingApproval ? 'Waiting for approval...' : 'Thinking...' }}</span>
                </div>
            </div>
        @endif

        @if ($error)
            <div class="mb-3 max-w-[80%] rounded-lg px-3 py-2 bg-red-900/20 border border-red-800/30 text-sm text-red-300">
                {{ $error }}
            </div>
        @endif
        </div>
    </div>

    {{-- Floating chat input --}}
    <div class="absolute bottom-0 left-0 right-0 z-20">
        <div class="max-w-2xl mx-auto px-4 pb-3">
            <div class="rounded-2xl border border-zinc-700 bg-zinc-900/95 backdrop-blur-md shadow-2xl overflow-hidden">
                <div class="flex items-end gap-2 p-3">
                    <textarea
                        wire:model="input"
                        wire:keydown.enter="submitMessage"
                        rows="1"
                        placeholder="Ask the AI script assistant..."
                        class="flex-1 bg-transparent border-0 text-sm text-zinc-200 placeholder-zinc-500 resize-none focus:outline-none focus:ring-0 disabled:opacity-50 min-h-[24px] max-h-[120px]"
                        x-data
                        x-init="$el.style.height = '24px'; $el.addEventListener('input', () => { $el.style.height = '24px'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px' })"
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
                <div class="flex items-center gap-1.5 px-3 pb-2.5">
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
                </div>
            </div>
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
