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

    #[Computed]
    public function projects()
    {
        return Project::withCount(['tests', 'scripts'])->latest()->get();
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

        $this->reset();

        unset($this->projects);
    }

    public function delete(string $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $project->delete();

        Flux::modal('delete-project')->close();

        Flux::toast(variant: 'success', text: 'Project deleted successfully.');

        unset($this->projects);
    }
};
?>

<div class="flex flex-col gap-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Projects</flux:heading>
            <flux:text>Manage your load testing projects</flux:text>
        </div>
        <flux:modal.trigger name="create-project">
            <flux:button variant="primary">Create Project</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->projects as $project)
            <div wire:key="{{ $project->id }}" class="group relative">
                <a
                    wire:navigate
                    href="{{ route('projects.overview', ['project' => $project]) }}"
                    class="block rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white truncate">
                                {{ $project->name }}
                            </h3>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 line-clamp-2">
                                {{ $project->description ?? 'No description' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click.prevent="delete('{{ $project->id }}')"
                            wire:confirm="Are you sure you want to delete this project? All tests, scripts, runs, and repositories will be permanently removed."
                            class="shrink-0 rounded-lg p-2 text-zinc-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 focus:opacity-100"
                        >
                            <flux:icon.trash class="size-5" />
                        </button>
                    </div>

                    <div class="mt-5 flex gap-6 text-sm border-t border-zinc-100 dark:border-zinc-800 pt-4">
                        <div>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                {{ $project->tests_count }}
                            </p>
                            <p class="text-zinc-500">
                                Tests
                            </p>
                        </div>

                        <div>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                {{ $project->scripts_count }}
                            </p>
                            <p class="text-zinc-500">
                                Scripts
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 py-16 gap-y-3">
                <flux:icon.folder-open class="size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500 dark:text-zinc-400">No projects created yet.</flux:text>
                <flux:modal.trigger name="create-project">
                    <flux:button variant="primary" size="sm">Create your first project</flux:button>
                </flux:modal.trigger>
            </div>
        @endforelse
    </div>

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
</div>
