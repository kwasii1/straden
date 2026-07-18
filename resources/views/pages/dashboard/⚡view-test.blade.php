<?php

use App\Models\Project;
use App\Models\Test;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;
    public Test $test;

    public function mount(string $slug, string $test_slug)
    {
        $this->project = Project::where('slug', $slug)->firstOrFail();
        $this->test = Test::where('slug', $test_slug)->firstOrFail();
    }


};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Test Detail</flux:heading>
        <flux:text>View and manage test script here.</flux:text>
    </div>
</div>
