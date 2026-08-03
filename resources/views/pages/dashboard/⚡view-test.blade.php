<?php

use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
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
    public function scripts()
    {
        return $this->test->scripts()
            ->with(['runs' => fn ($q) => $q->latest()->limit(1)])
            ->get();
    }

    #[Computed()]
    public function latestTestRun()
    {
        return $this->test->runs()->latest()->first();
    }

    #[Computed()]
    public function projectConnectors()
    {
        return $this->project->connectors()->get();
    }

    #[Computed()]
    public function connectorSummary(): string
    {
        $count = $this->projectConnectors->count();

        if ($count === 0) {
            return 'No connectors';
        }

        $types = $this->projectConnectors->pluck('type')->map(fn ($t) => $t->label())->unique()->implode(', ');

        return $count.' connector'.($count > 1 ? 's' : '').' ('.$types.')';
    }

    public function formatLastRun(?string $lastRunAt): string
    {
        if ($lastRunAt === null) {
            return 'Never run';
        }

        return Carbon::parse($lastRunAt)->diffForHumans();
    }

    public function runStatusColor(?string $status): string
    {
        return match ($status) {
            'passed' => '#16a34a',
            'running' => '#ca8a04',
            'failed', 'error' => '#dc2626',
            default => '#6b7280',
        };
    }

    public function runStatusLabel(?string $status): string
    {
        return match ($status) {
            null => 'No runs',
            default => ucfirst($status),
        };
    }

    public function submit()
    {
        $this->validate([
            'name' => 'string|required|max:255',
            'description' => 'string|nullable|max:150',
        ]);

        $script = Script::create([
            'name' => $this->name,
            'description' => $this->description,
            'test_id' => $this->test->id,
        ]);

        $basePath = 'scripts/'.$this->test->id.'/'.$script->id;

        Storage::disk('local')->makeDirectory($basePath);

        Storage::disk('local')->put($basePath.'/script.js', <<<'JS'
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 1,
  duration: '30s',
};

export default function () {
  const res = http.get('__TARGET_URL__');
  check(res, { 'status is 200': (r) => r.status === 200 });
  sleep(1);
}
JS);

        $script->update(['script_path' => $basePath.'/script.js']);

        Flux::modal('create-test-script')->close();

        Flux::toast(variant: 'success', text: 'Test script created successfully.');
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
        <div class="flex items-center gap-x-2">
            <flux:modal.trigger name="create-test-script">
                <flux:button variant="primary">Create Test Script</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="generate-with-ai">
                <flux:button variant="primary">Generate Script</flux:button>
            </flux:modal.trigger>
        </div>
    </div>
    <div class="grid grid-cols-3 border divide-x">
        <div class="flex flex-col p-3">
            <flux:text class="">TARGET ENVIRONMENT</flux:text>
            <flux:text class="">{{ $this->test->target_url }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="">CONNECTORS</flux:text>
            <flux:text class="">{{ $this->connectorSummary }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="">LAST RUN STATUS</flux:text>
            @php $latestRun = $this->latestTestRun; @endphp
            @if ($latestRun)
                <div class="flex items-center gap-x-2">
                    <div class="flex p-1 rounded-full" style="background-color: {{ $this->runStatusColor($latestRun->status) }}"></div>
                    <flux:text style="color: {{ $this->runStatusColor($latestRun->status) }}">
                        {{ $this->runStatusLabel($latestRun->status) }}
                    </flux:text>
                </div>
            @else
                <flux:text class="text-gray-400">Never run</flux:text>
            @endif
        </div>
    </div>
    <div class="flex flex-col gap-y-5">
        <flux:heading>Test Scripts</flux:heading>
        <div class="flex flex-col border divide-y">
            @forelse ($this->scripts as $script)
                @php $latestScriptRun = $script->runs->first(); @endphp
                <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->test, 'script' => $script]) }}">
                    <div class="grid grid-cols-3 p-3 cursor-pointer hover:bg-gray-600/5">
                        <div class="flex items-center gap-x-2">
                            <flux:icon.rocket-launch />
                            <div class="flex flex-col">
                                <flux:heading>{{ $script->name }}</flux:heading>
                                <flux:text>{{ $script->description ?: 'No description' }}</flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-x-2">
                            @if ($latestScriptRun)
                                <div class="flex p-1 rounded-full" style="background-color: {{ $this->runStatusColor($latestScriptRun->status) }}"></div>
                                <flux:text style="color: {{ $this->runStatusColor($latestScriptRun->status) }}">
                                    {{ $this->runStatusLabel($latestScriptRun->status) }}
                                </flux:text>
                            @else
                                <div class="flex p-1 rounded-full bg-gray-600"></div>
                                <flux:text class="text-gray-400">No runs</flux:text>
                            @endif
                        </div>
                        <div class="flex items-center justify-center">
                            @if ($latestScriptRun)
                                {{ $this->formatLastRun($script->last_run_at) }}
                            @else
                                Never run
                            @endif
                        </div>
                    </div>
                </a>
            @empty
            <div class="flex p-3 text-zinc-500">
                No scripts created yet.
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
    <flux:modal class="md:w-1/3 space-y-5" name="generate-with-ai" flyout>
        <livewire:agent-chat :project="$project" :test="$test" />
    </flux:modal>
</div>
