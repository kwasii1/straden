<?php

use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use App\Services\ScriptFileManager;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app', ['noPadding' => true])]
class extends Component
{
    public Project $project;

    public Test $test;

    public Script $script;

    public array $fileTree = [];

    public array $openTabs = [];

    public ?string $activeFilePath = null;

    public function mount(): void
    {
        $this->fileTree = $this->fm()->fileTree();

        if (! empty($this->fileTree)) {
            $this->selectFile('script.js');
        }
    }

    public function selectFile(string $path): void
    {
        $this->activeFilePath = $path;

        if (! in_array($path, $this->openTabs, true)) {
            $this->openTabs[] = $path;
        }
    }

    public function closeTab(string $path): void
    {
        $this->openTabs = array_values(array_filter(
            $this->openTabs,
            fn ($t) => $t !== $path
        ));

        if ($this->activeFilePath === $path) {
            $this->activeFilePath = $this->openTabs[0] ?? null;
        }
    }

    public function createFile(string $name, ?string $content = null, ?string $parentDir = null): void
    {
        $relPath = $this->fm()->createFile($name, $content, $parentDir);

        if ($relPath === null) {
            Flux::toast(variant: 'error', text: 'A file with that name already exists.');

            return;
        }

        $this->buildFileTree();
        $this->selectFile($relPath);
    }

    public function createFolder(string $name, ?string $parentDir = null): void
    {
        $this->fm()->createFolder($name, $parentDir);
        $this->buildFileTree();
    }

    public function moveItem(string $sourcePath, string $targetDir): void
    {
        if (str_starts_with($targetDir, $sourcePath.'/')) {
            Flux::toast(variant: 'error', text: 'Cannot move a folder into itself.');

            return;
        }

        $destRel = $this->fm()->move($sourcePath, $targetDir);

        if ($destRel === null) {
            Flux::toast(variant: 'error', text: 'An item with that name already exists at the target.');

            return;
        }

        $this->updatePathsAfterMove($sourcePath, $destRel);
        $this->buildFileTree();
    }

    public function renameItem(string $path, string $newName): void
    {
        if ($path === $this->fm()->entryPointPath()) {
            Flux::toast(variant: 'error', text: 'Cannot rename the entry point file.');

            return;
        }

        $newPath = $this->fm()->rename($path, $newName);

        if ($newPath === null) {
            Flux::toast(variant: 'error', text: 'An item with that name already exists.');

            return;
        }

        $this->updatePathsAfterMove($path, $newPath);
        $this->buildFileTree();
    }

    public function deleteItem(string $path): void
    {
        if ($path === $this->fm()->entryPointPath()) {
            Flux::toast(variant: 'error', text: 'Cannot delete the entry point file.');

            return;
        }

        $this->fm()->delete($path);

        $this->openTabs = array_values(array_filter(
            $this->openTabs,
            fn ($t) => $t !== $path && ! str_starts_with($t, $path.'/')
        ));

        if ($this->activeFilePath === $path || str_starts_with($this->activeFilePath ?? '', $path.'/')) {
            $this->activeFilePath = $this->openTabs[0] ?? null;
        }

        $this->buildFileTree();
    }

    public function readFile(string $relativePath): ?string
    {
        return $this->fm()->readFile($relativePath);
    }

    public function detectLanguage(string $path): string
    {
        return $this->fm()->detectLanguage($path);
    }

    private function fm(): ScriptFileManager
    {
        return new ScriptFileManager('scripts/'.$this->test->id.'/'.$this->script->id);
    }

    private function buildFileTree(): void
    {
        $this->fileTree = $this->fm()->fileTree();
    }

    private function updatePathsAfterMove(string $oldPath, string $newPath): void
    {
        $this->openTabs = array_map(function ($tab) use ($oldPath, $newPath) {
            if ($tab === $oldPath) {
                return $newPath;
            }
            if (str_starts_with($tab, $oldPath.'/')) {
                return $newPath.substr($tab, strlen($oldPath));
            }

            return $tab;
        }, $this->openTabs);

        if ($this->activeFilePath === $oldPath) {
            $this->activeFilePath = $newPath;
        } elseif ($this->activeFilePath !== null && str_starts_with($this->activeFilePath, $oldPath.'/')) {
            $this->activeFilePath = $newPath.substr($this->activeFilePath, strlen($oldPath));
        }
    }
};
?>

