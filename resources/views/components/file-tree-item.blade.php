@props(['item', 'depth' => 0])

@php
    $isExpandable = ! empty($item['children']);
@endphp

<div x-data="{ expanded: true }">
    <div
        @if ($isExpandable) @click="expanded = !expanded" @endif
        class="flex h-7 cursor-pointer items-center gap-1.5 rounded-md pr-2 text-[13px] text-zinc-700 select-none hover:bg-zinc-100"
        style="padding-left: {{ ($depth * 12) + 6 }}px"
    >
        @if ($isExpandable)
            <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-zinc-400" ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="!expanded" x-cloak />
            <flux:icon.folder-open variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="expanded" />
        @else
            <span class="w-3.5 shrink-0"></span>
            <x-file-icon :name="$item['name']" class="size-3.5" />
        @endif
        <span class="truncate">{{ $item['name'] }}</span>
    </div>

    @if ($isExpandable)
        <div x-show="expanded">
            @foreach ($item['children'] as $child)
                <x-file-tree-item :item="$child" :depth="$depth + 1" />
            @endforeach
        </div>
    @endif
</div>
