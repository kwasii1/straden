<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    //
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Runs</flux:heading>
        <flux:text>View and manage all load test runs.</flux:text>
    </div>
    <div>
        <livewire:run-table/>
    </div>
</div>