<div
    x-data="{
        sidebarTab: 'chat',
        creating: false,
        createType: 'file',
        newItemName: '',
        newItemParentDir: '',
        fileDragOver: false,
        dragCounter: 0,

        startCreate(type) {
            this.creating = true;
            this.createType = type;
            this.newItemName = '';
            this.newItemParentDir = '';
        },

        submitCreate() {
            if (! this.newItemName.trim()) return;
            if (this.createType === 'file') {
                $wire.createFile(this.newItemName.trim(), null, this.newItemParentDir);
            } else {
                $wire.createFolder(this.newItemName.trim(), this.newItemParentDir);
            }
            this.creating = false;
            this.newItemName = '';
        },

        cancelCreate() {
            this.creating = false;
            this.newItemName = '';
        },

        handleDrop(event, targetDir) {
            if (event.dataTransfer.files.length > 0) {
                for (const file of event.dataTransfer.files) {
                    const reader = new FileReader();
                    reader.onload = (e) => $wire.createFile(file.name, e.target.result, targetDir);
                    reader.readAsText(file);
                }
            } else {
                const sourcePath = event.dataTransfer.getData('text/plain');
                if (sourcePath) {
                    $wire.moveItem(sourcePath, targetDir);
                }
            }
        },

        handleFileUpload(event) {
            const files = event.target.files;
            for (const file of files) {
                const reader = new FileReader();
                reader.onload = (e) => $wire.createFile(file.name, e.target.result, null);
                reader.readAsText(file);
            }
            event.target.value = '';
        },

        treeDragEnter() {
            this.dragCounter++;
            this.fileDragOver = true;
        },

        treeDragLeave() {
            this.dragCounter--;
            if (this.dragCounter === 0) {
                this.fileDragOver = false;
            }
        },

        treeDragDrop(event) {
            this.dragCounter = 0;
            this.fileDragOver = false;
            if (event.dataTransfer.files.length > 0) {
                for (const file of event.dataTransfer.files) {
                    const reader = new FileReader();
                    reader.onload = (e) => $wire.createFile(file.name, e.target.result, null);
                    reader.readAsText(file);
                }
            }
        },
    }"
    @tree-drop.window="handleDrop($event.detail.event, $event.detail.targetDir)"
    class="flex flex-col h-full"
