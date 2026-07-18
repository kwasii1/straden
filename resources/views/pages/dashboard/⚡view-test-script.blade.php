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

<div>
    
</div>
