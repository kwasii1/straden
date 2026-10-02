<?php

use App\Models\Project;
use App\Models\Repository;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app', ['noPadding' => true])]
class extends Component
{
    public Project $project;

    public Repository $repository;

    public ?string $selectedFilePath = null;

    public string $fileContent = '';

    public string $fileLanguage = 'php';

    public ?array $fileTree = null;

    public function mount(): void
    {
        $fileTree = $this->repository->file_tree;

        $this->fileTree = is_array($fileTree) ? $fileTree : null;
    }

    public function selectFile(string $path): void
    {
        $this->selectedFilePath = $path;
        $this->fileContent = $this->readFileContent($path);
        $this->fileLanguage = $this->detectLanguage($path);
    }

    private function readFileContent(string $path): string
    {
        $basePath = $this->resolveBasePath();

        if ($basePath === null) {
            return '// Unable to resolve repository path.';
        }

        $fullPath = rtrim($basePath, '/').'/'.ltrim($path, '/');

        if (! file_exists($fullPath)) {
            return '// File not found on disk: '.$path;
        }

        if (! is_readable($fullPath)) {
            return '// File is not readable: '.$path;
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            return '// Failed to read file: '.$path;
        }

        return $content;
    }

    private function resolveBasePath(): ?string
    {
        if ($this->repository->type === 'local_path' && $this->repository->local_path) {
            return $this->repository->local_path;
        }

        if ($this->repository->type === 'git') {
            $clonePath = storage_path('app/repositories/'.$this->repository->id);

            if (is_dir($clonePath)) {
                return $clonePath;
            }

            return null;
        }

        return null;
    }

    private function detectLanguage(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'js' => 'javascript',
            'ts' => 'typescript',
            'jsx' => 'javascript',
            'tsx' => 'typescript',
            'json' => 'json',
            'html' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'md' => 'markdown',
            'py' => 'python',
            'rb' => 'ruby',
            'java' => 'java',
            'yml', 'yaml' => 'yaml',
            'xml' => 'xml',
            'sql' => 'sql',
            'sh', 'bash' => 'shell',
            'php' => 'php',
            default => 'plaintext',
        };
    }
};
?>

<div class="flex h-full flex-col">
    <div class="flex h-12 shrink-0 items-center gap-3 border-b border-zinc-200 px-4">
        <a
            wire:navigate
            href="{{ route('projects.repositories', ['project' => $this->project]) }}"
            class="ui-icon-button -ml-1.5"
            aria-label="Back to repositories"
            title="Back to repositories"
        >
            <flux:icon.arrow-left variant="micro" />
        </a>

        <div class="flex min-w-0 items-center gap-2">
            @if ($repository->type === 'git')
                <flux:icon.folder-git-2 variant="micro" class="text-zinc-400" />
            @else
                <flux:icon.folder variant="micro" class="text-zinc-400" />
            @endif

            <h1 class="truncate text-sm font-medium text-zinc-900">{{ $repository->name }}</h1>
        </div>

        @if ($this->selectedFilePath)
            <div class="flex min-w-0 items-center gap-1 font-mono text-xs text-zinc-500">
                @foreach (explode('/', $this->selectedFilePath) as $segment)
                    <flux:icon.chevron-right variant="micro" class="size-3.5 shrink-0 text-zinc-300" />
                    <span @class(['truncate', 'text-zinc-900' => $loop->last])>{{ $segment }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex min-h-0 flex-1">
        <aside class="flex w-72 shrink-0 flex-col overflow-hidden border-r border-zinc-200 bg-zinc-50">
            <div class="flex h-9 shrink-0 items-center border-b border-zinc-200 px-3">
                <h2 class="text-sm font-medium text-zinc-900">Files</h2>
            </div>
            @if ($fileTree)
                <div class="flex-1 overflow-y-auto p-1.5">
                    @foreach ($fileTree as $item)
                        @include('components.repository-tree-item', ['item' => $item, 'depth' => 0, 'path' => ''])
                    @endforeach
                </div>
            @else
                <x-empty-state compact icon="folder-open" title="No file tree available" description="Sync the repository to index its files." class="flex-1" />
            @endif
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            @if ($this->selectedFilePath)
                <div class="min-h-0 flex-1">
                    <x-code-editor
                        name="file_content"
                        :value="$fileContent"
                        :language="$fileLanguage"
                        height="100%"
                        wire:key="editor-{{ $repository->id }}-{{ md5($this->selectedFilePath) }}"
                        class="h-full !rounded-none !border-0"
                    />
                </div>
            @else
                <x-empty-state icon="document-text" title="No file selected" description="Select a file from the tree to view its contents." class="flex-1" />
            @endif
        </div>
    </div>
</div>
