<?php

use App\Models\Project;
use App\Models\Test;
use Flux\Flux;
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

    public string $sortField = 'created_at';

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
    public function tests()
    {
        return $this->project->tests()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function totalTests(): int
    {
        return $this->project->tests()->count();
    }

    public function delete(string $testId): void
    {
        $test = Test::findOrFail($testId);
        $test->delete();

        Flux::toast(variant: 'success', text: 'Test deleted successfully.');

        unset($this->tests, $this->totalTests);
    }
};
?>

<div class="flex flex-col gap-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Test Suites</flux:heading>
            <flux:text class="mt-1">Manage and orchestrate high-concurrency load scripts across edge clusters.</flux:text>
        </div>
        <flux:button icon="plus" variant="primary" :href="route('projects.new-test', ['project' => $this->project])" wire:navigate>New Test</flux:button>
    </div>

    {{-- Search + count --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search tests..."
                class="w-full rounded-lg border border-[#EDEDED] bg-white py-2 pl-9 pr-3 text-sm text-zinc-700 placeholder:text-zinc-400 focus:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:ring-zinc-800"
            />
        </div>

        <flux:text class="text-xs text-[#919191]">
            {{ $this->tests->total() }} {{ Str::plural('test', $this->tests->total()) }}
        </flux:text>
    </div>

    {{-- Table card --}}
    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
        @if ($this->tests->isEmpty())
            <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl bg-white py-16 dark:bg-zinc-900">
                @if ($search)
                    <flux:icon.magnifying-glass class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No tests match "{{ $search }}"</flux:text>
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                @else
                    <flux:icon.beaker class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No test suites created yet.</flux:text>
                    <flux:button icon="plus" variant="primary" size="sm" :href="route('projects.new-test', ['project' => $this->project])" wire:navigate>Create your first test</flux:button>
                @endif
            </div>
        @else
            <div class="flex items-center justify-between px-3 py-2.5">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">All tests</flux:text>

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
                                wire:click="sortBy('name')"
                                class="cursor-pointer select-none rounded-l-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Name
                                    @if ($sortField === 'name')
                                        <span class="text-zinc-400">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </span>
                            </th>
                            <th
                                wire:click="sortBy('target_url')"
                                class="cursor-pointer select-none bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Target URL
                                    @if ($sortField === 'target_url')
                                        <span class="text-zinc-400">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </span>
                            </th>
                            <th
                                class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400"
                            >
                                Project
                            </th>
                            <th
                                wire:click="sortBy('created_at')"
                                class="cursor-pointer select-none bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Created
                                    @if ($sortField === 'created_at')
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
                        @foreach ($this->tests as $test)
                            <tr wire:key="{{ $test->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex size-6 shrink-0 items-center justify-center rounded bg-zinc-100 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                            {{ strtoupper(substr($test->name, 0, 2)) }}
                                        </div>
                                        <a
                                            wire:navigate
                                            href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $test]) }}"
                                            class="font-semibold text-zinc-900 transition hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ $test->name }}
                                        </a>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        {{ $test->target_url ? parse_url($test->target_url, PHP_URL_HOST) : '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                    {{ $this->project->name }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums text-[#919191]">
                                    {{ $test->created_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a
                                            wire:navigate
                                            href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $test]) }}"
                                            class="rounded-md p-1 text-zinc-400 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                            title="View Test"
                                        >
                                            <flux:icon.eye class="size-3.5" />
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="delete('{{ $test->id }}')"
                                            wire:confirm="Are you sure you want to delete this test? All scripts and runs will be permanently removed."
                                            class="rounded-md p-1 text-zinc-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                            title="Delete Test"
                                        >
                                            <flux:icon.trash class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->tests->hasPages())
                <div class="flex items-center justify-between px-3 py-2.5">
                    <flux:text class="text-xs text-[#919191]">
                        Showing {{ $this->tests->firstItem() }}–{{ $this->tests->lastItem() }} of {{ $this->tests->total() }}
                    </flux:text>

                    <div class="flex items-center gap-1">
                        @if ($this->tests->onFirstPage())
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

                        @foreach ($this->tests->getUrlRange(max(1, $this->tests->currentPage() - 2), min($this->tests->lastPage(), $this->tests->currentPage() + 2)) as $page => $url)
                            @if ($page == $this->tests->currentPage())
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

                        @if ($this->tests->hasMorePages())
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
