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

    #[Computed()]
    public function projects() {
        return Project::all();
    }

    public function submit() {
        $this->validate([
            'name' => 'required|max:256|string',
            'description' => 'nullable|string'
        ]);

        Project::create([
            'name' => $this->name,
            'description' => $this->description
        ]);

        Flux::modal('create-project')->close();

        Flux::toast(variant: 'success', text:'Project Created Successfully');

        $this->reset();
    }
};
?>

<div class="flex flex-col gap-y-6">
    <div class="flex w-full justify-end">
        <flux:modal.trigger name="create-project">
            <flux:button variant="primary">Create Project</flux:button>
        </flux:modal.trigger>
    </div>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->projects as $project)
            <a
                wire:navigate
                href="{{ route('projects.overview', ['id' => $project->id]) }}"
                wire:key="{{ $project->id }}"
                class="block"
            >
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                {{ $project->name }}
                            </h3>

                            <p class="mt-1 text-sm text-zinc-500">
                                Project Overview
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg p-2 text-red-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                        >
                            <flux:icon.trash class="size-5" />
                        </button>
                    </div>

                    <div class="mt-5 flex gap-6 text-sm">
                        <div>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                1000
                            </p>
                            <p class="text-zinc-500">
                                Runs
                            </p>
                        </div>

                        <div>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                5
                            </p>
                            <p class="text-zinc-500">
                                Scripts
                            </p>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full flex h-40 w-full items-center justify-center rounded-xl border border-dashed border-zinc-300">
                <flux:text>No projects created</flux:text>
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
