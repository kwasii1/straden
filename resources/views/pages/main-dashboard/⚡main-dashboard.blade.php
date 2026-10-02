<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Test;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component
{
    #[Computed]
    public function totalProjects(): int
    {
        return Project::count();
    }

    #[Computed]
    public function totalTests(): int
    {
        return Test::count();
    }

    #[Computed]
    public function totalRuns(): int
    {
        return Run::count();
    }

    #[Computed]
    public function runsThisWeek(): int
    {
        return Run::where('created_at', '>=', now()->subDays(7))->count();
    }

    #[Computed]
    public function runsLastWeek(): int
    {
        return Run::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
    }

    #[Computed]
    public function runsTrendPercent(): ?int
    {
        $prev = $this->runsLastWeek;

        if ($prev === 0) {
            return null;
        }

        return (int) round((($this->runsThisWeek - $prev) / $prev) * 100);
    }

    #[Computed]
    public function failedRunsThisWeek(): int
    {
        return Run::where('created_at', '>=', now()->subDays(7))
            ->where('thresholds_passed', false)
            ->count();
    }

    #[Computed]
    public function activeRuns(): int
    {
        return Run::whereIn('status', ['running', 'queued'])->count();
    }

    /**
     * Runs per day, last 15 days, for the activity chart.
     */
    #[Computed]
    public function activity(): array
    {
        $counts = Run::where('created_at', '>=', now()->subDays(14)->startOfDay())
            ->get()
            ->groupBy(fn (Run $run) => $run->created_at->format('Y-m-d'))
            ->map->count();

        return collect(range(14, 0))
            ->map(function (int $daysAgo) use ($counts) {
                $date = now()->subDays($daysAgo);
                $key = $date->format('Y-m-d');

                return [
                    'label' => $date->format('d'),
                    'full' => $date->translatedFormat('d M'),
                    'count' => $counts->get($key, 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Run distribution by project, for the breakdown list.
     */
    #[Computed]
    public function projectBreakdown(): array
    {
        $palette = ['bg-blue-500', 'bg-violet-500', 'bg-amber-500', 'bg-emerald-500', 'bg-rose-500', 'bg-zinc-400'];

        $rows = Project::withCount('tests')
            ->get()
            ->map(fn (Project $project) => [
                'name' => $project->name,
                'count' => Run::whereHas('script.test', fn ($q) => $q->where('project_id', $project->id))->count(),
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->sortByDesc('count')
            ->values();

        $total = max($rows->sum('count'), 1);

        return $rows->take(5)->map(function ($row, $i) use ($total, $palette) {
            $row['percent'] = (int) round(($row['count'] / $total) * 100);
            $row['color'] = $palette[$i] ?? 'bg-zinc-400';

            return $row;
        })->all();
    }

    #[Computed]
    public function recentRuns()
    {
        return Run::with('script.test.project')
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentProjects()
    {
        return Project::withCount(['tests', 'scripts'])->orderByDesc('last_accessed_at')->latest()->limit(3)->get();
    }
};
?>

<div class="flex flex-col gap-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:text class="mt-1">Welcome back, here's how your load tests are doing</flux:text>
        </div>
        <flux:button href="{{ route('projects') }}" wire:navigate variant="primary" icon="plus">New project</flux:button>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-white">
                        <flux:icon.rectangle-stack class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Projects</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->totalProjects }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191]">across your workspace</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <flux:icon.beaker class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Tests</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->totalTests }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191]">configured</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-purple-500 text-white">
                        <flux:icon.play class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Total runs</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->totalRuns }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                @if ($this->runsTrendPercent !== null)
                    <div class="mt-0.5 flex items-center gap-1 text-xs">
                        <flux:icon
                            name="{{ $this->runsTrendPercent >= 0 ? 'arrow-up' : 'arrow-down' }}"
                            class="size-3 {{ $this->runsTrendPercent >= 0 ? 'text-emerald-500' : 'text-red-500' }}"
                        />
                        <span class="{{ $this->runsTrendPercent >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }} font-medium">
                            {{ abs($this->runsTrendPercent) }}%
                        </span>
                        <span class="text-[#919191]">vs last week</span>
                    </div>
                @else
                    <p class="mt-0.5 text-xs text-[#919191]">{{ $this->runsThisWeek }} this week</p>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex flex-col bg-white rounded-xl">
                <div class="flex items-center gap-3 bg-white px-4 py-3.5 dark:bg-zinc-900">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-orange-500 text-white">
                        <flux:icon.flag class="size-4" />
                    </div>
                    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Failed runs</flux:text>
                </div>
                <div class="px-4">
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->failedRunsThisWeek }}</p>
                </div>
            </div>
            <div class="px-4 py-2.5 dark:bg-zinc-800">
                <p class="mt-0.5 text-xs text-[#919191]">below threshold, last 7 days</p>
            </div>
        </div>
    </div>

    {{-- Activity chart + breakdown --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Run activity (Chart.js bar chart) --}}
        <div class="overflow-visible rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] col-span-2">
            <div class="flex items-center justify-between px-5 py-3">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Run activity</flux:text>
                <flux:text class="text-xs text-[#919191]">Last 15 days</flux:text>
            </div>

            <div class="rounded-xl bg-white p-5 dark:bg-zinc-900 overflow-visible">
                <div
                    wire:key="activity-chart-{{ md5(json_encode($this->activity)) }}"
                    wire:ignore
                    x-data="activityChart(@js($this->activity))"
                    x-init="init()"
                    x-on:activity-updated.window="updateData($event.detail.activity)"
                    class="relative h-48"
                >
                    <canvas x-ref="canvas"></canvas>

                    <div
                        x-show="tooltip.show"
                        x-transition.opacity.duration.100ms
                        x-cloak
                        class="pointer-events-none absolute z-50 w-36 -translate-x-1/2 -translate-y-full overflow-hidden rounded-lg border border-zinc-100 bg-white shadow-lg dark:border-zinc-700"
                        :style="`left: ${tooltip.x}px; top: ${tooltip.y}px;`"
                    >
                        <div class="bg-[#F5F5F5] px-3 py-1.5 dark:bg-zinc-800">
                            <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-text="tooltip.label"></span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="flex items-center gap-1.5 text-xs text-zinc-500">
                                <span class="size-1.5 rounded-full bg-blue-500"></span>
                                Runs
                            </span>
                            <span class="text-xs font-semibold text-zinc-900 dark:text-white" x-text="tooltip.value"></span>
                        </div>
                        <div class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border-b border-r border-zinc-100 bg-white dark:border-zinc-700"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Runs by project — Chart.js doughnut --}}
        <div class="overflow-visible rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="px-5 py-3">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Runs by project</flux:text>
            </div>

            <div class="overflow-visible rounded-xl bg-white p-5 dark:bg-zinc-900">
                @if (empty($this->projectBreakdown))
                    <div class="flex h-32 flex-col items-center justify-center gap-2 text-center">
                        <flux:icon.chart-pie class="size-8 text-zinc-300 dark:text-zinc-700" />
                        <flux:text class="text-xs text-[#919191]">No runs recorded yet</flux:text>
                    </div>
                @else
                    <div
                        wire:key="project-doughnut-{{ md5(json_encode($this->projectBreakdown)) }}"
                        wire:ignore
                        x-data="projectDoughnut(@js($this->projectBreakdown))"
                        x-init="init()"
                        x-on:project-breakdown-updated.window="updateData($event.detail.rows)"
                        class="relative mx-auto h-44 w-44"
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

                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-semibold text-zinc-900 dark:text-white">
                                {{ number_format(collect($this->projectBreakdown)->sum('count')) }}
                            </span>
                            <span class="text-[11px] text-[#919191]">runs</span>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 rounded-xl bg-[#F5F5F5] p-2 dark:bg-zinc-800">
                        @foreach ($this->projectBreakdown as $row)
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="size-3 shrink-0 rounded {{ $row['color'] }}"></span>
                                    <span class="truncate text-xs text-zinc-700 dark:text-zinc-300">{{ $row['name'] }}</span>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs text-[#919191]">{{ $row['count'] }}</span>
                                    <span class="w-10 text-right text-xs font-medium text-zinc-900 dark:text-white">{{ $row['percent'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent runs + recent projects --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] lg:col-span-2">
            <div class="flex items-center justify-between px-5 py-3">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Recent runs</flux:text>
                <flux:button href="{{ route('projects') }}" wire:navigate variant="ghost" size="sm">View all</flux:button>
            </div>

            @if ($this->recentRuns->isEmpty())
                <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl bg-white py-16 dark:bg-zinc-900">
                    <flux:icon.play class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No runs yet.</flux:text>
                </div>
            @else
                <div class="p-2 bg-white rounded-xl">
                    <table class="w-full border-separate border-spacing-0 bg-white dark:bg-zinc-900">
                        <thead>
                            <tr class="text-left">
                                <th class="rounded-l-xl bg-[#F5F5F5] px-5 py-3 text-xs font-medium text-[#959595]">Status</th>
                                <th class="bg-[#F5F5F5] px-5 py-3 text-xs font-medium text-[#959595]">Test</th>
                                <th class="bg-[#F5F5F5] px-5 py-3 text-xs font-medium text-[#959595]">Project</th>
                                <th class="bg-[#F5F5F5] px-5 py-3 text-xs font-medium text-[#959595]">p95</th>
                                <th class="rounded-r-xl bg-[#F5F5F5] px-5 py-3 text-xs font-medium text-[#959595]">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->recentRuns as $run)
                                @php
                                    $statusMap = [
                                        'completed' => ['label' => 'Completed', 'icon' => 'check-circle', 'variant' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10'],
                                        'passed' => ['label' => 'Passed', 'icon' => 'check-circle', 'variant' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10'],
                                        'running' => ['label' => 'Running', 'icon' => 'arrow-path', 'variant' => 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-500/10'],
                                        'queued' => ['label' => 'Queued', 'icon' => 'clock', 'variant' => 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10'],
                                        'error' => ['label' => 'Error', 'icon' => 'exclamation-triangle', 'variant' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-500/10'],
                                        'failed' => ['label' => 'Failed', 'icon' => 'exclamation-triangle', 'variant' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-500/10'],
                                    ];
                                    $status = $statusMap[$run->status] ?? ['label' => ucfirst($run->status), 'icon' => 'minus-circle', 'variant' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-800'];
                                @endphp
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium {{ $status['variant'] }}">
                                            <flux:icon :name="$status['icon']" class="size-3.5" />
                                            {{ $status['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs font-medium text-[#494949]">
                                        {{ $run->script?->test?->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-[#494949]">
                                        {{ $run->script?->test?->project?->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-[#494949]">
                                        {{ $run->req_duration_p95_ms ? round($run->req_duration_p95_ms) . ' ms' : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-[#494949]">
                                        {{ $run->created_at?->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1]">
            <div class="flex items-center justify-between px-5 py-3">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Recent projects</flux:text>
                <flux:button href="{{ route('projects') }}" wire:navigate variant="ghost" size="sm">View all</flux:button>
            </div>

            @if ($this->recentProjects->isEmpty())
                <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl bg-white py-16 dark:bg-zinc-900">
                    <flux:icon.folder-open class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">No projects yet.</flux:text>
                    <flux:button href="{{ route('projects') }}" variant="primary" size="sm" wire:navigate>Create a project</flux:button>
                </div>
            @else
                <div class="divide-y divide-zinc-100 rounded-xl bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @foreach ($this->recentProjects as $project)
                        <a
                            wire:navigate
                            href="{{ route('projects.overview', $project) }}"
                            wire:key="{{ $project->id }}"
                            class="flex items-center gap-3 px-5 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-700 text-sm font-semibold text-white">
                                {{ strtoupper(substr($project->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $project->name }}</p>
                                <p class="text-xs text-[#919191]">{{ $project->tests_count }} tests · {{ $project->scripts_count }} scripts</p>
                            </div>
                            <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300 dark:text-zinc-600" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
