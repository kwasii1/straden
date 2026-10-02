<div
    class="flex flex-col h-screen max-h-screen bg-white text-zinc-900 overflow-hidden relative"
    x-data="{ pendingMessage: '' }"
    @chat-optimistic-sent.window="pendingMessage = ''"
>
    @php
        $markdownClasses = 'min-w-0 break-words text-sm leading-6 text-zinc-800 [&>*:first-child]:mt-0 [&>*:last-child]:mb-0 [&_p]:my-3 [&_h1]:mt-5 [&_h1]:mb-2 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:text-zinc-900 [&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:text-sm [&_h2]:font-semibold [&_h2]:text-zinc-900 [&_h3]:mt-4 [&_h3]:mb-1.5 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-zinc-900 [&_h4]:mt-4 [&_h4]:mb-1 [&_h4]:font-medium [&_h4]:text-zinc-900 [&_strong]:font-semibold [&_strong]:text-zinc-900 [&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-1 [&_li]:pl-0.5 [&_li::marker]:text-zinc-400 [&_li>ul]:my-1 [&_li>ol]:my-1 [&_a]:font-medium [&_a]:text-zinc-900 [&_a]:underline [&_a]:decoration-zinc-300 [&_a]:underline-offset-[3px] [&_a]:hover:decoration-zinc-900 [&_blockquote]:my-3 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-200 [&_blockquote]:pl-3 [&_blockquote]:text-zinc-600 [&_hr]:my-5 [&_hr]:border-zinc-200 [&_code]:rounded-md [&_code]:bg-zinc-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.8125em] [&_code]:text-zinc-800 [&_pre]:my-3 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-zinc-50 [&_pre]:p-3 [&_pre]:font-mono [&_pre]:text-[13px] [&_pre]:leading-5 [&_pre]:ring-1 [&_pre]:ring-zinc-200/70 [&_pre]:ring-inset [&_pre_code]:rounded-none [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-[13px] [&_table]:my-3 [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto [&_table]:text-[13px] [&_th]:border-b [&_th]:border-zinc-200 [&_th]:px-2.5 [&_th]:py-1.5 [&_th]:text-left [&_th]:text-xs [&_th]:font-medium [&_th]:text-zinc-500 [&_td]:border-b [&_td]:border-zinc-100 [&_td]:px-2.5 [&_td]:py-1.5 [&_td]:tabular-nums';
    @endphp

    {{-- Header --}}
    <div class="z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-zinc-200 bg-white px-5">
        <h2 class="truncate text-sm font-medium text-zinc-900">Straden agent</h2>

        <div class="flex items-center gap-0.5">
            {{-- New Chat --}}
            <flux:tooltip content="New chat">
                <flux:button
                    wire:click="newConversation"
                    variant="ghost"
                    size="sm"
                    icon="plus"
                    square
                    aria-label="New chat"
                    class="text-zinc-500!"
                />
            </flux:tooltip>

            {{-- History --}}
            @if (! empty($conversations))
                <flux:dropdown position="bottom" align="end">
                    <flux:tooltip content="History">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="clock"
                            square
                            aria-label="Conversation history"
                            class="text-zinc-500!"
                        />
                    </flux:tooltip>
                    <flux:menu class="ui-scroll-thin max-h-72 w-72 overflow-y-auto">
                        @foreach ($conversations as $convo)
                            <div wire:key="convo-{{ $convo['id'] }}" @class(['group flex items-center gap-1 rounded-md pe-1 hover:bg-zinc-100', 'bg-zinc-100' => $conversationId === $convo['id']])>
                                <button
                                    type="button"
                                    wire:click="$set('conversationId', @js($convo['id']))"
                                    class="flex min-w-0 flex-1 flex-col items-start px-2 py-1.5 text-left"
                                >
                                    <span @class(['w-full truncate text-sm', 'font-medium text-zinc-900' => $conversationId === $convo['id'], 'text-zinc-700' => $conversationId !== $convo['id']])>{{ $convo['title'] }}</span>
                                    <span class="text-xs text-zinc-500">{{ $convo['created_at'] }}</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteConversation(@js($convo['id']))"
                                    wire:confirm="Delete this conversation? This can't be undone."
                                    class="ui-icon-button size-6 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 hover:text-red-600"
                                    aria-label="Delete conversation"
                                    title="Delete conversation"
                                >
                                    <flux:icon.trash variant="micro" />
                                </button>
                            </div>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" square aria-label="Close" class="text-zinc-500!" />
            </flux:modal.close>
        </div>
    </div>

    {{-- Chat Messages Scroll Area --}}
    <div
        id="chat-messages"
        class="flex-1 min-h-0 overflow-y-auto px-5 pt-6 pb-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            {{-- Empty State --}}
            @if (empty($displayMessages) && ! $isProcessing)
                <div class="mx-auto flex min-h-[50vh] w-full max-w-sm flex-col items-center justify-center text-center">
                    <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                        <flux:icon.command-line class="size-4.5" />
                    </div>
                    <p class="text-sm font-medium text-zinc-900">Build and run load tests</p>
                    <p class="mt-1 text-sm text-zinc-500">
                        Describe a test scenario. I'll check your endpoints, connectors and existing scripts, then propose a plan.
                    </p>

                    <div class="mt-6 flex w-full flex-col gap-2">
                        <button
                            type="button"
                            x-on:click="pendingMessage = 'Analyze my target endpoints and suggest a 30s load test scenario.'; $wire.set('input', 'Analyze my target endpoints and suggest a 30s load test scenario.'); $wire.submitMessage()"
                            class="ui-pressable w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-left text-sm text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50"
                        >
                            Suggest a load test for my target endpoints
                        </button>
                        <button
                            type="button"
                            x-on:click="pendingMessage = 'Generate a k6 script targeting the primary API connectors.'; $wire.set('input', 'Generate a k6 script targeting the primary API connectors.'); $wire.submitMessage()"
                            class="ui-pressable w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-left text-sm text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50"
                        >
                            Generate a k6 script for my API connectors
                        </button>
                    </div>
                </div>
            @endif

            {{-- Messages --}}
            @foreach ($displayMessages as $message)
                @if ($message['role'] === 'user')
                    <div class="flex flex-col items-end gap-1">
                        <div class="max-w-[85%] rounded-xl bg-zinc-100 px-3.5 py-2.5 text-sm leading-6 text-zinc-900 whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                        <span class="pe-1 text-xs text-zinc-400 tabular-nums">{{ \Carbon\Carbon::parse($message['created_at'])->format('g:i A') }}</span>
                    </div>
                @elseif ($message['role'] === 'assistant' && ($message['is_approval_pause'] || ! empty($message['tool_calls']) || ! empty($message['content'])))
                    <div class="flex flex-col gap-3">
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
                            <div wire:key="approval-{{ md5(json_encode($message['pending_call_ids'] ?? [])) }}">
                                <section
                                    class="ui-panel w-full overflow-hidden animate-enter"
                                    x-data="{ idx: 0, total: {{ $pendingTotal }} }"
                                    x-effect="$refs.track?.scrollTo({ left: idx * $refs.track.clientWidth, behavior: 'smooth' })"
                                >
                                    {{-- header --}}
                                    <header class="border-b border-zinc-200 px-4 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <flux:icon.exclamation-triangle variant="micro" class="size-4 shrink-0 text-amber-600" />
                                                <h3 class="truncate text-sm font-medium text-zinc-900">
                                                    {{ $pendingTotal }} {{ Str::plural('action', $pendingTotal) }} {{ $pendingTotal === 1 ? 'needs' : 'need' }} review
                                                </h3>
                                            </div>
                                            @if ($pendingTotal > 1)
                                                <span class="shrink-0 text-xs text-zinc-500 tabular-nums" x-text="(idx + 1) + ' / ' + total"></span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-zinc-500">
                                            Decide each one, or apply to all {{ $sessionPendingTotal }} pending in this chat.
                                            <span class="tabular-nums">{{ $decidedCount }} of {{ $pendingTotal }} decided.</span>
                                        </p>
                                    </header>

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
                                            <div class="min-w-full snap-center p-4" wire:key="approval-slide-{{ $callId }}">
                                                <p class="text-sm font-medium text-zinc-900">
                                                    {{ Str::headline($toolName) }}
                                                </p>

                                                @if (! empty($call['reason'] ?? null))
                                                    <p class="mt-1 text-sm text-zinc-500">
                                                        {{ $call['reason'] }}
                                                    </p>
                                                @endif

                                                @if (! empty($args))
                                                    <dl class="ui-inset mt-3 divide-y divide-zinc-200/70">
                                                        @foreach ($args as $key => $value)
                                                            <div class="px-3 py-2">
                                                                <dt class="font-mono text-xs text-zinc-500">{{ $key }}</dt>
                                                                <dd class="mt-0.5">
                                                                    @if ($key === 'entry_point_content')
                                                                        <pre class="font-mono text-xs leading-5 text-zinc-700 line-clamp-6 whitespace-pre-wrap break-words">{{ $value }}</pre>
                                                                    @else
                                                                        <span class="font-mono text-xs leading-5 text-zinc-700 break-words">{{ is_string($value) ? $value : json_encode($value) }}</span>
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                @endif

                                                @if ($awaitingApproval)
                                                    @if ($decision)
                                                        <div class="mt-3">
                                                            <x-status-badge
                                                                :status="$decision === 'approve' ? 'success' : 'failed'"
                                                                :label="($decision === 'approve' ? 'Approved' : 'Rejected').', waiting for the rest'"
                                                            />
                                                        </div>
                                                    @else
                                                        <div class="mt-3 flex items-center gap-2">
                                                            <flux:button
                                                                wire:click="approveToolCall('{{ $callId }}')"
                                                                variant="primary"
                                                                size="sm"
                                                                wire:loading.attr="disabled"
                                                            >
                                                                Approve
                                                            </flux:button>
                                                            <flux:button
                                                                wire:click="rejectToolCall('{{ $callId }}')"
                                                                size="sm"
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

                                    {{-- carousel nav + bulk actions --}}
                                    @if ($pendingTotal > 1 || $awaitingApproval)
                                        <footer class="ui-panel-footer">
                                            <div class="flex items-center gap-1">
                                                @if ($pendingTotal > 1)
                                                    <button
                                                        type="button"
                                                        x-on:click="idx = (idx - 1 + total) % total"
                                                        class="ui-icon-button"
                                                        aria-label="Previous approval"
                                                    >
                                                        <flux:icon.chevron-left variant="micro" class="size-4" />
                                                    </button>
                                                    <div class="flex items-center gap-1.5 px-1">
                                                        @foreach ($pendingTools as $slideIndex => $call)
                                                            <button
                                                                type="button"
                                                                x-on:click="idx = {{ $slideIndex }}"
                                                                class="h-1.5 rounded-full transition-[width,background-color] duration-200 ease-snappy"
                                                                :class="idx === {{ $slideIndex }} ? 'w-4 bg-zinc-900' : 'w-1.5 bg-zinc-300'"
                                                                aria-label="Go to approval {{ $slideIndex + 1 }}"
                                                            ></button>
                                                        @endforeach
                                                    </div>
                                                    <button
                                                        type="button"
                                                        x-on:click="idx = (idx + 1) % total"
                                                        class="ui-icon-button"
                                                        aria-label="Next approval"
                                                    >
                                                        <flux:icon.chevron-right variant="micro" class="size-4" />
                                                    </button>
                                                @endif
                                            </div>

                                            @if ($awaitingApproval)
                                                <div class="flex items-center gap-2">
                                                    <flux:button
                                                        wire:click="rejectAllToolCalls"
                                                        variant="ghost"
                                                        size="sm"
                                                        wire:loading.attr="disabled"
                                                    >
                                                        Reject all
                                                    </flux:button>
                                                    <flux:button
                                                        wire:click="approveAllToolCalls"
                                                        size="sm"
                                                        wire:loading.attr="disabled"
                                                    >
                                                        Approve all
                                                    </flux:button>
                                                </div>
                                            @endif
                                        </footer>
                                    @endif
                                </section>
                            </div>
                        @endif

                        {{--
                            Inline tool-call + response flow.

                            All tool calls for this turn are tucked under a single
                            collapsible "N tool calls" row (closed by default), so
                            a 20-call turn doesn't dump 20 open rows into the
                            transcript. Expanding it reveals one line per call with
                            its status; each line expands again for args/result.
                            The response text follows right after, so the turn
                            reads top to bottom: [N tool calls] then the answer.
                        --}}
                        @if (! empty($message['tool_calls']) && ! $message['is_approval_pause'])
                            @php $toolCallCount = count($message['tool_calls']); @endphp
                            <div x-data="{ groupOpen: false, openRows: {} }">
                                <button
                                    type="button"
                                    x-on:click="groupOpen = !groupOpen"
                                    x-bind:aria-expanded="groupOpen"
                                    class="-mx-1.5 inline-flex items-center gap-1.5 rounded-md px-1.5 py-1 text-xs text-zinc-500 transition-colors duration-150 hover:bg-zinc-50 hover:text-zinc-800"
                                >
                                    <flux:icon.wrench variant="micro" class="size-3.5 text-zinc-400" />
                                    <span class="tabular-nums">{{ $toolCallCount }} tool {{ Str::plural('call', $toolCallCount) }}</span>
                                    <flux:icon.chevron-right
                                        variant="micro"
                                        class="size-3.5 text-zinc-400 transition-transform duration-150 ease-snappy"
                                        x-bind:class="groupOpen ? 'rotate-90' : ''"
                                    />
                                </button>

                                <div x-show="groupOpen" x-collapse>
                                    <div class="mt-1 flex flex-col border-l border-zinc-200 pl-2.5 ms-[0.4rem]">
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
                                                $resultFailed = is_string($resultContent) && preg_match('/^\s*(error|failed|exception)\b/i', $resultContent) === 1;

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
                                                    default => 'wrench',
                                                };
                                            @endphp

                                            <div>
                                                <button
                                                    type="button"
                                                    x-on:click="openRows['{{ $callId }}'] = !openRows['{{ $callId }}']"
                                                    class="group/row flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left text-xs transition-colors duration-150 hover:bg-zinc-50"
                                                >
                                                    @if ($resultFailed)
                                                        <flux:icon.x-mark variant="micro" class="size-3.5 shrink-0 text-red-600" />
                                                    @elseif ($resultContent)
                                                        <flux:icon.check variant="micro" class="size-3.5 shrink-0 text-emerald-600" />
                                                    @else
                                                        <flux:icon :name="$icon" variant="micro" class="size-3.5 shrink-0 text-zinc-400" />
                                                    @endif
                                                    <span class="shrink-0 font-medium text-zinc-700">
                                                        {{ Str::headline($toolName) }}
                                                    </span>
                                                    <span class="min-w-0 truncate font-mono text-zinc-400">
                                                        {{ Str::limit(collect($args)->map(fn ($v, $k) => "$k: " . (is_string($v) ? $v : json_encode($v)))->implode(', '), 50) }}
                                                    </span>
                                                    <flux:icon.chevron-right
                                                        variant="micro"
                                                        class="ms-auto size-3.5 shrink-0 text-zinc-300 transition-[color,transform] duration-150 ease-snappy group-hover/row:text-zinc-500"
                                                        x-bind:class="openRows['{{ $callId }}'] ? 'rotate-90' : ''"
                                                    />
                                                </button>

                                                <div x-show="openRows['{{ $callId }}']" x-collapse>
                                                    <div class="ui-inset mt-1 mb-2 ms-[1.375rem] flex flex-col gap-0.5 px-3 py-2 font-mono text-xs leading-5 text-zinc-600 break-words">
                                                        @foreach ($args as $key => $value)
                                                            <div>
                                                                <span class="text-zinc-400">{{ $key }}:</span>
                                                                {{ is_string($value) ? Str::limit($value, 200) : json_encode($value) }}
                                                            </div>
                                                        @endforeach
                                                        @if ($resultContent)
                                                            <div @class([
                                                                'whitespace-pre-wrap',
                                                                'mt-1.5 border-t border-zinc-200/70 pt-1.5' => ! empty($args),
                                                                'text-red-700' => $resultFailed,
                                                                'text-zinc-700' => ! $resultFailed,
                                                            ])>{{ Str::limit($resultContent, 300) }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Response --}}
                        @if (! empty($message['content']))
                            <div class="flex flex-col gap-1.5">
                                <div class="{{ $markdownClasses }}">
                                    {!! \Illuminate\Support\Str::markdown($message['content']) !!}
                                </div>
                                <span class="text-xs text-zinc-400 tabular-nums">{{ \Carbon\Carbon::parse($message['created_at'])->format('g:i A') }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach

            {{-- Instant client echo --}}
            <template x-if="pendingMessage">
                <div class="flex flex-col items-end gap-1 animate-enter">
                    <div class="max-w-[85%] rounded-xl bg-zinc-100 px-3.5 py-2.5 text-sm leading-6 text-zinc-900 whitespace-pre-wrap break-words" x-text="pendingMessage"></div>
                    <span class="pe-1 text-xs text-zinc-400">Sending…</span>
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
                    <div class="flex flex-col gap-3 animate-enter">
                        <div x-show="approvalPending && !liveText && toolCount === 0" class="flex items-center gap-2 text-xs text-zinc-500">
                            <flux:icon.hand-raised variant="micro" class="size-3.5 text-amber-600" />
                            <span>Waiting for your approval</span>
                        </div>
                        <div x-show="thinking && !liveText && toolCount === 0 && !approvalPending" class="flex items-center gap-2 text-xs text-zinc-500">
                            <x-spinner class="size-3.5 text-zinc-400" />
                            <span>Thinking</span>
                        </div>

                        <div x-show="toolCount > 0" class="flex items-center gap-2 text-xs text-zinc-500">
                            <x-spinner x-show="streaming" class="size-3.5 text-zinc-400" />
                            <flux:icon.hand-raised x-show="!streaming && approvalPending" variant="micro" class="size-3.5 text-amber-600" />
                            <flux:icon.check x-show="!streaming && !approvalPending" variant="micro" class="size-3.5 text-emerald-600" />
                            <span class="tabular-nums" x-text="toolCount + ' tool call' + (toolCount === 1 ? '' : 's')"></span>
                        </div>

                        <div x-show="liveText" x-text="liveText" class="whitespace-pre-wrap break-words text-sm leading-6 text-zinc-800"></div>

                        <div x-show="!thinking && !liveText && toolCount === 0 && !approvalPending" class="flex items-center gap-2 text-xs text-zinc-500">
                            <x-spinner class="size-3.5 text-zinc-400" />
                            <span>Working</span>
                        </div>
                    </div>
                </template>

                @if ($isProcessing)
                    <div class="flex justify-start" x-show="!streaming && !liveText && !toolCount && !thinking && !approvalPending">
                        @include('components.loading-state', [
                            'label' => $awaitingApproval ? 'Waiting for your approval' : 'Analyzing context and running steps',
                            'variant' => 'Drive'
                        ])
                    </div>
                @endif
            </div>

            {{-- Error Banner --}}
            @if ($error)
                <div class="flex items-start gap-2.5 rounded-lg bg-red-50 px-3 py-2.5 text-sm ring-1 ring-red-600/15 ring-inset animate-enter" role="alert">
                    <flux:icon.exclamation-circle variant="micro" class="mt-0.5 size-4 shrink-0 text-red-600" />
                    <div class="min-w-0">
                        <p class="font-medium text-red-800">Execution error</p>
                        <p class="mt-0.5 break-words text-red-700">{{ $error }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Composer --}}
    <div class="z-20 shrink-0 bg-white px-5 pt-2 pb-5">
        <div class="mx-auto max-w-3xl rounded-xl border border-zinc-200 bg-white shadow-xs transition-[border-color,box-shadow] duration-150 focus-within:border-zinc-400 focus-within:ring-3 focus-within:ring-zinc-900/8">
            <textarea
                x-ref="chatInput"
                wire:model="input"
                rows="2"
                placeholder="Ask the agent to generate scripts or configure tests…"
                aria-label="Message the agent"
                class="block max-h-60 w-full resize-none bg-transparent px-3.5 pt-3 pb-1 text-sm leading-6 text-zinc-900 placeholder:text-zinc-400 focus:outline-none"
                x-on:keydown.enter="
                    if ($event.shiftKey) { return; }
                    $event.preventDefault();
                    if ($event.target.value.trim() === '') { return; }
                    pendingMessage = $event.target.value.trim();
                    $nextTick(() => document.getElementById('chat-messages')?.scrollTo({ top: document.getElementById('chat-messages').scrollHeight }));
                    $wire.submitMessage();
                "
            ></textarea>

            <div class="flex items-center justify-between gap-2 px-2 pb-2">
                <div class="flex min-w-0 items-center gap-0.5">
                    <flux:dropdown position="top" align="start">
                        <flux:button variant="ghost" size="xs" icon-trailing="chevron-down" class="font-normal! text-zinc-600!">
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
                        <flux:button variant="ghost" size="xs" icon-trailing="chevron-down" class="min-w-0 font-normal! text-zinc-600!">
                            <span class="truncate">{{ $selectedModel ?: 'Model' }}</span>
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
                                    class="mb-1 h-8 w-full rounded-md border border-zinc-200 bg-white px-2.5 text-xs text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none"
                                />
                                <template x-for="model in results" :key="model">
                                    <button
                                        type="button"
                                        x-text="model"
                                        x-on:click="$wire.set('selectedModel', model); search = ''"
                                        class="block w-full truncate rounded-md px-2 py-1.5 text-start text-sm text-zinc-800 hover:bg-zinc-100"
                                    ></button>
                                </template>
                                <p x-show="search.trim() !== '' && results.length === 0" class="px-2 py-1.5 text-xs text-zinc-500">No matching models.</p>
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

                <flux:tooltip content="Send (Enter). Shift+Enter adds a new line.">
                    <button
                        wire:click="submitMessage"
                        x-on:click="const ta = $refs.chatInput; if (ta && ta.value.trim() !== '') { pendingMessage = ta.value.trim(); }"
                        class="ui-pressable inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white hover:bg-zinc-800 disabled:opacity-40"
                        aria-label="Send message"
                        wire:loading.attr="disabled"
                    >
                        <flux:icon.arrow-up variant="micro" class="size-4" />
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
