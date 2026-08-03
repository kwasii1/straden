<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Models\Test;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public string $name;

    public string $target_endpoint;

    public ?string $repository_source = null;

    public array $connectors = [];

    public string $description = '';

    public Project $project;

    public Collection $availableConnectors;

    public Collection $repositories;

    public function mount(Project $project): void
    {
        $this->project = $project;

        $this->repositories = $project->repositories()->get();

        $this->availableConnectors = Connector::query()
            ->where(function ($q) use ($project) {
                $q->where('project_id', $project->id)
                    ->orWhere('is_system', true);
            })
            ->get();

        $influx = $this->availableConnectors->firstWhere('type', ConnectorType::InfluxDb);
        if ($influx) {
            $this->connectors = [(string) $influx->id];
        }
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'target_endpoint' => 'required|url|max:2048',
        ]);

        Test::create([
            'name' => $this->name,
            'project_id' => $this->project->id,
            'target_url' => $this->target_endpoint,
            'description' => $this->description,
        ]);

        Flux::toast(variant: 'success', text: 'Test Created Successfully');

        $this->reset(['name', 'target_endpoint', 'repository_source', 'connectors', 'description']);
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">New Test</flux:heading>
        <flux:text>Initialize a high-performance load simulation. Define your targets and orchestration parameters.</flux:text>
    </div>
    <div class="flex w-full justify-center">
        <form wire:submit="submit" class="w-5/6">
            <div class="flex w-full flex-col gap-y-5">
                <div class="grid grid-cols-2 gap-4 w-full">
                    <div>
                        <flux:input wire:model="name" label="Test Identification" />
                    </div>
                    <div>
                        <flux:input wire:model="target_endpoint" label="Target Endpoint" />
                    </div>
                </div>
                <div class="w-full">
                    <flux:textarea placeholder="Smoke test for project kodak" class="w-full min-w-full" wire:model="description" label="Description" />
                </div>
                <div class="grid grid-cols-2 gap-4 w-full">
                    <div>
                        <flux:select wire:model="repository_source" label="Repository Source" class="w-full!">
                            <flux:select.option value="">Select repository source</flux:select.option>
                            @foreach ($repositories as $repo)
                                <flux:select.option value="{{ $repo->id }}">
                                    {{ $repo->name }} ({{ $repo->type }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:field>
                            <flux:label>Connectors</flux:label>
                            @if ($availableConnectors->isEmpty())
                                <flux:description class="mt-1">No connectors available</flux:description>
                            @else
                                <flux:checkbox.group wire:model="connectors" variant="pills" class="mt-1">
                                    @foreach ($availableConnectors as $connector)
                                        <flux:checkbox
                                            value="{{ $connector->id }}"
                                            label="{{ $connector->name }} ({{ $connector->type->label() }})"
                                        />
                                    @endforeach
                                </flux:checkbox.group>
                            @endif
                        </flux:field>
                    </div>
                </div>
                <div class="flex justify-between">
                    <div class="flex items-center">
                        <flux:icon.information-circle class="size-4" />
                        <flux:text>Test configuration is immutable once running</flux:text>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Create Test</flux:button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
