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
        <flux:heading size="xl">Overview</flux:heading>
        <flux:text>Real time load testing performance and system telemetry</flux:text>
    </div>
    <div class="flex gap-x-2 w-full">
        <div class="flex w-1/4 border p-3">
            <div class="flex flex-col gap-y-5 w-2/3">
                <flux:text class="text-xs font-light">TOTAL TESTS</flux:text>
                <flux:text class="text-xl font-bold">24</flux:text>
            </div>
            <div class="flex items-center justify-end w-1/3">
                <flux:icon.beaker />
            </div>
        </div>
        <div class="flex w-1/4 border p-3">
            <div class="flex flex-col gap-y-5 w-2/3">
                <flux:text class="text-xs font-light">RUNS THIS WEEK</flux:text>
                <flux:text class="text-xl font-bold">134</flux:text>
            </div>
            <div class="flex items-center justify-end w-1/3">
                <flux:icon.beaker />
            </div>
        </div>
        <div class="flex w-1/4 border p-3">
            <div class="flex flex-col gap-y-5 w-2/3">
                <flux:text class="text-xs font-light">LAST RUN STATUS</flux:text>
                <flux:text class="text-xl font-bold text-green-600">PASSED</flux:text>
            </div>
            <div class="flex items-center justify-end w-1/3">
                <flux:icon.beaker />
            </div>
        </div>
        <div class="flex w-1/4 border p-3">
            <div class="flex flex-col gap-y-5 w-2/3">
                <flux:text class="text-xs font-light">TOTAL TESTS</flux:text>
                <flux:text class="text-xl font-bold">24</flux:text>
            </div>
            <div class="flex items-center justify-end w-1/3">
                <flux:icon.beaker />
            </div>
        </div>
    </div>
</div>
