@props(['item', 'depth' => 0, 'path' => ''])

@php
    $isExpandable = ! empty($item['children']);
    $currentPath = $path ? $path . '/' . $item['name'] : $item['name'];
    $isSelected = ! $isExpandable && $this->selectedFilePath === $currentPath;
@endphp

<div x-data="{ expanded: {{ $depth === 0 ? 'true' : 'false' }} }">
    @if ($isExpandable)
        <div
            @click="expanded = !expanded"
            class="flex h-7 cursor-pointer items-center gap-1.5 rounded-md pr-2 text-[13px] text-zinc-700 select-none hover:bg-zinc-100"
            style="padding-left: {{ ($depth * 12) + 6 }}px"
        >
            <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-zinc-400" ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="!expanded" x-cloak />
            <flux:icon.folder-open variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="expanded" />
            <span class="truncate">{{ $item['name'] }}</span>
        </div>
        <div x-show="expanded">
            @foreach ($item['children'] as $child)
                @include('components.repository-tree-item', ['item' => $child, 'depth' => $depth + 1, 'path' => $currentPath])
            @endforeach
        </div>
    @else
        <div
            wire:click="selectFile('{{ $currentPath }}')"
            @class([
                'flex h-7 cursor-pointer items-center gap-1.5 rounded-md pr-2 text-[13px] select-none',
                'bg-zinc-200/70 text-zinc-900' => $isSelected,
                'text-zinc-700 hover:bg-zinc-100' => ! $isSelected,
            ])
            style="padding-left: {{ ($depth * 12) + 6 }}px"
            @if ($isSelected) aria-current="true" @endif
        >
            <span class="w-3.5 shrink-0"></span>
            <x-file-icon :name="$item['name']" :class="$isSelected ? 'size-3.5 text-zinc-600!' : 'size-3.5'" />
            <span class="truncate">{{ $item['name'] }}</span>
        </div>
    @endif
</div>
