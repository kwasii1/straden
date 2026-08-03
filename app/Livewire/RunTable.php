<?php

namespace App\Livewire;

use App\Models\Run;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class RunTable extends PowerGridComponent
{
    public string $tableName = 'runTable';

    public ?string $projectId = null;

    public ?string $projectSlug = null;

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
        $query = Run::query()
            ->with(['script.test']);

        if ($this->projectId) {
            $query->whereHas('script.test', fn ($q) => $q->where('project_id', $this->projectId));
        }

        return $query;
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('status', fn (Run $run) => $this->formatStatusHtml($run->status))
            ->add('script_name', fn (Run $run) => $run->script?->name)
            ->add('test_name', fn (Run $run) => $run->script?->test?->name)
            ->add('triggered_by')
            ->add('started_at_formatted', fn (Run $run) => $run->started_at?->format('M j, Y H:i') ?? 'N/A')
            ->add('completed_at_formatted', fn (Run $run) => $run->completed_at?->format('M j, Y H:i') ?? 'N/A')
            ->add('duration_seconds_formatted', fn (Run $run) => $this->formatDuration($run->duration_seconds))
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Test', 'test_name'),

            Column::make('Script', 'script_name'),

            Column::make('Status', 'status')
                ->sortable(),

            Column::make('Triggered by', 'triggered_by')
                ->sortable(),

            Column::make('Started at', 'started_at_formatted', 'started_at')
                ->sortable(),

            Column::make('Completed at', 'completed_at_formatted', 'completed_at')
                ->sortable(),

            Column::make('Duration', 'duration_seconds_formatted', 'duration_seconds')
                ->sortable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function actions(Run $row): array
    {
        $params = [
            'project' => $this->projectSlug ?? '',
            'run' => $row->slug ?? $row->id,
        ];

        return [
            Button::add('view')
                ->slot('View')
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->route('projects.runs.view', $params),
        ];
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'N/A';
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

    private function formatStatusHtml(string $status): string
    {
        $color = match ($status) {
            'passed' => '#16a34a',
            'running' => '#ca8a04',
            'queued' => '#6b7280',
            'failed', 'error' => '#dc2626',
            default => '#6b7280',
        };

        return '<span style="color: '.$color.'">'.ucfirst($status).'</span>';
    }
}
