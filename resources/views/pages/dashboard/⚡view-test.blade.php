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

    public string $name = '';

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
            return 'No connectors configured';
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

    public function runStatusMeta(?string $status): array
    {
        return match ($status) {
            'passed' => [
                'label' => 'Passed',
                'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400',
            ],
            'running' => [
                'label' => 'Running',
                'class' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-400',
            ],
            'failed', 'error' => [
                'label' => ucfirst($status),
                'class' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400',
            ],
            default => [
                'label' => 'No Runs',
                'class' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
            ],
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

        $this->reset(['name', 'description']);

        Flux::modal('create-test-script')->close();

        Flux::toast(variant: 'success', text: 'Test script created successfully.');
    }
};
?>

<div class="flex flex-col gap-y-8 p-1 sm:p-2">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-x-2 text-xs text-zinc-400 mb-1">
                <a href="{{ route('projects', $project) }}" class="hover:text-zinc-600 dark:hover:text-zinc-300 transition">{{ $project->name }}</a>
                <span>/</span>
                <span>Tests</span>
            </div>
            <flux:heading size="xl" class="font-bold tracking-tight">{{ $this->test->name }}</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                View execution scripts, connector settings, and execution history.
            </flux:text>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <flux:modal.trigger name="generate-with-ai">
                <flux:button variant="subtle" icon="sparkles" size="sm" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    Straden Agent
                </flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="create-test-script">
                <flux:button variant="primary" icon="plus" size="sm" class="rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900">
                    Create Test Script
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Top 3 Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Card 1: Target Environment --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-white">
                        <flux:icon.globe-alt class="size-4" variant="outline" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Target Environment</flux:text>
                </div>
                <div class="px-4 pb-3.5">
                    <p class="text-lg font-semibold text-zinc-900 dark:text-white truncate font-mono" title="{{ $this->test->target_url }}">
                        {{ $this->test->target_url }}
                    </p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191]">active target endpoint</p>
            </div>
        </div>

        {{-- Card 2: Connectors --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500 text-white">
                        <flux:icon.arrows-right-left class="size-4" variant="outline" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Connectors</flux:text>
                </div>
                <div class="px-4 pb-3.5">
                    <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $this->projectConnectors->count() }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191] truncate">{{ $this->connectorSummary }}</p>
            </div>
        </div>

        {{-- Card 3: Last Execution --}}
        @php
            $latestRun = $this->latestTestRun;
            $meta = $this->runStatusMeta($latestRun?->status);
        @endphp
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <flux:icon.clock class="size-4" variant="outline" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Last Execution</flux:text>
                </div>
                <div class="px-4 pb-3.5 flex items-center justify-between">
                    <p class="text-lg font-semibold text-zinc-900 dark:text-white capitalize">
                        {{ $latestRun ? $latestRun->status : 'None' }}
                    </p>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $meta['class'] }}">
                        <span class="size-1.5 rounded-full bg-current"></span>
                        {{ $meta['label'] }}
                    </span>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191]">
                    {{ $latestRun ? \Carbon\Carbon::parse($latestRun->created_at)->diffForHumans() : 'no executions recorded' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Test Scripts Section --}}
    <div class="rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
        {{-- Card Header --}}
        <div class="flex items-center justify-between px-3 py-2.5">
            <div>
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Test Scripts</flux:text>
                <p class="text-xs text-[#919191] dark:text-zinc-400">List of executable test scripts under this test configuration</p>
            </div>
            <flux:modal.trigger name="create-test-script">
                <flux:button variant="ghost" size="sm" icon="plus" class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                    Add Script
                </flux:button>
            </flux:modal.trigger>
        </div>

        {{-- Inner Rounded White Div --}}
        <div class="p-2 bg-white dark:bg-zinc-900 rounded-xl overflow-x-auto">
            @if ($this->scripts->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 gap-y-3 text-center">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-900 text-white">
                        <flux:icon.rocket-launch class="size-5" variant="outline" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">No test scripts created yet</p>
                        <p class="text-xs text-[#919191] mt-0.5">Create your first load testing script or generate one using AI.</p>
                    </div>
                    <flux:modal.trigger name="create-test-script">
                        <flux:button variant="primary" size="sm" icon="plus" class="mt-2">
                            Create Test Script
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @else
                <table class="w-full border-separate border-spacing-0 bg-white dark:bg-zinc-900">
                    <thead>
                        <tr class="text-left">
                            <th class="rounded-l-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Script Details</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Last Status</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Last Execution</th>
                            <th class="rounded-r-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        @foreach ($this->scripts as $script)
                            @php
                                $latestScriptRun = $script->runs->first();
                                $statusMeta = $this->runStatusMeta($latestScriptRun?->status);
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->test, 'script' => $script]) }}" class="group flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white">
                                            <flux:icon.rocket-launch class="size-4" variant="outline" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-zinc-900 dark:text-white group-hover:underline">
                                                {{ $script->name }}
                                            </span>
                                            <span class="text-[11px] text-[#919191] max-w-xs truncate">
                                                {{ $script->description ?: 'No description provided' }}
                                            </span>
                                        </div>
                                    </a>
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusMeta['class'] }}">
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ $statusMeta['label'] }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap text-[#919191] tabular-nums">
                                    @if ($latestScriptRun)
                                        {{ $this->formatLastRun($script->last_run_at) }}
                                    @else
                                        Never run
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                    <flux:button
                                        wire:navigate
                                        href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->test, 'script' => $script]) }}"
                                        variant="ghost"
                                        size="sm"
                                        icon-trailing="chevron-right"
                                        class="text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white"
                                    >
                                        View
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Modals --}}
    <flux:modal class="md:w-1/3 space-y-5" name="create-test-script">
        <div>
            <flux:heading size="lg">Create Test Script</flux:heading>
            <flux:text class="text-xs text-zinc-500">Define a new script execution block for this test.</flux:text>
        </div>

        <form wire:submit="submit" class="space-y-4">
            <div>
                <flux:input wire:model="name" label="Script Name" placeholder="e.g., Checkout Flow Load Test" />
            </div>
            <div>
                <flux:textarea wire:model="description" label="Description" placeholder="Briefly explain what this script targets..." />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Create Script</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal class="md:w-1/3 p-0!" name="generate-with-ai" flyout>
        <livewire:agent-chat :project="$project" :test="$test" />
    </flux:modal>
</div>
