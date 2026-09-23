<?php

use App\Jobs\RunTestJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Services\ScriptFileManager;
use Flux\Flux;
use Livewire\Attributes\Computed;
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

    public string $mode = 'script';

    public bool $persistLogs = false;

    public function mount(): void
    {
        $this->persistLogs = (bool) $this->project->persist_run_logs;

        $this->fileTree = $this->fm()->fileTree();

        if (! empty($this->fileTree)) {
            $this->selectFile('script.js');
        }
    }

    public function updatedPersistLogs(bool $value): void
    {
        $this->project->update(['persist_run_logs' => $value]);

        Flux::toast(variant: 'success', text: $value
            ? 'Run logs will be persisted after each run.'
            : 'Run logs will be discarded after each run.');
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

        $this->dispatch('editor-moved', sourcePath: $sourcePath, destPath: $destRel);
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

        $this->dispatch('editor-moved', sourcePath: $path, destPath: $newPath);
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

        $this->dispatch('editor-deleted', path: $path);
    }

    public function readFile(string $relativePath): ?string
    {
        return $this->fm()->readFile($relativePath);
    }

    public function saveFile(string $path, string $content): void
    {
        try {
            $this->fm()->updateFile($path, $content);
        } catch (\InvalidArgumentException $e) {
            $this->addError('path', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: "Saved {$path}.");
    }

    public function runTest(): void
    {
        $run = $this->script->runs()->create([
            'status' => 'queued',
            'triggered_by' => 'manual',
            'triggered_by_user_id' => auth()->id(),
        ]);

        RunTestJob::dispatch($run);

        Flux::toast(variant: 'success', text: 'Test run queued.');
    }

    #[Computed]
    public function currentRun(): ?Run
    {
        return $this->script->runs()
            ->whereIn('status', ['queued', 'running'])
            ->latest()
            ->first();
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
        creating: false,
        createType: 'file',
        newItemName: '',
        newItemParentDir: '',

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

        saveEditor(detail) {
            $wire.saveFile(detail.path, detail.content).then(() => {
                if (Alpine.store('editor').buffers[detail.path]) {
                    Alpine.store('editor').buffers[detail.path].savedContent = detail.content;
                    Alpine.store('editor').buffers[detail.path].dirty = false;
                }
            });
        },

        handleEditorMoved(detail) {
            const buffers = Alpine.store('editor').buffers;
            const matches = Object.keys(buffers).filter(k => k === detail.sourcePath || k.startsWith(detail.sourcePath + '/'));

            for (const oldKey of matches) {
                const newKey = detail.destPath + oldKey.substring(detail.sourcePath.length);
                buffers[newKey] = buffers[oldKey];
                delete buffers[oldKey];
            }
        },

        handleEditorDeleted(detail) {
            const buffers = Alpine.store('editor').buffers;
            for (const key of Object.keys(buffers)) {
                if (key === detail.path || key.startsWith(detail.path + '/')) {
                    delete buffers[key];
                }
            }
        },
    }"
    @tree-drop.window="handleDrop($event.detail.event, $event.detail.targetDir)"
    @editor-save.window="saveEditor($event.detail)"
    @editor-moved.window="handleEditorMoved($event.detail)"
    @editor-deleted.window="handleEditorDeleted($event.detail)"
    class="flex flex-col h-full bg-white dark:bg-zinc-950"
>
    {{-- In-flow toolbar — replaces the old floating title / tab-switcher / run button.
         Sits in its own row so it never overlaps editor or chat content. --}}
    <div class="shrink-0 h-12 flex items-center justify-between gap-3 px-4 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 z-10">
        <div class="flex items-center gap-2 min-w-0">
            <flux:icon.document-text class="size-4 text-zinc-400 dark:text-zinc-500 shrink-0" />
            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">
                {{ $script->name }}
            </span>
        </div>

        <div class="flex bg-zinc-100 dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-full p-0.5 shrink-0">
            <button
                wire:click="$set('mode', 'script')"
                class="px-3.5 py-1 text-xs font-medium rounded-full transition-colors {{ $mode === 'script' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
            >
                Script
            </button>
            <button
                wire:click="$set('mode', 'agent')"
                class="px-3.5 py-1 text-xs font-medium rounded-full transition-colors {{ $mode === 'agent' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
            >
                Agent
            </button>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <label class="flex shrink-0 cursor-pointer select-none items-center gap-2" title="Persist k6 logs after the run completes">
                <flux:switch wire:model.live="persistLogs" />
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Persist logs</span>
            </label>

            @if ($this->currentRun)
                <flux:button
                    wire:navigate
                    :href="route('projects.runs.view', ['project' => $this->project, 'run' => $this->currentRun])"
                    size="sm"
                    variant="subtle"
                    icon="clock"
                >
                    View Current Run
                </flux:button>
            @endif

            <flux:button wire:click="runTest" wire:loading.attr="disabled" icon="play" variant="primary" size="sm">
                Run Test
            </flux:button>
        </div>
    </div>

    {{-- Content area --}}
    <div class="flex flex-1 min-h-0">
        {{-- Script mode: IDE (4/5) + File tree (1/5) --}}
        @if ($mode === 'script')
            <div class="flex flex-col flex-1 min-h-0 border-r border-zinc-200 dark:border-zinc-800"
                 style="width: 80%;">
                <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
                    <div class="shrink-0 flex items-center bg-zinc-50 dark:bg-zinc-950 overflow-x-auto
                                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden border-b border-zinc-200 dark:border-zinc-800">
                        @foreach ($openTabs as $tab)
                            @php $isActive = $tab === $activeFilePath; @endphp
                            <div
                                wire:click="selectFile('{{ $tab }}')"
                                class="group/tab flex items-center gap-2 px-3 py-1.5 text-sm border-r
                                       border-zinc-200 dark:border-zinc-800 shrink-0 cursor-pointer select-none
                                       {{ $isActive
                                           ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 -mb-px border-b border-b-white dark:border-b-zinc-800'
                                           : 'bg-zinc-50 dark:bg-zinc-950 text-zinc-500 dark:text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-900/50' }}">
                                <flux:icon.document-text class="size-3.5 shrink-0" />
                                <span class="truncate max-w-[160px]">{{ basename($tab) }}</span>
                                <span
                                    x-show="Alpine.store('editor').buffers['{{ $tab }}']?.dirty"
                                    class="size-1.5 shrink-0 rounded-full bg-amber-400"
                                ></span>
                                <button
                                    wire:click.stop="closeTab('{{ $tab }}')"
                                    class="rounded p-0.5 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-400
                                           hover:text-zinc-700 dark:hover:text-zinc-300 opacity-0 group-hover/tab:opacity-100
                                           transition-opacity">
                                    <flux:icon.x-mark class="size-3" />
                                </button>
                            </div>
                        @endforeach
                        <div class="flex-1 self-stretch bg-zinc-50 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800"></div>
                    </div>

                    @if ($activeFilePath)
                        <x-code-editor
                            name="script_content"
                            wire:key="editor-{{ $script->id }}-{{ $activeFilePath }}"
                            :value="$this->readFile($activeFilePath)"
                            :language="$this->detectLanguage($activeFilePath)"
                            height="100%"
                            :editable="true"
                            :save-path="$activeFilePath"
                            class="!rounded-none !border-0 flex-1"
                        />
                    @else
                        <div class="flex-1 flex items-center justify-center text-zinc-400 dark:text-zinc-600 text-sm">
                            Select a file to edit
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col bg-zinc-50 dark:bg-zinc-950 overflow-hidden"
                 style="width: 20%;">
                <div class="flex items-center gap-0.5 px-1 py-0.5 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
                    <button
                        @click="startCreate('file')"
                        title="New File"
                        class="p-1 rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors"
                    >
                        <flux:icon.document-plus class="size-4" />
                    </button>
                    <button
                        @click="startCreate('folder')"
                        title="New Folder"
                        class="p-1 rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors"
                    >
                        <flux:icon.folder-plus class="size-4" />
                    </button>
                    <div class="flex-1"></div>
                    <button
                        @click="$refs.fileUploadInput.click()"
                        title="Upload Files"
                        class="p-1 rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors"
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
                        class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded px-2 py-1
                               text-sm text-zinc-800 dark:text-zinc-200 outline-none focus:border-blue-500"
                        x-init="$el.focus()"
                    />
                </div>

                <div class="flex-1 overflow-y-auto p-1" @dragenter="$event.preventDefault()" @dragover.prevent @drop.prevent="$event.dataTransfer.files.length > 0 && handleFileUpload({target: {files: $event.dataTransfer.files}, preventDefault: () => {}, stopPropagation: () => {}})">
                    @foreach ($fileTree as $item)
                        <x-script-tree-item :item="$item" :depth="0" path="" :activeFilePath="$activeFilePath" />
                    @endforeach

                    @if (empty($fileTree))
                        <div class="text-zinc-400 dark:text-zinc-600 text-sm p-2">
                            No files yet.
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Agent mode: full-width chat --}}
        @if ($mode === 'agent')
            <div class="flex-1 flex flex-col min-h-0">
                <livewire:script-agent-chat :project="$project" :test="$test" :script="$script" wire:key="agent-chat-{{ $script->id }}" />
            </div>
        @endif
    </div>
</div>
