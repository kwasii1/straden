<?php

namespace App\Livewire;

use App\Models\Test;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TestTable extends PowerGridComponent
{
    public string $tableName = 'testTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Test::query()->with('project');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('target_url')
            ->add('project_id')
            ->add('project_name', fn ($test) => e($test->project->name))
            ->add('description')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Target url', 'target_url')
                ->sortable()
                ->searchable(),

            Column::make('Project', 'project_name')
                ->sortable()
                ->searchable(),
            // Column::make('Project', 'project_id')
            //     ->sortable()
            //     ->searchable(),

            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->js('alert('.$rowId.')');
    }

    public function actions(Test $row): array
    {
        return [
            Button::add('view')
                ->slot(Blade::render(<<<'HTML'
                    <flux:icon.eye class="size-4 border-none cursor-pointer" />
                HTML))
                ->id()
                ->route('projects.view-test', ['project' => $row->project, 'test' => $row]),
            Button::add('edit')
                ->slot(Blade::render(<<<'HTML'
                    <flux:icon.pencil class="size-4 border-none cursor-pointer" />
                HTML))
                ->id()
                ->dispatch('edit', ['rowId' => $row->id]),
            Button::add('delete')
                ->slot(Blade::render(<<<'HTML'
                    <flux:icon.trash class="size-4 border-none cursor-pointer" />
                HTML))
                ->id()
                ->dispatch('delete', ['rowId' => $row->id]),
        ];
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}
