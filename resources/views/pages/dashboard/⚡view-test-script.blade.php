<?php

use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;
    public Test $test;
    public Script $script;
};
?>

<div class="p-0">
    {{-- inside your Volt single-file component --}}
    <x-code-editor
        name="script_content"
        :value="$script->content"
        language="javascript"
        height="500px"
        wire:key="editor-{{ $script->id }}"
    />
</div>
