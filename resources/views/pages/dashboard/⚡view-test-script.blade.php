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
    class="flex flex-col h-full bg-white"
>
    {{-- Toolbar --}}
    <div class="z-10 flex h-11 shrink-0 items-center justify-between gap-3 border-b border-zinc-200 bg-white px-3">
        <nav class="flex min-w-0 items-center gap-1.5 text-sm" aria-label="Breadcrumb">
            <a href="{{ route('projects.view-test', ['project' => $project, 'test' => $test]) }}" wire:navigate class="truncate text-zinc-500 hover:text-zinc-900">{{ $test->name }}</a>
            <flux:icon.chevron-right variant="micro" class="shrink-0 text-zinc-300" />
            <span class="truncate font-medium text-zinc-900">{{ $script->name }}</span>
        </nav>

        <div class="inline-flex shrink-0 items-center rounded-lg bg-zinc-100 p-0.5" role="group" aria-label="View">
            <button
                type="button"
                wire:click="$set('mode', 'script')"
                aria-pressed="{{ $mode === 'script' ? 'true' : 'false' }}"
                @class([
                    'h-7 rounded-md px-3 text-xs font-medium',
                    'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' => $mode === 'script',
                    'text-zinc-500 hover:text-zinc-900' => $mode !== 'script',
                ])
            >
                Script
            </button>
            <button
                type="button"
                wire:click="$set('mode', 'agent')"
                aria-pressed="{{ $mode === 'agent' ? 'true' : 'false' }}"
                @class([
                    'h-7 rounded-md px-3 text-xs font-medium',
                    'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' => $mode === 'agent',
                    'text-zinc-500 hover:text-zinc-900' => $mode !== 'agent',
                ])
            >
                Agent
            </button>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <label class="flex shrink-0 cursor-pointer items-center gap-2 select-none" title="Keep k6 logs after the run completes">
                <flux:switch wire:model.live="persistLogs" />
                <span class="text-xs text-zinc-600">Persist logs</span>
            </label>

            @if ($this->currentRun)
                <flux:button
                    wire:navigate
                    :href="route('projects.runs.view', ['project' => $this->project, 'run' => $this->currentRun])"
                    size="sm"
                    variant="ghost"
                >
                    <x-spinner class="size-3.5 text-zinc-500" />
                    View current run
                </flux:button>
            @endif

            <flux:button wire:click="runTest" wire:loading.attr="disabled" icon="play" variant="primary" size="sm">
                Run test
            </flux:button>
        </div>
    </div>

    {{-- Content area --}}
    <div class="flex min-h-0 flex-1">
        {{-- Script mode: explorer + editor --}}
        @if ($mode === 'script')
            <aside class="flex w-64 shrink-0 flex-col overflow-hidden border-r border-zinc-200 bg-zinc-50">
                <div class="flex h-10 shrink-0 items-center gap-0.5 border-b border-zinc-200 pr-1.5 pl-3">
                    <span class="flex-1 text-[13px] font-medium text-zinc-900">Explorer</span>
                    <button type="button" @click="startCreate('file')" title="New file" aria-label="New file" class="ui-icon-button size-6">
                        <flux:icon.document-plus variant="micro" />
                    </button>
                    <button type="button" @click="startCreate('folder')" title="New folder" aria-label="New folder" class="ui-icon-button size-6">
                        <flux:icon.folder-plus variant="micro" />
                    </button>
                    <button type="button" @click="$refs.fileUploadInput.click()" title="Upload files" aria-label="Upload files" class="ui-icon-button size-6">
                        <flux:icon.arrow-up-tray variant="micro" />
                    </button>
                    <input type="file" x-ref="fileUploadInput" multiple hidden @change="handleFileUpload($event)" />
                </div>

                <div
                    x-show="creating"
                    x-cloak
                    class="shrink-0 px-1.5 pt-1.5"
                    x-transition:enter="transition ease-snappy duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                >
                    <input
                        x-ref="newItemInput"
                        x-model="newItemName"
                        @keydown.enter="submitCreate()"
                        @keydown.escape="cancelCreate()"
                        @blur="cancelCreate()"
                        :placeholder="createType === 'file' ? 'File name…' : 'Folder name…'"
                        x-effect="creating && $nextTick(() => $el.focus())"
                        class="ui-input h-7 rounded-md px-2 text-[13px]"
                    />
                </div>

                <div class="flex-1 overflow-y-auto p-1.5" @dragenter="$event.preventDefault()" @dragover.prevent @drop.prevent="$event.dataTransfer.files.length > 0 && handleFileUpload({target: {files: $event.dataTransfer.files}, preventDefault: () => {}, stopPropagation: () => {}})">
                    @foreach ($fileTree as $item)
                        <x-script-tree-item :item="$item" :depth="0" path="" :activeFilePath="$activeFilePath" />
                    @endforeach

                    @if (empty($fileTree))
                        <x-empty-state compact icon="document" title="No files yet" description="Create a file or drop one here." />
                    @endif
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="flex h-10 shrink-0 items-stretch overflow-x-auto bg-zinc-50 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($openTabs as $tab)
                        @php $isActive = $tab === $activeFilePath; @endphp
                        <div
                            wire:key="tab-{{ md5($tab) }}"
                            wire:click="selectFile('{{ $tab }}')"
                            title="{{ $tab }}"
                            @class([
                                'group/tab flex shrink-0 cursor-pointer items-center gap-2 border-r border-b border-zinc-200 pr-1.5 pl-3 text-[13px] select-none',
                                'border-b-transparent bg-white text-zinc-900' => $isActive,
                                'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900' => ! $isActive,
                            ])
                        >
                            <x-file-icon :name="$tab" class="size-3.5" />
                            <span class="max-w-[180px] truncate">{{ basename($tab) }}</span>
                            <span class="relative flex size-5 items-center justify-center">
                                <span
                                    x-show="$store.editor.buffers['{{ $tab }}']?.dirty"
                                    class="size-2 rounded-full bg-zinc-400 group-hover/tab:hidden"
                                ></span>
                                <button
                                    wire:click.stop="closeTab('{{ $tab }}')"
                                    title="Close"
                                    aria-label="Close {{ basename($tab) }}"
                                    class="absolute inset-0 hidden items-center justify-center rounded text-zinc-400 hover:bg-zinc-200 hover:text-zinc-900 group-hover/tab:flex"
                                    x-bind:class="{ '!flex': ! $store.editor.buffers['{{ $tab }}']?.dirty && @js($isActive) }"
                                >
                                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                                </button>
                            </span>
                        </div>
                    @endforeach
                    <div class="min-w-0 flex-1 border-b border-zinc-200"></div>
                </div>

                @if ($activeFilePath)
                    <div class="flex h-7 shrink-0 items-center gap-1 border-b border-zinc-200 bg-white px-3 text-xs text-zinc-500">
                        <span>{{ $script->name }}</span>
                        @foreach (explode('/', $activeFilePath) as $segment)
                            <flux:icon.chevron-right variant="micro" class="size-3 text-zinc-300" />
                            <span @class(['text-zinc-900' => $loop->last])>{{ $segment }}</span>
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
                    <div class="flex flex-1 items-center justify-center">
                        <x-empty-state icon="code-bracket" title="No file open" description="Select a file in the explorer to start editing." />
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
