<?php

use App\Enums\ConnectorType;
use App\GitProviders\GitProviderResolver;
use App\Models\Connector;
use App\Models\Project;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public string $gitName = '';

    public string $gitType = '';

    public string $gitToken = '';

    public string $gitWorkspace = '';

    public string $gitOrganization = '';

    #[Computed]
    public function gitConnectors()
    {
        return Connector::query()
            ->where('project_id', $this->project->id)
            ->whereIn('type', [
                ConnectorType::GitHub->value,
                ConnectorType::GitLab->value,
                ConnectorType::Bitbucket->value,
                ConnectorType::AzureDevOps->value,
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        $connectors = $this->gitConnectors;
        $totalRepos = 0;

        foreach ($connectors as $connector) {
            $settings = $connector->settings ?? [];
            $totalRepos += count($settings['cached_repos'] ?? []);
            $totalRepos += count($settings['cached_projects'] ?? []);
        }

        return [
            'total_connected' => $connectors->count(),
            'total_cached' => $totalRepos,
        ];
    }

    public function getGitTypeIconColor(string $type): string
    {
        return match ($type) {
            'github' => 'text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700',
            'gitlab' => 'text-orange-600 dark:text-orange-400 bg-orange-500/10 border-orange-500/20',
            'bitbucket' => 'text-blue-600 dark:text-blue-400 bg-blue-500/10 border-blue-500/20',
            'azure_devops' => 'text-sky-600 dark:text-sky-400 bg-sky-500/10 border-sky-500/20',
            default => 'text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700',
        };
    }

    public function getTokenLabel(): string
    {
        return $this->gitType === 'bitbucket' ? 'App Password' : 'Personal Access Token';
    }

    public function getScopesHelpText(): string
    {
        if (! $this->gitType) {
            return '';
        }

        try {
            $provider = GitProviderResolver::for(ConnectorType::from($this->gitType));

            return $provider->requiredScopesHelpText();
        } catch (\Throwable) {
            return '';
        }
    }

    public function addGitConnector(): void
    {
        $rules = [
            'gitName' => 'required|string|max:255',
            'gitType' => 'required|in:github,gitlab,bitbucket,azure_devops',
            'gitToken' => 'required|string|max:1024',
        ];

        if ($this->gitType === 'bitbucket') {
            $rules['gitWorkspace'] = 'required|string|max:255';
        }

        if ($this->gitType === 'azure_devops') {
            $rules['gitOrganization'] = 'required|string|max:255';
        }

        $this->validate($rules);

        $connectorType = ConnectorType::from($this->gitType);
        $provider = GitProviderResolver::for($connectorType);

        $context = [];
        if ($this->gitType === 'bitbucket') {
            $context['workspace'] = $this->gitWorkspace;
        }
        if ($this->gitType === 'azure_devops') {
            $context['organization'] = $this->gitOrganization;
        }

        try {
            $valid = $provider->validateToken($this->gitToken, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Token validation failed: '.$e->getMessage());

            return;
        }

        if (! $valid) {
            Flux::toast(variant: 'error', text: 'Invalid token. Please check your credentials and try again.');

            return;
        }

        try {
            $repos = $provider->listRepositories($this->gitToken, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to list repositories: '.$e->getMessage());

            return;
        }

        $settings = [
            'repos_fetched_at' => now()->toIso8601String(),
        ];

        if ($this->gitType === 'bitbucket') {
            $settings['workspace'] = $this->gitWorkspace;
            $settings['cached_repos'] = $repos;
        } elseif ($this->gitType === 'azure_devops') {
            $settings['organization'] = $this->gitOrganization;
            $settings['cached_projects'] = $repos;
        } else {
            $settings['cached_repos'] = $repos;
        }

        $connector = Connector::create([
            'project_id' => $this->project->id,
            'name' => $this->gitName,
            'type' => $connectorType,
            'token' => $this->gitToken,
            'settings' => $settings,
        ]);

        Flux::modal('create-git-connector')->close();

        Flux::toast(variant: 'success', text: 'Git provider connected successfully.');

        $this->resetGitConnectorForm();

        unset($this->gitConnectors);

        $this->redirect(route('projects.repository-picker', [
            'project' => $this->project,
            'connector' => $connector,
        ]), navigate: true);
    }

    public function deleteGitConnector(Connector $connector): void
    {
        $connector->delete();

        Flux::toast(variant: 'success', text: 'Git provider removed.');

        unset($this->gitConnectors);
    }

    public function refreshGitConnectorRepos(Connector $connector): void
    {
        $provider = GitProviderResolver::for($connector->type);

        $context = [];
        $settings = $connector->settings ?? [];

        if ($connector->type === ConnectorType::Bitbucket) {
            $context['workspace'] = $settings['workspace'] ?? '';
        }
        if ($connector->type === ConnectorType::AzureDevOps) {
            $context['organization'] = $settings['organization'] ?? '';
        }

        try {
            $repos = $provider->listRepositories($connector->token, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to refresh repositories: '.$e->getMessage());

            return;
        }

        $settings['repos_fetched_at'] = now()->toIso8601String();

        if ($connector->type === ConnectorType::AzureDevOps) {
            $settings['cached_projects'] = $repos;
        } else {
            $settings['cached_repos'] = $repos;
        }

        $connector->update(['settings' => $settings]);

        Flux::toast(variant: 'success', text: 'Repositories refreshed successfully.');

        unset($this->gitConnectors);
    }

    public function updatedGitType(): void
    {
        $this->reset(['gitWorkspace', 'gitOrganization']);
    }

    private function resetGitConnectorForm(): void
    {
        $this->reset(['gitName', 'gitType', 'gitToken', 'gitWorkspace', 'gitOrganization']);
    }
};
?>

<div class="flex flex-col gap-y-6">
    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-5">
        <div>
            <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">Git Providers</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Connect GitHub, GitLab, Bitbucket, or Azure DevOps to sync and import source repositories.</flux:text>
        </div>

        <flux:modal.trigger name="create-git-connector">
            <flux:button variant="primary" size="sm" icon="plus" class="shrink-0">
                Add Git Provider
            </flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Overview Metrics Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex items-center justify-between p-3.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white/60 dark:bg-zinc-900/60">
            <span class="text-xs text-zinc-500 font-medium">Connected Accounts</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $this->stats['total_connected'] }}</span>
        </div>

        <div class="flex items-center justify-between p-3.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white/60 dark:bg-zinc-900/60">
            <span class="text-xs text-zinc-500 font-medium">Synced Repositories</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $this->stats['total_cached'] }}</span>
        </div>

        <div class="flex items-center justify-between p-3.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10">
            <div class="flex items-center gap-2">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                </span>
                <span class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">Supported Platforms</span>
            </div>
            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">4 Providers</span>
        </div>
    </div>

    {{-- Connected Accounts List --}}
    <div class="flex flex-col rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900/90 shadow-xs overflow-hidden divide-y divide-zinc-200/80 dark:divide-zinc-800/80">
        @forelse ($this->gitConnectors as $connector)
            @php
                $settings = $connector->settings ?? [];
                $repoCount = count($settings['cached_repos'] ?? []);
                $projectCount = count($settings['cached_projects'] ?? []);
                $lastFetched = isset($settings['repos_fetched_at']) ? \Carbon\Carbon::parse($settings['repos_fetched_at']) : null;
            @endphp

            <div wire:key="git-connector-{{ $connector->id }}" class="group flex flex-col sm:flex-row sm:items-center justify-between p-4 gap-4 hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition">
                <div class="flex items-center gap-x-3.5">
                    {{-- Provider Icon Box --}}
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border {{ $this->getGitTypeIconColor($connector->type->value) }}">
                        <flux:icon.folder-git-2 class="size-5" />
                    </div>

                    <div class="space-y-0.5">
                        <div class="flex items-center gap-x-2">
                            <flux:heading class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $connector->name }}
                            </flux:heading>

                            <flux:badge size="sm" variant="subtle" color="zinc" class="text-[10px] font-mono capitalize">
                                {{ $connector->type->label() }}
                            </flux:badge>
                        </div>

                        <div class="flex items-center gap-x-3 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($repoCount > 0)
                                <span class="font-mono text-zinc-700 dark:text-zinc-300 font-medium">{{ $repoCount }} {{ Str::plural('repository', $repoCount) }}</span>
                            @elseif ($projectCount > 0)
                                <span class="font-mono text-zinc-700 dark:text-zinc-300 font-medium">{{ $projectCount }} {{ Str::plural('project', $projectCount) }}</span>
                            @else
                                <span class="text-zinc-400">No cached repos</span>
                            @endif

                            @if ($lastFetched)
                                <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                <span class="text-[11px] text-zinc-400">Synced {{ $lastFetched->diffForHumans() }}</span>
                            @endif

                            @if (isset($settings['workspace']))
                                <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                <span class="text-[11px] font-mono text-zinc-400">ws: {{ $settings['workspace'] }}</span>
                            @endif

                            @if (isset($settings['organization']))
                                <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                <span class="text-[11px] font-mono text-zinc-400">org: {{ $settings['organization'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Action Toolbar --}}
                <div class="flex items-center gap-x-1.5 self-end sm:self-auto">
                    <flux:button
                        wire:click="refreshGitConnectorRepos('{{ $connector->id }}')"
                        wire:loading.attr="disabled"
                        wire:target="refreshGitConnectorRepos('{{ $connector->id }}')"
                        variant="ghost"
                        size="sm"
                        icon="arrow-path"
                        class="text-xs h-8"
                    >
                        <span wire:loading.remove wire:target="refreshGitConnectorRepos('{{ $connector->id }}')">Refresh</span>
                        <span wire:loading wire:target="refreshGitConnectorRepos('{{ $connector->id }}')">Syncing...</span>
                    </flux:button>

                    <flux:button
                        variant="subtle"
                        size="sm"
                        icon="magnifying-glass"
                        :href="route('projects.repository-picker', ['project' => $this->project, 'connector' => $connector])"
                        wire:navigate
                        class="text-xs h-8"
                    >
                        Browse Repos
                    </flux:button>

                    <flux:button
                        wire:click="deleteGitConnector('{{ $connector->id }}')"
                        wire:confirm="Are you sure you want to remove this git provider connection?"
                        variant="ghost"
                        size="sm"
                        icon="trash"
                        class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 text-xs h-8"
                    />
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 mb-4 border border-zinc-200/50 dark:border-zinc-700/50">
                    <flux:icon.folder-git-2 class="size-6" />
                </div>

                <flux:heading size="md" class="text-zinc-800 dark:text-zinc-200 font-medium">No Git Providers Connected</flux:heading>

                <flux:text class="text-xs text-zinc-400 mt-1 max-w-sm">
                    Link your GitHub, GitLab, Bitbucket, or Azure DevOps account to browse and import repositories into your project.
                </flux:text>

                <flux:modal.trigger name="create-git-connector">
                    <flux:button variant="primary" size="sm" icon="plus" class="mt-5">
                        Connect First Provider
                    </flux:button>
                </flux:modal.trigger>
            </div>
        @endforelse
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-git-connector" class="md:w-1/2">
        <div class="space-y-6">
            <div class="border-b dark:border-zinc-800 pb-4">
                <flux:heading size="lg">Add Git Provider</flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Connect your source control provider using a personal access token or app password.</flux:text>
            </div>

            <form wire:submit="addGitConnector" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label class="text-xs">Connection Name</flux:label>
                        <flux:input wire:model="gitName" placeholder="Personal GitHub, Work GitLab..." />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-xs">Git Provider</flux:label>
                        <flux:select wire:model.live="gitType">
                            <flux:select.option value="">Select a provider...</flux:select.option>
                            <flux:select.option value="github">GitHub</flux:select.option>
                            <flux:select.option value="gitlab">GitLab</flux:select.option>
                            <flux:select.option value="bitbucket">Bitbucket</flux:select.option>
                            <flux:select.option value="azure_devops">Azure DevOps</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                @if ($gitType)
                    <flux:field>
                        <flux:label class="text-xs">{{ $this->getTokenLabel() }}</flux:label>
                        <flux:input
                            wire:model="gitToken"
                            type="password"
                            placeholder="{{ $gitType === 'bitbucket' ? 'Enter your app password...' : 'Enter your token...' }}"
                        />
                        @if ($scopesHelp = $this->getScopesHelpText())
                            <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 p-2.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800 leading-normal">
                                {!! $scopesHelp !!}
                            </div>
                        @endif
                    </flux:field>

                    @if ($gitType === 'bitbucket')
                        <flux:field>
                            <flux:label class="text-xs">Workspace</flux:label>
                            <flux:input wire:model="gitWorkspace" placeholder="my-workspace" />
                            <flux:description class="text-[11px]">Bitbucket requires a workspace slug to list repositories.</flux:description>
                        </flux:field>
                    @endif

                    @if ($gitType === 'azure_devops')
                        <flux:field>
                            <flux:label class="text-xs">Organization</flux:label>
                            <flux:input wire:model="gitOrganization" placeholder="my-org" />
                            <flux:description class="text-[11px]">Your Azure DevOps organization name (e.g., dev.azure.com/my-org).</flux:description>
                        </flux:field>
                    @endif
                @endif

                <div class="flex items-center justify-end gap-2 pt-4 border-t dark:border-zinc-800">
                    <flux:button x-on:click="$flux.modal('create-git-connector').close()" variant="ghost" size="sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addGitConnector">Authenticate & Connect</span>
                        <span wire:loading wire:target="addGitConnector">Validating...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
