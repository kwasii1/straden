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

    /**
     * Save a file. Autosaves pass $silent so typing doesn't spam toasts; the
     * editor's status bar shows the result instead.
     */
    public function saveFile(string $path, string $content, bool $silent = false): bool
    {
        try {
            $this->fm()->updateFile($path, $content);
        } catch (\InvalidArgumentException $e) {
            $this->addError('path', $e->getMessage());
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return false;
        }

        if (! $silent) {
            Flux::toast(variant: 'success', text: "Saved {$path}.");
        }

        return true;
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
            $wire.saveFile(detail.path, detail.content, detail.silent ?? false)
                .then((saved) => Alpine.store('editor').markSaved(detail.path, detail.content, saved === true))
                .catch(() => Alpine.store('editor').markSaved(detail.path, detail.content, false));
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
        {{-- Script mode: explorer + editor --}}
        @if ($mode === 'script')
            <aside class="flex w-64 shrink-0 flex-col overflow-hidden border-r border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex h-9 shrink-0 items-center gap-0.5 border-b border-zinc-200 pl-3 pr-1.5 dark:border-zinc-800">
                    <span class="flex-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Explorer</span>
                    <button @click="startCreate('file')" title="New file" class="rounded p-1 text-zinc-500 transition-colors hover:bg-zinc-200 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100">
                        <flux:icon.document-plus variant="micro" class="size-4" />
                    </button>
                    <button @click="startCreate('folder')" title="New folder" class="rounded p-1 text-zinc-500 transition-colors hover:bg-zinc-200 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100">
                        <flux:icon.folder-plus variant="micro" class="size-4" />
                    </button>
                    <button @click="$refs.fileUploadInput.click()" title="Upload files" class="rounded p-1 text-zinc-500 transition-colors hover:bg-zinc-200 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100">
                        <flux:icon.arrow-up-tray variant="micro" class="size-4" />
                    </button>
                    <input type="file" x-ref="fileUploadInput" multiple hidden @change="handleFileUpload($event)" />
                </div>

                <div x-show="creating" x-cloak class="shrink-0 px-2 pt-2" x-transition>
                    <input
                        x-ref="newItemInput"
                        x-model="newItemName"
                        @keydown.enter="submitCreate()"
                        @keydown.escape="cancelCreate()"
                        @blur="cancelCreate()"
                        :placeholder="createType === 'file' ? 'File name…' : 'Folder name…'"
                        x-effect="creating && $nextTick(() => $el.focus())"
                        class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm text-zinc-800 outline-none focus:border-blue-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                </div>

                <div class="flex-1 overflow-y-auto p-1.5" @dragenter="$event.preventDefault()" @dragover.prevent @drop.prevent="$event.dataTransfer.files.length > 0 && handleFileUpload({target: {files: $event.dataTransfer.files}, preventDefault: () => {}, stopPropagation: () => {}})">
                    @foreach ($fileTree as $item)
                        <x-script-tree-item :item="$item" :depth="0" path="" :activeFilePath="$activeFilePath" />
                    @endforeach

                    @if (empty($fileTree))
                        <div class="p-2 text-sm text-zinc-500 dark:text-zinc-400">No files yet.</div>
                    @endif
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="flex h-9 shrink-0 items-stretch overflow-x-auto border-b border-zinc-200 bg-zinc-50 [scrollbar-width:none] dark:border-zinc-800 dark:bg-zinc-900 [&::-webkit-scrollbar]:hidden">
                    @foreach ($openTabs as $tab)
                        @php $isActive = $tab === $activeFilePath; @endphp
                        <div
                            wire:key="tab-{{ md5($tab) }}"
                            wire:click="selectFile('{{ $tab }}')"
                            title="{{ $tab }}"
                            @class([
                                'group/tab relative flex shrink-0 cursor-pointer select-none items-center gap-2 border-r border-zinc-200 px-3 text-[13px] dark:border-zinc-800',
                                'bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100' => $isActive,
                                'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200' => ! $isActive,
                            ])
                        >
                            @if ($isActive)
                                <span class="absolute inset-x-0 top-0 h-0.5 bg-zinc-900 dark:bg-white"></span>
                            @endif
                            <x-file-icon :name="$tab" class="size-3.5" />
                            <span class="max-w-[180px] truncate">{{ basename($tab) }}</span>
                            <span class="relative flex size-4 items-center justify-center">
                                <span
                                    x-show="$store.editor.buffers['{{ $tab }}']?.dirty"
                                    class="size-2 rounded-full bg-zinc-500 group-hover/tab:hidden dark:bg-zinc-300"
                                ></span>
                                <button
                                    wire:click.stop="closeTab('{{ $tab }}')"
                                    title="Close"
                                    class="absolute inset-0 hidden items-center justify-center rounded text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 group-hover/tab:flex dark:hover:bg-white/10 dark:hover:text-zinc-200"
                                    x-bind:class="{ '!flex': ! $store.editor.buffers['{{ $tab }}']?.dirty && @js($isActive) }"
                                >
                                    <flux:icon.x-mark variant="micro" class="size-3" />
                                </button>
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($activeFilePath)
                    <div class="flex h-7 shrink-0 items-center gap-1 border-b border-zinc-200 bg-white px-3 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-400">
                        <span>{{ $script->name }}</span>
                        @foreach (explode('/', $activeFilePath) as $segment)
                            <flux:icon.chevron-right variant="micro" class="size-3 text-zinc-400" />
                            <span @class(['text-zinc-800 dark:text-zinc-200' => $loop->last])>{{ $segment }}</span>
                        @endforeach
                    </div>

                    <x-code-editor
                        name="script_content"
                        wire:key="editor-{{ $script->id }}-{{ $activeFilePath }}"
                        :value="$this->readFile($activeFilePath)"
                        :language="$this->detectLanguage($activeFilePath)"
                        height="100%"
                        :editable="true"
                        :save-path="$activeFilePath"
                        class="min-h-0 flex-1 !rounded-none !border-0"
                    />
                @else
                    <div class="flex flex-1 flex-col items-center justify-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon.code-bracket class="size-8 text-zinc-300 dark:text-zinc-600" />
                        Select a file from the explorer to start editing.
                    </div>
                @endif
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
