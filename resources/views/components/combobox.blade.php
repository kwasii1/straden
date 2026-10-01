@props([
    'options' => [],
    'placeholder' => 'Select an option...',
    'searchPlaceholder' => 'Search...',
    'emptyText' => 'No results found.',
    'allowCustom' => false,
    'disabled' => false,
])

@php
    $model = $attributes->wire('model');
    $options = collect($options)->map(fn ($option) => is_array($option)
        ? ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value']), 'hint' => $option['hint'] ?? null]
        : ['value' => (string) $option, 'label' => (string) $option, 'hint' => null]
    )->values();
@endphp

<div
    x-data="{
        open: false,
        up: false,
        listMax: 256,
        search: '',
        active: 0,
        value: $wire.entangle(@js($model->value()), @js($model->hasModifier('live'))),
        options: @js($options),
        allowCustom: @js((bool) $allowCustom),
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (q === '') return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(q) || o.value.toLowerCase().includes(q));
        },
        get showCustom() {
            const q = this.search.trim();
            return this.allowCustom && q !== '' && ! this.options.some(o => o.value.toLowerCase() === q.toLowerCase());
        },
        get items() {
            return this.showCustom
                ? [...this.filtered, { value: this.search.trim(), label: `Use &quot;${this.search.trim()}&quot;`, hint: null, custom: true }]
                : this.filtered;
        },
        get selectedLabel() {
            const match = this.options.find(o => o.value === this.value);
            return match ? match.label : (this.value || '');
        },
        toggle() {
            this.open ? this.close() : this.show();
        },
        place() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom - 16;
            const above = rect.top - 16;
            this.up = below < 320 && above > below;
            this.listMax = Math.max(120, Math.min(256, (this.up ? above : below) - 56));
        },
        show() {
            this.place();
            this.open = true;
            this.search = '';
            this.active = Math.max(0, this.items.findIndex(o => o.value === this.value));
            this.$nextTick(() => {
                this.$refs.search.focus();
                this.scrollToActive();
            });
        },
        close(refocus = false) {
            this.open = false;
            if (refocus) this.$refs.trigger.focus();
        },
        select(item) {
            if (! item) return;
            this.value = item.value;
            this.close(true);
        },
        move(step) {
            if (this.items.length === 0) return;
            this.active = (this.active + step + this.items.length) % this.items.length;
            this.scrollToActive();
        },
        scrollToActive() {
            this.$nextTick(() => this.$refs.list?.querySelector(`[data-index='${this.active}']`)?.scrollIntoView({ block: 'nearest' }));
        },
    }"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="close(true)"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative') }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="show()"
        @disabled($disabled)
        aria-haspopup="listbox"
        x-bind:aria-expanded="open"
        class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-start text-sm text-zinc-700 shadow-xs disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/10 dark:text-zinc-300"
    >
        <span x-show="selectedLabel" x-text="selectedLabel" class="truncate"></span>
        <span x-show="! selectedLabel" class="truncate text-zinc-400">{{ $placeholder }}</span>
        <flux:icon.chevron-up-down variant="mini" class="size-4 shrink-0 text-zinc-400" />
    </button>

    <div
        x-show="open"
        x-transition.opacity.duration.100ms
        x-cloak
        x-bind:class="up ? 'bottom-full mb-1' : 'mt-1'"
        class="absolute z-50 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
    >
        <div class="flex items-center gap-2 border-b border-zinc-200 px-3 dark:border-zinc-600">
            <flux:icon.magnifying-glass variant="mini" class="size-4 shrink-0 text-zinc-400" />
            <input
                type="text"
                x-ref="search"
                x-model="search"
                x-on:input="active = 0"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="select(items[active])"
                x-on:keydown.tab="close()"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
                class="h-10 w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder-zinc-400 focus:ring-0 focus:outline-none dark:text-zinc-100"
            />
        </div>

        <ul x-ref="list" role="listbox" x-bind:style="`max-height: ${listMax}px`" class="overflow-y-auto p-1">
            <template x-for="(item, index) in items" :key="item.value + (item.custom ? ':custom' : '')">
                <li
                    role="option"
                    x-bind:data-index="index"
                    x-bind:aria-selected="item.value === value"
                    x-on:click="select(item)"
                    x-on:mousemove="active = index"
                    x-bind:class="active === index ? 'bg-zinc-100 dark:bg-zinc-600' : ''"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-800 dark:text-zinc-100"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <flux:icon.plus x-show="item.custom" variant="micro" class="size-3.5 shrink-0 text-zinc-400" />
                        <span x-text="item.label" class="truncate"></span>
                        <span x-show="item.hint" x-text="item.hint" class="shrink-0 text-xs text-zinc-400"></span>
                    </span>
                    <flux:icon.check x-show="! item.custom && item.value === value" variant="micro" class="size-4 shrink-0 text-zinc-600 dark:text-zinc-300" />
                </li>
            </template>

            <li x-show="items.length === 0" class="px-2 py-3 text-center text-sm text-zinc-400">{{ $emptyText }}</li>
        </ul>
    </div>
</div>
