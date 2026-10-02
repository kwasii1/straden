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

    public function getTokenLabel(): string
    {
        return $this->gitType === 'bitbucket' ? 'App password' : 'Personal access token';
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

<div class="flex flex-col gap-8">
    @php
        $gitProviderOptions = [
            'github' => ['label' => 'GitHub', 'monogram' => 'GH', 'description' => 'Personal access token'],
            'gitlab' => ['label' => 'GitLab', 'monogram' => 'GL', 'description' => 'Personal access token'],
            'bitbucket' => ['label' => 'Bitbucket', 'monogram' => 'BB', 'description' => 'App password and workspace'],
            'azure_devops' => ['label' => 'Azure DevOps', 'monogram' => 'AZ', 'description' => 'Personal access token and organization'],
        ];
    @endphp

    <x-page-header title="Git providers" description="Connect GitHub, GitLab, Bitbucket or Azure DevOps to sync and import source repositories.">
        <x-slot:actions>
            <flux:modal.trigger name="create-git-connector">
                <flux:button variant="primary" icon="plus">Add git provider</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    <section class="ui-panel overflow-hidden">
        @if ($this->gitConnectors->isEmpty())
            <x-empty-state icon="folder-git-2" title="No git providers connected" description="Link a GitHub, GitLab, Bitbucket or Azure DevOps account to browse and import repositories into this project.">
                <flux:modal.trigger name="create-git-connector">
                    <flux:button size="sm" icon="plus">Connect a provider</flux:button>
                </flux:modal.trigger>
            </x-empty-state>
        @else
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Connected accounts</h2>
                <span class="text-xs text-zinc-500 tabular-nums">
                    {{ trans_choice(':count account|:count accounts', $this->stats['total_connected']) }}, {{ trans_choice(':count repository cached|:count repositories cached', $this->stats['total_cached']) }}
                </span>
            </header>

            <div class="ui-list">
                @foreach ($this->gitConnectors as $connector)
                    @php
                        $settings = $connector->settings ?? [];
                        $repoCount = count($settings['cached_repos'] ?? []);
                        $projectCount = count($settings['cached_projects'] ?? []);
                        $lastFetched = isset($settings['repos_fetched_at']) ? \Carbon\Carbon::parse($settings['repos_fetched_at']) : null;
                    @endphp

                    <div wire:key="git-connector-{{ $connector->id }}" class="ui-list-row flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="ui-monogram size-9">{{ $gitProviderOptions[$connector->type->value]['monogram'] ?? strtoupper(substr($connector->type->label(), 0, 2)) }}</span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-zinc-900">{{ $connector->name }}</span>
                                    <x-status-badge status="connected" />
                                </div>

                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-zinc-500">
                                    <span>{{ $connector->type->label() }}</span>

                                    @if ($repoCount > 0)
                                        <span class="tabular-nums">{{ $repoCount }} {{ Str::plural('repository', $repoCount) }}</span>
                                    @elseif ($projectCount > 0)
                                        <span class="tabular-nums">{{ $projectCount }} {{ Str::plural('project', $projectCount) }}</span>
                                    @else
                                        <span>No cached repositories</span>
                                    @endif

                                    @if ($lastFetched)
                                        <span>Synced {{ $lastFetched->diffForHumans() }}</span>
                                    @endif

                                    @if (isset($settings['workspace']))
                                        <span>Workspace <span class="font-mono text-zinc-700">{{ $settings['workspace'] }}</span></span>
                                    @endif

                                    @if (isset($settings['organization']))
                                        <span>Organization <span class="font-mono text-zinc-700">{{ $settings['organization'] }}</span></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1 self-end sm:self-auto">
                            <flux:button
                                wire:click="refreshGitConnectorRepos('{{ $connector->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="refreshGitConnectorRepos('{{ $connector->id }}')"
                                variant="ghost"
                                size="sm"
                                icon="arrow-path"
                            >
                                <span wire:loading.remove wire:target="refreshGitConnectorRepos('{{ $connector->id }}')">Refresh</span>
                                <span wire:loading wire:target="refreshGitConnectorRepos('{{ $connector->id }}')">Syncing…</span>
                            </flux:button>

                            <flux:button
                                size="sm"
                                :href="route('projects.repository-picker', ['project' => $this->project, 'connector' => $connector])"
                                wire:navigate
                            >
                                Browse repositories
                            </flux:button>

                            <button
                                type="button"
                                wire:click="deleteGitConnector('{{ $connector->id }}')"
                                wire:confirm="Are you sure you want to remove this git provider connection?"
                                class="ui-icon-button hover:bg-red-50 hover:text-red-600"
                                aria-label="Remove git provider"
                                title="Remove"
                            >
                                <flux:icon.trash variant="micro" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <flux:modal name="create-git-connector" class="md:w-[36rem]">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Add git provider</flux:heading>
                <flux:text class="mt-1">Connect your source control provider with a personal access token or app password.</flux:text>
            </div>

            <form wire:submit="addGitConnector" class="flex flex-col gap-6">
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-zinc-800">Provider</legend>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($gitProviderOptions as $value => $option)
                            <label
                                wire:key="git-provider-option-{{ $value }}"
                                class="ui-tile flex cursor-pointer items-center gap-3 p-4 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-500/70"
                                @if ($gitType === $value) data-selected @endif
                            >
                                <input type="radio" wire:model.live="gitType" value="{{ $value }}" class="sr-only" />
                                <span class="ui-monogram size-8">{{ $option['monogram'] }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-zinc-900">{{ $option['label'] }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $option['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <flux:error name="gitType" />
                </fieldset>

                <flux:field>
                    <flux:label>Connection name</flux:label>
                    <flux:input wire:model="gitName" placeholder="Personal GitHub, Work GitLab…" />
                    <flux:error name="gitName" />
                </flux:field>

                @if ($gitType)
                    <flux:field>
                        <flux:label>{{ $this->getTokenLabel() }}</flux:label>
                        <flux:input
                            wire:model="gitToken"
                            type="password"
                            placeholder="{{ $gitType === 'bitbucket' ? 'Enter your app password' : 'Enter your token' }}"
                        />
                        <flux:error name="gitToken" />
                        @if ($scopesHelp = $this->getScopesHelpText())
                            <div class="ui-inset px-3 py-2.5 text-xs leading-relaxed text-zinc-600 [&_a]:font-medium [&_a]:text-zinc-900 [&_a]:decoration-zinc-300 [&_a]:underline-offset-2 [&_strong]:font-medium [&_strong]:text-zinc-800">
                                {!! $scopesHelp !!}
                            </div>
                        @endif
                    </flux:field>

                    @if ($gitType === 'bitbucket')
                        <flux:field>
                            <flux:label>Workspace</flux:label>
                            <flux:input wire:model="gitWorkspace" placeholder="my-workspace" />
                            <flux:description>Bitbucket needs a workspace slug to list repositories.</flux:description>
                            <flux:error name="gitWorkspace" />
                        </flux:field>
                    @endif

                    @if ($gitType === 'azure_devops')
                        <flux:field>
                            <flux:label>Organization</flux:label>
                            <flux:input wire:model="gitOrganization" placeholder="my-org" />
                            <flux:description>Your Azure DevOps organization name, as in dev.azure.com/my-org.</flux:description>
                            <flux:error name="gitOrganization" />
                        </flux:field>
                    @endif
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addGitConnector">
                        <span wire:loading.remove wire:target="addGitConnector">Validate and connect</span>
                        <span wire:loading wire:target="addGitConnector">Validating…</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
