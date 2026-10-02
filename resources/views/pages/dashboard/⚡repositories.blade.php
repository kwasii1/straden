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

        SyncRepositoryJob::dispatch($repository, auth()->id());

        Flux::toast(variant: 'info', text: 'Sync dispatched.');
    }

    private function resetRepositoryForm(): void
    {
        $this->reset(['name', 'local_path']);
        $this->type = 'local_path';
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="Repositories" description="Connect git repositories or local paths to give the agent file context.">
        <x-slot:actions>
            <flux:modal.trigger name="create-repository">
                <flux:button icon="plus">Add local path</flux:button>
            </flux:modal.trigger>
            <flux:button variant="primary" icon="plus" :href="route('projects.git-providers', ['project' => $project])" wire:navigate>
                Add from git provider
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    <section class="ui-panel overflow-hidden">
        @if ($this->repositories->isEmpty())
            <x-empty-state icon="folder-git-2" title="No repositories connected" description="Add a git repository from a provider or connect a local path to get started." />
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th class="text-right!">Last synced</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->repositories as $repository)
                            <tr wire:key="repo-{{ $repository->id }}">
                                <td>
                                    <a
                                        wire:navigate
                                        href="{{ route('projects.repository-browse', ['project' => $this->project, 'repository' => $repository]) }}"
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                                            @if ($repository->type === 'git')
                                                <flux:icon.folder-git-2 variant="micro" />
                                            @else
                                                <flux:icon.folder variant="micro" />
                                            @endif
                                        </span>
                                        <span class="truncate font-medium text-zinc-900 hover:underline hover:decoration-zinc-300 hover:underline-offset-[3px]">{{ $repository->name }}</span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $repository->type === 'git' ? 'Git' : 'Local path' }}</span>

                                        @if ($repository->connector)
                                            <span class="text-zinc-500">{{ $repository->connector->name }}</span>
                                        @elseif ($repository->type === 'git')
                                            <flux:tooltip content="This repository's git provider connection has been removed. Reconnect to sync.">
                                                <x-status-badge status="warning" label="Reconnect needed" />
                                            </flux:tooltip>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($repository->sync_status === 'synced')
                                        <x-status-badge status="synced" />
                                    @elseif ($repository->sync_status === 'syncing')
                                        <x-status-badge status="syncing" />
                                    @elseif ($repository->sync_status === 'auth_error')
                                        <flux:tooltip :content="$repository->sync_error ?? 'Authentication failed'">
                                            <x-status-badge status="failed" label="Auth error" />
                                        </flux:tooltip>
                                    @elseif ($repository->sync_status === 'failed')
                                        <flux:tooltip :content="$repository->sync_error ?? 'Sync failed'">
                                            <x-status-badge status="failed" />
                                        </flux:tooltip>
                                    @else
                                        <x-status-badge status="pending" />
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap text-zinc-500">
                                    {{ $repository->last_synced_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($repository->sync_status !== 'syncing')
                                            <button
                                                type="button"
                                                wire:click="syncRepository('{{ $repository->id }}')"
                                                class="ui-icon-button"
                                                aria-label="Sync repository"
                                                title="Sync"
                                            >
                                                <flux:icon.arrow-path variant="micro" />
                                            </button>
                                        @endif

                                        <a
                                            href="{{ route('projects.repository-browse', ['project' => $this->project, 'repository' => $repository]) }}"
                                            wire:navigate
                                            class="ui-icon-button"
                                            aria-label="Browse files"
                                            title="Browse"
                                        >
                                            <flux:icon.eye variant="micro" />
                                        </a>

                                        <button
                                            type="button"
                                            wire:click="deleteRepository('{{ $repository->id }}')"
                                            wire:confirm="Are you sure you want to remove this repository?"
                                            class="ui-icon-button hover:bg-red-50 hover:text-red-600"
                                            aria-label="Remove repository"
                                            title="Remove"
                                        >
                                            <flux:icon.trash variant="micro" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <flux:modal name="create-repository" class="md:w-[28rem]">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Add local path</flux:heading>
                <flux:text class="mt-1">Connect a local directory to give the agent file context.</flux:text>
            </div>

            <form wire:submit="addRepository" class="flex flex-col gap-6">
                <flux:input wire:model="name" label="Name" placeholder="My local codebase" />

                <flux:field>
                    <flux:label>Path</flux:label>
                    <flux:input wire:model="local_path" placeholder="/home/user/projects/my-app" class:input="font-mono" />
                    <flux:description>The server needs read access to this path.</flux:description>
                    <flux:error name="local_path" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Add repository</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

@script
<script>
    const projectId = '{{ $project->id }}';

    window.Echo.private('project.' + projectId)
        .listen('.RepositorySyncUpdated', () => {
            console.log("HELLO SYNC COMPLETED")
            $wire.$refresh();
        });
</script>
@endscript