>
    <div class="shrink-0 flex justify-between items-center p-1">
        <flux:heading>{{ $script->name }}</flux:heading>
        <flux:button icon="play" variant="primary">Run Test</flux:button>
    </div>

    <div class="flex flex-1 min-h-0">
        <div class="flex flex-col w-3/5 min-h-0">
            <div class="flex-1 flex flex-col min-h-0 border border-zinc-800 overflow-hidden">
                <div class="shrink-0 flex items-center bg-zinc-950 overflow-x-auto
                            [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($openTabs as $tab)
                        @php $isActive = $tab === $activeFilePath; @endphp
                        <div
                            wire:click="selectFile('{{ $tab }}')"
                            class="group/tab flex items-center gap-2 px-3 py-1.5 text-sm border-r
                                   border-zinc-800 shrink-0 cursor-pointer select-none
                                   {{ $isActive
                                      ? ' bg-zinc-800 text-zinc-100 -mb-px border-b border-b-zinc-800'
                                      : ' bg-zinc-950 text-zinc-500 hover:bg-zinc-900/50' }}">
                            <flux:icon.document-text class="size-3.5 shrink-0" />
                            <span class="truncate max-w-[160px]">{{ basename($tab) }}</span>
                            <button
                                wire:click.stop="closeTab('{{ $tab }}')"
                                class="rounded p-0.5 hover:bg-zinc-700 text-zinc-500
                                       hover:text-zinc-300 opacity-0 group-hover/tab:opacity-100
                                       transition-opacity">
                                <flux:icon.x-mark class="size-3" />
                            </button>
                        </div>
                    @endforeach
                    <div class="flex-1 self-stretch bg-zinc-950 border-b border-zinc-800"></div>
                </div>

                @if ($activeFilePath)
                    <x-code-editor
                        name="script_content"
                        wire:key="editor-{{ $script->id }}-{{ $activeFilePath }}"
                        :value="$this->readFile($activeFilePath)"
                        :language="$this->detectLanguage($activeFilePath)"
                        height="100%"
                        class="!rounded-none !border-0 flex-1"
                    />
                @else
                    <div class="flex-1 flex items-center justify-center text-zinc-600 text-sm">
                        Select a file to edit
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col w-2/5 min-h-0 border border-zinc-800 bg-zinc-950 overflow-hidden">
            <div class="grid grid-cols-2 border-b border-zinc-800 shrink-0">
                <button
                    @click="sidebarTab = 'chat'"
                    :class="sidebarTab === 'chat'
                        ? 'bg-zinc-800 text-zinc-100'
                        : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-900/50'"
                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
                >
                    <flux:icon.sparkles class="size-4" />
                    AI Chat
                </button>
                <button
                    @click="sidebarTab = 'files'"
                    :class="sidebarTab === 'files'
                        ? 'bg-zinc-800 text-zinc-100'
                        : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-900/50'"
                    class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
                >
                    <flux:icon.folder-tree class="size-4" />
                    File Tree
                </button>
            </div>

            <div x-show="sidebarTab === 'chat'" class="flex-1 flex flex-col min-h-0">
                <x-chat-panel />
            </div>

            <div x-show="sidebarTab === 'files'" class="flex-1 flex flex-col min-h-0">
                <div class="flex items-center gap-0.5 px-1 py-0.5 border-b border-zinc-800 shrink-0">
                    <button
                        @click="startCreate('file')"
                        title="New File"
                        class="p-1 rounded hover:bg-zinc-700 text-zinc-400 hover:text-zinc-200 transition-colors"
                    >
                        <flux:icon.document-plus class="size-4" />
                    </button>
                    <button
                        @click="startCreate('folder')"
                        title="New Folder"
                        class="p-1 rounded hover:bg-zinc-700 text-zinc-400 hover:text-zinc-200 transition-colors"
                    >
                        <flux:icon.folder-plus class="size-4" />
                    </button>
                    <div class="flex-1"></div>
                    <button
                        @click="$refs.fileUploadInput.click()"
                        title="Upload Files"
                        class="p-1 rounded hover:bg-zinc-700 text-zinc-400 hover:text-zinc-200 transition-colors"
                    >
                        <flux:icon.arrow-up-tray class="size-4" />
                    </button>
                    <input
                        type="file"
                        x-ref="fileUploadInput"
                        multiple
                        hidden
                        @change="handleFileUpload($event)"
                    />
                </div>

                <div x-show="creating" class="px-2 py-1 shrink-0" x-transition>
                    <input
                        x-ref="newItemInput"
                        x-model="newItemName"
                        @keydown.enter="submitCreate()"
                        @keydown.escape="cancelCreate()"
                        :placeholder="createType === 'file' ? 'Filename...' : 'Folder name...'"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded px-2 py-1
                               text-sm text-zinc-200 outline-none focus:border-blue-500"
                        x-init="$el.focus()"
                    />
                </div>

                <div
                    class="flex-1 overflow-y-auto p-1 relative"
                    @dragenter="treeDragEnter()"
                    @dragleave="treeDragLeave()"
                    @dragover.prevent
                    @drop.prevent="treeDragDrop($event)"
                >
                    @foreach ($fileTree as $item)
                        <x-script-tree-item :item="$item" :depth="0" path="" :activeFilePath="$activeFilePath" />
                    @endforeach

                    @if (empty($fileTree))
                        <div class="text-zinc-600 text-sm p-2">
                            No files yet.
                        </div>
                    @endif

                    <div
                        x-show="fileDragOver"
                        class="absolute inset-0 flex items-center justify-center
                               bg-blue-900/30 border-2 border-dashed border-blue-500/50
                               rounded-lg z-10 pointer-events-none"
                    >
                        <span class="text-blue-300 text-sm font-medium">Drop files to upload</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
