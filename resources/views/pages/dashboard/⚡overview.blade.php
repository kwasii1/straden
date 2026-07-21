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

        return [
            'labels' => array_map('ucfirst', $statuses),
            'counts' => array_map(fn ($s) => $counts[$s] ?? 0, $statuses),
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
            'passed' => 'green',
            'failed' => 'red',
            'running' => 'blue',
            'error' => 'orange',
            default => 'zinc',
        };
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Overview</flux:heading>
        <flux:text>High-level summary of tests, runs, and load testing metrics for this project</flux:text>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex shrink-0 items-center justify-center size-10 rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.beaker class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="flex flex-col min-w-0">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Total Tests</flux:text>
                <flux:text class="text-xl font-bold">{{ $this->totalTests }}</flux:text>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex shrink-0 items-center justify-center size-10 rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.play class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="flex flex-col min-w-0">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Total Runs</flux:text>
                <flux:text class="text-xl font-bold">{{ $this->totalRuns }}</flux:text>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex shrink-0 items-center justify-center size-10 rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.calendar-days class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="flex flex-col min-w-0">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Runs This Week</flux:text>
                <flux:text class="text-xl font-bold">{{ $this->runsThisWeek }}</flux:text>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex shrink-0 items-center justify-center size-10 rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon.clock class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="flex flex-col min-w-0">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Last Run</flux:text>
                @if ($this->lastRun)
                    <flux:badge :color="$this->statusColor($this->lastRun->status)" size="sm">{{ ucfirst($this->lastRun->status) }}</flux:badge>
                @else
                    <flux:text class="text-xl font-bold">None</flux:text>
                @endif
            </div>
        </div>
    </div>

    {{-- Empty State --}}
    @if ($this->totalRuns === 0)
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 py-16 gap-y-3">
            <flux:icon.chart-bar class="size-12 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-500 dark:text-zinc-400">No run data yet for this project.</flux:text>
            <flux:button href="{{ route('projects.tests', $project) }}" variant="primary" size="sm">Create your first test</flux:button>
        </div>
    @else
        {{-- Charts Row --}}
        <div
            class="grid grid-cols-1 lg:grid-cols-2 gap-6"
            x-data="{
                statusChart: null,
                perfChart: null,

                init() {
                    this.initCharts();
                },

                initCharts() {
                    this.initStatusChart();
                    this.initPerfChart();
                },

                initStatusChart() {
                    if (this.statusChart) this.statusChart.destroy();

                    const canvas = this.$refs.statusChart;
                    if (!canvas) return;

                    const data = @json($this->statusDistribution);
                    if (data.counts.every(c => c === 0)) return;

                    this.statusChart = new Chart(canvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.counts,
                                backgroundColor: ['#22c55e', '#ef4444', '#3b82f6', '#71717a', '#f97316'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } },
                            },
                            cutout: '65%',
                        },
                    });
                },

                initPerfChart() {
                    if (this.perfChart) this.perfChart.destroy();

                    const canvas = this.$refs.perfChart;
                    if (!canvas) return;

                    const data = @json($this->performanceTrend);
                    if (data.labels.length === 0) return;

                    this.perfChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'p95 (ms)',
                                    data: data.p95,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    pointHoverRadius: 5,
                                },
                                {
                                    label: 'p99 (ms)',
                                    data: data.p99,
                                    borderColor: '#ef4444',
                                    backgroundColor: 'rgba(239, 68, 68, 0.05)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    pointHoverRadius: 5,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Milliseconds' },
                                    grid: { color: 'rgba(0,0,0,0.06)' },
                                },
                                x: {
                                    grid: { display: false },
                                },
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } },
                            },
                        },
                    });
                },
            }"
        >
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Run Status Distribution</flux:heading>
                <div class="relative h-64">
                    <canvas x-ref="statusChart"></canvas>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="lg" class="mb-4">Response Time Trend</flux:heading>
                <div class="relative h-64">
                    <canvas x-ref="perfChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Recent Runs --}}
        <div>
            <flux:heading size="lg" class="mb-4">Recent Runs</flux:heading>
            <div class="flex flex-col rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->recentRuns as $run)
                    <div class="flex items-center gap-4 px-6 py-4">
                        <flux:badge :color="$this->statusColor($run['status'])" size="sm">{{ ucfirst($run['status']) }}</flux:badge>

                        <div class="flex flex-col min-w-0 flex-1">
                            <flux:text class="text-sm font-medium truncate">
                                {{ $run['script']['test']['name'] ?? 'Unknown' }} / {{ $run['script']['name'] ?? 'Unknown' }}
                            </flux:text>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($run['triggered_by_user_id'])
                                    Triggered by user
                                @else
                                    Triggered {{ $run['triggered_by'] }}
                                @endif
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-4 shrink-0">
                            @if (isset($run['duration_seconds']))
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 tabular-nums">{{ $run['duration_seconds'] }}s</flux:text>
                            @endif

                            @if (isset($run['error_rate']))
                                <flux:text class="text-sm tabular-nums {{ $run['error_rate'] > 1 ? 'text-red-500' : 'text-green-600' }}">
                                    {{ number_format($run['error_rate'], 1) }}% err
                                </flux:text>
                            @endif

                            <flux:text class="text-xs text-zinc-400 dark:text-zinc-500 tabular-nums whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
