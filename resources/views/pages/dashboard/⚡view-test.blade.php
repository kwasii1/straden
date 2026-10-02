<?php

use App\Enums\ConnectorType;
use App\Models\Project;
use App\Models\Script;
use App\Models\Test;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
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

    /**
     * Selected connector IDs for this test (InfluxDB excluded — always on).
     *
     * @var array<int, string>
     */
    public array $testConnectors = [];

    /**
     * Repository IDs linked to this test (empty = all project repositories).
     *
     * @var array<int, string>
     */
    public array $testRepositories = [];

    public string $testName = '';

    public string $targetUrl = '';

    public string $testDescription = '';

    public function mount(Project $project, Test $test): void
    {
        $this->project = $project;
        $this->test = $test;
        $this->testConnectors = $this->testConnectorIds();
        $this->testRepositories = $this->testRepositoryIds();
    }

    public function startUpdate(): void
    {
        $this->test->refresh();
        $this->testName = $this->test->name;
        $this->targetUrl = $this->test->target_url;
        $this->testDescription = $this->test->description ?? '';
        $this->testConnectors = $this->testConnectorIds();
        $this->testRepositories = $this->testRepositoryIds();
    }

    public function updateTest(): void
    {
        $this->validate([
            'testName' => 'required|string|max:255',
            'targetUrl' => 'required|url|max:2048',
            'testDescription' => 'nullable|string|max:2000',
            'testConnectors' => 'nullable|array',
            'testConnectors.*' => 'string',
            'testRepositories' => 'nullable|array',
            'testRepositories.*' => 'string',
        ]);

        $this->test->update([
            'name' => $this->testName,
            'target_url' => $this->targetUrl,
            'description' => $this->testDescription !== '' ? $this->testDescription : null,
        ]);

        $this->test->syncConnectors($this->testConnectors);
        $this->testConnectors = $this->testConnectorIds();
        $this->test->syncRepositories($this->testRepositories);
        $this->testRepositories = $this->testRepositoryIds();

        Flux::modal('update-test')->close();
        Flux::toast(variant: 'success', text: 'Test updated successfully.');
    }

    /**
     * IDs of the repositories linked to this test.
     *
     * @return array<int, string>
     */
    private function testRepositoryIds(): array
    {
        return $this->test->repositories()->pluck('repositories.id')->map(strval(...))->all();
    }

    /** @return Collection<int, array{value: string, label: string, hint: string}> */
    #[Computed]
    public function repositoryOptions(): Collection
    {
        return $this->project->repositories()->orderBy('name')->get()
            ->map(fn ($repo) => ['value' => (string) $repo->id, 'label' => $repo->name, 'hint' => (string) $repo->type]);
    }

    /**
     * IDs of this test's connectors, excluding the always-on InfluxDB.
     *
     * @return array<int, string>
     */
    private function testConnectorIds(): array
    {
        return $this->test->connectors()
            ->where('connectors.type', '!=', ConnectorType::InfluxDb->value)
            ->pluck('connectors.id')
            ->map(strval(...))
            ->all();
    }

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
    public function testConnectorsList()
    {
        return $this->test->connectors()->orderBy('name')->get();
    }

    #[Computed()]
    public function connectorSummary(): string
    {
        $connectors = $this->testConnectorsList;
        $count = $connectors->count();

        if ($count === 0) {
            return 'No connectors attached';
        }

        $types = $connectors->pluck('type')->map(fn ($t) => $t->label())->unique()->implode(', ');

        return $count.' connector'.($count > 1 ? 's' : '').' ('.$types.')';
    }

    public function formatLastRun(Carbon|string|null $lastRunAt): string
    {
        if ($lastRunAt === null) {
            return 'Never run';
        }

        return Carbon::parse($lastRunAt)->diffForHumans();
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
  const res = http.get(__ENV.TARGET_URL);
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

<div class="flex flex-col gap-8" @agent-done.window="$wire.$refresh()" @agent-approval-requested.window="$wire.$refresh()">
    <x-page-header :title="$this->test->name" description="Scripts, connectors and run history for this test.">
        <x-slot:breadcrumbs>
            <nav class="flex items-center gap-1.5" aria-label="Breadcrumb">
                <a href="{{ route('projects.overview', $project) }}" wire:navigate class="truncate hover:text-zinc-900">{{ $project->name }}</a>
                <flux:icon.chevron-right variant="micro" class="text-zinc-300" />
                <a href="{{ route('projects.tests', $project) }}" wire:navigate class="hover:text-zinc-900">Tests</a>
            </nav>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:modal.trigger name="update-test">
                <flux:button wire:click="startUpdate" icon="pencil-square">Edit test</flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="generate-with-ai">
                <flux:button icon="sparkles">Straden agent</flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="create-test-script">
                <flux:button variant="primary" icon="plus">New script</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    {{-- Summary --}}
    @php
        $latestRun = $this->latestTestRun;
    @endphp
    <div class="ui-panel grid grid-cols-1 gap-px overflow-hidden bg-zinc-200 md:grid-cols-3 [&>*]:bg-white">
        <div class="min-w-0 px-4 py-3.5">
            <p class="truncate text-sm text-zinc-500">Target</p>
            <p class="mt-1.5 truncate font-mono text-sm font-medium text-zinc-900" title="{{ $this->test->target_url }}">{{ $this->test->target_url }}</p>
            <p class="mt-1.5 truncate text-xs text-zinc-500">Every script sends its requests here</p>
        </div>

        <x-stat label="Connectors" :value="$this->testConnectorsList->count()" :hint="$this->connectorSummary" />

        <div class="min-w-0 px-4 py-3.5">
            <p class="truncate text-sm text-zinc-500">Last run</p>
            <div class="mt-1.5">
                @if ($latestRun)
                    <x-status-badge :status="$latestRun->status" />
                @else
                    <span class="text-sm text-zinc-500">No runs yet</span>
                @endif
            </div>
            <p class="mt-1.5 truncate text-xs text-zinc-500">
                {{ $latestRun ? \Carbon\Carbon::parse($latestRun->created_at)->diffForHumans() : 'Run a script to see results here' }}
            </p>
        </div>
    </div>

    {{-- Scripts --}}
    <section class="ui-panel overflow-hidden">
        <header class="ui-panel-header">
            <h2 class="ui-panel-title">Scripts</h2>
            @unless ($this->scripts->isEmpty())
                <span class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count script|:count scripts', $this->scripts->count()) }}</span>
            @endunless
        </header>

        @if ($this->scripts->isEmpty())
            <x-empty-state icon="code-bracket" title="No scripts yet" description="Write a k6 script yourself, or ask the Straden agent to generate one from your target.">
                <flux:modal.trigger name="create-test-script">
                    <flux:button size="sm" icon="plus">New script</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="generate-with-ai">
                    <flux:button size="sm" variant="ghost" icon="sparkles">Ask the agent</flux:button>
                </flux:modal.trigger>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Script</th>
                            <th>Last status</th>
                            <th class="text-right!">Last run</th>
                            <th class="w-12"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->scripts as $script)
                            @php
                                $latestScriptRun = $script->runs->first();
                                $scriptUrl = route('projects.view-test-script', ['project' => $this->project, 'test' => $this->test, 'script' => $script]);
                            @endphp
                            <tr wire:key="script-{{ $script->id }}" class="ui-table-row-link" x-on:click="Livewire.navigate(@js($scriptUrl))">
                                <td class="max-w-md">
                                    <a wire:navigate href="{{ $scriptUrl }}" x-on:click.stop class="block truncate font-medium text-zinc-900">{{ $script->name }}</a>
                                    @if ($script->description)
                                        <p class="truncate text-xs text-zinc-500">{{ $script->description }}</p>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap">
                                    @if ($latestScriptRun)
                                        <x-status-badge :status="$latestScriptRun->status" />
                                    @else
                                        <span class="text-zinc-500">No runs</span>
                                    @endif
                                </td>

                                <td class="text-right whitespace-nowrap text-zinc-500">
                                    @if ($latestScriptRun)
                                        {{ $this->formatLastRun($script->last_run_at) }}
                                    @else
                                        Never run
                                    @endif
                                </td>

                                <td class="text-right">
                                    <flux:icon.chevron-right variant="micro" class="inline text-zinc-300" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Modals --}}
    <flux:modal class="space-y-6 md:w-[28rem]" name="create-test-script">
        <div>
            <flux:heading size="lg">New script</flux:heading>
            <flux:text class="mt-1">Starts with a minimal k6 script you can edit.</flux:text>
        </div>

        <form wire:submit="submit" class="flex flex-col gap-6">
            <flux:input wire:model="name" label="Name" placeholder="Checkout flow" />
            <flux:textarea wire:model="description" label="Description" placeholder="What this script exercises" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Create script</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal class="md:w-1/3 p-0!" name="generate-with-ai" flyout :closable="false">
        <livewire:agent-chat :project="$project" :test="$test" />
    </flux:modal>

    <flux:modal class="space-y-6 scrollbar-none md:w-[36rem]" name="update-test" flyout>
        <div>
            <flux:heading size="lg">Edit test</flux:heading>
            <flux:text class="mt-1">Change the details, connectors and repositories for this test.</flux:text>
        </div>

        <form wire:submit="updateTest" class="flex flex-col gap-6">
            <flux:input wire:model="testName" label="Name" placeholder="Checkout flow" />
            <flux:input wire:model="targetUrl" label="Target URL" placeholder="https://api.example.com" />
            <flux:textarea wire:model="testDescription" label="Description" placeholder="What this test covers" rows="3" />
            <div>
                <livewire:connector-picker wire:model="testConnectors" :project="$project" wire:key="update-test-connectors-{{ $this->test->id }}" />
            </div>
            <div>
                <x-multi-combobox
                    wire:model="testRepositories"
                    label="Repositories"
                    :options="$this->repositoryOptions"
                    placeholder="All project repositories"
                    search-placeholder="Search repositories..."
                    empty-text="No repositories in this project yet."
                    description="The AI agents only read code from these repositories. Leave empty to use every repository in the project."
                />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
