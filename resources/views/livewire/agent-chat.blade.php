<div
    class="flex flex-col h-screen max-h-screen bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 overflow-hidden relative"
    x-data="{ pendingMessage: '' }"
    @chat-optimistic-sent.window="pendingMessage = ''"
>
    {{-- Fixed Header --}}
    <div class="shrink-0 px-4 py-2.5 border-b border-zinc-200/80 dark:border-zinc-800/80 flex items-center justify-between bg-white/80 dark:bg-zinc-950/80 backdrop-blur-md z-20">
        <flux:heading size="sm" class="font-medium leading-tight text-zinc-800 dark:text-zinc-200">Straden Agent</flux:heading>

        <div class="flex items-center gap-1">
            {{-- New Chat --}}
            <flux:tooltip content="New chat">
                <flux:button
                    wire:click="newConversation"
                    variant="subtle"
                    size="sm"
                    icon="plus"
                    inset
                    class="!size-8 !p-0 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
                />
            </flux:tooltip>

            {{-- History --}}
            @if (! empty($conversations))
                <flux:dropdown position="bottom" align="end">
                    <flux:tooltip content="History">
                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="clock"
                            inset
                            class="!size-8 !p-0 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
                        />
                    </flux:tooltip>
                    <flux:menu class="max-h-64 overflow-y-auto w-64">
                        <flux:menu.radio.group wire:model.live="conversationId">
                            <flux:menu.radio value="">New Conversation</flux:menu.radio>
                            @foreach ($conversations as $convo)
                                <flux:menu.radio value="{{ $convo['id'] }}">
                                    {{ \Illuminate\Support\Str::limit($convo['title'], 28) }}
                                </flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    {{-- Chat Messages Scroll Area --}}
    <div
        id="chat-messages"
        class="flex-1 min-h-0 overflow-y-auto px-4 pt-5 pb-8 space-y-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        {{-- Empty State --}}
        @if (empty($displayMessages) && ! $isProcessing)
            <div class="flex flex-col items-center justify-center min-h-[50vh] text-center max-w-sm mx-auto px-4">
                <flux:icon.command-line class="size-5 text-zinc-300 dark:text-zinc-700 mb-3" />
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Build & execute load tests</h3>
                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1 leading-relaxed">
                    Describe your test scenario. I'll scan your endpoints, connectors, and existing scripts before crafting an execution plan.
                </p>

                <div class="grid grid-cols-1 gap-1.5 w-full mt-6">
                    <button
                        x-on:click="pendingMessage = 'Analyze my target endpoints and suggest a 30s load test scenario.'; $wire.set('input', 'Analyze my target endpoints and suggest a 30s load test scenario.'); $wire.submitMessage()"
                        class="text-left text-xs px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition text-zinc-500 dark:text-zinc-400"
                    >
                        Suggest a load test for my target endpoints
                    </button>
                    <button
                        x-on:click="pendingMessage = 'Generate a k6 script targeting the primary API connectors.'; $wire.set('input', 'Generate a k6 script targeting the primary API connectors.'); $wire.submitMessage()"
                        class="text-left text-xs px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition text-zinc-500 dark:text-zinc-400"
                    >
                        Generate a k6 script for my API connectors
                    </button>
                </div>
            </div>
        @endif

        {{-- Messages --}}
        @foreach ($displayMessages as $message)
            @if ($message['role'] === 'user')
                <div class="flex flex-col items-end gap-0.5">
                    <div class="max-w-[80%] rounded-2xl rounded-tr-sm px-3.5 py-2 text-[13px] bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                        <div class="whitespace-pre-wrap break-words leading-relaxed">{{ $message['content'] }}</div>
                    </div>
                    <span class="text-[10px] text-zinc-400 dark:text-zinc-600 pr-1">{{ \Carbon\Carbon::parse($message['created_at'])->format('g:i A') }}</span>
                </div>
            @elseif ($message['role'] === 'assistant')

                {{-- Approval Pause Carousel --}}
                @if ($message['is_approval_pause'])
                    @php
                        $pendingTools = collect($message['tool_calls'] ?? [])
                            ->filter(fn ($c) => in_array($c['id'] ?? '', $message['pending_call_ids'] ?? []))
                            ->values()
                            ->all();
                        $pendingTotal = count($pendingTools);
                        $sessionPendingTotal = collect($displayMessages)
                            ->where('is_approval_pause', true)
                            ->flatMap(fn ($m) => $m['pending_call_ids'] ?? [])
                            ->unique()
                            ->count();
                        $decidedCount = count(array_intersect(
                            $message['pending_call_ids'] ?? [],
                            array_keys($pendingDecisions)
                        ));
                    @endphp
                    <div class="flex justify-start" wire:key="approval-{{ md5(json_encode($message['pending_call_ids'] ?? [])) }}">
                        <div
                            class="w-full max-w-md"
                            x-data="{ idx: 0, total: {{ $pendingTotal }} }"
                            x-effect="$refs.track?.scrollTo({ left: idx * $refs.track.clientWidth, behavior: 'smooth' })"
                        >
                            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                                {{-- header + bulk actions --}}
                                <div class="px-3 pt-3 pb-2.5 border-b border-zinc-100 dark:border-zinc-800/70">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <flux:icon.exclamation-triangle class="size-3.5 text-amber-500 shrink-0" />
                                            <span class="text-[12.5px] font-medium text-zinc-800 dark:text-zinc-200 truncate">
                                                {{ $pendingTotal }} action{{ $pendingTotal === 1 ? '' : 's' }} need review
                                            </span>
                                        </div>
                                        <span class="shrink-0 text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500" x-text="(idx + 1) + ' / ' + total"></span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                        Decide each one, or apply to all {{ $sessionPendingTotal }} pending across this chat.
                                        <span class="tabular-nums">({{ $decidedCount }}/{{ $pendingTotal }} decided)</span>
                                    </p>
                                    @if ($awaitingApproval)
                                        <div class="mt-2 flex items-center gap-1.5">
                                            <flux:button
                                                wire:click="approveAllToolCalls"
                                                variant="primary"
                                                size="sm"
                                                class="rounded-lg text-xs flex-1"
                                                wire:loading.attr="disabled"
                                            >
                                                Approve all
                                            </flux:button>
                                            <flux:button
                                                wire:click="rejectAllToolCalls"
                                                variant="subtle"
                                                size="sm"
                                                class="rounded-lg text-xs flex-1 text-rose-600 dark:text-rose-400"
                                                wire:loading.attr="disabled"
                                            >
                                                Reject all
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>

                                {{-- carousel track --}}
                                <div
                                    x-ref="track"
                                    @scroll.throttle.50ms="idx = Math.round($event.target.scrollLeft / $event.target.clientWidth)"
                                    class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                                >
                                    @foreach ($pendingTools as $slideIndex => $call)
                                        @php
                                            $callId = $call['id'] ?? '';
                                            $toolName = $call['name'] ?? $call['function']['name'] ?? 'Unknown Tool';
                                            $args = $call['arguments'] ?? [];
                                            if (is_string($args)) $args = json_decode($args, true) ?? [];
                                            $decision = $pendingDecisions[$callId] ?? null;
                                        @endphp
                                        <div class="min-w-full snap-center p-3" wire:key="approval-slide-{{ $callId }}">
                                            <span class="text-[12.5px] font-medium text-zinc-800 dark:text-zinc-200">
                                                {{ Str::headline($toolName) }}
                                            </span>

                                            @if (! empty($call['reason'] ?? null))
                                                <p class="mt-1 text-[11.5px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                                    {{ $call['reason'] }}
                                                </p>
                                            @endif

                                            <div class="mt-2 flex flex-col gap-1">
                                                @foreach ($args as $key => $value)
                                                    <div class="rounded-md bg-zinc-50 dark:bg-zinc-900 px-2 py-1.5">
                                                        <span class="block text-[10px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                                            {{ $key }}
                                                        </span>
                                                        @if ($key === 'entry_point_content')
                                                            <pre class="mt-0.5 text-[11px] font-mono text-zinc-600 dark:text-zinc-300 leading-relaxed line-clamp-6 whitespace-pre-wrap break-words">{{ $value }}</pre>
                                                        @else
                                                            <span class="text-[11px] font-mono text-zinc-600 dark:text-zinc-300 break-words">
                                                                {{ is_string($value) ? $value : json_encode($value) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if ($awaitingApproval)
                                                @if ($decision)
                                                    <div class="mt-2.5">
                                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2 py-1 rounded-full {{ $decision === 'approve' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400' }}">
                                                            <span class="size-1.5 rounded-full {{ $decision === 'approve' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                                            {{ $decision === 'approve' ? 'Approved' : 'Rejected' }} — waiting for the rest
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-1.5 mt-2.5">
                                                        <flux:button
                                                            wire:click="approveToolCall('{{ $callId }}')"
                                                            variant="primary"
                                                            size="sm"
                                                            class="rounded-lg text-xs flex-1"
                                                            wire:loading.attr="disabled"
                                                        >
                                                            Approve
                                                        </flux:button>
                                                        <flux:button
                                                            wire:click="rejectToolCall('{{ $callId }}')"
                                                            variant="subtle"
                                                            size="sm"
                                                            class="rounded-lg text-xs flex-1 text-rose-600 dark:text-rose-400"
                                                            wire:loading.attr="disabled"
                                                        >
                                                            Reject
                                                        </flux:button>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- carousel nav --}}
                                @if ($pendingTotal > 1)
                                    <div class="flex items-center justify-between px-3 pb-3">
                                        <button
                                            type="button"
                                            x-on:click="idx = (idx - 1 + total) % total"
                                            class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
                                            aria-label="Previous approval"
                                        >
                                            <flux:icon.chevron-left class="size-3.5" />
                                        </button>
                                        <div class="flex items-center gap-1.5">
                                            @foreach ($pendingTools as $slideIndex => $call)
                                                <button
                                                    type="button"
                                                    x-on:click="idx = {{ $slideIndex }}"
                                                    class="h-1.5 rounded-full transition-all"
                                                    :class="idx === {{ $slideIndex }} ? 'w-4 bg-zinc-900 dark:bg-zinc-100' : 'w-1.5 bg-zinc-300 dark:bg-zinc-700'"
                                                    aria-label="Go to approval {{ $slideIndex + 1 }}"
                                                ></button>
                                            @endforeach
                                        </div>
                                        <button
                                            type="button"
                                            x-on:click="idx = (idx + 1) % total"
                                            class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
                                            aria-label="Next approval"
                                        >
                                            <flux:icon.chevron-right class="size-3.5" />
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{--
                    Inline tool-call + response flow.

                    All tool calls for this turn are tucked under a single
                    collapsible "N tool calls" header (closed by default), so
                    a 20-call turn doesn't dump 20 open rows into the
                    transcript. Expanding it reveals one line per call, each
                    with a type icon that swaps to a chevron on hover to
                    signal it can be expanded further for args/result. The
                    text response follows right after, so the order reads as
                    one continuous turn: [N tool calls] → response bubble.
                --}}
                @if (! empty($message['tool_calls']) && ! $message['is_approval_pause'])
                    @php $toolCallCount = count($message['tool_calls']); @endphp
                    <div x-data="{ groupOpen: false, openRows: {} }" class="max-w-[85%]">
                        <button
                            type="button"
                            x-on:click="groupOpen = !groupOpen"
                            class="flex items-center gap-1.5 px-2 py-1 -mx-2 rounded-md text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors"
                        >
                            <flux:icon.chevron-right
                                class="size-3 shrink-0 transition-transform duration-150"
                                x-bind:class="groupOpen ? 'rotate-90' : ''"
                            />
                            <span class="text-[11.5px] font-medium tabular-nums">
                                {{ $toolCallCount }} tool {{ Str::plural('call', $toolCallCount) }}
                            </span>
                        </button>

                        <div x-show="groupOpen" x-collapse class="mt-1 flex flex-col gap-0.5 pl-1">
                            @foreach ($message['tool_calls'] as $call)
                                @php
                                    $callId = $call['id'] ?? Str::random(6);
                                    $toolName = $call['name'] ?? $call['function']['name'] ?? 'Unknown';
                                    $args = $call['arguments'] ?? [];
                                    if (is_string($args)) $args = json_decode($args, true) ?? [];

                                    $result = collect($message['tool_results'] ?? [])->first(fn ($r) => ($r['tool_call_id'] ?? $r['id'] ?? null) === $callId);
                                    $resultContent = $result['content'] ?? $result['result'] ?? null;
                                    if (is_array($resultContent)) {
                                        $resultContent = implode("\n", array_map(fn ($r) => $r['text'] ?? '', $resultContent));
                                    }

                                    $toolKey = strtolower($toolName);
                                    $icon = match (true) {
                                        str_contains($toolKey, 'scan') || str_contains($toolKey, 'insight') => 'magnifying-glass',
                                        str_contains($toolKey, 'validate') => 'check-circle',
                                        str_contains($toolKey, 'create') || str_contains($toolKey, 'write') => 'pencil-square',
                                        str_contains($toolKey, 'update') || str_contains($toolKey, 'rename') || str_contains($toolKey, 'move') => 'arrow-path',
                                        str_contains($toolKey, 'delete') || str_contains($toolKey, 'remove') => 'trash',
                                        str_contains($toolKey, 'list') || str_contains($toolKey, 'read') => 'document-text',
                                        str_contains($toolKey, 'prometheus') || str_contains($toolKey, 'metric') || str_contains($toolKey, 'query') => 'chart-bar',
                                        str_contains($toolKey, 'database') || str_contains($toolKey, 'redis') => 'circle-stack',
                                        str_contains($toolKey, 'repo') => 'folder',
                                        default => 'sparkles',
                                    };
                                @endphp

                                <div class="group/row">
                                    <button
                                        type="button"
                                        x-on:click="openRows['{{ $callId }}'] = !openRows['{{ $callId }}']"
                                        class="flex items-center gap-1.5 w-full text-left px-2 py-1 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors"
                                    >
                                        <span class="relative flex size-3 shrink-0 items-center justify-center">
                                            <flux:icon
                                                :name="$icon"
                                                class="size-3 text-zinc-300 dark:text-zinc-600 transition-opacity duration-100 group-hover/row:opacity-0"
                                            />
                                            <flux:icon.chevron-right
                                                class="size-3 absolute text-zinc-400 dark:text-zinc-500 opacity-0 group-hover/row:opacity-100 transition-all duration-100"
                                                x-bind:class="openRows['{{ $callId }}'] ? 'rotate-90' : ''"
                                            />
                                        </span>
                                        <span class="text-[11.5px] font-medium text-zinc-500 dark:text-zinc-400 shrink-0">
                                            {{ Str::headline($toolName) }}
                                        </span>
                                        <span class="text-[11px] font-mono text-zinc-400 dark:text-zinc-600 truncate">
                                            {{ Str::limit(collect($args)->map(fn ($v, $k) => "$k: " . (is_string($v) ? $v : json_encode($v)))->implode(', '), 50) }}
                                        </span>
                                    </button>

                                    <div
                                        x-show="openRows['{{ $callId }}']"
                                        x-collapse
                                        class="ml-[18px] mt-0.5 mb-1 flex flex-col gap-1 border-l border-zinc-100 dark:border-zinc-800 pl-3 py-0.5"
                                    >
                                        @foreach ($args as $key => $value)
                                            <span class="text-[11px] font-mono text-zinc-400 dark:text-zinc-500 leading-relaxed break-words">
                                                <span class="text-zinc-500 dark:text-zinc-400">{{ $key }}:</span>
                                                {{ is_string($value) ? Str::limit($value, 200) : json_encode($value) }}
                                            </span>
                                        @endforeach
                                        @if ($resultContent)
                                            <span class="text-[11px] font-mono text-emerald-600/80 dark:text-emerald-400/80 leading-relaxed break-words">
                                                → {{ Str::limit($resultContent, 300) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Response bubble --}}
                @if (! empty($message['content']))
                    <div class="flex flex-col items-start gap-0.5">
                        <div class="max-w-[80%] rounded-2xl rounded-tl-sm px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 border border-zinc-100 dark:border-zinc-800/70">
                            <div class="prose prose-sm dark:prose-invert max-w-none text-[13px] leading-relaxed prose-pre:my-2 prose-pre:p-3 prose-pre:bg-zinc-950 prose-pre:border prose-pre:border-zinc-800 prose-code:text-indigo-400 prose-code:font-mono prose-code:text-[11px] break-words">
                                {!! \Illuminate\Support\Str::markdown($message['content']) !!}
                            </div>
                        </div>
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-600 pl-1">{{ \Carbon\Carbon::parse($message['created_at'])->format('g:i A') }}</span>
                    </div>
                @endif
            @endif
        @endforeach

        {{-- Instant client echo --}}
        <template x-if="pendingMessage">
            <div class="flex flex-col items-end gap-0.5">
                <div class="max-w-[80%] rounded-2xl rounded-tr-sm px-3.5 py-2 text-[13px] bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                    <div class="whitespace-pre-wrap break-words leading-relaxed" x-text="pendingMessage"></div>
                </div>
                <span class="text-[10px] text-zinc-400 dark:text-zinc-600 pr-1">Sending…</span>
            </div>
        </template>

        {{-- Live stream + loading / thinking --}}
        <div
            x-data="chatStream(@js('test.'.$test->id))"
            @agent-approval-requested.window="reset(); $wire.reloadMessages(); setTimeout(() => $wire.reloadMessages(), 1200)"
            @agent-done.window="reset(); $wire.reloadMessages(); setTimeout(() => $wire.reloadMessages(), 1200)"
            @agent-error.window="reset(); $wire.reloadError($event.detail)"
        >
            <template x-if="streaming || thinking || liveText || toolCount > 0 || approvalPending">
                <div class="flex justify-start">
                    <div class="max-w-[80%] rounded-2xl rounded-tl-sm px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 border border-zinc-100 dark:border-zinc-800/70">
                        <div x-show="approvalPending && !liveText && toolCount === 0" class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
                            <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Approval needed…</span>
                        </div>
                        <div x-show="thinking && !liveText && toolCount === 0 && !approvalPending" class="flex items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                            <span class="size-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                            <span>Thinking…</span>
                        </div>

                        <div x-show="liveText" x-text="liveText" class="whitespace-pre-wrap break-words leading-relaxed text-[13px]"></div>

                        <div x-show="toolCount > 0" class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-medium text-zinc-400 dark:text-zinc-500">
                            <span class="size-3 animate-spin rounded-full border-2 border-zinc-300 dark:border-zinc-700 border-t-transparent"></span>
                            <span x-text="toolCount + ' tool call' + (toolCount === 1 ? '' : 's')"></span>
                        </div>

                        <div x-show="!thinking && !liveText && toolCount === 0" class="flex items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                            <span class="size-3 animate-spin rounded-full border-2 border-zinc-300 dark:border-zinc-700 border-t-transparent"></span>
                            <span>Working…</span>
                        </div>
                    </div>
                </div>
            </template>

            @if ($isProcessing)
                <div class="flex justify-start" x-show="!streaming && !liveText && !toolCount && !thinking && !approvalPending">
                    @include('components.loading-state', [
                        'label' => $awaitingApproval ? 'Awaiting action approval' : 'Agent analyzing context & executing steps',
                        'variant' => 'Drive'
                    ])
                </div>
            @endif
        </div>

        {{-- Error Banner --}}
        @if ($error)
            <div class="flex justify-start">
                <div class="max-w-[80%] rounded-xl px-3 py-2 text-xs bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 flex items-start gap-2">
                    <flux:icon.exclamation-circle class="size-4 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Execution error</p>
                        <p class="mt-0.5 text-rose-600/90 dark:text-rose-300/90">{{ $error }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Fixed Bottom Textarea Input Container --}}
    <div class="shrink-0 p-3 sm:p-4 bg-gradient-to-t from-white via-white/95 dark:from-zinc-950 dark:via-zinc-950/95 to-transparent pt-4 z-20">
        <div class="max-w-3xl mx-auto rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2 flex flex-col gap-2">
            <textarea
                x-ref="chatInput"
                wire:model="input"
                rows="2"
                placeholder="Ask assistant to generate scripts, configure tests…"
                class="w-full bg-transparent text-[13px] text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 resize-none focus:outline-none px-2 py-1 leading-relaxed"
                x-on:keydown.enter="
                    if ($event.shiftKey) { return; }
                    $event.preventDefault();
                    if ($event.target.value.trim() === '') { return; }
                    pendingMessage = $event.target.value.trim();
                    $nextTick(() => document.getElementById('chat-messages')?.scrollTo({ top: document.getElementById('chat-messages').scrollHeight }));
                    $wire.submitMessage();
                "
            ></textarea>

            <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/60 px-1">
                <div class="flex items-center gap-1.5 min-w-0">
                    <flux:dropdown position="top" align="start">
                        <flux:button variant="subtle" size="sm" icon-trailing="chevron-down" class="!px-2 !py-1 !text-[11px] !rounded-lg text-zinc-500 dark:text-zinc-400">
                            {{ $selectedProvider ? ($availableProviders[$selectedProvider] ?? $selectedProvider) : 'Provider' }}
                        </flux:button>
                        <flux:menu class="max-h-48 overflow-y-auto">
                            <flux:menu.radio.group wire:model.live="selectedProvider">
                                <flux:menu.radio value="">Default provider</flux:menu.radio>
                                @foreach ($availableProviders as $key => $label)
                                    <flux:menu.radio value="{{ $key }}">{{ $label }}</flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:dropdown position="top" align="start">
                        <flux:button variant="subtle" size="sm" icon-trailing="chevron-down" class="!px-2 !py-1 !text-[11px] !rounded-lg text-zinc-500 dark:text-zinc-400">
                            {{ $selectedModel ?: 'Model' }}
                        </flux:button>
                        <flux:menu class="max-h-48 overflow-y-auto">
                            <div
                                wire:key="model-search-{{ $selectedProvider }}"
                                x-data="{
                                    search: '',
                                    models: @js($this->allModels),
                                    get results() {
                                        const q = this.search.trim().toLowerCase();
                                        return q === '' ? [] : this.models.filter(m => m.toLowerCase().includes(q)).slice(0, 50);
                                    },
                                }"
                            >
                                <input
                                    type="search"
                                    x-model="search"
                                    x-on:keydown.stop
                                    placeholder="Search all models…"
                                    class="mb-1 w-full rounded-md border border-zinc-200 bg-transparent px-2 py-1 text-xs dark:border-zinc-700"
                                />
                                <template x-for="model in results" :key="model">
                                    <button
                                        type="button"
                                        x-text="model"
                                        x-on:click="$wire.set('selectedModel', model); search = ''"
                                        class="block w-full truncate rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    ></button>
                                </template>
                                <p x-show="search.trim() !== '' && results.length === 0" class="px-2 py-1.5 text-xs text-zinc-400">No matching models.</p>
                            <div x-show="search.trim() === ''">
                            <flux:menu.radio.group wire:model.live="selectedModel">
                                <flux:menu.radio value="">Default model</flux:menu.radio>
                                @foreach ($availableModels as $model)
                                    <flux:menu.radio value="{{ $model }}">{{ $model }}</flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                            </div>
                            </div>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <flux:tooltip content="Send (Enter) · Shift+Enter for new line">
                    <button
                        wire:click="submitMessage"
                        x-on:click="const ta = $refs.chatInput; if (ta && ta.value.trim() !== '') { pendingMessage = ta.value.trim(); }"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 transition disabled:opacity-40"
                        wire:loading.attr="disabled"
                    >
                        <flux:icon.arrow-up class="size-3.5" />
                    </button>
                </flux:tooltip>
            </div>
        </div>
    </div>
