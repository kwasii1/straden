<?php

use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;
    public Test $test;

    public string $name;
    public string $description = '';

    #[Computed()]
    public function scripts() {
        return $this->test->scripts;
    }

    public function submit() {
        $this->validate([
            'name' => 'string|required|max:255',
            'description' => 'string|nullable|max:150',
        ]);

        Script::create([
            'name' => $this->name,
            'description' => $this->description,
            'test_id' => $this->test->id
        ]);

        Flux::modal('create-test-script')->close();

        Flux::toast(variant: 'success', text:'Test script Created Successfully');
    }


};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Test Detail</flux:heading>
        <flux:text>View and manage test scripts here.</flux:text>
    </div>
    <div class="flex justify-between items-center">
        <flux:heading size="lg">{{ $this->test->name }}</flux:heading>
        <flux:modal.trigger name="create-test-script">
            <flux:button variant="primary">Create Test Script</flux:button>
        </flux:modal.trigger>
    </div>
    <div class="grid grid-cols-3 border divide-x">
        <div class="flex flex-col p-3">
            <flux:text class="">TARGET ENVIRONMENT</flux:text>
            <flux:text class="">{{ $this->test->target_url }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="">CONNECTORS</flux:text>
            <flux:text class="">{{ $this->test->target_url }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="">LAST RUN STATUS</flux:text>
            <flux:text class="">{{ $this->test->target_url }}</flux:text>
        </div>
    </div>
    <div class="flex flex-col gap-y-5">
        <flux:heading>Test Scripts</flux:heading>
        <div class="flex flex-col border divide-y">
            @forelse ($this->scripts as $script)
                <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->test, 'script' => $script]) }}">
                    <div class="grid grid-cols-3 p-3 cursor-pointer hover:bg-gray-600/5">
                        <div class="flex items-center gap-x-2">
                            <flux:icon.rocket-launch />
                            <div class="flex flex-col">
                                <flux:heading>{{ $script->name }}</flux:heading>
                                <flux:text>Validation</flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-x-2">
                            <div class="flex p-1 rounded-full bg-green-600"></div>
                            <flux:text class="text-green-600">Passed</flux:text>
                        </div>
                        <div class="flex items-center justify-center">Last Run 2hrs ago</div>
                    </div>
                </a>
            @empty
            <div class="flex">
                No scripts.
            </div>
            @endforelse
        </div>
    </div>
    <flux:modal class="md:w-1/3 space-y-5" name="create-test-script">
        <flux:heading>Create Test Script</flux:heading>
        <form wire:submit="submit" class="space-y-3">
            <div>
                <flux:input wire:model="name" label="Name" />
            </div>
            <div>
                <flux:textarea wire:model="description" label="Description" />
            </div>
            <div>
                <flux:button type="submit" variant="primary">Submit</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
