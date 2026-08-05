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

    public function getGitTypeIconColor(string $type): string
    {
        return match ($type) {
            'github' => 'text-zinc-600 dark:text-zinc-400',
            'gitlab' => 'text-orange-600 dark:text-orange-400',
            'bitbucket' => 'text-blue-600 dark:text-blue-400',
            'azure_devops' => 'text-sky-600 dark:text-sky-400',
            default => 'text-zinc-500 dark:text-zinc-400',
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

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Git Providers</flux:heading>
                <flux:text>Connect to GitHub, GitLab, Bitbucket, or Azure DevOps to browse and sync repositories.</flux:text>
            </div>
            <flux:modal.trigger name="create-git-connector">
                <flux:button variant="primary" icon="plus">Add Git Provider</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
        @forelse ($this->gitConnectors as $connector)
            <div wire:key="git-connector-{{ $connector->id }}" class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                <div class="flex items-center gap-x-4">
                    <div class="flex items-center gap-x-3">
                        <flux:icon.folder-git-2 class="size-5 {{ $this->getGitTypeIconColor($connector->type->value) }}" />

                        <div>
                            <flux:heading class="font-medium">{{ $connector->name }}</flux:heading>
                            <div class="flex items-center gap-x-2 mt-0.5">
                                <flux:badge size="sm" variant="subtle" color="zinc">
                                    {{ $connector->type->label() }}
                                </flux:badge>

                                @php
                                    $settings = $connector->settings ?? [];
                                    $repoCount = count($settings['cached_repos'] ?? []);
                                    $projectCount = count($settings['cached_projects'] ?? []);
                                @endphp

                                @if ($repoCount > 0)
                                    <flux:text class="text-xs">{{ $repoCount }} {{ Str::plural('repository', $repoCount) }} available</flux:text>
                                @elseif ($projectCount > 0)
                                    <flux:text class="text-xs">{{ $projectCount }} {{ Str::plural('project', $projectCount) }} available</flux:text>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-x-2">
                    <flux:button
                        wire:click="refreshGitConnectorRepos('{{ $connector->id }}')"
                        variant="ghost"
                        size="sm"
                        icon="arrow-path"
                    >
                        Refresh
                    </flux:button>

                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="magnifying-glass"
                        :href="route('projects.repository-picker', ['project' => $this->project, 'connector' => $connector])"
                        wire:navigate
                    >
                        Browse Repos
                    </flux:button>

                    <flux:button
                        wire:click="deleteGitConnector('{{ $connector->id }}')"
                        wire:confirm="Are you sure you want to remove this git provider connection?"
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
                        No git providers connected.
                    </flux:text>
                    <flux:text class="text-center text-sm">
                        Connect a git provider to browse and sync repositories.
                    </flux:text>
                </div>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-git-connector" class="md:w-1/2">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Git Provider</flux:heading>
                <flux:text class="mt-2">Connect to a git provider using a personal access token.</flux:text>
            </div>

            <form wire:submit="addGitConnector" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="gitName" label="Connection Name" placeholder="Personal GitHub" />

                    <flux:select wire:model.live="gitType" label="Provider">
                        <flux:select.option value="">Select a provider...</flux:select.option>
                        <flux:select.option value="github">GitHub</flux:select.option>
                        <flux:select.option value="gitlab">GitLab</flux:select.option>
                        <flux:select.option value="bitbucket">Bitbucket</flux:select.option>
                        <flux:select.option value="azure_devops">Azure DevOps</flux:select.option>
                    </flux:select>
                </div>

                @if ($gitType)
                    <flux:field>
                        <flux:label>{{ $this->getTokenLabel() }}</flux:label>
                        <flux:input wire:model="gitToken" type="password" placeholder="{{ $gitType === 'bitbucket' ? 'Enter your app password...' : 'Enter your personal access token...' }}" />
                        <flux:description>{!! $this->getScopesHelpText() !!}</flux:description>
                    </flux:field>

                    @if ($gitType === 'bitbucket')
                        <flux:input wire:model="gitWorkspace" label="Workspace" placeholder="my-workspace" />
                        <flux:description>Bitbucket requires a workspace to list repositories.</flux:description>
                    @endif

                    @if ($gitType === 'azure_devops')
                        <flux:input wire:model="gitOrganization" label="Organization" placeholder="my-org" />
                        <flux:description>Your Azure DevOps organization name (e.g. dev.azure.com/my-org).</flux:description>
                    @endif
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Connect</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
