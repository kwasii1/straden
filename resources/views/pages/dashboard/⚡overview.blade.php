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

    public function statusColor(string $status): string
    {
        return match ($status) {
            'passed' => 'emerald',
            'failed' => 'rose',
            'running' => 'sky',
            'error' => 'amber',
            default => 'zinc',
        };
    }

    public function statusHex(string $status): string
    {
        return match ($status) {
            'passed' => '#10b981',
            'failed' => '#f43f5e',
            'running' => '#0284c7',
            'queued' => '#64748b',
            'error' => '#f59e0b',
            default => '#94a3b8',
        };
    }
};
?>

<div class="flex flex-col gap-y-8 p-1 sm:p-2">
    {{-- Dashboard Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Overview</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                High-level summary of tests, runs, and load testing metrics for <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $project->name }}</span>
            </flux:text>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Tests --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl pb-3.5">
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <flux:icon.beaker class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Total Tests</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->totalTests }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5">
                <p class="text-xs text-[#919191] dark:text-zinc-400">across {{ $this->totalScripts }} linked scripts</p>
            </div>
        </div>

        {{-- Card 2: Total Runs --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl pb-3.5">
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-white">
                        <flux:icon.play class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Total Runs</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($this->totalRuns) }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5">
                <p class="text-xs text-[#919191] dark:text-zinc-400">
                    avg error rate: <span class="font-medium {{ ($this->avgErrorRate ?? 0) > 1 ? 'text-rose-500' : 'text-emerald-600' }}">{{ number_format($this->avgErrorRate ?? 0, 1) }}%</span>
                </p>
            </div>
        </div>

        {{-- Card 3: Runs This Week --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl pb-3.5">
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white">
                        <flux:icon.calendar-days class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Runs This Week</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->runsThisWeek }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5">
                <p class="text-xs text-[#919191] dark:text-zinc-400">executions active during week</p>
            </div>
        </div>

        {{-- Card 4: Last Execution --}}
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
            <div class="flex flex-col bg-white dark:bg-zinc-900 rounded-xl pb-3.5">
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500 text-white">
                        <flux:icon.clock class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Last Execution</flux:text>
                </div>
                <div class="px-4">
                    @if ($this->lastRun)
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white capitalize flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-{{ $this->statusColor($this->lastRun->status) }}-500"></span>
                            {{ $this->lastRun->status }}
                        </p>
                    @else
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">None</p>
                    @endif
                </div>
            </div>
            <div class="px-4 py-2.5">
                <p class="text-xs text-[#919191] dark:text-zinc-400">
                    {{ $this->lastRun ? \Carbon\Carbon::parse($this->lastRun->created_at)->diffForHumans() : 'No executions recorded' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Empty State --}}
    @if ($this->totalRuns === 0)
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
            <div class="flex flex-col items-center justify-center bg-white dark:bg-zinc-900 rounded-xl py-16 gap-y-3">
                <div class="flex size-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-zinc-800 text-blue-500">
                    <flux:icon.chart-bar class="size-6" />
                </div>
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">No run data available for this project yet.</p>
                <flux:button href="{{ route('projects.tests', $project) }}" variant="primary" size="sm" class="mt-1">
                    Create your first test
                </flux:button>
            </div>
        </div>
    @else
        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Performance Trend Card --}}
            <div class="lg:col-span-7 rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2 flex flex-col gap-y-5">
                <div class="flex items-center justify-between px-3 py-2.5">
                    <div>
                        <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Response Time Trend</flux:text>
                        <p class="text-xs text-[#919191] dark:text-zinc-400">p95 & p99 percentile latency over recent executions</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-xl p-4">
                    <div
                        wire:ignore
                        x-data="performanceTrend(@js($this->performanceTrend))"
                    >
                        {{-- p95 / p99 toggle filters --}}
                        <div class="mb-3 flex items-center gap-2">
                            <button
                                type="button"
                                @click="toggle('p95')"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="visible.p95
                                    ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-400'
                                    : 'border-[#EDEDED] bg-white text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900'"
                            >
                                <span class="size-1.5 rounded-full bg-sky-500" :class="!visible.p95 && 'opacity-30'"></span>
                                p95
                            </button>
                            <button
                                type="button"
                                @click="toggle('p99')"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="visible.p99
                                    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-400'
                                    : 'border-[#EDEDED] bg-white text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900'"
                            >
                                <span class="size-1.5 rounded-full bg-rose-500" :class="!visible.p99 && 'opacity-30'"></span>
                                p99
                            </button>
                        </div>

                        <div class="relative h-72 w-full">
                            <canvas x-ref="canvas"></canvas>

                            <div
                                x-show="tooltip.show"
                                x-transition.opacity.duration.100ms
                                x-cloak
                                class="pointer-events-none absolute z-50 w-40 -translate-x-1/2 -translate-y-full overflow-hidden rounded-lg border border-zinc-100 bg-white shadow-lg dark:border-zinc-700"
                                :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`"
                            >
                                <div class="bg-[#F5F5F5] px-3 py-1.5 dark:bg-zinc-800">
                                    <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-text="tooltip.label"></span>
                                </div>
                                <template x-for="row in tooltip.rows" :key="row.label">
                                    <div class="flex items-center justify-between px-3 py-1.5">
                                        <span class="flex items-center gap-1.5 text-xs text-zinc-500">
                                            <span class="size-1.5 rounded-full" :style="`background-color: ${row.color}`"></span>
                                            <span x-text="row.label"></span>
                                        </span>
                                        <span class="text-xs font-semibold text-zinc-900 dark:text-white" x-text="row.value"></span>
                                    </div>
                                </template>
                                <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border-b border-r border-zinc-100 bg-white dark:border-zinc-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status Distribution Card --}}
            <div class="lg:col-span-5 rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2 flex flex-col justify-between">
                <div class="flex items-center justify-between px-3 py-2.5">
                    <div>
                        <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Status Distribution</flux:text>
                        <p class="text-xs text-[#919191] dark:text-zinc-400">Breakdown of execution results</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-xl p-4 flex flex-col justify-between h-full">
                    <div class="relative my-2 flex items-center justify-center">
                        <div
                            wire:ignore
                            x-data="statusDoughnut(@js($this->statusDistribution))"
                            class="relative h-44 w-44"
                        >
                            <canvas x-ref="canvas"></canvas>

                            <div
                                x-show="tooltip.show"
                                x-transition.opacity.duration.100ms
                                x-cloak
                                class="pointer-events-none absolute z-50 w-36 -translate-x-1/2 -translate-y-full overflow-hidden rounded-lg border border-zinc-100 bg-white shadow-lg dark:border-zinc-700"
                                :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`"
                            >
                                <div class="flex items-center gap-1.5 bg-[#F5F5F5] px-3 py-1.5 dark:bg-zinc-800">
                                    <span class="size-1.5 rounded-full" :style="`background-color: ${tooltip.color}`"></span>
                                    <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-text="tooltip.label"></span>
                                </div>
                                <div class="flex items-center justify-between px-3 py-2 text-xs">
                                    <span class="text-zinc-500">Runs</span>
                                    <span class="font-semibold text-zinc-900 dark:text-white" x-text="tooltip.value"></span>
                                </div>
                                <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border-b border-r border-zinc-100 bg-white dark:border-zinc-700"></div>
                            </div>

                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                                <span class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                                    {{ number_format($this->statusDistribution['total']) }}
                                </span>
                                <span class="text-[10px] uppercase tracking-wider text-zinc-400 font-medium">Total Runs</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->statusDistribution['items'] as $item)
                            <div class="flex items-center justify-between py-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full" style="background-color: {{ $this->statusHex($item['status']) }}"></span>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $item['count'] }}</span>
                                    <span class="w-8 text-right text-zinc-400 tabular-nums">{{ $item['percentage'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Executions Section --}}
        <div class="rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
            <div class="flex items-center justify-between px-3 py-2.5">
                <div>
                    <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Recent Executions</flux:text>
                    <p class="text-xs text-[#919191] dark:text-zinc-400">Latest test execution records and statuses</p>
                </div>
                <flux:button variant="ghost" size="sm" icon-trailing="arrow-right" class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                    View All
                </flux:button>
            </div>

            <div class="p-2 bg-white dark:bg-zinc-900 rounded-xl overflow-x-auto">
                <table class="w-full border-separate border-spacing-0 bg-white dark:bg-zinc-900">
                    <thead>
                        <tr class="text-left">
                            <th class="rounded-l-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Status</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Test / Script</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Triggered By</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Duration</th>
                            <th class="bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400">Error Rate</th>
                            <th class="rounded-r-xl bg-[#F5F5F5] dark:bg-zinc-800/60 px-5 py-3 text-xs font-medium text-[#959595] dark:text-zinc-400 text-right">Executed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        @foreach ($this->recentRuns as $run)
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ match($run['status']) {
                                            'passed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400',
                                            'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400',
                                            'running' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-400',
                                            'error' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
                                            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                                        } }}">
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ ucfirst($run['status']) }}
                                    </span>
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-zinc-900 dark:text-white">
                                            {{ $run['script']['test']['name'] ?? 'Unknown Test' }}
                                        </span>
                                        <span class="text-[11px] text-[#919191]">
                                            {{ $run['script']['name'] ?? 'Unknown Script' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap text-zinc-500">
                                    @if ($run['triggered_by_user_id'])
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon.user class="size-3 text-zinc-400" /> User
                                        </span>
                                    @else
                                        <span class="capitalize">{{ $run['triggered_by'] ?? 'System' }}</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ isset($run['duration_seconds']) ? $run['duration_seconds'].'s' : '—' }}
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap tabular-nums">
                                    @if (isset($run['error_rate']))
                                        <span class="font-semibold {{ $run['error_rate'] > 1 ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format($run['error_rate'], 1) }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap text-right text-[#919191] tabular-nums">
                                    {{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
