@props(['tabs' => []])

<div {{ $attributes->merge(['class' => 'flex items-center bg-zinc-950 overflow-x-auto overflow-y-hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden']) }}>
    @foreach ($tabs as $tab)
        @php
            $isActive = $tab['active'] ?? false;
        @endphp
        <div class="group/tab flex items-center gap-2 px-3 py-1.5 text-sm border-r border-zinc-800 shrink-0 cursor-pointer select-none{{ $isActive
                ? ' bg-zinc-800 text-zinc-100 -mb-px border-b border-b-zinc-800'
                : ' bg-zinc-950 text-zinc-500 hover:bg-zinc-900/50' }}">
            <flux:icon.document-text class="size-3.5 shrink-0" />
            <span class="truncate max-w-[160px]">{{ $tab['name'] }}</span>
            <button class="rounded p-0.5 hover:bg-zinc-700 text-zinc-500 hover:text-zinc-300 opacity-0 group-hover/tab:opacity-100 transition-opacity">
                <flux:icon.x-mark class="size-3" />
            </button>
        </div>
    @endforeach
    <div class="flex-1 self-stretch bg-zinc-950 border-b border-zinc-800"></div>
</div>
