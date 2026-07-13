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
        <flux:heading size="xl">New Test</flux:heading>
        <flux:text>Initialize a high-performance load simulation. Define your targets and orchestration parameters.</flux:text>
    </div>
    <div class="flex w-full justify-center">
        <form action="" class="w-5/6">
            <div class="flex w-full flex-col gap-y-5">
                <div class="flex items-center flex-row gap-x-5 w-full">
                    <div class="w-1/2">
                        <flux:input class="w-full!" label="Test Identification" />
                    </div>
                    <div class="w-1/2">
                        <flux:input label="Test Identification" />
                    </div>
                </div>
                <div class="flex items-center flex-row gap-x-5 w-full">
                    <div class="w-1/2">
                        <flux:select class="w-full!" label="Repository Source">
                            <flux:select.option>Select repository source</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="w-1/2">
                        <flux:input label="Test Identification" />
                    </div>
                </div>
                <div class="flex flex-col gap-y-2">
                    <flux:label>Starting Point</flux:label>
                    <div class="flex divide-x">
                        <div class="flex flex-col gap-y-1 w-1/3 p-5 items-center justify-center border">
                            <flux:icon.chat-bubble-left-right />
                            <flux:heading>Describe it in chat</flux:heading>
                            <flux:text>AI-ASSISTED</flux:text>
                        </div>
                        <div class="flex flex-col gap-y-1 w-1/3 p-5 items-center justify-center border">
                            <flux:icon.code-bracket />
                            <flux:heading>Start from blank script</flux:heading>
                            <flux:text>ADVANCED</flux:text>
                        </div>
                        <div class="flex flex-col gap-y-1 w-1/3 p-5 items-center justify-center border">
                            <flux:icon.chat-bubble-left-right />
                            <flux:heading>Start from template</flux:heading>
                            <flux:text>READY TO USE</flux:text>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between">
                    <div class="flex items-center">
                        <flux:icon.information-circle class="size-4" />
                        <flux:text>Test configuration is immutable once running</flux:text>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <flux:button variant="ghost">Cancel</flux:button>
                        <flux:button variant="primary">Create Test</flux:button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
