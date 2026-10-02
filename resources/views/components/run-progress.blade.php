@props(['percent' => null])

<div class="relative size-10" title="{{ $percent !== null ? $percent.'% complete' : 'Run in progress' }}">
    @if ($percent !== null)
        <svg class="size-10 -rotate-90" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="2.5" class="text-zinc-200" />
            <circle
                cx="18"
                cy="18"
                r="15.915"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                pathLength="100"
                stroke-dasharray="100"
                stroke-dashoffset="{{ 100 - $percent }}"
                class="text-brand-500 transition-[stroke-dashoffset] duration-500 ease-snappy"
            />
        </svg>
        <span class="absolute inset-0 flex items-center justify-center text-[10px] font-semibold text-zinc-700 tabular-nums">{{ $percent }}%</span>
    @else
        <x-spinner class="size-10 text-brand-500" />
    @endif
</div>
