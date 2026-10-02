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

    // Branch override state — only touched if the user explicitly opens it.
    public ?string $branchPickerFor = null;

    public array $branches = [];

    public bool $loadingBranches = false;

    public string $overrideBranch = '';

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

        return array_values(array_filter($cachedRepos, function (array $repo) use ($lower) {
            return str_contains(mb_strtolower($repo['full_name']), $lower);
        }));
    }

    #[Computed]
    public function projects()
    {
        // Fixed: previous condition (`! $this->connector->type === ...`) due to
        // operator precedence always evaluated true, so Azure DevOps projects
        // never loaded. Parens fix the intent.
        if (! ($this->connector->type === ConnectorType::AzureDevOps)) {
            return [];
        }

        $settings = $this->connector->settings ?? [];

        return $settings['cached_projects'] ?? [];
    }

    #[Computed]
    public function addedFullNames(): array
    {
        return Repository::query()
            ->where('project_id', $this->project->id)
            ->where('connector_id', $this->connector->id)
            ->pluck('full_name')
            ->all();
    }

    public function selectProject(string $name): void
    {
        $this->selectedProject = $name;
        $this->refreshRepos();
    }

    public function backToProjects(): void
    {
        $this->selectedProject = null;
        $this->search = '';
        unset($this->repos);
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

            $repos = $provider->listRepositories($this->connector->token, $context);

            if ($this->connector->type === ConnectorType::AzureDevOps && $this->selectedProject === null) {
                $settings['cached_projects'] = $repos;
            } else {
                $settings['cached_repos'] = $repos;
            }

            $settings['repos_fetched_at'] = now()->toIso8601String();

            $this->connector->update(['settings' => $settings]);

            Flux::toast(variant: 'success', text: 'Repositories refreshed successfully.');

            unset($this->repos, $this->projects);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to refresh repositories: '.$e->getMessage());
        }
    }

    /**
     * One-click add. Uses the repo's cached default branch — no live branch
     * API call in the critical path, which is what made the old two-step
     * select → load branches → add flow feel slow.
     */
    public function addRepository(string $fullName, ?string $branch = null): void
    {
        $settings = $this->connector->settings ?? [];
        $cachedRepos = $settings['cached_repos'] ?? [];

        $selectedRepo = collect($cachedRepos)->firstWhere('full_name', $fullName);

        if (! $selectedRepo) {
            Flux::toast(variant: 'error', text: 'Repository not found. Try refreshing the list.');

            return;
        }

        $alreadyAdded = Repository::query()
            ->where('project_id', $this->project->id)
            ->where('connector_id', $this->connector->id)
            ->where('full_name', $fullName)
            ->exists();

        if ($alreadyAdded) {
            Flux::toast(variant: 'warning', text: 'This repository has already been added.');

            return;
        }

        Repository::create([
            'project_id' => $this->project->id,
            'connector_id' => $this->connector->id,
            'name' => basename($selectedRepo['full_name']),
            'type' => 'git',
            'full_name' => $selectedRepo['full_name'],
            'git_url' => $selectedRepo['clone_url'],
            'git_branch' => $branch ?? ($selectedRepo['default_branch'] ?? 'main'),
            'sync_status' => 'pending',
        ]);

        Flux::toast(variant: 'success', text: 'Repository added successfully.');

        $this->redirect(route('projects.repositories', [
            'project' => $this->project,
        ]), navigate: true);
    }

    public function openBranchPicker(string $fullName): void
    {
        $this->branchPickerFor = $fullName;
        $this->branches = [];
        $this->loadingBranches = true;

        $settings = $this->connector->settings ?? [];
        $cachedRepos = $settings['cached_repos'] ?? [];
        $repo = collect($cachedRepos)->firstWhere('full_name', $fullName);

        $this->overrideBranch = $repo['default_branch'] ?? 'main';

        if (! $repo) {
            $this->loadingBranches = false;

            return;
        }

        try {
            $provider = GitProviderResolver::for($this->connector->type);

            $context = [];
            if ($this->connector->type === ConnectorType::AzureDevOps) {
                $context['organization'] = $settings['organization'] ?? '';
            }

            $this->branches = $provider->listBranches($this->connector->token, $repo, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to list branches: '.$e->getMessage());
        } finally {
            $this->loadingBranches = false;
        }
    }

    public function closeBranchPicker(): void
    {
        $this->branchPickerFor = null;
        $this->branches = [];
        $this->overrideBranch = '';
    }

    public function addRepositoryWithBranch(): void
    {
        if ($this->branchPickerFor === null) {
            return;
        }

        $this->addRepository($this->branchPickerFor, $this->overrideBranch);
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="Browse repositories" description="Pick repositories from {{ $connector->name }} ({{ $connector->type->label() }}) to import into this project.">
        <x-slot:breadcrumbs>
            <nav class="flex items-center gap-1.5" aria-label="Breadcrumb">
                <a href="{{ route('projects.git-providers', ['project' => $project]) }}" wire:navigate class="text-zinc-500 hover:text-zinc-900">Git providers</a>
                <flux:icon.chevron-right variant="micro" class="text-zinc-300" />
                <span class="truncate text-zinc-500">{{ $connector->name }}</span>
            </nav>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:button wire:click="refreshRepos" size="sm" icon="arrow-path">Refresh</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject === null)
        {{-- Azure DevOps: project selection step --}}
        <section class="ui-panel overflow-hidden">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Select a project</h2>
            </header>

            @if (count($this->projects) === 0)
                <x-empty-state icon="folder" title="No projects found" description="Refresh to load the projects in this Azure DevOps organization." />
            @else
                <div class="ui-list">
                    @foreach ($this->projects as $projectItem)
                        <button
                            type="button"
                            wire:key="project-{{ $projectItem['name'] }}"
                            wire:click="selectProject('{{ $projectItem['name'] }}')"
                            class="ui-list-row group"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                                <flux:icon.folder variant="micro" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900">{{ $projectItem['name'] }}</span>
                                @if (! empty($projectItem['description']))
                                    <span class="block truncate text-xs text-zinc-500">{{ $projectItem['description'] }}</span>
                                @endif
                            </span>
                            <flux:icon.chevron-right variant="micro" class="text-zinc-300 transition-transform duration-150 ease-snappy group-hover:translate-x-0.5 group-hover:text-zinc-500" />
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        {{-- Repository selection step --}}
        <section class="ui-panel overflow-hidden">
            <header class="ui-panel-header">
                @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject)
                    <flux:button wire:click="backToProjects" variant="ghost" size="sm" icon="arrow-left" class="-ml-2">
                        {{ $selectedProject }}
                    </flux:button>
                @else
                    <h2 class="ui-panel-title">Repositories</h2>
                @endif

                <div class="w-full max-w-64">
                    <flux:input
                        wire:model.live.debounce.150ms="search"
                        icon="magnifying-glass"
                        size="sm"
                        placeholder="Search repositories"
                        aria-label="Search repositories"
                    />
                </div>
            </header>

            @if (count($this->repos) === 0)
                <x-empty-state icon="folder-git-2" title="No repositories found" description="Refresh the list or try a different search." />
            @else
                <div class="ui-list">
                    @foreach ($this->repos as $repo)
                        @php $isAdded = in_array($repo['full_name'], $this->addedFullNames, true); @endphp

                        <div wire:key="repo-{{ $repo['full_name'] }}">
                            <div class="ui-list-row justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                                        <flux:icon.folder-git-2 variant="micro" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900">{{ $repo['full_name'] }}</div>
                                        <div class="mt-0.5 flex items-center gap-3 text-xs text-zinc-500">
                                            <span class="flex items-center gap-1">
                                                <flux:icon.git-branch variant="micro" class="size-3.5 text-zinc-400" />
                                                <span class="font-mono">{{ $repo['default_branch'] }}</span>
                                            </span>
                                            <span class="flex items-center gap-1">
                                                @if ($repo['private'])
                                                    <flux:icon.lock-closed variant="micro" class="size-3.5 text-zinc-400" />
                                                    Private
                                                @else
                                                    <flux:icon.globe-alt variant="micro" class="size-3.5 text-zinc-400" />
                                                    Public
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($isAdded)
                                        <x-status-badge status="success" label="Added" />
                                    @else
                                        <flux:button
                                            wire:click="openBranchPicker('{{ $repo['full_name'] }}')"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Change branch
                                        </flux:button>

                                        <flux:button
                                            wire:click="addRepository('{{ $repo['full_name'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="addRepository('{{ $repo['full_name'] }}')"
                                            size="sm"
                                            icon="plus"
                                        >
                                            <span wire:loading.remove wire:target="addRepository('{{ $repo['full_name'] }}')">Add</span>
                                            <span wire:loading wire:target="addRepository('{{ $repo['full_name'] }}')">Adding…</span>
                                        </flux:button>
                                    @endif
                                </div>
                            </div>

                            {{-- Inline branch override, only rendered when explicitly opened --}}
                            @if ($branchPickerFor === $repo['full_name'])
                                <div class="animate-enter flex items-end gap-2 border-t border-zinc-100 bg-zinc-50 px-4 py-3">
                                    <div class="flex-1">
                                        @if ($loadingBranches)
                                            <flux:field>
                                                <flux:label>Branch</flux:label>
                                                <flux:input disabled value="Loading branches…" />
                                            </flux:field>
                                        @elseif ($branches)
                                            <flux:select wire:model="overrideBranch" label="Branch">
                                                @foreach ($branches as $branch)
                                                    <flux:select.option value="{{ $branch['name'] }}">{{ $branch['name'] }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @else
                                            <flux:input wire:model="overrideBranch" label="Branch" placeholder="main" />
                                        @endif
                                    </div>

                                    <flux:button wire:click="closeBranchPicker" variant="ghost">
                                        Cancel
                                    </flux:button>

                                    <flux:button
                                        wire:click="addRepositoryWithBranch"
                                        wire:loading.attr="disabled"
                                        variant="primary"
                                    >
                                        Add
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
