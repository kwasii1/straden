<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::main-app')]
class extends Component
{
    use WithPagination;

        #[Url(as: 'q', history: true)]
        public string $search = '';

        #[Url(as: 'sort', history: true)]
        public string $sortBy = 'created_at';

        #[Url(as: 'dir', history: true)]
        public string $sortDirection = 'desc';

        #[Url(as: 'per_page', history: true)]
        public int $perPage = 10;

        public array $columns = [
            ['key' => 'name',       'label' => 'Name',    'sortable' => true],
            ['key' => 'status',     'label' => 'Status',  'sortable' => false],
            ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
        ];

        public function sort(string $column): void
        {
            if ($this->sortBy === $column) {
                $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                $this->sortBy = $column;
                $this->sortDirection = 'asc';
            }
            $this->resetPage();
        }

        public function updatedSearch(): void { $this->resetPage(); }
        public function updatedPerPage(): void { $this->resetPage(); }

        // You control the query entirely — joins, eager loads, scopes, whatever.
        #[Computed()]
        public function rows()
        {
            return User::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->when($this->sortBy, fn ($q) => $q->orderBy($this->sortBy, $this->sortDirection))
                ->paginate($this->perPage);
        }
};
?>

<div class="">
    <div class="flex flex-col">
        <flux:heading size="xl">Test Suites</flux:heading>
        <flux:text>Manage and ochestrate hig-concurrency load scripts across edge clusters.</flux:text>
    </div>
    <div class="flex w-full justify-end gap-x-2">
        <flux:button icon="plus" variant="primary">New Test</flux:button>
    </div>
    <div class="flex w-full">
        
    </div>
</div>
