@props(['item', 'depth' => 0, 'path' => '', 'activeFilePath' => null])

@php
    $isFolder = array_key_exists('children', $item);
    $currentPath = $path ? $path.'/'.$item['name'] : $item['name'];
    $isRenamable = ! ($path === '' && $item['name'] === 'script.js');
    $isDeletable = ! ($path === '' && $item['name'] === 'script.js');
    $isActive = ! $isFolder && $activeFilePath === $currentPath;
    $rowClasses = 'group/item flex h-7 cursor-pointer items-center gap-1.5 rounded-md pr-1 text-[13px] select-none';
    $renameInputClasses = 'h-5 min-w-0 flex-1 rounded border border-zinc-300 bg-white px-1 text-[13px] text-zinc-900 outline-none focus:border-zinc-500 focus:ring-2 focus:ring-zinc-900/8';
    $renameButtonClasses = 'inline-flex size-5 items-center justify-center rounded text-zinc-400 opacity-0 transition-opacity duration-100 group-hover/item:opacity-100 hover:bg-zinc-200 hover:text-zinc-900';
    $deleteButtonClasses = 'inline-flex size-5 items-center justify-center rounded text-zinc-400 opacity-0 transition-opacity duration-100 group-hover/item:opacity-100 hover:bg-red-50 hover:text-red-600';
@endphp

<div x-data="{ expanded: true, dragOver: false, editing: false, newName: '' }">
    @if ($isFolder)
        <div
            @click="if (!editing) expanded = !expanded"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent.stop="dragOver = false; $dispatch('tree-drop', { event: $event, targetDir: '{{ $currentPath }}' })"
            :class="dragOver ? 'bg-zinc-200/70 ring-1 ring-inset ring-zinc-400' : 'hover:bg-zinc-100'"
            class="{{ $rowClasses }} text-zinc-700"
            style="padding-left: {{ ($depth * 12) + 6 }}px"
        >
            <flux:icon.chevron-right
                variant="micro"
                class="size-3.5 shrink-0 text-zinc-400"
                ::class="expanded ? 'rotate-90' : ''" />
            <flux:icon.folder variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="!expanded" x-cloak />
            <flux:icon.folder-open variant="micro" class="size-3.5 shrink-0 text-zinc-400" x-show="expanded" />

            <template x-if="!editing">
                <span class="min-w-0 flex-1 truncate">{{ $item['name'] }}</span>
            </template>
            <template x-if="editing">
                <input
                    x-ref="renameInput"
                    x-model="newName"
                    @keydown.enter="$wire.renameItem('{{ $currentPath }}', newName); editing = false"
                    @keydown.escape="editing = false"
                    @blur="editing = false"
                    @click.stop
                    class="{{ $renameInputClasses }}"
                    x-init="$el.focus(); $el.select()"
                />
            </template>

            <template x-if="!editing">
                <div class="flex shrink-0 items-center gap-0.5">
                    @if ($isRenamable)
                        <button
                            type="button"
                            @click.stop="editing = true; newName = '{{ $item['name'] }}'"
                            title="Rename"
                            aria-label="Rename {{ $item['name'] }}"
                            class="{{ $renameButtonClasses }}"
                        >
                            <flux:icon.pencil variant="micro" class="size-3" />
                        </button>
                    @endif
                    @if ($isDeletable)
                        <button
                            type="button"
                            @click.stop="$wire.deleteItem('{{ $currentPath }}')"
                            title="Delete"
                            aria-label="Delete {{ $item['name'] }}"
                            class="{{ $deleteButtonClasses }}"
                        >
                            <flux:icon.trash variant="micro" class="size-3" />
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
            @class([
                $rowClasses,
                'bg-zinc-200/70 text-zinc-900' => $isActive,
                'text-zinc-700 hover:bg-zinc-100' => ! $isActive,
            ])
            style="padding-left: {{ ($depth * 12) + 6 }}px"
            @if ($isActive) aria-current="true" @endif
        >
            <span class="w-3.5 shrink-0"></span>
            <x-file-icon :name="$item['name']" :class="$isActive ? 'size-3.5 text-zinc-600!' : 'size-3.5'" />

            <template x-if="!editing">
                <span class="min-w-0 flex-1 truncate">{{ $item['name'] }}</span>
            </template>
            <template x-if="editing">
                <input
                    x-ref="renameInput"
                    x-model="newName"
                    @keydown.enter="$wire.renameItem('{{ $currentPath }}', newName); editing = false"
                    @keydown.escape="editing = false"
                    @blur="editing = false"
                    @click.stop
                    class="{{ $renameInputClasses }}"
                    x-init="$el.focus(); $el.select()"
                />
            </template>

            <template x-if="!editing">
                <div class="flex shrink-0 items-center gap-0.5">
                    @if ($isRenamable)
                        <button
                            type="button"
                            @click.stop="editing = true; newName = '{{ $item['name'] }}'"
                            title="Rename"
                            aria-label="Rename {{ $item['name'] }}"
                            class="{{ $renameButtonClasses }}"
                        >
                            <flux:icon.pencil variant="micro" class="size-3" />
                        </button>
                    @endif
                    @if ($isDeletable)
                        <button
                            type="button"
                            @click.stop="$wire.deleteItem('{{ $currentPath }}')"
                            title="Delete"
                            aria-label="Delete {{ $item['name'] }}"
                            class="{{ $deleteButtonClasses }}"
                        >
                            <flux:icon.trash variant="micro" class="size-3" />
                        </button>
                    @endif
                </div>
            </template>
        </div>
    @endif
</div>
