<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    #[Computed]
    public function totalTests(): int
    {
        return $this->project->tests()->count();
    }

    #[Computed]
    public function totalScripts(): int
    {
        return Script::whereHas('test', fn ($q) => $q->where('project_id', $this->project->id))->count();
    }

    #[Computed]
    public function totalRuns(): int
    {
        return Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))->count();
    }

    #[Computed]
    public function runsThisWeek(): int
    {
        return Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
    }

    #[Computed]
    public function lastRun(): ?Run
    {
        return Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->latest()
            ->first();
    }

    #[Computed]
    public function avgErrorRate(): ?float
    {
        return Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->whereNotNull('error_rate')
            ->avg('error_rate');
    }

    #[Computed]
    public function statusDistribution(): array
    {
        $counts = Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statuses = ['passed', 'failed', 'running', 'queued', 'error'];
        $total = array_sum($counts);

        $data = [];
        foreach ($statuses as $s) {
            $count = $counts[$s] ?? 0;
            $data[] = [
                'status' => $s,
                'label' => ucfirst($s),
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        }

        return [
            'total' => $total,
            'labels' => array_map('ucfirst', $statuses),
            'counts' => array_map(fn ($s) => $counts[$s] ?? 0, $statuses),
            'items' => $data,
        ];
    }

    #[Computed]
    public function performanceTrend(): array
    {
        $runs = Run::whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->whereNotNull('completed_at')
            ->whereNotNull('req_duration_p95_ms')
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get(['completed_at', 'req_duration_p95_ms', 'req_duration_p99_ms'])
            ->reverse();

        return [
            'labels' => $runs->map(fn ($r) => $r->completed_at->format('M j'))->values()->toArray(),
            'p95' => $runs->pluck('req_duration_p95_ms')->values()->toArray(),
            'p99' => $runs->pluck('req_duration_p99_ms')->values()->toArray(),
        ];
    }

    #[Computed]
    public function recentRuns(): array
    {
        return Run::with('script.test')
            ->whereHas('script.test', fn ($q) => $q->where('project_id', $this->project->id))
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function statusHex(string $status): string
    {
        return match ($status) {
            'passed' => '#0ca30c',
            'failed' => '#d03b3b',
            'running' => '#3d5bdb',
            'queued' => '#a1a1aa',
            'error' => '#fab219',
            default => '#a1a1aa',
        };
    }
};
?>

<div class="flex flex-col gap-8">
    <x-page-header title="Overview" description="High-level summary of tests, runs, and load testing metrics for {{ $project->name }}." />

    {{-- Headline numbers --}}
    <div class="ui-panel grid grid-cols-2 gap-px overflow-hidden bg-zinc-200 lg:grid-cols-4 [&>*]:bg-white">
        <x-stat label="Total tests" :value="number_format($this->totalTests)" :hint="trans_choice('Across :count linked script|Across :count linked scripts', $this->totalScripts)" />

        <x-stat label="Total runs" :value="number_format($this->totalRuns)">
            <x-slot:footer>
                Avg error rate
                <span @class(['font-medium tabular-nums', 'text-red-700' => ($this->avgErrorRate ?? 0) > 1, 'text-zinc-900' => ($this->avgErrorRate ?? 0) <= 1])>{{ number_format($this->avgErrorRate ?? 0, 1) }}%</span>
            </x-slot:footer>
        </x-stat>

        <x-stat label="Runs this week" :value="number_format($this->runsThisWeek)" hint="Since Monday" />

        <div class="min-w-0 px-4 py-3.5">
            <p class="truncate text-sm text-zinc-500">Last execution</p>
            @if ($this->lastRun)
                <div class="mt-1 flex h-8 items-center">
                    <x-status-badge :status="$this->lastRun->status" />
                </div>
                <p class="mt-1 truncate text-xs text-zinc-500">{{ $this->lastRun->created_at?->diffForHumans() }}</p>
            @else
                <p class="ui-metric mt-1">None</p>
                <p class="mt-1 truncate text-xs text-zinc-500">No executions recorded</p>
            @endif
        </div>
    </div>

    @if ($this->totalRuns === 0)
        <section class="ui-panel">
            <x-empty-state icon="chart-bar" title="No run data available for this project yet." description="Create a test and run it to see latency trends and results here.">
                <flux:button :href="route('projects.tests', $project)" variant="primary" size="sm">Create your first test</flux:button>
            </x-empty-state>
        </section>
    @else
        {{-- Charts --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <section
                wire:ignore
                x-data="performanceTrend(@js($this->performanceTrend))"
                class="ui-panel lg:col-span-7"
            >
                <header class="ui-panel-header">
                    <div class="min-w-0">
                        <h2 class="ui-panel-title">Response time trend</h2>
                        <p class="text-xs text-zinc-500">p95 and p99 latency over the last 20 completed runs</p>
                    </div>

                    {{-- p95 / p99 series toggles --}}
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            @click="toggle('p95')"
                            :aria-pressed="visible.p95.toString()"
                            class="ui-pressable inline-flex h-7 items-center gap-1.5 rounded-md px-2 text-xs hover:bg-zinc-100"
                            :class="visible.p95 ? 'text-zinc-700' : 'text-zinc-400'"
                        >
                            <span class="size-2 rounded-[2px]" :style="`background-color: ${colors.p95}`" :class="!visible.p95 && 'opacity-30'"></span>
                            p95
                        </button>
                        <button
                            type="button"
                            @click="toggle('p99')"
                            :aria-pressed="visible.p99.toString()"
                            class="ui-pressable inline-flex h-7 items-center gap-1.5 rounded-md px-2 text-xs hover:bg-zinc-100"
                            :class="visible.p99 ? 'text-zinc-700' : 'text-zinc-400'"
                        >
                            <span class="size-2 rounded-[2px]" :style="`background-color: ${colors.p99}`" :class="!visible.p99 && 'opacity-30'"></span>
                            p99
                        </button>
                    </div>
                </header>

                <div class="p-4">
                    <div class="relative h-72 w-full">
                        <canvas x-ref="canvas" role="img" aria-label="p95 and p99 response time over recent runs"></canvas>
                        <x-chart-tooltip />
                    </div>
                </div>
            </section>

            <section class="ui-panel flex flex-col lg:col-span-5">
                <header class="ui-panel-header">
                    <div class="min-w-0">
                        <h2 class="ui-panel-title">Status distribution</h2>
                        <p class="text-xs text-zinc-500">How every run in this project ended</p>
                    </div>
                </header>

                <div class="flex flex-1 flex-col gap-4 p-4">
                    <div class="flex justify-center py-2">
                        <div
                            wire:ignore
                            x-data="statusDoughnut(@js($this->statusDistribution))"
                            class="relative size-44"
                        >
                            <canvas x-ref="canvas" role="img" aria-label="Runs by status"></canvas>

                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="ui-metric">{{ number_format($this->statusDistribution['total']) }}</span>
                                <span class="text-xs text-zinc-500">Total runs</span>
                            </div>

                            <x-chart-tooltip />
                        </div>
                    </div>

                    <ul class="ui-list">
                        @foreach ($this->statusDistribution['items'] as $item)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span class="flex items-center gap-2">
                                    <span class="size-2 rounded-[2px]" style="background-color: {{ $this->statusHex($item['status']) }}"></span>
                                    <span class="text-zinc-700">{{ $item['label'] }}</span>
                                </span>
                                <span class="flex items-center gap-3 tabular-nums">
                                    <span class="font-medium text-zinc-900">{{ $item['count'] }}</span>
                                    <span class="w-9 text-right text-zinc-500">{{ $item['percentage'] }}%</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>

        {{-- Recent executions --}}
        <section class="ui-panel overflow-hidden">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Recent executions</h2>
                <flux:button :href="route('projects.runs', $project)" wire:navigate variant="ghost" size="sm">View all</flux:button>
            </header>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Test</th>
                            <th>Triggered by</th>
                            <th class="text-right!">Duration</th>
                            <th class="text-right!">Error rate</th>
                            <th class="text-right!">Executed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->recentRuns as $run)
                            @php($runUrl = route('projects.runs.view', ['project' => $project, 'run' => $run['slug']]))
                            <tr
                                wire:key="recent-run-{{ $run['id'] }}"
                                x-on:click="Livewire.navigate('{{ $runUrl }}')"
                                class="ui-table-row-link"
                            >
                                <td class="whitespace-nowrap">
                                    <x-status-badge :status="$run['status']" />
                                </td>

                                <td class="max-w-72">
                                    <a href="{{ $runUrl }}" wire:navigate x-on:click.stop class="block truncate font-medium text-zinc-900 decoration-zinc-300 underline-offset-[3px] hover:underline">
                                        {{ $run['script']['test']['name'] ?? 'Unknown test' }}
                                    </a>
                                    <span class="block truncate text-xs text-zinc-500">
                                        {{ $run['script']['name'] ?? 'Unknown script' }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap text-zinc-500">
                                    @if ($run['triggered_by_user_id'])
                                        <span class="inline-flex items-center gap-1.5">
                                            <flux:icon.user variant="micro" class="text-zinc-400" /> User
                                        </span>
                                    @else
                                        <span class="capitalize">{{ $run['triggered_by'] ?? 'System' }}</span>
                                    @endif
                                </td>

                                <td class="text-right whitespace-nowrap tabular-nums">
                                    {{ isset($run['duration_seconds']) ? $run['duration_seconds'].'s' : '—' }}
                                </td>

                                <td class="text-right whitespace-nowrap tabular-nums">
                                    @if (isset($run['error_rate']))
                                        <span @class(['font-medium text-red-700' => $run['error_rate'] > 1])>
                                            {{ number_format($run['error_rate'], 1) }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>

                                <td class="text-right whitespace-nowrap text-zinc-500">
                                    {{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
