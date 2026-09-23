@props(['item', 'depth' => 0, 'path' => '', 'activeFilePath' => null])

@php
    $isFolder = array_key_exists('children', $item);
    $currentPath = $path ? $path.'/'.$item['name'] : $item['name'];
    $isRenamable = ! ($path === '' && $item['name'] === 'script.js');
    $isDeletable = ! ($path === '' && $item['name'] === 'script.js');
@endphp

<div x-data="{ expanded: true, dragOver: false, editing: false, newName: '' }">
    @if ($isFolder)
        <div
            @click="if (!editing) expanded = !expanded"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent.stop="dragOver = false; $dispatch('tree-drop', { event: $event, targetDir: '{{ $currentPath }}' })"
            :class="dragOver ? 'bg-blue-800/50 ring-1 ring-blue-500/50' : ''"
            class="group/item flex items-center gap-1.5 py-1 px-2 hover:bg-zinc-800/50 rounded cursor-pointer text-sm select-none transition-colors"
            style="padding-left: {{ ($depth * 16) + 8 }}px"
        >
            <flux:icon.chevron-right
                class="size-3 text-zinc-500 shrink-0 transition-transform"
                ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder class="size-3.5 text-amber-400 shrink-0" x-show="!expanded" x-cloak />
            <flux:icon.folder-open class="size-3.5 text-amber-400 shrink-0" x-show="expanded" />

            <template x-if="!editing">
                <span class="text-zinc-300 truncate flex-1 min-w-0">{{ $item['name'] }}</span>
            </template>
            <template x-if="editing">
                <input
                    x-ref="renameInput"
                    x-model="newName"
                    @keydown.enter="$wire.renameItem('{{ $currentPath }}', newName); editing = false"
                    @keydown.escape="editing = false"
                    @blur="editing = false"
                    @click.stop
                    class="flex-1 min-w-0 bg-zinc-700 border border-blue-500 rounded px-1 py-0 text-sm text-zinc-200 outline-none"
                    x-init="$el.focus(); $el.select()"
                />
            </template>

            <template x-if="!editing">
                <div class="flex items-center gap-0.5 shrink-0">
                    @if ($isRenamable)
                        <button
                            @click.stop="editing = true; newName = '{{ $item['name'] }}'"
                            title="Rename"
                            class="rounded p-0.5 hover:bg-zinc-600 text-zinc-600 hover:text-zinc-300
                                   opacity-0 group-hover/item:opacity-100 transition-opacity"
                        >
                            <flux:icon.pencil class="size-3" />
                        </button>
                    @endif
                    @if ($isDeletable)
                        <button
                            @click.stop="$wire.deleteItem('{{ $currentPath }}')"
                            title="Delete"
                            class="rounded p-0.5 hover:bg-red-800/50 text-zinc-600 hover:text-red-400
                                   opacity-0 group-hover/item:opacity-100 transition-opacity"
                        >
                            <flux:icon.trash class="size-3" />
                        </button>
                    @endif
                </div>
            </template>
        </div>
        <div x-show="expanded">
            @foreach ($item['children'] as $child)
                <x-script-tree-item :item="$child" :depth="$depth + 1" :path="$currentPath" :activeFilePath="$activeFilePath" />
            @endforeach
        </div>
    @else
        <div
            :draggable="!editing"
            @dragstart="if (!editing) { event.dataTransfer.setData('text/plain', '{{ $currentPath }}'); event.dataTransfer.effectAllowed = 'move' } else { event.preventDefault() }"
            @click="if (!editing) $wire.selectFile('{{ $currentPath }}')"
            class="group/item flex items-center gap-1.5 py-1 px-2 hover:bg-zinc-800/50 rounded cursor-pointer text-sm select-none {{ $activeFilePath === $currentPath ? 'bg-zinc-700/50' : '' }}"
            style="padding-left: {{ ($depth * 16) + 8 }}px"
        >
            <span class="w-3 shrink-0"></span>
            <flux:icon.document-text class="size-3.5 text-blue-400 shrink-0" />

            <template x-if="!editing">
                <span class="text-zinc-300 truncate flex-1 min-w-0">{{ $item['name'] }}</span>
            </template>
            <template x-if="editing">
                <input
                    x-ref="renameInput"
                    x-model="newName"
                    @keydown.enter="$wire.renameItem('{{ $currentPath }}', newName); editing = false"
                    @keydown.escape="editing = false"
                    @blur="editing = false"
                    @click.stop
                    class="flex-1 min-w-0 bg-zinc-700 border border-blue-500 rounded px-1 py-0 text-sm text-zinc-200 outline-none"
                    x-init="$el.focus(); $el.select()"
                />
            </template>

            <template x-if="!editing">
                <div class="flex items-center gap-0.5 shrink-0">
                    @if ($isRenamable)
                        <button
                            @click.stop="editing = true; newName = '{{ $item['name'] }}'"
                            title="Rename"
                            class="rounded p-0.5 hover:bg-zinc-600 text-zinc-600 hover:text-zinc-300
                                   opacity-0 group-hover/item:opacity-100 transition-opacity"
                        >
                            <flux:icon.pencil class="size-3" />
                        </button>
                    @endif
                    @if ($isDeletable)
                        <button
                            @click.stop="$wire.deleteItem('{{ $currentPath }}')"
                            title="Delete"
                            class="rounded p-0.5 hover:bg-red-800/50 text-zinc-600 hover:text-red-400
                                   opacity-0 group-hover/item:opacity-100 transition-opacity"
                        >
                            <flux:icon.trash class="size-3" />
                        </button>
                    @endif
                </div>
            </template>
        </div>
    @endif
</div>
