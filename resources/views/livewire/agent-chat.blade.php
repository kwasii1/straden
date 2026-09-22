<div class="flex flex-col h-screen max-h-screen bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 overflow-hidden relative">
    {{-- Fixed Header --}}
    <div class="shrink-0 px-4 py-3 border-b border-zinc-200/80 dark:border-zinc-800/80 flex items-center justify-between bg-white/80 dark:bg-zinc-950/80 backdrop-blur-md z-20">
        <div class="flex items-center gap-2.5">
            <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-sm">
                <flux:icon.sparkles class="size-4" />
            </div>
            <div>
                <flux:heading size="sm" class="font-semibold leading-tight">Straden Agent</flux:heading>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Chat History Popover Dropdown --}}
            @if (! empty($conversations))
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="subtle" size="sm" icon="clock" class="!px-2.5 !py-1.5 !rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                        <span class="hidden sm:inline text-xs">History</span>
                    </flux:button>
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

            {{-- New Chat Button --}}
            <button
                wire:click="newConversation"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition border border-zinc-200/60 dark:border-zinc-800/60"
            >
                <flux:icon.plus class="size-3.5" />
                <span class="hidden sm:inline">New Chat</span>
            </button>
        </div>
    </div>

    {{-- Chat Messages Scroll Area --}}
    <div
        id="chat-messages"
        class="flex-1 min-h-0 overflow-y-auto px-4 pt-4 pb-8 space-y-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        {{-- Empty State --}}
        @if (empty($displayMessages) && ! $isProcessing)
            <div class="flex flex-col items-center justify-center min-h-[50vh] text-center max-w-sm mx-auto px-4">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 mb-4 border border-zinc-200/60 dark:border-zinc-800/60 shadow-sm">
                    <flux:icon.sparkles class="size-6 text-indigo-500" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Build & Execute Load Tests</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                    Describe your test scenario. I will scan your endpoints, connectors, and existing scripts before crafting an execution plan.
                </p>

                <div class="grid grid-cols-1 gap-2 w-full mt-6">
                    <button
                        x-on:click="$wire.set('input', 'Analyze my target endpoints and suggest a 30s load test scenario.'); $wire.submitMessage()"
                        class="text-left text-xs p-2.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition text-zinc-600 dark:text-zinc-300"
                    >
                        ⚡ <span class="font-medium text-zinc-900 dark:text-zinc-100">Suggest load test</span> for target endpoints
                    </button>
                    <button
                        x-on:click="$wire.set('input', 'Generate a k6 script targeting the primary API connectors.'); $wire.submitMessage()"
                        class="text-left text-xs p-2.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition text-zinc-600 dark:text-zinc-300"
                    >
                        🧪 <span class="font-medium text-zinc-900 dark:text-zinc-100">Generate k6 script</span> for API connectors
                    </button>
                </div>
            </div>
        @endif

        {{-- Messages --}}
        @foreach ($displayMessages as $message)
            @if ($message['role'] === 'user')
                <div class="flex flex-col items-end gap-0.5">
                    <div class="max-w-[85%] rounded-2xl rounded-tr-xs px-4 py-2.5 text-xs sm:text-sm bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm">
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
                            <div class="overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-lg shadow-zinc-900/5 border border-zinc-200/70 dark:border-zinc-800/70">
                                {{-- header + bulk actions --}}
                                <div class="px-3.5 pt-3.5 pb-3 border-b border-zinc-100 dark:border-zinc-800/70">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                                <flux:icon.exclamation-triangle class="size-3.5" />
                                            </span>
                                            <span class="text-[13px] font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                                {{ $pendingTotal }} action{{ $pendingTotal === 1 ? '' : 's' }} need review
                                            </span>
                                        </div>
                                        <span class="shrink-0 text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500" x-text="(idx + 1) + ' / ' + total"></span>
                                    </div>
                                    <p class="mt-1 text-[11.5px] text-zinc-500 dark:text-zinc-400">
                                        Decide each one, or apply to all {{ $sessionPendingTotal }} pending across this chat. The agent resumes once every pending call is decided.
                                        <span class="tabular-nums">({{ $decidedCount }}/{{ $pendingTotal }} decided here)</span>
                                    </p>
                                    @if ($awaitingApproval)
                                        <div class="mt-2.5 flex items-center gap-2">
                                            <flux:button
                                                wire:click="approveAllToolCalls"
                                                variant="primary"
                                                size="sm"
                                                class="rounded-lg text-xs flex-1"
                                                wire:loading.attr="disabled"
                                            >
                                                Approve All
                                            </flux:button>
                                            <flux:button
                                                wire:click="rejectAllToolCalls"
                                                variant="subtle"
                                                size="sm"
                                                class="rounded-lg text-xs flex-1 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                                wire:loading.attr="disabled"
                                            >
                                                Reject All
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
                                        <div class="min-w-full snap-center p-3.5" wire:key="approval-slide-{{ $callId }}">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[13px] font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ Str::headline($toolName) }}
                                                </span>
                                            </div>

                                            @if (! empty($call['reason'] ?? null))
                                                <p class="mt-1.5 text-[12px] text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                                    {{ $call['reason'] }}
                                                </p>
                                            @endif

                                            <div class="mt-2.5 flex flex-col gap-1">
                                                @foreach ($args as $key => $value)
                                                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/60 px-2.5 py-1.5">
                                                        <span class="block text-[10.5px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                                            {{ $key }}
                                                        </span>
                                                        @if ($key === 'entry_point_content')
                                                            <pre class="mt-0.5 text-[11px] font-mono text-zinc-600 dark:text-zinc-300 leading-relaxed line-clamp-6 whitespace-pre-wrap break-words">{{ $value }}</pre>
                                                        @else
                                                            <span class="text-[11.5px] font-mono text-zinc-700 dark:text-zinc-300 break-words">
                                                                {{ is_string($value) ? $value : json_encode($value) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if ($awaitingApproval)
                                                @if ($decision)
                                                    <div class="mt-3">
                                                        <span class="inline-flex items-center gap-1.5 text-[11.5px] font-medium px-2.5 py-1 rounded-full {{ $decision === 'approve' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400' }}">
                                                            <span class="size-1.5 rounded-full {{ $decision === 'approve' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                                            {{ $decision === 'approve' ? 'Approved' : 'Rejected' }} — waiting for the rest
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2 mt-3">
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
                                                            class="rounded-lg text-xs flex-1 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
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
                                    <div class="flex items-center justify-between px-3.5 pb-3.5">
                                        <button
                                            type="button"
                                            x-on:click="idx = (idx - 1 + total) % total"
                                            class="inline-flex size-7 items-center justify-center rounded-lg border border-zinc-200/70 dark:border-zinc-800/70 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition"
                                            aria-label="Previous approval"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                        </button>
                                        <div class="flex items-center gap-1.5">
                                            @foreach ($pendingTools as $slideIndex => $call)
                                                <button
                                                    type="button"
                                                    x-on:click="idx = {{ $slideIndex }}"
                                                    class="h-1.5 rounded-full transition-all"
                                                    :class="idx === {{ $slideIndex }} ? 'w-5 bg-zinc-900 dark:bg-zinc-100' : 'w-1.5 bg-zinc-300 dark:bg-zinc-700'"
                                                    aria-label="Go to approval {{ $slideIndex + 1 }}"
                                                ></button>
                                            @endforeach
                                        </div>
                                        <button
                                            type="button"
                                            x-on:click="idx = (idx + 1) % total"
                                            class="inline-flex size-7 items-center justify-center rounded-lg border border-zinc-200/70 dark:border-zinc-800/70 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition"
                                            aria-label="Next approval"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Tool Call Group (reasoning trace) — now shown BEFORE the markdown response --}}
                @if (! empty($message['tool_calls']) && ! $message['is_approval_pause'])
                    <div
                        x-data="{
                            open: false,
                            openRows: {},
                            toggleRow(id) { this.openRows[id] = !this.openRows[id] }
                        }"
                        class="flex justify-start"
                    >
                        <div class="max-w-[85%] w-full">
                            {{-- collapsed run header --}}
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="-mx-1.5 flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-[12px] text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition-colors"
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                                    class="transition-transform duration-200"
                                    :style="open ? 'transform: rotate(0deg)' : 'transform: rotate(-90deg)'">
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                                <span class="tabular-nums">
                                    {{ count($message['tool_calls']) }} tool {{ Str::plural('call', count($message['tool_calls'])) }}
                                </span>
                            </button>

                            {{-- rows --}}
                            <div class="grid transition-[grid-template-rows,opacity] duration-300"
                                :style="open ? 'grid-template-rows:1fr;opacity:1' : 'grid-template-rows:0fr;opacity:0'">
                                <div class="overflow-hidden">
                                    <div class="mt-1.5 flex flex-col gap-1">
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

                                                $icon = match (true) {
                                                    str_contains($toolName, 'write') || str_contains($toolName, 'create') => 'write',
                                                    str_contains($toolName, 'run') || str_contains($toolName, 'exec') => 'run',
                                                    str_contains($toolName, 'read') || str_contains($toolName, 'scan') => 'read',
                                                    default => 'think',
                                                };
                                            @endphp

                                            <div>
                                                <button
                                                    type="button"
                                                    x-on:click="toggleRow('{{ $callId }}')"
                                                    class="group/row -mx-[3px] flex h-7 w-[calc(100%+6px)] min-w-0 items-center gap-2 rounded-lg px-[3px] text-left hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition-colors"
                                                >
                                                    <span class="relative flex size-4 shrink-0 items-center justify-center text-zinc-400">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                            class="transition-opacity duration-100 group-hover/row:opacity-0"
                                                            :class="openRows['{{ $callId }}'] ? 'opacity-0' : ''">
                                                            @if ($icon === 'write')
                                                                <path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z" />
                                                            @elseif ($icon === 'run')
                                                                <path d="M4 17l6-5-6-5M12 19h8" />
                                                            @elseif ($icon === 'read')
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" />
                                                            @else
                                                                <path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z" fill="currentColor" />
                                                            @endif
                                                        </svg>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                                                            class="absolute transition-opacity duration-150 group-hover/row:opacity-100"
                                                            :class="openRows['{{ $callId }}'] ? 'opacity-100 rotate-0' : 'opacity-0 -rotate-90'">
                                                            <path d="M6 9l6 6 6-6" />
                                                        </svg>
                                                    </span>
                                                    <span class="shrink-0 text-[12.5px] font-medium text-zinc-800 dark:text-zinc-200">
                                                        {{ Str::headline($toolName) }}
                                                    </span>
                                                    <span class="inline-flex h-5.5 min-w-0 flex-1 items-center truncate rounded-md bg-zinc-100 dark:bg-zinc-800/80 px-1.5 text-[11px] font-mono text-[#43464c] dark:text-zinc-400">
                                                        {{ Str::limit(collect($args)->map(fn ($v, $k) => "$k: " . (is_string($v) ? $v : json_encode($v)))->implode(', '), 60) ?: 'no arguments' }}
                                                    </span>
                                                </button>

                                                {{-- expanded detail (args + result, no black background) --}}
                                                <div class="grid transition-[grid-template-rows,opacity] duration-300"
                                                    :style="openRows['{{ $callId }}'] ? 'grid-template-rows:1fr;opacity:1' : 'grid-template-rows:0fr;opacity:0'">
                                                    <div class="overflow-hidden">
                                                        <div class="mt-0.5 mb-1 ml-2 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-800 py-0.5 pl-3.5">
                                                            @foreach ($args as $key => $value)
                                                                <span class="text-[11.5px] font-mono text-zinc-500 dark:text-zinc-400 leading-relaxed break-words">
                                                                    <span class="text-indigo-500">{{ $key }}:</span>
                                                                    {{ is_string($value) ? Str::limit($value, 200) : json_encode($value) }}
                                                                </span>
                                                            @endforeach
                                                            @if ($resultContent)
                                                                <span class="text-[11.5px] font-mono text-emerald-600 dark:text-emerald-400 leading-relaxed break-words mt-0.5">
                                                                    → {{ Str::limit($resultContent, 300) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Markdown Response — now shown AFTER the tool-call trace --}}
                @if (! empty($message['content']))
                    <div class="flex flex-col items-start gap-0.5">
                        <div class="max-w-[85%] rounded-2xl rounded-tl-xs px-4 py-3 bg-zinc-100 dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 border border-zinc-200/60 dark:border-zinc-800/60 shadow-xs">
                            <div class="prose prose-sm dark:prose-invert max-w-none text-xs sm:text-sm leading-relaxed prose-pre:my-2 prose-pre:p-3 prose-pre:bg-zinc-950 prose-pre:border prose-pre:border-zinc-800 prose-code:text-indigo-400 prose-code:font-mono prose-code:text-[11px] break-words">
                                {!! \Illuminate\Support\Str::markdown($message['content']) !!}
                            </div>
                        </div>
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-600 pl-1">{{ \Carbon\Carbon::parse($message['created_at'])->format('g:i A') }}</span>
                    </div>
                @endif
            @endif
        @endforeach

        {{-- Live stream + loading / thinking --}}
        <div
            x-data="chatStream(@js('test.'.$test->id))"
            @agent-approval-requested.window="reset(); $wire.reloadMessages(); setTimeout(() => $wire.reloadMessages(), 1200)"
            @agent-done.window="reset(); $wire.reloadMessages(); setTimeout(() => $wire.reloadMessages(), 1200)"
            @agent-error.window="reset(); $wire.reloadError($event.detail)"
        >
            {{-- Live agent bubble (streamed reasoning / text / tool activity) --}}
            <template x-if="streaming || thinking || liveText || toolCount > 0 || approvalPending">
                <div class="flex justify-start">
                    <div class="max-w-[85%] rounded-2xl rounded-tl-xs px-4 py-3 bg-zinc-100 dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 border border-zinc-200/60 dark:border-zinc-800/60 shadow-xs">
                        <div x-show="approvalPending && !liveText && toolCount === 0" class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
                            <span class="size-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Approval needed — loading details…</span>
                        </div>
                        <div x-show="thinking && !liveText && toolCount === 0 && !approvalPending" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="size-2 rounded-full bg-indigo-400 animate-pulse"></span>
                            <span>Thinking…</span>
                        </div>

                        <div x-show="liveText" x-text="liveText" class="whitespace-pre-wrap break-words leading-relaxed text-xs sm:text-sm"></div>

                        <div x-show="toolCount > 0" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-zinc-200/60 dark:bg-zinc-800 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:text-zinc-300">
                            <span class="size-3 animate-spin rounded-full border-2 border-zinc-400 border-t-transparent"></span>
                            <span x-text="toolCount + ' tool call' + (toolCount === 1 ? '' : 's')"></span>
                        </div>

                        <div x-show="!thinking && !liveText && toolCount === 0" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="size-3 animate-spin rounded-full border-2 border-zinc-400 border-t-transparent"></span>
                            <span>Working…</span>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Fallback loading state shown before the first stream event --}}
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
                <div class="max-w-[85%] rounded-xl px-3.5 py-2.5 text-xs bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 flex items-start gap-2">
                    <flux:icon.exclamation-circle class="size-4 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Execution Error</p>
                        <p class="mt-0.5 text-rose-600/90 dark:text-rose-300/90">{{ $error }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Fixed Bottom Textarea Input Container --}}
    <div class="shrink-0 p-3 sm:p-4 bg-gradient-to-t from-white via-white/95 dark:from-zinc-950 dark:via-zinc-950/95 to-transparent pt-4 z-20">
        <div class="max-w-3xl mx-auto rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl shadow-xl shadow-zinc-900/5 p-2 flex flex-col gap-2">
            <textarea
                wire:model="input"
                wire:keydown.enter="submitMessage"
                rows="2"
                placeholder="Ask assistant to generate scripts, configure tests..."
                class="w-full bg-transparent text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 resize-none focus:outline-none px-2 py-1 leading-relaxed"
            ></textarea>

            <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/60 px-1">
                <div class="flex items-center gap-1.5 min-w-0">
                    <flux:dropdown position="top" align="start">
                        <flux:button variant="subtle" size="sm" icon-trailing="chevron-down" class="!px-2 !py-1 !text-[11px] !rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 text-zinc-600 dark:text-zinc-400">
                            {{ $selectedProvider ? ($availableProviders[$selectedProvider] ?? $selectedProvider) : 'Provider' }}
                        </flux:button>
                        <flux:menu class="max-h-48 overflow-y-auto">
                            <flux:menu.radio.group wire:model.live="selectedProvider">
                                <flux:menu.radio value="">Default Provider</flux:menu.radio>
                                @foreach ($availableProviders as $key => $label)
                                    <flux:menu.radio value="{{ $key }}">{{ $label }}</flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:dropdown position="top" align="start">
                        <flux:button variant="subtle" size="sm" icon-trailing="chevron-down" class="!px-2 !py-1 !text-[11px] !rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 text-zinc-600 dark:text-zinc-400">
                            {{ $selectedModel ?: 'Model' }}
                        </flux:button>
                        <flux:menu class="max-h-48 overflow-y-auto">
                            <flux:menu.radio.group wire:model.live="selectedModel">
                                <flux:menu.radio value="">Default Model</flux:menu.radio>
                                @foreach ($availableModels as $model)
                                    <flux:menu.radio value="{{ $model }}">{{ $model }}</flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <button
                    wire:click="submitMessage"
                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:opacity-90 transition shadow-xs disabled:opacity-40"
                    wire:loading.attr="disabled"
                >
                    <flux:icon.arrow-up class="size-4" />
                </button>
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

        // Re-scroll once the modal is actually visible/sized —
        // covers the case where the container had 0 scrollHeight
        // at script-execution time because the modal was still hidden.
        const modal = container.closest('[data-flux-modal], dialog, .modal');
        if (modal) {
            // Flux modals fire this on open; adjust the event name if yours differs.
            modal.addEventListener('flux:show', () => {
                stickToBottom = true;
                scrollToBottom();
                requestAnimationFrame(() => scrollToBottom());
            });

            // Fallback: also watch for the modal's own visibility/display changes,
            // in case it doesn't dispatch a custom event.
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
