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

    public function statusVariant(string $status): array
    {
        return match ($status) {
            'passed', 'completed' => ['label' => ucfirst($status), 'class' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10'],
            'running' => ['label' => 'Running', 'class' => 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-500/10'],
            'queued' => ['label' => 'Queued', 'class' => 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10'],
            'failed', 'error' => ['label' => ucfirst($status), 'class' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-500/10'],
            default => ['label' => ucfirst($status), 'class' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-800'],
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'passed', 'completed' => 'bg-emerald-500',
            'running' => 'bg-blue-500',
            'queued' => 'bg-amber-500',
            'failed', 'error' => 'bg-red-500',
            default => 'bg-zinc-400',
        };
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

<div class="flex flex-col gap-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Runs</flux:heading>
            <flux:text class="mt-1">View and manage all load test runs.</flux:text>
        </div>
    </div>

    {{-- Search + count --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by test or script..."
                class="w-full rounded-lg border border-[#EDEDED] bg-white py-2 pl-9 pr-3 text-sm text-zinc-700 placeholder:text-zinc-400 focus:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:ring-zinc-800"
            />
        </div>

        <flux:text class="text-xs text-[#919191]">
            {{ $this->runs->total() }} {{ Str::plural('run', $this->runs->total()) }}
        </flux:text>
    </div>

    {{-- Table card --}}
    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
        @if ($this->runs->isEmpty())
            <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl bg-white py-16 dark:bg-zinc-900">
                @if ($search)
                    <flux:icon.magnifying-glass class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No runs match "{{ $search }}"</flux:text>
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                @else
                    <flux:icon.play class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No runs recorded yet.</flux:text>
                    <flux:button icon="plus" variant="primary" size="sm" :href="route('projects.tests', ['project' => $this->project])" wire:navigate>Run a test</flux:button>
                @endif
            </div>
        @else
            <div class="flex items-center justify-between px-3 py-2.5">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">All runs</flux:text>

                <div class="flex items-center gap-2">
                    <label class="text-xs text-[#919191]">Show</label>
                    <select
                        wire:model.live="perPage"
                        class="rounded-md border border-[#EDEDED] bg-white px-2 py-1 text-xs text-zinc-600 focus:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:focus:ring-zinc-800"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <div class="p-2 bg-white dark:bg-zinc-900 rounded-xl overflow-x-auto">
                <table class="w-full border-separate border-spacing-0 bg-white dark:bg-zinc-900">
                    <thead>
                        <tr class="text-left">
                            <th
                                class="cursor-pointer select-none rounded-l-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                Status
                            </th>
                            <th
                                class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400"
                            >
                                Test
                            </th>
                            <th
                                class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400"
                            >
                                Script
                            </th>
                            <th
                                wire:click="sortBy('duration_seconds')"
                                class="cursor-pointer select-none bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Duration
                                    @if ($sortField === 'duration_seconds')
                                        <span class="text-zinc-400">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </span>
                            </th>
                            <th
                                class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400"
                            >
                                Error Rate
                            </th>
                            <th
                                wire:click="sortBy('started_at')"
                                class="cursor-pointer select-none bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Started
                                    @if ($sortField === 'started_at')
                                        <span class="text-zinc-400">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </span>
                            </th>
                            <th class="rounded-r-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        @foreach ($this->runs as $run)
                            @php
                                $status = $this->statusVariant($run->status);
                            @endphp
                            <tr wire:key="{{ $run->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status['class'] }}">
                                        <span class="size-1.5 rounded-full {{ $this->statusColor($run->status) }}"></span>
                                        {{ $status['label'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <a
                                        wire:navigate
                                        href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $run->script?->test]) }}"
                                        class="font-semibold text-zinc-900 transition hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ $run->script?->test?->name ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                    {{ $run->script?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ $this->formatDuration($run->duration_seconds) }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums">
                                    @if (isset($run->error_rate))
                                        <span class="font-semibold {{ $run->error_rate > 1 ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format($run->error_rate, 1) }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums text-[#919191]">
                                    {{ $run->started_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                    <a
                                        wire:navigate
                                        href="{{ route('projects.runs.view', ['project' => $this->project, 'run' => $run]) }}"
                                        class="inline-flex items-center justify-center rounded-md p-1 text-zinc-400 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                        title="View Run"
                                    >
                                        <flux:icon.eye class="size-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->runs->hasPages())
                <div class="flex items-center justify-between px-3 py-2.5">
                    <flux:text class="text-xs text-[#919191]">
                        Showing {{ $this->runs->firstItem() }}–{{ $this->runs->lastItem() }} of {{ $this->runs->total() }}
                    </flux:text>

                    <div class="flex items-center gap-1">
                        @if ($this->runs->onFirstPage())
                            <span class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-zinc-300 dark:text-zinc-600 cursor-default">
                                <flux:icon.chevron-left class="size-3.5" />
                            </span>
                        @else
                            <button
                                wire:click="previousPage"
                                class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            >
                                <flux:icon.chevron-left class="size-3.5" />
                            </button>
                        @endif

                        @foreach ($this->runs->getUrlRange(max(1, $this->runs->currentPage() - 2), min($this->runs->lastPage(), $this->runs->currentPage() + 2)) as $page => $url)
                            @if ($page == $this->runs->currentPage())
                                <span class="inline-flex items-center justify-center rounded-md bg-zinc-100 px-2.5 py-1.5 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $page }}</span>
                            @else
                                <button
                                    wire:click="gotoPage({{ $page }})"
                                    class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach

                        @if ($this->runs->hasMorePages())
                            <button
                                wire:click="nextPage"
                                class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            >
                                <flux:icon.chevron-right class="size-3.5" />
                            </button>
                        @else
                            <span class="inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs text-zinc-300 dark:text-zinc-600 cursor-default">
                                <flux:icon.chevron-right class="size-3.5" />
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
