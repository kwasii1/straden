<?php

use App\Enums\ConnectorType;
use App\GitProviders\GitProviderResolver;
use App\Models\Connector;
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

    public Connector $connector;

    public string $search = '';

    public ?string $selectedProject = null;

    public ?string $selectedRepoFullName = null;

    public function mount(): void
    {
        if (! $this->connector->isGitProvider()) {
            abort(404);
        }
    }

    #[Computed]
    public function repos()
    {
        $settings = $this->connector->settings ?? [];
        $cachedRepos = $settings['cached_repos'] ?? [];

        if ($this->search === '' || $this->search === '0') {
            return $cachedRepos;
        }

        $lower = mb_strtolower($this->search);

        return array_filter($cachedRepos, function (array $repo) use ($lower) {
            return str_contains(mb_strtolower($repo['full_name']), $lower);
        });
    }

    #[Computed]
    public function projects()
    {
        if (! $this->connector->type === ConnectorType::AzureDevOps) {
            return [];
        }

        $settings = $this->connector->settings ?? [];

        return $settings['cached_projects'] ?? [];
    }

    #[Computed]
    public function projectRepos()
    {
        if ($this->selectedProject === null) {
            return [];
        }

        $organization = $this->connector->settings['organization'] ?? '';

        try {
            $provider = GitProviderResolver::for($this->connector->type);

            $repos = $provider->listRepositories($this->connector->token, [
                'organization' => $organization,
                'project' => $this->selectedProject,
            ]);

            $settings = $this->connector->settings ?? [];
            $settings['cached_repos'] = $repos;
            $settings['repos_fetched_at'] = now()->toIso8601String();

            $this->connector->update(['settings' => $settings]);

            return $repos;
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to list repositories: '.$e->getMessage());

            return [];
        }
    }

    public function refreshRepos(): void
    {
        try {
            $provider = GitProviderResolver::for($this->connector->type);

            $context = [];
            $settings = $this->connector->settings ?? [];

            if ($this->connector->type === ConnectorType::Bitbucket) {
                $context['workspace'] = $settings['workspace'] ?? '';
            }

            if ($this->connector->type === ConnectorType::AzureDevOps) {
                $context['organization'] = $settings['organization'] ?? '';
                $context['project'] = $this->selectedProject;
            }

            $repos = $provider->listRepositories(decrypt($this->connector->token), $context);

            if ($this->connector->type === ConnectorType::AzureDevOps && $this->selectedProject === null) {
                $settings['cached_projects'] = $repos;
            } else {
                $settings['cached_repos'] = $repos;
            }

            $settings['repos_fetched_at'] = now()->toIso8601String();

            $this->connector->update(['settings' => $settings]);

            Flux::toast(variant: 'success', text: 'Repositories refreshed successfully.');

            unset($this->repos, $this->projectRepos, $this->projects);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to refresh repositories: '.$e->getMessage());
        }
    }

    public function selectRepo(string $fullName): void
    {
        $this->selectedRepoFullName = $fullName;
    }

    public function addRepository(): void
    {
        if ($this->selectedRepoFullName === null) {
            return;
        }

        $settings = $this->connector->settings ?? [];
        $cachedRepos = $settings['cached_repos'] ?? [];
        $selectedRepo = null;

        foreach ($cachedRepos as $repo) {
            if ($repo['full_name'] === $this->selectedRepoFullName) {
                $selectedRepo = $repo;

                break;
            }
        }

        if ($selectedRepo === null) {
            Flux::toast(variant: 'error', text: 'Selected repository not found.');

            return;
        }

        $name = basename($selectedRepo['full_name']);

        Repository::create([
            'project_id' => $this->project->id,
            'connector_id' => $this->connector->id,
            'name' => $name,
            'type' => 'git',
            'full_name' => $selectedRepo['full_name'],
            'git_url' => $selectedRepo['clone_url'],
            'git_branch' => $selectedRepo['default_branch'],
            'sync_status' => 'pending',
        ]);

        Flux::toast(variant: 'success', text: 'Repository added successfully.');

        $this->redirect(route('projects.repositories', [
            'project' => $this->project,
        ]), navigate: true);
    }

    public function getRepoIcon(string $fullName): string
    {
        return match ($this->connector->type) {
            ConnectorType::Bitbucket => 'text-blue-600 dark:text-blue-400',
            ConnectorType::GitLab => 'text-orange-600 dark:text-orange-400',
            ConnectorType::AzureDevOps => 'text-sky-600 dark:text-sky-400',
            default => 'text-zinc-500 dark:text-zinc-400',
        };
    }

    public function getRepoIconName(): string
    {
        return match ($this->connector->type) {
            ConnectorType::GitHub => 'folder-git-2',
            ConnectorType::GitLab => 'folder-git-2',
            ConnectorType::Bitbucket => 'folder-git-2',
            ConnectorType::AzureDevOps => 'folder-git-2',
            default => 'folder-git-2',
        };
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <div class="flex items-center gap-x-3">
            <flux:button
                variant="ghost"
                size="sm"
                icon="arrow-left"
                :href="route('projects.git-providers', ['project' => $project])"
                wire:navigate
            />
            <div>
                <flux:heading size="xl">Browse Repositories</flux:heading>
                <flux:text>{{ $connector->name }} &middot; {{ $connector->type->label() }}</flux:text>
            </div>
        </div>
    </div>

    @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject === null)
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Select a Project</flux:heading>
                <flux:button
                    wire:click="refreshRepos"
                    variant="ghost"
                    size="sm"
                    icon="arrow-path"
                >
                    Refresh Projects
                </flux:button>
            </div>

            <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
                @forelse ($this->projects as $projectItem)
                    <button
                        wire:key="project-{{ $projectItem['name'] }}"
                        wire:click="$set('selectedProject', '{{ $projectItem['name'] }}')"
                        class="flex items-center gap-x-3 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition text-left"
                    >
                        <flux:icon.folder class="size-5 text-sky-500" />
                        <div>
                            <flux:heading class="font-medium">{{ $projectItem['name'] }}</flux:heading>
                            @if (!empty($projectItem['description']))
                                <flux:text class="text-xs">{{ $projectItem['description'] }}</flux:text>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="flex h-40 w-full items-center justify-center">
                        <div class="flex flex-col items-center gap-y-2">
                            <flux:icon.folder class="size-10 text-zinc-400" />
                            <flux:text class="text-center">No projects found.</flux:text>
                            <flux:text class="text-center text-sm">Try refreshing the project list.</flux:text>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-x-4">
                    @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject)
                        <flux:button
                            wire:click="$set('selectedProject', null)"
                            variant="ghost"
                            size="sm"
                            icon="arrow-left"
                        >
                            {{ $selectedProject }}
                        </flux:button>
                    @else
                        <flux:heading size="lg">Select a Repository</flux:heading>
                    @endif
                </div>
                <flux:button
                    wire:click="refreshRepos"
                    variant="ghost"
                    size="sm"
                    icon="arrow-path"
                >
                    Refresh
                </flux:button>
            </div>

            <div class="relative">
                <flux:input
                    wire:model.live.debounce.150ms="search"
                    icon="magnifying-glass"
                    placeholder="Search repositories..."
                    class="mb-4"
                />
            </div>

            <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
                @forelse ($this->repos as $repo)
                    <button
                        wire:key="repo-{{ $repo['full_name'] }}"
                        wire:click="selectRepo('{{ $repo['full_name'] }}')"
                        class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition text-left {{ $selectedRepoFullName === $repo['full_name'] ? 'bg-zinc-50 dark:bg-zinc-800/50 ring-2 ring-zinc-200 dark:ring-zinc-600 rounded-lg' : '' }}"
                    >
                        <div class="flex items-center gap-x-3">
                            <flux:icon.folder-git-2 class="size-5 {{ $this->getRepoIcon($repo['full_name']) }}" />
                            <div>
                                <flux:heading class="font-medium">{{ $repo['full_name'] }}</flux:heading>
                                <div class="flex items-center gap-x-2 mt-0.5">
                                    <flux:text class="text-xs">{{ $repo['default_branch'] }}</flux:text>
                                    @if ($repo['private'])
                                        <flux:badge size="sm" variant="subtle" color="amber">Private</flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="subtle" color="emerald">Public</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($selectedRepoFullName === $repo['full_name'])
                            <flux:badge size="sm" variant="solid" color="zinc">Selected</flux:badge>
                        @endif
                    </button>
                @empty
                    <div class="flex h-40 w-full items-center justify-center">
                        <div class="flex flex-col items-center gap-y-2">
                            <flux:icon.folder-git-2 class="size-10 text-zinc-400" />
                            <flux:text class="text-center">No repositories found.</flux:text>
                            <flux:text class="text-center text-sm">Try refreshing the repository list or adjusting your search.</flux:text>
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($selectedRepoFullName)
                <div class="flex">
                    <flux:spacer />
                    <flux:button wire:click="addRepository" variant="primary">
                        Add Repository
                    </flux:button>
                </div>
            @endif
        </div>
    @endif
</div>
