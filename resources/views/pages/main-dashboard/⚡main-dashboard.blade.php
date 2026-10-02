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
        $palette = ['#3d5bdb', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];

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
            $row['color'] = $palette[$i] ?? '#a1a1aa';

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

<div class="flex flex-col gap-8">
    <x-page-header title="Dashboard" description="How your load tests are doing across every project.">
        <x-slot:actions>
            <flux:button :href="route('projects')" wire:navigate variant="primary" icon="plus">New project</flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Headline numbers --}}
    <div class="ui-panel grid grid-cols-2 gap-px overflow-hidden bg-zinc-200 lg:grid-cols-4 [&>*]:bg-white">
        <x-stat label="Projects" :value="number_format($this->totalProjects)" hint="In this workspace" />
        <x-stat label="Tests" :value="number_format($this->totalTests)" hint="Configured" />
        <x-stat label="Runs" :value="number_format($this->totalRuns)">
            <x-slot:footer>
                @if ($this->runsTrendPercent !== null)
                    <span @class(['font-medium tabular-nums', 'text-emerald-700' => $this->runsTrendPercent >= 0, 'text-red-700' => $this->runsTrendPercent < 0])>
                        {{ $this->runsTrendPercent >= 0 ? '+' : '−' }}{{ abs($this->runsTrendPercent) }}%
                    </span>
                    vs last week
                @else
                    <span class="tabular-nums">{{ $this->runsThisWeek }}</span> this week
                @endif
            </x-slot:footer>
        </x-stat>
        <x-stat label="Failed runs" :value="number_format($this->failedRunsThisWeek)" hint="Missed thresholds, last 7 days" />
    </div>

    {{-- Activity + breakdown --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="ui-panel lg:col-span-2">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Run activity</h2>
                <span class="text-xs text-zinc-500">Last 15 days</span>
            </header>

            <div class="p-4">
                <div
                    wire:key="activity-chart-{{ md5(json_encode($this->activity)) }}"
                    wire:ignore
                    x-data="activityChart(@js($this->activity))"
                    x-init="init()"
                    x-on:activity-updated.window="updateData($event.detail.activity)"
                    class="relative h-52"
                >
                    <canvas x-ref="canvas" role="img" aria-label="Runs per day over the last 15 days"></canvas>
                    <x-chart-tooltip />
                </div>
            </div>
        </section>

        <section class="ui-panel">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Runs by project</h2>
                @unless (empty($this->projectBreakdown))
                    <span class="text-xs text-zinc-500 tabular-nums">{{ number_format(collect($this->projectBreakdown)->sum('count')) }} total</span>
                @endunless
            </header>

            @if (empty($this->projectBreakdown))
                <x-empty-state compact icon="chart-bar" title="No runs yet" description="Runs appear here once a test has been run." />
            @else
                <ul class="flex flex-col gap-3.5 p-4">
                    @foreach ($this->projectBreakdown as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="size-2 shrink-0 rounded-[2px]" style="background-color: {{ $row['color'] }}"></span>
                                    <span class="truncate text-zinc-700">{{ $row['name'] }}</span>
                                </span>
                                <span class="shrink-0 text-zinc-500 tabular-nums">
                                    <span class="font-medium text-zinc-900">{{ $row['count'] }}</span>
                                    <span class="ms-1.5 inline-block w-9 text-right">{{ $row['percent'] }}%</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-zinc-100">
                                <div class="h-full rounded-full" style="width: {{ max($row['percent'], 2) }}%; background-color: {{ $row['color'] }}"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Recent runs + recent projects --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="ui-panel overflow-hidden lg:col-span-2">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Recent runs</h2>
                <flux:button :href="route('projects')" wire:navigate variant="ghost" size="sm">View projects</flux:button>
            </header>

            @if ($this->recentRuns->isEmpty())
                <x-empty-state icon="play" title="No runs yet" description="Open a project and run a test to see results here." />
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Test</th>
                                <th>Project</th>
                                <th class="text-right!">p95</th>
                                <th class="text-right!">Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->recentRuns as $run)
                                @php
                                    $runProject = $run->script?->test?->project;
                                    $runUrl = $runProject ? route('projects.runs.view', ['project' => $runProject, 'run' => $run]) : null;
                                @endphp
                                <tr wire:key="recent-run-{{ $run->id }}" @if ($runUrl) class="ui-table-row-link" x-on:click="Livewire.navigate(@js($runUrl))" @endif>
                                    <td class="whitespace-nowrap"><x-status-badge :status="$run->status" /></td>
                                    <td class="max-w-56 truncate font-medium text-zinc-900">{{ $run->script?->test?->name ?? '—' }}</td>
                                    <td class="max-w-48 truncate text-zinc-500">{{ $runProject?->name ?? '—' }}</td>
                                    <td class="text-right whitespace-nowrap tabular-nums">{{ $run->req_duration_p95_ms ? round($run->req_duration_p95_ms).' ms' : '—' }}</td>
                                    <td class="text-right whitespace-nowrap text-zinc-500">{{ $run->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="ui-panel overflow-hidden">
            <header class="ui-panel-header">
                <h2 class="ui-panel-title">Recent projects</h2>
                <flux:button :href="route('projects')" wire:navigate variant="ghost" size="sm">View all</flux:button>
            </header>

            @if ($this->recentProjects->isEmpty())
                <x-empty-state icon="folder-open" title="No projects yet" description="Projects group your tests, runs and connectors.">
                    <flux:button :href="route('projects')" variant="primary" size="sm" wire:navigate>Create project</flux:button>
                </x-empty-state>
            @else
                <div class="ui-list">
                    @foreach ($this->recentProjects as $project)
                        <a wire:navigate href="{{ route('projects.overview', $project) }}" wire:key="{{ $project->id }}" class="ui-list-row group">
                            <span class="ui-monogram size-8">{{ strtoupper(substr($project->name, 0, 2)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900">{{ $project->name }}</span>
                                <span class="block text-xs text-zinc-500">{{ trans_choice(':count test|:count tests', $project->tests_count) }}, {{ trans_choice(':count script|:count scripts', $project->scripts_count) }}</span>
                            </span>
                            <flux:icon.chevron-right variant="micro" class="text-zinc-300 transition-transform duration-150 ease-snappy group-hover:translate-x-0.5 group-hover:text-zinc-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
