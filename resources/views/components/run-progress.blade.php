@props(['percent' => null])

<div class="relative size-10" title="{{ $percent !== null ? $percent.'% complete' : 'Run in progress' }}">
    <svg class="size-10 -rotate-90" viewBox="0 0 36 36">
        <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" class="text-zinc-200 dark:text-zinc-700" />

        @if ($percent !== null)
            <circle
                cx="18"
                cy="18"
                r="15.915"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                pathLength="100"
                stroke-dasharray="100"
                stroke-dashoffset="{{ 100 - $percent }}"
                class="text-blue-500 transition-all duration-700 ease-out"
            />
        @else
            <circle
                cx="18"
                cy="18"
                r="15.915"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                pathLength="100"
                stroke-dasharray="25"
                class="text-blue-500/60 animate-pulse"
            />
        @endif
    </svg>

    @if ($percent !== null)
        <span class="absolute inset-0 flex items-center justify-center text-[10px] font-semibold text-zinc-700 dark:text-zinc-200">{{ $percent }}%</span>
    @endif
</div>
