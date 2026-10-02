<?php

use App\Models\Project;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component
{
    public string $name = '';
    public ?string $description = null;
    public string $search = '';

    public ?Project $editingProject = null;
    public string $editName = '';
    public ?string $editDescription = null;

    #[Computed]
    public function projects()
    {
        return Project::withCount(['tests', 'scripts'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->get();
    }

    #[Computed]
    public function totalProjects(): int
    {
        return Project::count();
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|max:256|string',
            'description' => 'nullable|string',
        ]);

        Project::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        Flux::modal('create-project')->close();

        Flux::toast(variant: 'success', text: 'Project created successfully.');

        $this->reset('name', 'description');

        unset($this->projects, $this->totalProjects);
    }

    public function edit(Project $project): void
    {
        $this->editingProject = $project;
        $this->editName = $project->name;
        $this->editDescription = $project->description;

        Flux::modal('edit-project')->show();
    }

    public function update(): void
    {
        $this->validate([
            'editName' => 'required|max:256|string',
            'editDescription' => 'nullable|string',
        ]);

        $this->editingProject->update([
            'name' => $this->editName,
            'description' => $this->editDescription,
        ]);

        Flux::modal('edit-project')->close();

        Flux::toast(variant: 'success', text: 'Project updated successfully.');

        $this->reset('editName', 'editDescription', 'editingProject');

        unset($this->projects, $this->totalProjects);
    }

    public function delete(string $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $project->delete();

        Flux::toast(variant: 'success', text: 'Project deleted successfully.');

        unset($this->projects, $this->totalProjects);
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="Projects" description="Each project groups the tests, scripts and runs for one system.">
        <x-slot:actions>
            <flux:modal.trigger name="create-project">
                <flux:button variant="primary" icon="plus">Create project</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    <section class="ui-panel overflow-hidden">
        @if ($this->totalProjects > 0)
            <header class="ui-panel-header">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    size="sm"
                    icon="magnifying-glass"
                    placeholder="Search projects"
                    aria-label="Search projects"
                    class="sm:max-w-xs"
                />

                <span class="shrink-0 text-xs text-zinc-500 tabular-nums">
                    {{ $this->projects->count() }} of {{ $this->totalProjects }} {{ Str::plural('project', $this->totalProjects) }}
                </span>
            </header>
        @endif

        @if ($this->projects->isEmpty())
            @if ($search)
                <x-empty-state icon="magnifying-glass" title="No matching projects" :description="'Nothing matches “'.$search.'”. Try a different name.'">
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="folder-open" title="No projects yet" description="Create a project to start writing tests and running them.">
                    <flux:modal.trigger name="create-project">
                        <flux:button size="sm" icon="plus">Create project</flux:button>
                    </flux:modal.trigger>
                </x-empty-state>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="text-right!">Tests</th>
                            <th class="text-right!">Scripts</th>
                            <th class="text-right!">Created</th>
                            <th class="w-20"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->projects as $project)
                            @php
                                $projectUrl = route('projects.overview', ['project' => $project]);
                            @endphp
                            <tr wire:key="{{ $project->id }}" class="ui-table-row-link" x-on:click="Livewire.navigate(@js($projectUrl))">
                                <td class="py-2.5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="ui-monogram size-8">{{ strtoupper(substr($project->name, 0, 2)) }}</span>
                                        <div class="min-w-0">
                                            <a href="{{ $projectUrl }}" wire:navigate x-on:click.stop class="block max-w-xs truncate font-medium text-zinc-900">{{ $project->name }}</a>
                                            <p class="max-w-xs truncate text-xs text-zinc-500">{{ $project->description ?: 'No description' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right tabular-nums">{{ $project->tests_count }}</td>
                                <td class="text-right tabular-nums">{{ $project->scripts_count }}</td>
                                <td class="text-right whitespace-nowrap text-zinc-500">{{ $project->created_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <button
                                            type="button"
                                            wire:click="edit('{{ $project->id }}')"
                                            x-on:click.stop
                                            class="ui-icon-button"
                                            aria-label="Edit project"
                                            title="Edit project"
                                        >
                                            <flux:icon.pencil-square variant="micro" />
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="delete('{{ $project->id }}')"
                                            wire:confirm="Are you sure you want to delete this project? All tests, scripts, runs, and repositories will be permanently removed."
                                            x-on:click.stop
                                            class="ui-icon-button hover:bg-red-50 hover:text-red-600"
                                            aria-label="Delete project"
                                            title="Delete project"
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

    {{-- Modal: Create project --}}
    <flux:modal name="create-project" class="md:w-[28rem]">
        <form wire:submit="submit" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Create project</flux:heading>
                <flux:text class="mt-1">Give it a name you will recognise in the project switcher.</flux:text>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="Checkout API" />

            <flux:textarea wire:model="description" label="Description" placeholder="What this project load tests" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Create project</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: Edit project --}}
    <flux:modal name="edit-project" class="md:w-[28rem]">
        <form wire:submit="update" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Edit project</flux:heading>
                <flux:text class="mt-1">Update the name and description.</flux:text>
            </div>

            <flux:input wire:model="editName" label="Name" placeholder="Project name" />

            <flux:textarea wire:model="editDescription" label="Description" placeholder="What this project load tests" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
