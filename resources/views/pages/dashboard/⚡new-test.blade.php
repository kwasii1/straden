<?php

use App\Models\Project;
use App\Models\Test;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public string $name;

    public string $target_endpoint;

    /** @var array<int, string> */
    public array $repositoryIds = [];

    public array $connectors = [];

    public string $description = '';

    public Project $project;

    public Collection $repositories;

    public function mount(Project $project): void
    {
        $this->project = $project;

        $this->repositories = $project->repositories()->get();
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'target_endpoint' => 'required|url|max:2048',
            'connectors' => 'nullable|array',
            'connectors.*' => 'string',
            'repositoryIds' => 'nullable|array',
            'repositoryIds.*' => 'string',
        ]);

        $test = Test::create([
            'name' => $this->name,
            'project_id' => $this->project->id,
            'target_url' => $this->target_endpoint,
            'description' => $this->description,
        ]);

        $test->syncConnectors((array) ($this->connectors ?? []));
        $test->syncRepositories($this->repositoryIds);

        Flux::toast(variant: 'success', text: 'Test Created Successfully');

        $this->redirectRoute('projects.tests', ['project' => $this->project], navigate: true);
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="New test" description="Name the test and point it at the endpoint you want to put under load.">
        <x-slot:breadcrumbs>
            <span class="flex items-center gap-1.5">
                <a href="{{ route('projects.tests', ['project' => $project]) }}" wire:navigate class="hover:text-zinc-900">Tests</a>
                <flux:icon.chevron-right variant="micro" class="text-zinc-300" />
                <span class="text-zinc-700">New test</span>
            </span>
        </x-slot:breadcrumbs>
    </x-page-header>

    <form wire:submit="submit" class="flex w-full max-w-2xl flex-col gap-6">
        <flux:input wire:model="name" label="Name" placeholder="Checkout flow" />

        <flux:input wire:model="target_endpoint" label="Target endpoint" placeholder="https://api.example.com" />

        <flux:textarea wire:model="description" label="Description" placeholder="What this test covers, for example checkout under peak traffic" rows="3" />

        <x-multi-combobox
            wire:model="repositoryIds"
            label="Repositories"
            :options="$repositories->map(fn ($repo) => ['value' => $repo->id, 'label' => $repo->name, 'hint' => $repo->type])"
            placeholder="All project repositories"
            search-placeholder="Search repositories..."
            empty-text="No repositories in this project yet."
            description="The AI agents only read code from these repositories. Leave empty to use every repository in the project."
        />

        <livewire:connector-picker wire:model="connectors" :project="$project" wire:key="new-test-connectors" />

        <div class="flex flex-col gap-4 border-t border-zinc-200 pt-6">
            <p class="flex items-center gap-1.5 text-xs text-zinc-500">
                <flux:icon.information-circle variant="micro" class="text-zinc-400" />
                Test configuration can't be changed while it's running.
            </p>

            <div class="flex items-center gap-2">
                <flux:button type="submit" variant="primary">Create test</flux:button>
                <flux:button :href="route('projects.tests', ['project' => $project])" wire:navigate variant="ghost">Cancel</flux:button>
            </div>
        </div>
    </form>
</div>
