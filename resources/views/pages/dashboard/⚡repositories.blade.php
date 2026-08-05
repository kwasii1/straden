<?php

use App\Jobs\SyncRepositoryJob;
use App\Models\Project;
use App\Models\Repository;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public string $name = '';
    public string $type = 'local_path';
    public string $local_path = '';

    #[Computed]
    public function repositories()
    {
        return $this->project->repositories()->with('connector')->orderBy('created_at', 'desc')->get();
    }

    public function addRepository(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:local_path',
            'local_path' => 'required|string|max:1024',
        ];

        $this->validate($rules);

        $this->project->repositories()->create([
            'name' => $this->name,
            'type' => $this->type,
            'local_path' => $this->local_path,
        ]);

        Flux::modal('create-repository')->close();

        Flux::toast(variant: 'success', text: 'Repository added successfully.');

        $this->resetRepositoryForm();
    }

    public function deleteRepository(Repository $repository): void
    {
        $repository->delete();

        Flux::toast(variant: 'success', text: 'Repository removed.');
    }

    public function syncRepository(Repository $repository): void
    {
        $repository->update([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ]);

        SyncRepositoryJob::dispatch($repository);

        Flux::toast(variant: 'info', text: 'Sync dispatched.');
    }

    private function resetRepositoryForm(): void
    {
        $this->reset(['name', 'local_path']);
        $this->type = 'local_path';
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Repositories</flux:heading>
        <flux:text>Connect git repositories or local paths to provide file context for AI-assisted operations.</flux:text>
    </div>

    <div class="flex w-full justify-end gap-x-3">
        <flux:modal.trigger name="create-repository">
            <flux:button variant="primary" icon="plus">Add Local Path</flux:button>
        </flux:modal.trigger>
        <flux:button variant="primary" icon="plus" :href="route('projects.settings', ['project' => $project])" wire:navigate>
            Add from Git Provider
        </flux:button>
    </div>

    <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
        @forelse ($this->repositories as $repository)
            <div wire:key="repo-{{ $repository->id }}" class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                <div class="flex items-center gap-x-4">
                    <div class="flex items-center gap-x-3">
                        @if ($repository->type === 'git')
                            <flux:icon.folder-git-2 class="size-5 text-zinc-400" />
                        @else
                            <flux:icon.folder class="size-5 text-zinc-400" />
                        @endif

                        <div>
                            <flux:heading class="font-medium">{{ $repository->name }}</flux:heading>
                            <div class="flex items-center gap-x-2 mt-0.5">
                                <flux:badge size="sm" variant="subtle" color="zinc">
                                    {{ $repository->type === 'git' ? 'Git' : 'Local Path' }}
                                </flux:badge>

                                @if ($repository->connector)
                                    <flux:badge size="sm" variant="subtle" color="zinc">
                                        {{ $repository->connector->name }}
                                    </flux:badge>
                                @elseif ($repository->type === 'git')
                                    <flux:tooltip content="This repository's git provider connection has been removed. Reconnect to sync.">
                                        <flux:badge size="sm" variant="subtle" color="amber">Reconnect Needed</flux:badge>
                                    </flux:tooltip>
                                @endif

                                @if ($repository->sync_status === 'synced')
                                    <flux:badge size="sm" variant="subtle" color="emerald">Synced</flux:badge>
                                @elseif ($repository->sync_status === 'syncing')
                                    <flux:badge size="sm" variant="subtle" color="amber">Syncing...</flux:badge>
                                @elseif ($repository->sync_status === 'auth_error')
                                    <flux:tooltip :content="$repository->sync_error ?? 'Authentication failed'">
                                        <flux:badge size="sm" variant="subtle" color="red">Auth Error</flux:badge>
                                    </flux:tooltip>
                                @elseif ($repository->sync_status === 'failed')
                                    <flux:tooltip :content="$repository->sync_error ?? 'Sync failed'">
                                        <flux:badge size="sm" variant="subtle" color="red">Failed</flux:badge>
                                    </flux:tooltip>
                                @else
                                    <flux:badge size="sm" variant="subtle" color="zinc">Pending</flux:badge>
                                @endif

                                @if ($repository->last_synced_at)
                                    <flux:text class="text-xs">
                                        Synced {{ $repository->last_synced_at->diffForHumans() }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-x-2">
                    @if ($repository->sync_status !== 'syncing')
                        <flux:button
                            wire:click="syncRepository('{{ $repository->id }}')"
                            variant="ghost"
                            size="sm"
                            icon="arrow-path"
                        >
                            Sync
                        </flux:button>
                    @endif

                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="eye"
                        :href="route('projects.repository-browse', ['project' => $this->project, 'repository' => $repository])"
                        wire:navigate
                    >
                        Browse
                    </flux:button>

                    <flux:button
                        wire:click="deleteRepository('{{ $repository->id }}')"
                        wire:confirm="Are you sure you want to remove this repository?"
                        variant="ghost"
                        size="sm"
                        icon="trash"
                        class="text-red-500 hover:text-red-600"
                    />
                </div>
            </div>
        @empty
            <div class="flex h-40 w-full items-center justify-center">
                <div class="flex flex-col items-center gap-y-2">
                    <flux:icon.folder-git-2 class="size-10 text-zinc-400" />
                    <flux:text class="text-center">
                        No repositories connected.
                    </flux:text>
                    <flux:text class="text-center text-sm">
                        Add a git repository from a provider or connect a local path to get started.
                    </flux:text>
                </div>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-repository" class="md:w-1/2">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Local Path</flux:heading>
                <flux:text class="mt-2">Connect a local directory to provide file context for AI-assisted operations.</flux:text>
            </div>

            <form wire:submit="addRepository" class="space-y-6">
                <flux:input wire:model="name" label="Repository Name" placeholder="My Local Codebase" />

                <flux:input wire:model="local_path" label="Local Path" placeholder="/home/user/projects/my-app" />
                <flux:description>The server must have read access to this path.</flux:description>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Add Repository</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
