@props([
    'options' => [],
    'label' => null,
    'description' => null,
    'placeholder' => 'Select...',
    'searchPlaceholder' => 'Search...',
    'emptyText' => 'No results found.',
])

@php
    $model = $attributes->wire('model');
    $options = collect($options)->map(fn ($option) => [
        'value' => (string) $option['value'],
        'label' => (string) ($option['label'] ?? $option['value']),
        'hint' => $option['hint'] ?? null,
    ])->values();
@endphp

<div
    x-data="{
        open: false,
        up: false,
        listMax: 256,
        search: '',
        active: 0,
        selected: $wire.entangle(@js($model->value()), @js($model->hasModifier('live'))),
        options: @js($options),
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (q === '') return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(q) || (o.hint ?? '').toLowerCase().includes(q));
        },
        get chosen() {
            return (this.selected ?? []).map(v => this.options.find(o => o.value === v)).filter(Boolean);
        },
        isSelected(value) {
            return (this.selected ?? []).includes(value);
        },
        toggle(item) {
            if (! item) return;
            const current = [...(this.selected ?? [])];
            this.selected = this.isSelected(item.value)
                ? current.filter(v => v !== item.value)
                : [...current, item.value];
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
            this.active = 0;
            this.$nextTick(() => this.$refs.search.focus());
        },
        close(refocus = false) {
            this.open = false;
            if (refocus) this.$refs.trigger.focus();
        },
        move(step) {
            if (this.filtered.length === 0) return;
            this.active = (this.active + step + this.filtered.length) % this.filtered.length;
            this.$nextTick(() => this.$refs.list?.querySelector(`[data-index='${this.active}']`)?.scrollIntoView({ block: 'nearest' }));
        },
    }"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="close(true)"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative') }}
>
    @if ($label)
        <flux:label class="mb-2">{{ $label }}</flux:label>
    @endif

    <button
        type="button"
        x-ref="trigger"
        x-on:click="open ? close() : show()"
        x-on:keydown.down.prevent="show()"
        aria-haspopup="listbox"
        x-bind:aria-expanded="open"
        class="flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-2 py-1.5 text-start text-sm shadow-xs dark:border-white/10 dark:bg-white/10"
    >
        <template x-for="item in chosen" :key="item.value">
            <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                <span x-text="item.label"></span>
                <span role="button" tabindex="-1" x-on:click.stop="toggle(item)" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-white" aria-label="Remove">&times;</span>
            </span>
        </template>
        <span x-show="chosen.length === 0" class="px-1 text-zinc-400">{{ $placeholder }}</span>
        <flux:icon.chevron-up-down variant="mini" class="ms-auto size-4 shrink-0 text-zinc-400" />
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
                x-on:keydown.enter.prevent="toggle(filtered[active])"
                x-on:keydown.tab="close()"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
                class="h-10 w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder-zinc-400 focus:ring-0 focus:outline-none dark:text-zinc-100"
            />
        </div>

        <ul x-ref="list" role="listbox" aria-multiselectable="true" x-bind:style="`max-height: ${listMax}px`" class="overflow-y-auto p-1">
            <template x-for="(item, index) in filtered" :key="item.value">
                <li
                    role="option"
                    x-bind:data-index="index"
                    x-bind:aria-selected="isSelected(item.value)"
                    x-on:click="toggle(item)"
                    x-on:mousemove="active = index"
                    x-bind:class="active === index ? 'bg-zinc-100 dark:bg-zinc-600' : ''"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-800 dark:text-zinc-100"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span x-text="item.label" class="truncate"></span>
                        <span x-show="item.hint" x-text="item.hint" class="shrink-0 text-xs text-zinc-400"></span>
                    </span>
                    <flux:icon.check x-show="isSelected(item.value)" variant="micro" class="size-4 shrink-0 text-zinc-600 dark:text-zinc-300" />
                </li>
            </template>

            <li x-show="filtered.length === 0" class="px-2 py-3 text-center text-sm text-zinc-400">{{ $emptyText }}</li>
        </ul>
    </div>

    @if ($description)
        <flux:description class="mt-2 text-xs">{{ $description }}</flux:description>
    @endif
</div>
