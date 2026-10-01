<div>
    <flux:field>
        <flux:label>Connectors</flux:label>

        @if (empty($options))
            <flux:description class="mt-1">No connectors available</flux:description>
        @else
            <div
                x-data="{
                    open: false,
                    up: false,
                    listMax: 256,
                    search: '',
                    options: @js($options),
                    selected: @js($selected),
                    get filtered() {
                        const q = this.search.trim().toLowerCase();
                        if (q === '') return this.options;
                        return this.options.filter((o) => o.name.toLowerCase().includes(q) || o.type.toLowerCase().includes(q));
                    },
                    place() {
                        const rect = $refs.trigger.getBoundingClientRect();
                        const below = window.innerHeight - rect.bottom - 16;
                        const above = rect.top - 16;
                        this.up = below < 320 && above > below;
                        this.listMax = Math.max(120, Math.min(256, (this.up ? above : below) - 64));
                    },
                    get selectedCount() { return this.selected.length; },
                    isSelected(id) { return this.selected.includes(id); },
                    toggle(id) {
                        $wire.toggle(id).then(() => {
                            this.selected = $wire.selected;
                        });
                    },
                }"
                @keydown.escape.window="open = false"
                class="relative"
            >
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click="if (! open) place(); open = !open; if (open) $nextTick(() => $refs.search.focus())"
                    class="flex h-10 w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-left text-sm text-zinc-900 shadow-xs transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                >
                    <flux:icon.circle-stack class="size-4 shrink-0 text-zinc-400" />
                    <span class="min-w-0 flex-1 truncate" x-text="selectedCount === 0 ? 'Select connectors…' : selectedCount + ' selected'"></span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                        class="shrink-0 text-zinc-400 transition-transform duration-200"
                        :style="open ? 'transform: rotate(180deg)' : 'transform: rotate(0deg)'">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-on:click.outside="open = false"
                    x-bind:class="up ? 'bottom-full mb-1.5' : 'mt-1.5'"
                    class="absolute right-0 z-50 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="border-b border-zinc-100 p-2 dark:border-zinc-800">
                        <input
                            x-ref="search"
                            x-model="search"
                            type="text"
                            placeholder="Search connectors…"
                            class="w-full rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                        />
                    </div>
                    <ul x-bind:style="`max-height: ${listMax}px`" class="overflow-y-auto p-1">
                        <template x-for="option in filtered" :key="option.id">
                            <li>
                                <button
                                    type="button"
                                    x-on:click="toggle(option.id)"
                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    <span class="min-w-0 flex-1 truncate">
                                        <span x-text="option.name" class="font-medium"></span>
                                        <span x-text="'(' + option.type + ')'" class="text-zinc-400"></span>
                                    </span>
                                    <svg x-show="isSelected(option.id)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-emerald-500">
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-3 py-2 text-xs text-zinc-400">
                            No connectors match.
                        </li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Selected pills (server-rendered from Livewire state) --}}
        @if (! empty($this->selectedOptions()))
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($this->selectedOptions() as $option)
                    <span
                        wire:key="connector-pill-{{ $option['id'] }}"
                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-100 py-1 pr-1.5 pl-2.5 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                    >
                        {{ $option['name'] }}
                        <button
                            type="button"
                            wire:click="remove('{{ $option['id'] }}')"
                            class="inline-flex size-4 items-center justify-center rounded-full text-zinc-400 transition hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            aria-label="Remove {{ $option['name'] }}"
                        >
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endforeach
            </div>
        @endif

        @if ($influxConnectorId)
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 py-1 pr-2.5 pl-2.5 text-xs font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-400">
                    <flux:icon.check-circle class="size-3.5" />
                    {{ $influxConnectorName ?? 'InfluxDB' }} · always on
                </span>
            </div>
        @endif
    </flux:field>
</div>
