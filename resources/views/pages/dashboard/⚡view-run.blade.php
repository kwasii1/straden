<?php

use App\Models\Project;
use App\Models\Run;
use App\Services\EndpointGrouper;
use App\Services\InfluxDbService;
use App\Services\RunResultService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public Run $run;

    public ?string $selectedEndpoint = null;

    /**
     * Extra time-series charts the user has opted into viewing.
     *
     * @var array<int, string>
     */
    public array $extraCharts = [];

    public function mount(): void
    {
        $this->run->load('script.test');
    }

    public function isActive(): bool
    {
        return in_array($this->run->status, ['queued', 'running'], true);
    }

    public function hasLog(): bool
    {
        return RunResultService::hasLog($this->run->id);
    }

    public function progressPercent(): ?int
    {
        if ($this->run->status === 'queued') {
            return null;
        }

        if ($this->run->status !== 'running') {
            return null;
        }

        $duration = $this->run->run_config['duration_seconds'] ?? null;

        if (! is_int($duration) || $duration <= 0 || $this->run->started_at === null) {
            return null;
        }

        $elapsed = (int) abs(now()->diffInSeconds($this->run->started_at));

        return (int) min(99, floor(($elapsed / $duration) * 100));
    }

    public function toggleExtraChart(string $key): void
    {
        if ($this->hasExtraChart($key)) {
            $this->extraCharts = array_values(array_diff($this->extraCharts, [$key]));
        } else {
            $this->extraCharts[] = $key;
        }
    }

    public function hasExtraChart(string $key): bool
    {
        return in_array($key, $this->extraCharts, true);
    }

    /**
     * Raw endpoint names grouped into route patterns, so one dynamic route
     * (e.g. GET /todos/:id) renders as a single filter row instead of one
     * row per identifier.
     *
     * @return array<int, array{pattern: string, label: string, count: int, names: array<int, string>}>
     */
    #[Computed]
    public function endpoints(): array
    {
        try {
            $names = (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->endpointsForRun($this->run->id);
        } catch (\Throwable) {
            return [];
        }

        return EndpointGrouper::group($names);
    }

    /**
     * The raw endpoint names behind the current selection.
     *
     * @return array<int, string>
     */
    public function selectedEndpointNames(): array
    {
        if ($this->selectedEndpoint === null || $this->selectedEndpoint === '') {
            return [];
        }

        foreach ($this->endpoints as $group) {
            if ($group['pattern'] === $this->selectedEndpoint) {
                return $group['names'];
            }
        }

        // Stale selection (endpoints reloaded): treat the value as one raw name.
        return [$this->selectedEndpoint];
    }

    #[Computed]
    public function influxMetrics(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->metricsForRun(
                    $this->run->id,
                    $this->endpointFilter(),
                    InfluxDbService::runTimeRange($this->run->started_at, $this->run->completed_at)
                );
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function selectedEndpointSummary(): ?array
    {
        $names = $this->selectedEndpointNames();

        if ($names === []) {
            return null;
        }

        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->endpointSummary($this->run->id, $names);
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function responseCodeSummary(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->responseCodeBreakdown($this->run->id, $this->endpointFilter());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function endpointFilter(): ?array
    {
        $names = $this->selectedEndpointNames();

        return $names === [] ? null : $names;
    }

    #[Computed]
    public function httpTiming(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->httpTimingOverTime(
                    $this->run->id,
                    $this->endpointFilter(),
                    InfluxDbService::runTimeRange($this->run->started_at, $this->run->completed_at)
                );
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function iterationDuration(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->iterationDurationOverTime(
                    $this->run->id,
                    InfluxDbService::runTimeRange($this->run->started_at, $this->run->completed_at)
                );
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function iterations(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->iterationsOverTime(
                    $this->run->id,
                    InfluxDbService::runTimeRange($this->run->started_at, $this->run->completed_at)
                );
        } catch (\Throwable) {
            return null;
        }
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'N/A';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $remainingSeconds > 0
            ? "{$minutes}m {$remainingSeconds}s"
            : "{$minutes}m";
    }

    public function formatMetric(mixed $value, string $unit = ''): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return $unit ? "{$value} {$unit}" : (string) $value;
    }

    public function cancelRun(): void
    {
        RunResultService::cancel($this->run);
        $this->run->refresh();
    }
};
?>

<div class="flex flex-col gap-8">
    @php
        $runTest = $this->run->script->test;
        $runScript = $this->run->script;
        $isRunActive = $this->isActive();
        $triggeredBy = $this->run->triggered_by_user_id
            ? 'Triggered by '.($this->run->triggeredByUser?->name ?? 'Unknown').' ('.$this->run->triggered_by.')'
            : 'Triggered by '.$this->run->triggered_by;
    @endphp

    <x-page-header :title="$this->run->slug" :description="$triggeredBy">
        <x-slot:breadcrumbs>
            <nav class="flex min-w-0 items-center gap-1.5" aria-label="Breadcrumb">
                <a wire:navigate href="{{ route('projects.overview', ['project' => $this->project]) }}" class="truncate transition-colors hover:text-zinc-900">{{ $this->project->name }}</a>
                <flux:icon.chevron-right variant="micro" class="shrink-0 text-zinc-300" />
                <a wire:navigate href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $runTest]) }}" class="truncate transition-colors hover:text-zinc-900">{{ $runTest->name }}</a>
                <flux:icon.chevron-right variant="micro" class="shrink-0 text-zinc-300" />
                <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $runTest, 'script' => $runScript]) }}" class="truncate transition-colors hover:text-zinc-900">{{ $runScript->name }}</a>
            </nav>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:button :href="route('projects.runs', ['project' => $this->project])" wire:navigate variant="ghost" size="sm" icon="arrow-left">All runs</flux:button>

            @if ($this->hasLog())
                <flux:modal.trigger name="run-logs">
                    <flux:button size="sm" icon="command-line" title="View run logs" aria-label="View run logs" />
                </flux:modal.trigger>
            @endif

            @if ($isRunActive)
                <flux:button wire:click="cancelRun" variant="danger" size="sm">Cancel run</flux:button>
            @endif

            <flux:modal.trigger name="run-insights">
                <flux:button variant="primary" size="sm" icon="sparkles">AI insights</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    {{-- Run summary --}}
    <div class="ui-panel grid grid-cols-2 gap-px overflow-hidden bg-zinc-200 lg:grid-cols-4 [&>*]:bg-white">
            <div class="flex min-w-0 items-center justify-between gap-3 px-4 py-3.5">
                <div class="min-w-0">
                    <p class="truncate text-sm text-zinc-500">Status</p>
                    <div class="mt-1.5"><x-status-badge :status="$this->run->status" /></div>
                </div>
                @if ($isRunActive)
                    <x-run-progress :percent="$this->progressPercent()" />
                @endif
            </div>
            <div class="min-w-0 px-4 py-3.5">
                <p class="truncate text-sm text-zinc-500">Started</p>
                <p class="mt-1.5 truncate text-sm font-medium text-zinc-900 tabular-nums">{{ $this->run->started_at?->format('M j, Y H:i:s') ?? 'N/A' }}</p>
            </div>
            <div class="min-w-0 px-4 py-3.5">
                <p class="truncate text-sm text-zinc-500">Completed</p>
                <p class="mt-1.5 truncate text-sm font-medium text-zinc-900 tabular-nums">{{ $this->run->completed_at?->format('M j, Y H:i:s') ?? 'N/A' }}</p>
            </div>
            <div class="min-w-0 px-4 py-3.5">
                <p class="truncate text-sm text-zinc-500">Duration</p>
                <p class="mt-1.5 truncate text-sm font-medium text-zinc-900 tabular-nums">{{ $this->formatDuration($this->run->duration_seconds) }}</p>
            </div>
    </div>

    {{-- Error / warning message (collapsed by default, chevron to expand) --}}
    @if ($this->run->error_message)
        @php
            $errorSummary = Str::limit(trim((string) strtok($this->run->error_message, "\n")), 140);
            $isRunWarning = $this->run->status !== 'error';
            $errorTone = $isRunWarning
                ? ['icon' => 'text-amber-600', 'title' => 'text-amber-900', 'summary' => 'text-amber-800/80', 'pre' => 'text-amber-900 ring-amber-200']
                : ['icon' => 'text-red-600', 'title' => 'text-red-900', 'summary' => 'text-red-800/80', 'pre' => 'text-red-900 ring-red-200'];
        @endphp
        <div
            x-data="{ open: false }"
            @class([
                'overflow-hidden rounded-xl border',
                'border-amber-200 bg-amber-50/60' => $isRunWarning,
                'border-red-200 bg-red-50/60' => ! $isRunWarning,
            ])
        >
            <button
                type="button"
                x-on:click="open = !open"
                :aria-expanded="open.toString()"
                class="flex w-full items-center gap-2.5 px-4 py-3 text-left"
            >
                <flux:icon.exclamation-triangle variant="mini" class="size-4 shrink-0 {{ $errorTone['icon'] }}" />
                <span class="shrink-0 text-sm font-medium {{ $errorTone['title'] }}">{{ $isRunWarning ? 'Warning' : 'Error' }}</span>
                <span x-show="!open" class="min-w-0 flex-1 truncate font-mono text-xs {{ $errorTone['summary'] }}">{{ $errorSummary }}</span>
                <flux:icon.chevron-down
                    variant="micro"
                    class="ml-auto shrink-0 transition-transform duration-200 ease-snappy {{ $errorTone['icon'] }}"
                    x-bind:class="open ? 'rotate-0' : '-rotate-90'"
                />
            </button>
            <div x-cloak class="grid transition-[grid-template-rows,opacity] duration-200 ease-snappy"
                :style="open ? 'grid-template-rows:1fr;opacity:1' : 'grid-template-rows:0fr;opacity:0'">
                <div class="overflow-hidden">
                    <pre class="mx-4 mb-4 max-h-80 overflow-y-auto rounded-lg bg-white/70 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words ring-1 ring-inset {{ $errorTone['pre'] }}">{{ $this->run->error_message }}</pre>
                </div>
            </div>
        </div>
    @endif

    @if (in_array($this->run->status, ['passed', 'failed', 'error']))
        {{-- Performance metrics --}}
        @php
            $metricStats = [
                ['label' => 'Max VUs', 'value' => $this->run->vus_max !== null ? number_format($this->run->vus_max) : 'N/A', 'hint' => 'Peak concurrent virtual users'],
                ['label' => 'Total requests', 'value' => $this->run->requests_total !== null ? number_format($this->run->requests_total) : 'N/A', 'hint' => 'Sent during the run'],
                ['label' => 'Request rate', 'value' => $this->formatMetric($this->run->requests_per_second, 'req/s'), 'hint' => 'Average throughput'],
                ['label' => 'p95 latency', 'value' => $this->formatMetric($this->run->req_duration_p95_ms, 'ms'), 'hint' => '95th percentile'],
                ['label' => 'p99 latency', 'value' => $this->formatMetric($this->run->req_duration_p99_ms, 'ms'), 'hint' => '99th percentile'],
                ['label' => 'Error rate', 'value' => $this->formatMetric($this->run->error_rate, '%'), 'hint' => 'Failed requests'],
            ];
        @endphp

        <div class="ui-panel grid grid-cols-2 gap-px overflow-hidden bg-zinc-200 sm:grid-cols-3 xl:grid-cols-6 [&>*]:bg-white">
            @foreach ($metricStats as $stat)
                <x-stat :label="$stat['label']" :value="$stat['value']" />
            @endforeach
        </div>

        {{-- Response codes, checks, thresholds --}}
        @php
            $codeSummary = $this->responseCodeSummary;
            $hasCodeSummary = $codeSummary && $codeSummary['total'] > 0;
            $hasChecks = $this->run->checks_total !== null;
        @endphp

        @if ($hasCodeSummary || $hasChecks)
            @php
                $codeGroups = [
                    '2xx' => ['label' => '2xx', 'color' => '#0ca30c'],
                    '3xx' => ['label' => '3xx', 'color' => '#a1a1aa'],
                    '4xx' => ['label' => '4xx', 'color' => '#fab219'],
                    '5xx' => ['label' => '5xx', 'color' => '#d03b3b'],
                ];
                if ($hasChecks) {
                    $checksFailed = $this->run->checks_failed ?? 0;
                    $checksPassed = $this->run->checks_total - $checksFailed;
                    $checksPassRate = $this->run->checks_total > 0 ? round(($checksPassed / $this->run->checks_total) * 100, 1) : 0;
                }
            @endphp

            <section @class(['ui-panel grid grid-cols-1 divide-zinc-200 overflow-hidden max-lg:divide-y lg:divide-x', 'lg:grid-cols-2' => $hasCodeSummary && $hasChecks])>
                @if ($hasCodeSummary)
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="ui-panel-title">Response codes</h2>
                            <span class="text-xs text-zinc-500 tabular-nums">{{ number_format($codeSummary['total']) }} responses</span>
                        </div>
                        <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-zinc-100">
                            @foreach ($codeGroups as $group => $codeGroup)
                                @php $percent = $codeSummary['groups'][$group]['percent'] ?? 0; @endphp
                                @if ($percent > 0)
                                    <div class="h-full" style="width: {{ $percent }}%; background-color: {{ $codeGroup['color'] }}"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            @foreach ($codeGroups as $group => $codeGroup)
                                @php $g = $codeSummary['groups'][$group] ?? ['count' => 0, 'percent' => 0]; @endphp
                                <span @class(['flex items-center gap-1.5 tabular-nums', 'text-zinc-400' => $g['count'] === 0])>
                                    <span class="size-2 rounded-[2px]" style="background-color: {{ $codeGroup['color'] }}"></span>
                                    <span class="text-zinc-500">{{ $codeGroup['label'] }}</span>
                                    <span @class(['font-medium', 'text-zinc-900' => $g['count'] > 0])>{{ number_format($g['count']) }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasChecks)
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="ui-panel-title">Checks</h2>
                            <span class="text-xs text-zinc-500 tabular-nums">{{ $checksPassRate }}% passed</span>
                        </div>
                        <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-zinc-100">
                            @if ($checksPassed > 0)
                                <div class="h-full bg-emerald-600" style="width: {{ $checksPassRate }}%"></div>
                            @endif
                            @if ($checksFailed > 0)
                                <div class="h-full bg-red-600" style="width: {{ 100 - $checksPassRate }}%"></div>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm tabular-nums">
                            <span class="text-zinc-500">Passed <span class="font-medium text-zinc-900">{{ number_format($checksPassed) }}</span></span>
                            <span class="text-zinc-500">Failed <span @class(['font-medium', 'text-red-700' => $checksFailed > 0, 'text-zinc-400' => $checksFailed === 0])>{{ number_format($checksFailed) }}</span></span>
                            <span class="text-zinc-500">Total <span class="font-medium text-zinc-900">{{ number_format($this->run->checks_total) }}</span></span>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- Thresholds --}}
        @if ($this->run->thresholds_summary !== null)
            @php
                $thresholdsPassed = collect($this->run->thresholds_summary)->filter(fn ($threshold) => $threshold['ok'] ?? false)->count();
            @endphp
            <section class="ui-panel overflow-hidden">
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">Thresholds</h2>
                    <span class="text-xs text-zinc-500 tabular-nums">{{ $thresholdsPassed }} of {{ count($this->run->thresholds_summary) }} passed</span>
                </header>
                <div class="ui-list">
                    @foreach ($this->run->thresholds_summary as $threshold)
                        @php
                            $thresholdValue = $threshold['value'] ?? null;
                            $thresholdName = $threshold['name'] ?? '';
                            $thresholdCondition = $threshold['condition'] ?? null;

                            $valueLabel = null;
                            if (is_numeric($thresholdValue)) {
                                $valueLabel = is_float($thresholdValue + 0)
                                    ? number_format((float) $thresholdValue, 2)
                                    : number_format((int) $thresholdValue);
                            } elseif ($thresholdValue !== null) {
                                $valueLabel = (string) $thresholdValue;
                            }
                        @endphp
                        <div class="ui-list-row justify-between gap-4">
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span class="truncate font-mono text-[13px] text-zinc-900">{{ $thresholdName }}</span>
                                @if ($thresholdCondition)
                                    <span class="truncate font-mono text-xs text-zinc-500">{{ $thresholdCondition }}</span>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                @if ($valueLabel !== null)
                                    <span class="text-sm text-zinc-700 tabular-nums">{{ $valueLabel }}</span>
                                @endif
                                <x-status-badge :status="$threshold['ok'] ? 'passed' : 'failed'" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- InfluxDB time-series charts --}}
        @php $metrics = $this->influxMetrics; @endphp
        @if ($metrics)
            <section class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-sm font-medium text-zinc-900">Time series</h2>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            @if ($this->selectedEndpoint)
                                @php
                                    $activeGroup = collect($this->endpoints)->firstWhere('pattern', $this->selectedEndpoint);
                                    $activeLabel = $activeGroup['label'] ?? $this->selectedEndpoint;
                                    $activeCount = $activeGroup['count'] ?? 1;
                                @endphp
                                Filtered to <span class="font-mono text-zinc-700">{{ $activeLabel }}</span>{{ $activeCount > 1 ? ' (× '.$activeCount.')' : '' }}. VUs, checks and data transfer are run-level only.
                            @else
                                Sampled every 5 seconds.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" icon-trailing="chevron-down">Charts</flux:button>
                            <flux:menu>
                                <flux:menu.item wire:click="toggleExtraChart('timing')">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :icon="$this->hasExtraChart('timing') ? 'check' : 'plus'" variant="micro" class="text-zinc-500" />
                                        HTTP timing
                                    </div>
                                </flux:menu.item>
                                <flux:menu.item wire:click="toggleExtraChart('iteration-duration')">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :icon="$this->hasExtraChart('iteration-duration') ? 'check' : 'plus'" variant="micro" class="text-zinc-500" />
                                        Iteration duration
                                    </div>
                                </flux:menu.item>
                                <flux:menu.item wire:click="toggleExtraChart('iterations')">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :icon="$this->hasExtraChart('iterations') ? 'check' : 'plus'" variant="micro" class="text-zinc-500" />
                                        Iterations
                                    </div>
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        @if (! empty($this->endpoints))
                            @php
                                $currentEndpointGroup = collect($this->endpoints)->firstWhere('pattern', $this->selectedEndpoint);
                            @endphp
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    groups: @js(collect($this->endpoints)->map(fn ($group) => ['pattern' => $group['pattern'], 'label' => $group['label'], 'count' => $group['count']])->values()),
                                    selected: @js($this->selectedEndpoint),
                                    get current() { return this.groups.find((g) => g.pattern === this.selected) ?? null; },
                                    get filtered() {
                                        const q = this.search.trim().toLowerCase();
                                        if (q === '') return this.groups;
                                        return this.groups.filter((g) => g.label.toLowerCase().includes(q) || g.pattern.toLowerCase().includes(q));
                                    },
                                    get visible() { return this.filtered.slice(0, 100); },
                                    pick(pattern) {
                                        this.selected = pattern;
                                        $wire.set('selectedEndpoint', pattern);
                                        this.open = false;
                                        this.search = '';
                                    },
                                }"
                                @keydown.escape.window="open = false"
                                class="relative w-72"
                                wire:key="endpoint-filter"
                            >
                                <button
                                    type="button"
                                    x-on:click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
                                    :aria-expanded="open.toString()"
                                    aria-label="Filter by endpoint"
                                    class="flex h-8 w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-2.5 text-left text-sm text-zinc-900 shadow-xs transition-colors hover:bg-zinc-50"
                                >
                                    <flux:icon.globe-alt variant="micro" class="shrink-0 text-zinc-400" />
                                    <span class="min-w-0 flex-1 truncate" x-text="current ? current.label : 'All endpoints'">{{ $currentEndpointGroup['label'] ?? 'All endpoints' }}</span>
                                    <span x-show="current && current.count > 1" x-text="'× ' + current?.count" class="shrink-0 rounded-md bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-500 tabular-nums"></span>
                                    <flux:icon.chevron-down
                                        variant="micro"
                                        class="shrink-0 text-zinc-400 transition-transform duration-200 ease-snappy"
                                        x-bind:class="open ? 'rotate-180' : 'rotate-0'"
                                    />
                                </button>
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-on:click.outside="open = false"
                                    x-transition:enter="transition ease-snappy duration-200"
                                    x-transition:enter-start="opacity-0 scale-[0.97]"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-out duration-100"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="absolute right-0 z-50 mt-1.5 w-full origin-top-right overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg shadow-zinc-900/5"
                                >
                                    <div class="border-b border-zinc-100 p-1.5">
                                        <input
                                            x-ref="search"
                                            x-model="search"
                                            type="text"
                                            placeholder="Search endpoints…"
                                            class="ui-input h-8 text-xs shadow-none"
                                        />
                                    </div>
                                    <ul class="max-h-64 overflow-y-auto p-1">
                                        <li>
                                            <button
                                                type="button"
                                                x-on:click="pick('')"
                                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-zinc-700 transition-colors hover:bg-zinc-100"
                                            >
                                                <span class="min-w-0 flex-1 truncate">All endpoints</span>
                                                <flux:icon.check x-show="!current" variant="micro" class="shrink-0 text-zinc-900" />
                                            </button>
                                        </li>
                                        <template x-for="group in visible" :key="group.pattern">
                                            <li>
                                                <button
                                                    type="button"
                                                    x-on:click="pick(group.pattern)"
                                                    :title="group.pattern"
                                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-zinc-700 transition-colors hover:bg-zinc-100"
                                                >
                                                    <span class="min-w-0 flex-1 truncate font-mono" x-text="group.label"></span>
                                                    <span x-show="group.count > 1" x-text="'× ' + group.count" class="shrink-0 rounded-md bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-500 tabular-nums"></span>
                                                    <flux:icon.check x-show="current && current.pattern === group.pattern" variant="micro" class="shrink-0 text-zinc-900" />
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="filtered.length === 0" class="px-2 py-1.5 text-xs text-zinc-500">
                                            No endpoints match.
                                        </li>
                                        <li x-show="filtered.length > visible.length" class="px-2 py-1.5 text-xs text-zinc-500 tabular-nums">
                                            Showing <span x-text="visible.length"></span> of <span x-text="filtered.length"></span>. Refine the search to narrow down.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($this->selectedEndpoint)
                    @php $summary = $this->selectedEndpointSummary; @endphp
                    @if ($summary)
                        <div class="ui-panel grid grid-cols-2 gap-px overflow-hidden bg-zinc-200 lg:grid-cols-4 [&>*]:bg-white">
                            <x-stat label="Total requests" :value="$summary['total_requests'] !== null ? number_format($summary['total_requests']) : 'N/A'" hint="Hits on this endpoint" />
                            <x-stat label="p95 latency" :value="$this->formatMetric($summary['p95_ms'], 'ms')" hint="95th percentile" />
                            <x-stat label="p99 latency" :value="$this->formatMetric($summary['p99_ms'], 'ms')" hint="99th percentile" />
                            <x-stat label="Error rate" :value="$this->formatMetric($summary['error_rate_percent'], '%')" hint="Failed requests on this endpoint" />
                        </div>
                    @endif
                @endif

                @php $hasChartData = ! empty($metrics['request_rate']['labels']); @endphp
                @if ($hasChartData)
                    @php
                        $isRunLevelView = $this->selectedEndpoint === null || $this->selectedEndpoint === '';
                        $chartPanels = [];

                        if ($isRunLevelView) {
                            $chartPanels[] = ['key' => 'vus-chart', 'title' => 'Active VUs', 'unit' => 'Virtual users', 'component' => 'runVusChart', 'data' => $metrics['vus'] ?? ['labels' => [], 'values' => []], 'hash' => $metrics['vus'] ?? []];
                        }

                        $chartPanels[] = ['key' => 'request-rate-chart', 'title' => 'Request rate', 'unit' => 'req/s', 'component' => 'runRequestRateChart', 'data' => $metrics['request_rate'] ?? ['labels' => [], 'values' => []], 'hash' => $metrics['request_rate'] ?? []];
                        $chartPanels[] = ['key' => 'response-time-chart', 'title' => 'Response time', 'unit' => 'ms', 'component' => 'runResponseTimeChart', 'data' => $metrics['response_time'] ?? ['labels' => [], 'p95' => [], 'p99' => []], 'hash' => $metrics['response_time'] ?? []];
                        $chartPanels[] = ['key' => 'error-rate-chart', 'title' => 'Error rate', 'unit' => '%', 'component' => 'runErrorRateChart', 'data' => $metrics['error_rate'] ?? ['labels' => [], 'values' => []], 'hash' => $metrics['error_rate'] ?? []];
                        $chartPanels[] = ['key' => 'response-codes-chart', 'title' => 'Response codes', 'unit' => 'Responses', 'component' => 'runResponseCodesChart', 'data' => $metrics['response_codes'] ?? ['labels' => [], '2xx' => [], '3xx' => [], '4xx' => [], '5xx' => []], 'hash' => $metrics['response_codes'] ?? []];

                        if ($this->hasExtraChart('timing')) {
                            $timing = $this->httpTiming;

                            if ($timing && ! empty($timing['labels'])) {
                                $chartPanels[] = ['key' => 'http-timing-chart', 'title' => 'HTTP timing', 'unit' => 'ms, stacked', 'component' => 'runHttpTimingChart', 'data' => $timing, 'hash' => $timing];
                            }
                        }

                        if ($isRunLevelView) {
                            $chartPanels[] = ['key' => 'checks-chart', 'title' => 'Checks', 'unit' => 'Per interval', 'component' => 'runChecksChart', 'data' => $metrics['checks'] ?? ['labels' => [], 'passed' => [], 'failed' => []], 'hash' => $metrics['checks'] ?? []];
                            $chartPanels[] = ['key' => 'data-transfer-chart', 'title' => 'Data transfer', 'unit' => 'Bytes', 'component' => 'runDataTransferChart', 'data' => $metrics['data_transfer'] ?? ['labels' => [], 'sent' => [], 'received' => []], 'hash' => $metrics['data_transfer'] ?? []];

                            if ($this->hasExtraChart('iteration-duration')) {
                                $iterationDuration = $this->iterationDuration;

                                if ($iterationDuration && ! empty($iterationDuration['labels'])) {
                                    $chartPanels[] = ['key' => 'iteration-duration-chart', 'title' => 'Iteration duration', 'unit' => 'ms', 'component' => 'runIterationDurationChart', 'data' => $iterationDuration, 'hash' => $iterationDuration];
                                }
                            }

                            if ($this->hasExtraChart('iterations')) {
                                $iterations = $this->iterations;

                                if ($iterations && ! empty($iterations['labels'])) {
                                    $chartPanels[] = ['key' => 'iterations-chart', 'title' => 'Iterations', 'unit' => 'Per interval', 'component' => 'runIterationsChart', 'data' => $iterations, 'hash' => $iterations];
                                }
                            }
                        }
                    @endphp

                    <div
                        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
                        @if ($isRunActive) wire:poll.5s @endif
                    >
                        @foreach ($chartPanels as $chartPanel)
                            <section class="ui-panel" wire:key="panel-{{ $chartPanel['key'] }}">
                                <header class="ui-panel-header">
                                    <h2 class="ui-panel-title">{{ $chartPanel['title'] }}</h2>
                                    <span class="text-xs text-zinc-500">{{ $chartPanel['unit'] }}</span>
                                </header>
                                <div class="p-4">
                                    <div
                                        wire:key="{{ $chartPanel['key'] }}-{{ md5(json_encode($chartPanel['hash'])) }}"
                                        wire:ignore
                                        x-data="{{ $chartPanel['component'] }}(@js($chartPanel['data']))"
                                        class="relative h-56"
                                    >
                                        <canvas x-ref="canvas" role="img" aria-label="{{ $chartPanel['title'] }} over the run"></canvas>
                                        <x-chart-tooltip />
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <div class="ui-panel">
                        <x-empty-state icon="chart-bar" title="No time-series data" description="InfluxDB has no samples recorded for this run." />
                    </div>
                @endif
            </section>
        @endif
    @endif

    {{-- Waiting for run --}}
    @if ($this->run->status === 'queued' || $this->run->status === 'running')
        <div class="ui-panel flex flex-col items-center justify-center gap-4 px-6 py-14 text-center" wire:poll.5s>
            <x-plowing-bull />
            <div>
                <p class="text-sm font-medium text-zinc-900">Plowing through your test run…</p>
                <p class="mt-1 text-sm text-zinc-500">Metrics and charts appear here when the run finishes.</p>
            </div>
        </div>
    @endif

    {{-- Footer actions --}}
    <div class="flex gap-2">
        <flux:button wire:navigate :href="route('projects.runs', ['project' => $this->project])" size="sm" icon="arrow-left">
            Back to runs
        </flux:button>
        <flux:button wire:navigate :href="route('projects.view-test-script', ['project' => $this->project, 'test' => $runTest, 'script' => $runScript])" variant="ghost" size="sm">
            View script
        </flux:button>
    </div>

    {{-- AI insights --}}
    <flux:modal name="run-insights" flyout class="md:w-2xl">
        <livewire:run-insight-panel :run="$run" />
    </flux:modal>

    {{-- Live run logs --}}
    <flux:modal class="p-0!" name="run-logs" variant="flyout" position="bottom" :closable="false">
        <livewire:run-terminal :run="$run" />
    </flux:modal>
</div>
