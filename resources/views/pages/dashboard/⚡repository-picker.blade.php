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

    public function getRepoIconColor(): string
    {
        return match ($this->connector->type) {
            ConnectorType::Bitbucket => 'text-blue-600',
            ConnectorType::GitLab => 'text-orange-600',
            ConnectorType::AzureDevOps => 'text-sky-600',
            default => 'text-[#919191]',
        };
    }
};
?>

<div class="flex flex-col gap-y-8">
    {{-- Header --}}
    <div class="flex items-center gap-x-3">
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            :href="route('projects.git-providers', ['project' => $project])"
            wire:navigate
        />
        <div>
            <flux:heading size="xl" class="font-semibold text-zinc-900">Browse Repositories</flux:heading>
            <flux:text class="text-xs text-[#919191]">{{ $connector->name }} &middot; {{ $connector->type->label() }}</flux:text>
        </div>
    </div>

    @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject === null)
        {{-- Azure DevOps: project selection step --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm" class="text-[#919191] font-medium uppercase tracking-wide">Select a Project</flux:heading>
                <flux:button wire:click="refreshRepos" variant="ghost" size="sm" icon="arrow-path">
                    Refresh
                </flux:button>
            </div>

            <div class="flex flex-col rounded-xl border border-[#EDEDED] bg-[#F1F1F1] divide-y divide-[#EDEDED] overflow-hidden">
                @forelse ($this->projects as $projectItem)
                    <button
                        wire:key="project-{{ $projectItem['name'] }}"
                        wire:click="selectProject('{{ $projectItem['name'] }}')"
                        class="flex items-center gap-x-3 p-4 bg-white hover:bg-[#F8F8F8] transition text-left"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#F1F1F1] border border-[#EDEDED]">
                            <flux:icon.folder class="size-4 text-sky-600" />
                        </div>
                        <div>
                            <flux:heading class="font-medium text-sm text-zinc-900">{{ $projectItem['name'] }}</flux:heading>
                            @if (!empty($projectItem['description']))
                                <flux:text class="text-xs text-[#919191]">{{ $projectItem['description'] }}</flux:text>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="flex h-40 w-full items-center justify-center bg-white">
                        <div class="flex flex-col items-center gap-y-2">
                            <flux:icon.folder class="size-8 text-[#C7C7C7]" />
                            <flux:text class="text-center text-sm text-zinc-700">No projects found.</flux:text>
                            <flux:text class="text-center text-xs text-[#919191]">Try refreshing the project list.</flux:text>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        {{-- Repository selection step --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                @if ($connector->type === \App\Enums\ConnectorType::AzureDevOps && $selectedProject)
                    <flux:button wire:click="backToProjects" variant="ghost" size="sm" icon="arrow-left">
                        {{ $selectedProject }}
                    </flux:button>
                @else
                    <flux:heading size="sm" class="text-[#919191] font-medium uppercase tracking-wide">Repositories</flux:heading>
                @endif

                <flux:button wire:click="refreshRepos" variant="ghost" size="sm" icon="arrow-path">
                    Refresh
                </flux:button>
            </div>

            <flux:input
                wire:model.live.debounce.150ms="search"
                icon="magnifying-glass"
                placeholder="Search repositories..."
            />

            <div class="flex flex-col rounded-xl border border-[#EDEDED] bg-[#F1F1F1] divide-y divide-[#EDEDED] overflow-hidden">
                @forelse ($this->repos as $repo)
                    @php $isAdded = in_array($repo['full_name'], $this->addedFullNames, true); @endphp

                    <div wire:key="repo-{{ $repo['full_name'] }}" class="bg-white">
                        <div class="flex items-center justify-between gap-x-4 p-4">
                            <div class="flex items-center gap-x-3 min-w-0">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#F1F1F1] border border-[#EDEDED]">
                                    <flux:icon.folder-git-2 class="size-4 {{ $this->getRepoIconColor() }}" />
                                </div>
                                <div class="min-w-0">
                                    <flux:heading class="font-medium text-sm text-zinc-900 truncate">{{ $repo['full_name'] }}</flux:heading>
                                    <div class="flex items-center gap-x-2 mt-0.5">
                                        <flux:text class="text-xs text-[#919191]">{{ $repo['default_branch'] }}</flux:text>
                                        @if ($repo['private'])
                                            <flux:badge size="sm" variant="subtle" color="amber">Private</flux:badge>
                                        @else
                                            <flux:badge size="sm" variant="subtle" color="emerald">Public</flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-x-2 shrink-0">
                                @if ($isAdded)
                                    <flux:badge size="sm" variant="solid" color="zinc" icon="check">Added</flux:badge>
                                @else
                                    <flux:button
                                        wire:click="openBranchPicker('{{ $repo['full_name'] }}')"
                                        variant="ghost"
                                        size="sm"
                                        class="text-xs text-[#919191]"
                                    >
                                        Change branch
                                    </flux:button>

                                    <flux:button
                                        wire:click="addRepository('{{ $repo['full_name'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="addRepository('{{ $repo['full_name'] }}')"
                                        variant="primary"
                                        size="sm"
                                        icon="plus"
                                    >
                                        <span wire:loading.remove wire:target="addRepository('{{ $repo['full_name'] }}')">Add</span>
                                        <span wire:loading wire:target="addRepository('{{ $repo['full_name'] }}')">Adding...</span>
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Inline branch override, only rendered when explicitly opened --}}
                        @if ($branchPickerFor === $repo['full_name'])
                            <div class="flex items-end gap-x-3 px-4 pb-4 pt-1 border-t border-[#EDEDED] bg-[#F8F8F8]">
                                <div class="flex-1">
                                    @if ($loadingBranches)
                                        <flux:field>
                                            <flux:label class="text-xs text-[#919191]">Branch</flux:label>
                                            <flux:input disabled value="Loading branches..." />
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

                                <flux:button wire:click="closeBranchPicker" variant="ghost" size="sm">
                                    Cancel
                                </flux:button>

                                <flux:button
                                    wire:click="addRepositoryWithBranch"
                                    wire:loading.attr="disabled"
                                    variant="primary"
                                    size="sm"
                                >
                                    Add
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex h-40 w-full items-center justify-center bg-white">
                        <div class="flex flex-col items-center gap-y-2">
                            <flux:icon.folder-git-2 class="size-8 text-[#C7C7C7]" />
                            <flux:text class="text-center text-sm text-zinc-700">No repositories found.</flux:text>
                            <flux:text class="text-center text-xs text-[#919191]">Try refreshing or adjusting your search.</flux:text>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
