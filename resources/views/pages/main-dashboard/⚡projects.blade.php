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

<div class="flex flex-col gap-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Projects</flux:heading>
            <flux:text class="mt-1">Manage your load testing projects</flux:text>
        </div>
        <flux:modal.trigger name="create-project">
            <flux:button variant="primary" icon="plus">Create project</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Search + count --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search projects..."
                class="w-full rounded-lg border border-[#EDEDED] bg-white py-2 pl-9 pr-3 text-sm text-zinc-700 placeholder:text-zinc-400 focus:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:ring-zinc-800"
            />
        </div>

        <flux:text class="text-xs text-[#919191]">
            {{ $this->projects->count() }} of {{ $this->totalProjects }} {{ Str::plural('project', $this->totalProjects) }}
        </flux:text>
    </div>

    {{-- Project Grid (Stat Card Style) --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->projects as $project)
            <div wire:key="{{ $project->id }}" class="group overflow-hidden rounded-xl border border-[#EDEDED] bg-[#F1F1F1] transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-800/40 dark:hover:border-zinc-700">

                {{-- Inner Top Card Section --}}
                <div class="flex flex-col bg-white p-5 dark:bg-zinc-900">
                    <a href="{{ route('projects.overview', ['project' => $project]) }}" wire:navigate class="block">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-xs font-bold text-white shadow-sm">
                                    {{ strtoupper(substr($project->name, 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-zinc-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                        {{ $project->name }}
                                    </h3>
                                    <p class="mt-0.5 line-clamp-1 text-xs text-[#919191]">
                                        {{ $project->description ?? 'No description' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Stats Badges with White Outlined Icon --}}
                        <div class="mt-4 flex items-center gap-3">
                            {{-- Tests --}}
                            <div class="flex items-center gap-2 rounded-lg bg-zinc-50 px-2.5 py-1.5 border border-zinc-100 dark:bg-zinc-800/60 dark:border-zinc-800">
                                <div class="flex size-5 shrink-0 items-center justify-center rounded-md bg-emerald-500 text-white shadow-sm outline outline-2 outline-white dark:outline-zinc-900">
                                    <flux:icon.beaker class="size-3" />
                                </div>
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $project->tests_count }} {{ Str::plural('Test', $project->tests_count) }}
                                </span>
                            </div>

                            {{-- Scripts --}}
                            <div class="flex items-center gap-2 rounded-lg bg-zinc-50 px-2.5 py-1.5 border border-zinc-100 dark:bg-zinc-800/60 dark:border-zinc-800">
                                <div class="flex size-5 shrink-0 items-center justify-center rounded-md bg-purple-500 text-white shadow-sm outline outline-2 outline-white dark:outline-zinc-900">
                                    <flux:icon.code-bracket class="size-3" />
                                </div>
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $project->scripts_count }} {{ Str::plural('Script', $project->scripts_count) }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                {{-- Bottom Stat-Style Bar (Hosts Actions in place of % trends) --}}
                <div class="flex items-center justify-between px-4 py-2.5 dark:bg-zinc-800/80">
                    <span class="text-[11px] text-[#919191]">
                        Created {{ $project->created_at?->diffForHumans() ?? 'recently' }}
                    </span>

                    {{-- Actions (Edit & Delete Icons) --}}
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            wire:click="edit('{{ $project->id }}')"
                            class="rounded-md p-1 text-zinc-400 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            title="Edit Project"
                        >
                            <flux:icon.pencil-square class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            wire:click="delete('{{ $project->id }}')"
                            wire:confirm="Are you sure you want to delete this project? All tests, scripts, runs, and repositories will be permanently removed."
                            class="rounded-md p-1 text-zinc-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                            title="Delete Project"
                        >
                            <flux:icon.trash class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            @if ($search)
                <div class="col-span-full flex flex-col items-center justify-center gap-y-3 rounded-xl border border-dashed border-zinc-200 py-16 dark:border-zinc-700">
                    <flux:icon.magnifying-glass class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No projects match "{{ $search }}"</flux:text>
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                </div>
            @else
                <div class="col-span-full flex flex-col items-center justify-center gap-y-3 rounded-xl border border-dashed border-zinc-200 py-16 dark:border-zinc-700">
                    <flux:icon.folder-open class="size-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No projects created yet.</flux:text>
                    <flux:modal.trigger name="create-project">
                        <flux:button variant="primary" size="sm">Create your first project</flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        @endforelse
    </div>

    {{-- Modal: Create Project --}}
    <flux:modal name="create-project" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create project</flux:heading>
                <flux:text class="mt-2">Enter a project name and description to create a project.</flux:text>
            </div>

            <form wire:submit="submit" class="space-y-6">
                <flux:input wire:model="name" label="Name" placeholder="Project name" />

                <flux:textarea wire:model="description" label="Description" placeholder="Project description" />

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Create Project</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal: Edit Project --}}
    <flux:modal name="edit-project" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit project</flux:heading>
                <flux:text class="mt-2">Update project details.</flux:text>
            </div>

            <form wire:submit="update" class="space-y-6">
                <flux:input wire:model="editName" label="Name" placeholder="Project name" />

                <flux:textarea wire:model="editDescription" label="Description" placeholder="Project description" />

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Save Changes</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
