<?php

namespace App\Livewire;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Multi-select searchable combobox for connectors.
 *
 * Binds to the parent via `wire:model`. The InfluxDB connector is excluded
 * from the options and always attached — it renders as a locked pill.
 */
class ConnectorPicker extends Component
{
    public Project $project;

    #[Modelable]
    public array $selected = [];

    /**
     * Selectable options as [{id, name, type}]. InfluxDB excluded.
     *
     * @var array<int, array{id: string, name: string, type: string}>
     */
    public array $options = [];

    public ?string $influxConnectorId = null;

    public ?string $influxConnectorName = null;

    /**
     * @param  array<int, string>  $selected
     */
    public function mount(Project $project, array $selected = []): void
    {
        $this->project = $project;

        $this->options = Connector::query()
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('is_system', true);
            })
            ->where('type', '!=', ConnectorType::InfluxDb->value)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Connector $connector) => [
                'id' => (string) $connector->id,
                'name' => $connector->name,
                'type' => $connector->type->label(),
            ])
            ->all();

        $influx = Connector::query()
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('is_system', true);
            })
            ->where('type', ConnectorType::InfluxDb->value)
            ->first(['id', 'name']);

        $this->influxConnectorId = $influx ? (string) $influx->id : null;
        $this->influxConnectorName = $influx?->name;
        $this->selected = $this->sanitize($selected);
    }

    public function toggle(string $id): void
    {
        if ($id === $this->influxConnectorId) {
            return;
        }

        if (! in_array($id, array_column($this->options, 'id'), true)) {
            return;
        }

        $this->selected = in_array($id, $this->selected, true)
            ? array_values(array_diff($this->selected, [$id]))
            : [...$this->selected, $id];
    }

    public function remove(string $id): void
    {
        if ($id === $this->influxConnectorId) {
            return;
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    public function updatedSelected(): void
    {
        $this->selected = $this->sanitize($this->selected);
    }

    /**
     * Options for the current selection, in selection order.
     *
     * @return array<int, array{id: string, name: string, type: string}>
     */
    public function selectedOptions(): array
    {
        $byId = [];

        foreach ($this->options as $option) {
            $byId[$option['id']] = $option;
        }

        $selected = [];

        foreach ($this->selected as $id) {
            if (isset($byId[$id])) {
                $selected[] = $byId[$id];
            }
        }

        return $selected;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private function sanitize(array $ids): array
    {
        $valid = array_column($this->options, 'id');

        return array_values(array_intersect(
            array_map(strval(...), $ids),
            $valid
        ));
    }

    public function render()
    {
        return view('livewire.connector-picker');
    }
}
