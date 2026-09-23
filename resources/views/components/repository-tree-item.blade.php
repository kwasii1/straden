@props(['item', 'depth' => 0, 'path' => ''])

@php
    $isExpandable = ! empty($item['children']);
    $currentPath = $path ? $path . '/' . $item['name'] : $item['name'];
@endphp

<div x-data="{ expanded: true }">
    @if ($isExpandable)
        <div
            @click="expanded = !expanded"
            class="flex items-center gap-1.5 py-1 px-2 hover:bg-zinc-800/50 rounded cursor-pointer text-sm select-none"
            style="padding-left: {{ ($depth * 16) + 8 }}px"
        >
            <flux:icon.chevron-right class="size-3 text-zinc-500 shrink-0 transition-transform" ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder class="size-3.5 text-amber-400 shrink-0" x-show="!expanded" x-cloak />
            <flux:icon.folder-open class="size-3.5 text-amber-400 shrink-0" x-show="expanded" />
            <span class="text-zinc-300 truncate">{{ $item['name'] }}</span>
        </div>
        <div x-show="expanded">
            @foreach ($item['children'] as $child)
                @include('components.repository-tree-item', ['item' => $child, 'depth' => $depth + 1, 'path' => $currentPath])
            @endforeach
        </div>
    @else
        <div
            wire:click="selectFile('{{ $currentPath }}')"
            class="flex items-center gap-1.5 py-1 px-2 hover:bg-zinc-800/50 rounded cursor-pointer text-sm select-none {{ $this->selectedFilePath === $currentPath ? 'bg-zinc-700/50' : '' }}"
            style="padding-left: {{ ($depth * 16) + 8 }}px"
        >
            <span class="w-3 shrink-0"></span>
            <flux:icon.document-text class="size-3.5 text-blue-400 shrink-0" />
            <span class="text-zinc-300 truncate">{{ $item['name'] }}</span>
        </div>
    @endif
</div>
