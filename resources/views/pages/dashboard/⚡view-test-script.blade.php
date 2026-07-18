<?php

use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app', ['noPadding' => true])]
class extends Component
{
    public Project $project;
    public Test $test;
    public Script $script;
};
?>

<div class="flex flex-col h-full">
    <div class="flex flex-1 min-h-0">
        <div class="flex flex-col w-3/5 min-h-0">
            <div class="shrink-0 flex justify-between items-center p-3">
                <flux:heading>{{ $script->name }}</flux:heading>
                <flux:button icon="play" variant="primary">Run Test</flux:button>
            </div>
            <div class="flex-1 flex flex-col min-h-0 rounded border border-zinc-800 overflow-hidden">
                <x-editor-tabs class="shrink-0" :tabs="[
                    ['name' => 'test-script.js', 'active' => true],
                    ['name' => 'helpers.ts', 'active' => false],
                    ['name' => 'setup.test.js', 'active' => false],
                    ['name' => 'mock-data.json', 'active' => false],
                    ['name' => 'mock-data.json', 'active' => false],
                    ['name' => 'mock-data.json', 'active' => false],
                ]" />
                <x-code-editor
                    name="script_content"
                    :value="$script->content"
                    language="javascript"
                    height="100%"
                    wire:key="editor-{{ $script->id }}"
                    class="!rounded-none !border-0 flex-1"
                />
            </div>
        </div>
        <x-editor-sidebar class="w-2/5 min-h-0" />
    </div>
</div>
