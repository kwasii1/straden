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

<div class="flex flex-col gap-8">
    <x-page-header title="Tests" description="Each test targets one endpoint and holds the k6 scripts you run against it.">
        <x-slot:actions>
            <flux:button icon="plus" variant="primary" :href="route('projects.new-test', ['project' => $this->project])" wire:navigate>New test</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4">
        {{-- Search + count --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full sm:max-w-xs">
                <flux:input
                    icon="magnifying-glass"
                    size="sm"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search tests"
                    aria-label="Search tests"
                />
            </div>

            <p class="text-xs text-zinc-500 tabular-nums">
                {{ $this->tests->total() }} {{ Str::plural('test', $this->tests->total()) }}
            </p>
        </div>

        <section class="ui-panel overflow-hidden">
            @if ($this->tests->isEmpty())
                @if ($search)
                    <x-empty-state icon="magnifying-glass" :title="'No tests match “'.$search.'”'" description="Try a different name, or clear the search to see every test.">
                        <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">Clear search</flux:button>
                    </x-empty-state>
                @else
                    <x-empty-state icon="beaker" title="No tests yet" description="Create a test to point a load script at one of this project's endpoints.">
                        <flux:button icon="plus" variant="primary" size="sm" :href="route('projects.new-test', ['project' => $this->project])" wire:navigate>Create your first test</flux:button>
                    </x-empty-state>
                @endif
            @else
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">All tests</h2>

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
                                <th>
                                    <button type="button" class="ui-table-sort" wire:click="sortBy('name')">
                                        Name
                                        @if ($sortField === 'name')
                                            @if ($sortDirection === 'asc')
                                                <flux:icon.chevron-up variant="micro" />
                                            @else
                                                <flux:icon.chevron-down variant="micro" />
                                            @endif
                                        @endif
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="ui-table-sort" wire:click="sortBy('target_url')">
                                        Target URL
                                        @if ($sortField === 'target_url')
                                            @if ($sortDirection === 'asc')
                                                <flux:icon.chevron-up variant="micro" />
                                            @else
                                                <flux:icon.chevron-down variant="micro" />
                                            @endif
                                        @endif
                                    </button>
                                </th>
                                <th>Project</th>
                                <th>
                                    <button type="button" class="ui-table-sort" wire:click="sortBy('created_at')">
                                        Created
                                        @if ($sortField === 'created_at')
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
                            @foreach ($this->tests as $test)
                                <tr wire:key="{{ $test->id }}">
                                    <td class="whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <span class="ui-monogram size-6 rounded-md text-[10px]">{{ strtoupper(substr($test->name, 0, 2)) }}</span>
                                            <a
                                                wire:navigate
                                                href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $test]) }}"
                                                class="font-medium text-zinc-900 decoration-zinc-300 underline-offset-[3px] hover:underline"
                                            >
                                                {{ $test->name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap text-zinc-500">
                                        {{ $test->target_url ? parse_url($test->target_url, PHP_URL_HOST) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap text-zinc-500">
                                        {{ $this->project->name }}
                                    </td>
                                    <td class="whitespace-nowrap text-zinc-500">
                                        {{ $test->created_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-0.5">
                                            <a
                                                wire:navigate
                                                href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $test]) }}"
                                                class="ui-icon-button"
                                                title="View test"
                                                aria-label="View test"
                                            >
                                                <flux:icon.eye variant="micro" />
                                            </a>
                                            <button
                                                type="button"
                                                wire:click="delete('{{ $test->id }}')"
                                                wire:confirm="Are you sure you want to delete this test? All scripts and runs will be permanently removed."
                                                class="ui-icon-button hover:bg-red-50 hover:text-red-600"
                                                title="Delete test"
                                                aria-label="Delete test"
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

                <x-pagination :paginator="$this->tests" class="ui-panel-footer" />
            @endif
        </section>
    </div>
</div>
