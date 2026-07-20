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

<div class="flex flex-col h-full">
    <div class="flex items-center gap-x-3 px-4 py-2 border-b border-zinc-200 dark:border-zinc-700 shrink-0">
        <a
            wire:navigate
            href="{{ route('projects.repositories', ['project' => $this->project]) }}"
            class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition"
        >
            <flux:icon.arrow-left class="size-4" />
        </a>

        @if ($repository->type === 'git')
            <flux:icon.folder-git-2 class="size-4 text-zinc-400" />
        @else
            <flux:icon.folder class="size-4 text-zinc-400" />
        @endif

        <flux:heading>{{ $repository->name }}</flux:heading>

        @if ($this->selectedFilePath)
            <flux:separator vertical />
            <flux:text class="text-sm text-zinc-500">{{ $this->selectedFilePath }}</flux:text>
        @endif
    </div>

    <div class="flex flex-1 min-h-0">
        <div class="w-72 border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-950 overflow-y-auto shrink-0">
            @if ($fileTree)
                <div class="p-1">
                    @foreach ($fileTree as $item)
                        @include('components.repository-tree-item', ['item' => $item, 'depth' => 0, 'path' => ''])
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-full p-4 gap-y-2">
                    <flux:icon.folder-open class="size-8 text-zinc-400" />
                    <flux:text class="text-center text-sm">No file tree available. Sync the repository to index its files.</flux:text>
                </div>
            @endif
        </div>

        <div class="flex-1 min-w-0 flex flex-col min-h-0">
            @if ($this->selectedFilePath)
                <div class="flex-1 min-h-0">
                    <x-code-editor
                        name="file_content"
                        :value="$fileContent"
                        :language="$fileLanguage"
                        height="100%"
                        wire:key="editor-{{ $repository->id }}-{{ md5($this->selectedFilePath) }}"
                        class="!rounded-none !border-0 h-full"
                    />
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-full gap-y-3">
                    <flux:icon.document-text class="size-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500">Select a file from the tree to view its contents.</flux:text>
                </div>
            @endif
        </div>
    </div>
</div>
