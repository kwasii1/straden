<?php

use App\Models\Project;
use App\Models\Run;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component
{
    #[Computed]
    public function totalProjects(): int
    {
        return Project::count();
    }

    #[Computed]
    public function totalTests(): int
    {
        return \App\Models\Test::count();
    }

    #[Computed]
    public function totalRuns(): int
    {
        return Run::count();
    }

    #[Computed]
    public function recentProjects()
    {
        return Project::latest()->limit(3)->get();
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:text>Welcome to Straden, your load testing management platform</flux:text>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <div class="flex shrink-0 items-center justify-center size-12 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <flux:icon.rectangle-stack class="size-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Projects</flux:text>
                <flux:text class="text-2xl font-bold">{{ $this->totalProjects }}</flux:text>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <div class="flex shrink-0 items-center justify-center size-12 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                <flux:icon.beaker class="size-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Tests</flux:text>
                <flux:text class="text-2xl font-bold">{{ $this->totalTests }}</flux:text>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5">
            <div class="flex shrink-0 items-center justify-center size-12 rounded-lg bg-purple-50 dark:bg-purple-900/30">
                <flux:icon.play class="size-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Runs</flux:text>
                <flux:text class="text-2xl font-bold">{{ $this->totalRuns }}</flux:text>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Recent Projects</flux:heading>
            <flux:button href="{{ route('projects') }}" wire:navigate variant="ghost" size="sm">View all</flux:button>
        </div>

        @if ($this->recentProjects->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 py-16 gap-y-3">
                <flux:icon.folder-open class="size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500 dark:text-zinc-400">No projects yet.</flux:text>
                <flux:button href="{{ route('projects') }}" variant="primary" size="sm" wire:navigate>Create your first project</flux:button>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->recentProjects as $project)
                    <a
                        wire:navigate
                        href="{{ route('projects.overview', $project) }}"
                        wire:key="{{ $project->id }}"
                        class="block rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 transition hover:border-zinc-300 hover:shadow-md dark:hover:border-zinc-600"
                    >
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $project->name }}</h3>
                        @if ($project->description)
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $project->description }}</p>
                        @endif
                        <div class="mt-4 flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                            <flux:text class="text-xs">View project &rarr;</flux:text>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
