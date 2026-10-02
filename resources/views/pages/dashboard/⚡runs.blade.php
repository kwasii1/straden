<?php

use App\Models\Project;
use App\Models\Run;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::main-app')]
class extends Component
{
    use WithPagination;

    public Project $project;

    public string $search = '';

    public string $sortField = 'started_at';

    public string $sortDirection = 'desc';

    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function runs()
    {
        return Run::with(['script.test'])
            ->whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->whereHas('script.test', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('script', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function totalRuns(): int
    {
        return Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))->count();
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $remainingSeconds > 0
            ? "{$minutes}m {$remainingSeconds}s"
            : "{$minutes}m";
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="Runs" description="Every load test run in this project, with its result and timings." />

    <div class="flex flex-col gap-4">
        {{-- Search + count --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full sm:max-w-xs">
                <flux:input
                    icon="magnifying-glass"
                    size="sm"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by test or script"
                    aria-label="Search runs by test or script"
                />
            </div>

            <p class="text-xs text-zinc-500 tabular-nums">
                {{ $this->runs->total() }} {{ Str::plural('run', $this->runs->total()) }}
            </p>
        </div>

        <section class="ui-panel overflow-hidden">
            @if ($this->runs->isEmpty())
                @if ($search)
                    <x-empty-state icon="magnifying-glass" :title="'No runs match “'.$search.'”'" description="Try a different test or script name, or clear the search.">
                        <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                    </x-empty-state>
                @else
                    <x-empty-state icon="play" title="No runs yet" description="Open a test and run one of its scripts to see results here.">
                        <flux:button icon="plus" variant="primary" size="sm" :href="route('projects.tests', ['project' => $this->project])" wire:navigate>Run a test</flux:button>
                    </x-empty-state>
                @endif
            @else
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">All runs</h2>

                    <label class="flex items-center gap-2 text-xs text-zinc-500">
                        Show
                        <select wire:model.live="perPage" class="ui-select">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </header>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Test</th>
                                <th>Script</th>
                                <th class="text-right!">
                                    <button type="button" class="ui-table-sort" wire:click="sortBy('duration_seconds')">
                                        Duration
                                        @if ($sortField === 'duration_seconds')
                                            @if ($sortDirection === 'asc')
                                                <flux:icon.chevron-up variant="micro" />
                                            @else
                                                <flux:icon.chevron-down variant="micro" />
                                            @endif
                                        @endif
                                    </button>
                                </th>
                                <th class="text-right!">Error rate</th>
                                <th class="text-right!">
                                    <button type="button" class="ui-table-sort" wire:click="sortBy('started_at')">
                                        Started
                                        @if ($sortField === 'started_at')
                                            @if ($sortDirection === 'asc')
                                                <flux:icon.chevron-up variant="micro" />
                                            @else
                                                <flux:icon.chevron-down variant="micro" />
                                            @endif
                                        @endif
                                    </button>
                                </th>
                                <th class="text-right!"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->runs as $run)
                                <tr wire:key="{{ $run->id }}">
                                    <td class="whitespace-nowrap">
                                        <x-status-badge :status="$run->status" />
                                    </td>
                                    <td class="max-w-64 truncate">
                                        <a
                                            wire:navigate
                                            href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $run->script?->test]) }}"
                                            class="font-medium text-zinc-900 decoration-zinc-300 underline-offset-[3px] hover:underline"
                                        >
                                            {{ $run->script?->test?->name ?? '—' }}
                                        </a>
                                    </td>
                                    <td class="max-w-56 truncate text-zinc-500">
                                        {{ $run->script?->name ?? '—' }}
                                    </td>
                                    <td class="text-right whitespace-nowrap tabular-nums">
                                        {{ $this->formatDuration($run->duration_seconds) }}
                                    </td>
                                    <td class="text-right whitespace-nowrap tabular-nums">
                                        @if (isset($run->error_rate))
                                            <span @class(['font-medium text-red-700' => $run->error_rate > 1])>
                                                {{ number_format($run->error_rate, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap text-zinc-500">
                                        {{ $run->started_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <a
                                            wire:navigate
                                            href="{{ route('projects.runs.view', ['project' => $this->project, 'run' => $run]) }}"
                                            class="ui-icon-button"
                                            title="View run"
                                            aria-label="View run"
                                        >
                                            <flux:icon.eye variant="micro" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-pagination :paginator="$this->runs" class="ui-panel-footer" />
            @endif
        </section>
    </div>
</div>
