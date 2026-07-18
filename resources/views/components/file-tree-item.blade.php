@props(['item', 'depth' => 0])

@php
    $isExpandable = ! empty($item['children']);
@endphp

<div x-data="{ expanded: true }">
    <div
        @if ($isExpandable) @click="expanded = !expanded" @endif
        class="flex items-center gap-1.5 py-1 px-2 hover:bg-zinc-800/50 rounded cursor-pointer text-sm select-none"
        style="padding-left: {{ ($depth * 16) + 8 }}px"
    >
        @if ($isExpandable)
            <flux:icon.chevron-right class="size-3 text-zinc-500 shrink-0 transition-transform" ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder class="size-3.5 text-amber-400 shrink-0" x-show="!expanded" x-cloak />
            <flux:icon.folder-open class="size-3.5 text-amber-400 shrink-0" x-show="expanded" />
        @else
            <span class="w-3 shrink-0"></span>
            <flux:icon.document-text class="size-3.5 text-blue-400 shrink-0" />
        @endif
        <span class="text-zinc-300 truncate">{{ $item['name'] }}</span>
    </div>

    @if ($isExpandable)
        <div x-show="expanded">
            @foreach ($item['children'] as $child)
                <x-file-tree-item :item="$child" :depth="$depth + 1" />
            @endforeach
        </div>
    @endif
</div>
