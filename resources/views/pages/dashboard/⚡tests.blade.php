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

};
?>

<div class="flex flex-col gap-y-10">
    @php
        $projectId = request()->route('slug');
    @endphp
    <div class="flex flex-col">
        <flux:heading size="xl">Test Suites</flux:heading>
        <flux:text>Manage and ochestrate hig-concurrency load scripts across edge clusters.</flux:text>
    </div>
    <div class="flex w-full justify-end gap-x-2">
        <flux:button icon="plus" variant="primary" :href="route('projects.new-test', ['slug' => $projectId])" wire:navigate>New Test</flux:button>
    </div>
    <div>
        <livewire:test-table/>
    </div>
</div>
