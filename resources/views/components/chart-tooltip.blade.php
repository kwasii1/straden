<div
    x-show="tooltip.show"
    x-transition.opacity.duration.100ms
    x-cloak
    class="pointer-events-none absolute z-50 w-40 -translate-x-1/2 -translate-y-full overflow-hidden rounded-lg border border-zinc-100 bg-white shadow-lg dark:border-zinc-700"
    :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`"
>
    <div class="bg-[#F5F5F5] px-3 py-1.5 dark:bg-zinc-800">
        <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-text="tooltip.label"></span>
    </div>
    <template x-for="row in tooltip.rows" :key="row.label">
        <div class="flex items-center justify-between px-3 py-1.5">
            <span class="flex items-center gap-1.5 text-xs text-zinc-500">
                <span class="size-1.5 rounded-full" :style="`background-color: ${row.color}`"></span>
                <span x-text="row.label"></span>
            </span>
            <span class="text-xs font-semibold text-zinc-900 dark:text-white" x-text="row.value"></span>
        </div>
    </template>
    <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border-b border-r border-zinc-100 bg-white dark:border-zinc-700"></div>
</div>
