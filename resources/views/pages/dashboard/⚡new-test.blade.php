<?php

use App\Models\Project;
use App\Models\Test;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public string $name;
    public string $target_endpoint;
    public string $repository_source;
    public $connectors = [];
    public string $description;

    public Project $project;

    public function mount(string $slug)
    {
        $this->project = Project::where('slug', $slug)->firstOrFail();
    }

    public function submit() {
        $this->validate([
            'name' => 'required|string|max:255',
            'target_endpoint' => 'required|url|max:2048',
        ]);

        Test::create([
            'name' => $this->name,
            'project_id' => $this->project->id,
            'target_url' => $this->target_endpoint,
            'description' => $this->description
        ]);

        Flux::toast(variant: 'success', text:'Test Created Successfully');

        $this->reset();
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
                        <flux:select class="w-full!" label="Repository Source">
                            <flux:select.option>Select repository source</flux:select.option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:input label="Connectors" />
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
