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
    public string $type = 'git';
    public string $git_url = '';
    public string $git_branch = 'main';
    public string $git_auth_type = 'none';
    public string $git_credentials = '';
    public string $local_path = '';

    #[Computed]
    public function repositories()
    {
        return $this->project->repositories()->orderBy('created_at', 'desc')->get();
    }

    public function addRepository(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:git,local_path',
        ];

        if ($this->type === 'git') {
            $rules['git_url'] = 'required|url|max:2048';
            $rules['git_branch'] = 'required|string|max:255';
            $rules['git_auth_type'] = 'required|in:none,ssh_key,token';
        } else {
            $rules['local_path'] = 'required|string|max:1024';
        }

        $this->validate($rules);

        $this->project->repositories()->create([
            'name' => $this->name,
            'type' => $this->type,
            'git_url' => $this->type === 'git' ? $this->git_url : null,
            'git_branch' => $this->type === 'git' ? $this->git_branch : 'main',
            'git_auth_type' => $this->type === 'git' ? $this->git_auth_type : null,
            'git_credentials' => $this->type === 'git' && $this->git_auth_type !== 'none' ? $this->git_credentials : null,
            'local_path' => $this->type === 'local_path' ? $this->local_path : null,
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

    public function updatedType(): void
    {
        $this->reset(['git_url', 'git_branch', 'git_auth_type', 'git_credentials', 'local_path']);
        $this->git_branch = 'main';
        $this->git_auth_type = 'none';
    }

    private function resetRepositoryForm(): void
    {
        $this->reset(['name', 'type', 'git_url', 'git_branch', 'git_auth_type', 'git_credentials', 'local_path']);
        $this->type = 'git';
        $this->git_branch = 'main';
        $this->git_auth_type = 'none';
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Repositories</flux:heading>
        <flux:text>Connect git repositories or local paths to provide file context for AI-assisted operations.</flux:text>
    </div>

    <div class="flex w-full justify-end">
        <flux:modal.trigger name="create-repository">
            <flux:button variant="primary" icon="plus">Add Repository</flux:button>
        </flux:modal.trigger>
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

                                @if ($repository->sync_status === 'synced')
                                    <flux:badge size="sm" variant="subtle" color="emerald">Synced</flux:badge>
                                @elseif ($repository->sync_status === 'syncing')
                                    <flux:badge size="sm" variant="subtle" color="amber">Syncing...</flux:badge>
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
                        Add a git repository or local path to get started.
                    </flux:text>
                </div>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-repository" class="md:w-1/2">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Repository</flux:heading>
                <flux:text class="mt-2">Connect a repository to provide file context for AI-assisted operations.</flux:text>
            </div>

            <form wire:submit="addRepository" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Repository Name" placeholder="My Repository" />

                    <flux:select wire:model.live="type" label="Repository Type">
                        <flux:select.option value="git">Git Repository</flux:select.option>
                        <flux:select.option value="local_path">Local Path</flux:select.option>
                    </flux:select>
                </div>

                @if ($type === 'git')
                    <flux:input wire:model="git_url" label="Git URL" placeholder="https://github.com/user/repo.git" />

                    <flux:input wire:model="git_branch" label="Branch" placeholder="main" />

                    <flux:select wire:model.live="git_auth_type" label="Authentication">
                        <flux:select.option value="none">None (Public)</flux:select.option>
                        <flux:select.option value="ssh_key">SSH Key</flux:select.option>
                        <flux:select.option value="token">Access Token</flux:select.option>
                    </flux:select>

                    @if ($git_auth_type !== 'none')
                        <flux:field>
                            <flux:label>
                                {{ $git_auth_type === 'ssh_key' ? 'SSH Private Key' : 'Access Token' }}
                            </flux:label>
                            <flux:textarea
                                wire:model="git_credentials"
                                rows="4"
                                :placeholder="$git_auth_type === 'ssh_key' ? 'Paste your private SSH key...' : 'Paste your access token...'"
                                class="font-mono text-sm"
                            />
                            <flux:description>Credentials are encrypted before storage.</flux:description>
                        </flux:field>
                    @endif
                @else
                    <flux:input wire:model="local_path" label="Local Path" placeholder="/home/user/projects/my-app" />
                    <flux:description>The server must have read access to this path.</flux:description>
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Add Repository</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
