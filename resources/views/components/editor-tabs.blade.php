@props(['tabs' => []])

<div {{ $attributes->merge(['class' => 'flex h-10 items-stretch overflow-x-auto overflow-y-hidden bg-zinc-50 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden']) }}>
    @foreach ($tabs as $tab)
        @php
            $isActive = $tab['active'] ?? false;
        @endphp
        <div @class([
            'group/tab flex shrink-0 cursor-pointer items-center gap-2 border-r border-b border-zinc-200 pr-1.5 pl-3 text-[13px] select-none',
            'border-b-transparent bg-white text-zinc-900' => $isActive,
            'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900' => ! $isActive,
        ])>
            <x-file-icon :name="$tab['name']" class="size-3.5" />
            <span class="max-w-[160px] truncate">{{ $tab['name'] }}</span>
            <button
                type="button"
                aria-label="Close {{ $tab['name'] }}"
                @class([
                    'inline-flex size-5 items-center justify-center rounded text-zinc-400 hover:bg-zinc-200 hover:text-zinc-900',
                    'opacity-0 group-hover/tab:opacity-100' => ! $isActive,
                ])
            >
                <flux:icon.x-mark variant="micro" class="size-3.5" />
            </button>
        </div>
    @endforeach
    <div class="min-w-0 flex-1 border-b border-zinc-200"></div>
</div>
