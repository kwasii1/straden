<div
    x-show="tooltip.show"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-end="opacity-0"
    x-cloak
    class="pointer-events-none absolute z-50 min-w-40 -translate-x-1/2 -translate-y-[calc(100%+8px)] rounded-lg border border-zinc-200 bg-white py-1.5 shadow-[0_4px_16px_-4px_rgb(24_24_27/0.12)]"
    :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`"
>
    <p class="px-3 pb-1 text-xs text-zinc-500 tabular-nums" x-text="tooltip.label"></p>
    <template x-for="row in tooltip.rows" :key="row.label">
        <div class="flex items-center justify-between gap-4 px-3 py-0.5">
            <span class="flex items-center gap-1.5 text-xs text-zinc-600">
                <span class="h-2 w-2 rounded-[2px]" :style="`background-color: ${row.color}`"></span>
                <span x-text="row.label"></span>
            </span>
            <span class="text-xs font-medium text-zinc-900 tabular-nums" x-text="row.value"></span>
        </div>
    </template>
</div>
