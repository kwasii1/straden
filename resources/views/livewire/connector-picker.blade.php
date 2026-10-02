<div>
    <flux:field>
        <flux:label>Connectors</flux:label>

        @if (empty($options))
            <flux:description>No connectors available.</flux:description>
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
                    x-bind:aria-expanded="open"
                    aria-haspopup="listbox"
                    class="flex h-10 w-full items-center gap-2 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-left text-sm text-zinc-700 shadow-xs transition-[border-color,box-shadow] duration-150 hover:border-zinc-300 aria-expanded:border-zinc-400 aria-expanded:ring-3 aria-expanded:ring-zinc-900/8"
                >
                    <flux:icon.circle-stack variant="mini" class="size-4 shrink-0 text-zinc-400" />
                    <span
                        class="min-w-0 flex-1 truncate"
                        x-bind:class="selectedCount === 0 ? 'text-zinc-400' : ''"
                        x-text="selectedCount === 0 ? 'Select connectors…' : selectedCount + ' selected'"
                    ></span>
                    <flux:icon.chevron-up-down variant="mini" class="size-4 shrink-0 text-zinc-400" />
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-on:click.outside="open = false"
                    x-transition:enter="transition ease-snappy duration-150"
                    x-transition:enter-start="opacity-0 scale-[0.97]"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-out duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-bind:class="up ? 'bottom-full mb-1 origin-bottom' : 'mt-1 origin-top'"
                    class="absolute right-0 z-50 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-md shadow-zinc-900/5"
                >
                    <div class="flex items-center gap-2 border-b border-zinc-200 px-3">
                        <flux:icon.magnifying-glass variant="mini" class="size-4 shrink-0 text-zinc-400" />
                        <input
                            x-ref="search"
                            x-model="search"
                            type="text"
                            placeholder="Search connectors…"
                            autocomplete="off"
                            class="h-10 w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder:text-zinc-400 focus:ring-0 focus:outline-none"
                        />
                    </div>
                    <ul x-bind:style="`max-height: ${listMax}px`" class="overflow-y-auto p-1">
                        <template x-for="option in filtered" :key="option.id">
                            <li>
                                <button
                                    type="button"
                                    x-on:click="toggle(option.id)"
                                    x-bind:aria-pressed="isSelected(option.id)"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-zinc-800 transition-colors duration-100 hover:bg-zinc-100"
                                >
                                    <span class="flex min-w-0 flex-1 items-center gap-2">
                                        <span x-text="option.name" class="truncate"></span>
                                        <span x-text="option.type" class="shrink-0 text-xs text-zinc-500"></span>
                                    </span>
                                    <flux:icon.check x-show="isSelected(option.id)" variant="micro" class="size-4 shrink-0 text-zinc-900" />
                                </button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-2 py-3 text-center text-sm text-zinc-500">
                            No connectors match.
                        </li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Always-on InfluxDB + selected chips (server-rendered from Livewire state) --}}
        @if ($influxConnectorId || ! empty($this->selectedOptions()))
            <div class="flex flex-wrap gap-1.5">
                @if ($influxConnectorId)
                    <span class="inline-flex h-6 items-center gap-1 rounded-md bg-zinc-50 px-2 text-xs text-zinc-700 ring-1 ring-zinc-200 ring-inset" title="Always attached to this test">
                        <flux:icon.lock-closed variant="micro" class="size-3 text-zinc-400" />
                        {{ $influxConnectorName ?? 'InfluxDB' }}
                        <span class="text-zinc-500">always on</span>
                    </span>
                @endif

                @foreach ($this->selectedOptions() as $option)
                    <span
                        wire:key="connector-pill-{{ $option['id'] }}"
                        class="inline-flex h-6 items-center gap-1 rounded-md bg-zinc-100 pr-0.5 pl-2 text-xs text-zinc-700 ring-1 ring-zinc-200 ring-inset"
                    >
                        {{ $option['name'] }}
                        <button
                            type="button"
                            wire:click="remove('{{ $option['id'] }}')"
                            class="inline-flex size-5 items-center justify-center rounded text-zinc-400 transition-colors duration-100 hover:bg-zinc-200 hover:text-zinc-700"
                            aria-label="Remove {{ $option['name'] }}"
                        >
                            <flux:icon.x-mark variant="micro" class="size-3" />
                        </button>
                    </span>
                @endforeach
            </div>
        @endif
    </flux:field>
</div>