</div>

@script
<script>
    // Persist provider/model selection so it survives page refreshes.
    if (! window.__agentSelectionBound) {
        window.__agentSelectionBound = true;

        const storedProvider = localStorage.getItem('straden.ai.provider');
        const storedModel = localStorage.getItem('straden.ai.model');

        $wire.call('restoreSelection', storedProvider, storedModel);

        window.addEventListener('agent-provider-changed', (e) => {
            localStorage.setItem('straden.ai.provider', e.detail.provider ?? '');
        });

        window.addEventListener('agent-model-changed', (e) => {
            localStorage.setItem('straden.ai.model', e.detail.model ?? '');
        });
    }

    const container = document.getElementById('chat-messages');

    let stickToBottom = true;

    const isNearBottom = () => {
        if (! container) return true;
        return container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    };

    const scrollToBottom = (behavior = 'auto') => {
        if (! container) return;
        container.scrollTo({ top: container.scrollHeight, behavior });
    };

    if (container) {
        container.addEventListener('scroll', () => {
            stickToBottom = isNearBottom();
        });

        const observer = new MutationObserver(() => {
            if (stickToBottom) {
                scrollToBottom();
            }
        });

        observer.observe(container, {
            childList: true,
            subtree: true,
            characterData: true,
        });

        scrollToBottom();
        requestAnimationFrame(() => scrollToBottom());
        setTimeout(() => scrollToBottom(), 150);

        const modal = container.closest('[data-flux-modal], dialog, .modal');
        if (modal) {
            modal.addEventListener('flux:show', () => {
                stickToBottom = true;
                scrollToBottom();
                requestAnimationFrame(() => scrollToBottom());
            });

            const modalObserver = new MutationObserver(() => {
                if (modal.offsetParent !== null) {
                    stickToBottom = true;
                    scrollToBottom();
                }
            });
            modalObserver.observe(modal, { attributes: true, attributeFilter: ['class', 'style', 'open'] });
        }
    }

    $wire.on('chat-scroll-bottom', () => {
        stickToBottom = true;
        scrollToBottom('smooth');
    });
</script>
@endscript
