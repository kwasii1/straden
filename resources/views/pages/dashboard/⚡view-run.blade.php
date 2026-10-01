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

    public function statusVariant(string $status): array
    {
        return match ($status) {
            'passed' => ['label' => 'Passed', 'class' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10'],
            'running' => ['label' => 'Running', 'class' => 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-500/10'],
            'queued' => ['label' => 'Queued', 'class' => 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10'],
            'failed', 'error' => ['label' => ucfirst($status), 'class' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-500/10'],
            default => ['label' => ucfirst($status), 'class' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-800'],
        };
    }

    public function statusDot(string $status): string
    {
        return match ($status) {
            'passed' => 'bg-emerald-500',
            'running' => 'bg-blue-500',
            'queued' => 'bg-amber-500',
            'failed', 'error' => 'bg-red-500',
            default => 'bg-zinc-400',
        };
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

<div class="flex flex-col gap-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Run Detail</flux:heading>
            <flux:text class="mt-1">View test run results and performance metrics.</flux:text>
        </div>
        <flux:modal.trigger name="run-insights">
            <flux:button variant="primary" icon="sparkles">AI Insights</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-x-2 text-sm text-zinc-500">
        <a wire:navigate href="{{ route('projects.overview', ['project' => $this->project]) }}" class="transition-colors hover:text-zinc-300">
            {{ $this->project->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <a wire:navigate href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $this->run->script->test]) }}" class="transition-colors hover:text-zinc-300">
            {{ $this->run->script->test->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->run->script->test, 'script' => $this->run->script]) }}" class="transition-colors hover:text-zinc-300">
            {{ $this->run->script->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-zinc-300">{{ $this->run->slug }}</span>
    </nav>

    {{-- Run meta card --}}
    @php $runStatus = $this->statusVariant($this->run->status); @endphp
    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
        <div class="flex flex-col bg-white dark:bg-zinc-900">
            <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $runStatus['class'] }}">
                            <span class="size-1.5 rounded-full {{ $this->statusDot($this->run->status) }}"></span>
                            {{ $runStatus['label'] }}
                        </span>
                        <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $this->run->slug }}</flux:text>
                    </div>
                    <flux:text class="text-xs text-[#919191]">
                        @if ($this->run->triggered_by_user_id)
                            Triggered by {{ $this->run->triggeredByUser?->name ?? 'Unknown' }} ({{ $this->run->triggered_by }})
                        @else
                            Triggered by {{ $this->run->triggered_by }}
                        @endif
                    </flux:text>
                </div>

                <div class="flex items-center gap-3">
                    @if ($this->isActive())
                        @php $progress = $this->progressPercent(); @endphp
                        <x-run-progress :percent="$progress" />
                    @endif

                    @if ($this->hasLog())
                        <flux:modal.trigger name="run-logs">
                            <flux:button size="sm" icon="command-line" title="View run logs" aria-label="View run logs" />
                        </flux:modal.trigger>
                    @endif

                    @if ($this->isActive())
                        <flux:button wire:click="cancelRun" variant="danger" size="sm">Cancel Run</flux:button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-zinc-100 border-t border-zinc-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-zinc-800 dark:border-zinc-800">
                <div class="flex flex-col gap-y-1 px-5 py-4">
                    <flux:text class="text-[11px] font-medium tracking-wide text-zinc-500 uppercase">Started At</flux:text>
                    <flux:text class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $this->run->started_at?->format('M j, Y H:i:s') ?? 'N/A' }}
                    </flux:text>
                </div>
                <div class="flex flex-col gap-y-1 px-5 py-4">
                    <flux:text class="text-[11px] font-medium tracking-wide text-zinc-500 uppercase">Completed At</flux:text>
                    <flux:text class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $this->run->completed_at?->format('M j, Y H:i:s') ?? 'N/A' }}
                    </flux:text>
                </div>
                <div class="flex flex-col gap-y-1 px-5 py-4">
                    <flux:text class="text-[11px] font-medium tracking-wide text-zinc-500 uppercase">Duration</flux:text>
                    <flux:text class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $this->formatDuration($this->run->duration_seconds) }}
                    </flux:text>
                </div>
            </div>
        </div>
    </div>

    {{-- Error / warning message (collapsed by default, chevron to expand) --}}
    @if ($this->run->error_message)
        @php
            $errorSummary = Str::limit(trim((string) strtok($this->run->error_message, "\n")), 140);
            $isRunWarning = $this->run->status !== 'error';
        @endphp
        <div
            x-data="{ open: false }"
            class="overflow-hidden rounded-xl border {{ $isRunWarning ? 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30' : 'border-red-200 bg-red-50 dark:border-red-900/60 dark:bg-red-950/30' }}"
        >
            <button
                type="button"
                x-on:click="open = !open"
                :aria-expanded="open.toString()"
                class="flex w-full items-center gap-2 px-5 py-4 text-left"
            >
                <flux:icon.exclamation-triangle class="size-4 shrink-0 {{ $isRunWarning ? 'text-amber-500 dark:text-amber-400' : 'text-red-500 dark:text-red-400' }}" />
                <flux:heading size="sm" class="shrink-0 {{ $isRunWarning ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400' }}">{{ $isRunWarning ? 'Warning' : 'Error' }}</flux:heading>
                <span x-show="!open" class="min-w-0 flex-1 truncate font-mono text-xs {{ $isRunWarning ? 'text-amber-700/80 dark:text-amber-300/80' : 'text-red-700/80 dark:text-red-300/80' }}">{{ $errorSummary }}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                    class="shrink-0 transition-transform duration-200 {{ $isRunWarning ? 'text-amber-500 dark:text-amber-400' : 'text-red-500 dark:text-red-400' }}"
                    :style="open ? 'transform: rotate(0deg)' : 'transform: rotate(-90deg)'">
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </button>
            <div x-cloak class="grid transition-[grid-template-rows,opacity] duration-300"
                :style="open ? 'grid-template-rows:1fr;opacity:1' : 'grid-template-rows:0fr;opacity:0'">
                <div class="overflow-hidden">
                    <pre class="mx-5 mb-4 max-h-80 overflow-y-auto rounded-lg p-3 text-sm whitespace-pre-wrap break-words {{ $isRunWarning ? 'bg-amber-100/60 text-amber-700 dark:bg-black/30 dark:text-amber-300' : 'bg-red-100/60 text-red-700 dark:bg-black/30 dark:text-red-300' }}">{{ $this->run->error_message }}</pre>
                </div>
            </div>
        </div>
    @endif

    @if (in_array($this->run->status, ['passed', 'failed', 'error']))
        {{-- Performance metrics --}}
        @php
            $metricCards = [
                ['label' => 'VUs Max', 'value' => $this->formatMetric($this->run->vus_max), 'icon' => 'user', 'color' => 'bg-violet-500', 'hint' => 'peak concurrent virtual users'],
                ['label' => 'Total Requests', 'value' => $this->formatMetric($this->run->requests_total), 'icon' => 'globe-alt', 'color' => 'bg-blue-500', 'hint' => 'requests sent during the run'],
                ['label' => 'Requests / Second', 'value' => $this->formatMetric($this->run->requests_per_second), 'icon' => 'arrows-right-left', 'color' => 'bg-emerald-500', 'hint' => 'average throughput'],
                ['label' => 'P95 Duration', 'value' => $this->formatMetric($this->run->req_duration_p95_ms, 'ms'), 'icon' => 'clock', 'color' => 'bg-sky-500', 'hint' => '95th percentile latency'],
                ['label' => 'P99 Duration', 'value' => $this->formatMetric($this->run->req_duration_p99_ms, 'ms'), 'icon' => 'clock', 'color' => 'bg-rose-500', 'hint' => '99th percentile latency'],
                ['label' => 'Error Rate', 'value' => $this->formatMetric($this->run->error_rate, '%'), 'icon' => 'exclamation-triangle', 'color' => 'bg-amber-500', 'hint' => 'failed request percentage'],
            ];
        @endphp

        <div class="flex items-center justify-between">
            <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Performance Metrics</flux:text>
            <flux:text class="text-xs text-[#919191]">Aggregated run results</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($metricCards as $card)
                <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                    <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                        <div class="flex items-center gap-3 px-4 py-3.5">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $card['color'] }} text-white">
                                <flux:icon :name="$card['icon']" class="size-4" />
                            </div>
                            <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ $card['label'] }}</flux:text>
                        </div>
                        <div class="px-4">
                            <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $card['value'] }}</p>
                        </div>
                    </div>
                    <div class="px-4 py-2.5">
                        <p class="text-xs text-[#919191] dark:text-zinc-400">{{ $card['hint'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Response codes --}}
        @php $codeSummary = $this->responseCodeSummary; @endphp
        @if ($codeSummary && $codeSummary['total'] > 0)
            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                <div class="flex items-center justify-between px-3 py-2.5">
                    <div>
                        <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Response Codes</flux:text>
                        <p class="text-xs text-[#919191] dark:text-zinc-400">Distribution of HTTP status codes</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (['2xx' => ['label' => '2xx Success', 'color' => '#16a34a'], '3xx' => ['label' => '3xx Redirect', 'color' => '#ca8a04'], '4xx' => ['label' => '4xx Client Error', 'color' => '#ea580c'], '5xx' => ['label' => '5xx Server Error', 'color' => '#dc2626']] as $group => $codeGroup)
                            @php $g = $codeSummary['groups'][$group] ?? ['count' => 0, 'percent' => 0]; @endphp
                            <div class="flex flex-col gap-y-1">
                                <flux:text class="text-[11px] font-medium tracking-wide text-zinc-500 uppercase">{{ $codeGroup['label'] }}</flux:text>
                                <div class="flex items-baseline gap-x-2">
                                    <span class="text-2xl font-semibold tabular-nums" style="color: {{ $codeGroup['color'] }}">{{ number_format($g['count']) }}</span>
                                    <flux:text class="text-sm text-zinc-500">{{ $g['percent'] }}%</flux:text>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-1.5 rounded-full" style="width: {{ max($g['percent'], 2) }}%; background-color: {{ $codeGroup['color'] }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Checks --}}
        @if ($this->run->checks_total !== null)
            @php
                $checksCards = [
                    ['label' => 'Total Checks', 'value' => $this->run->checks_total, 'icon' => 'document-text', 'color' => 'bg-blue-500', 'hint' => 'assertions evaluated'],
                    ['label' => 'Failed', 'value' => $this->run->checks_failed ?? 0, 'icon' => 'x-mark', 'color' => 'bg-rose-500', 'hint' => 'assertions that failed'],
                    ['label' => 'Passed', 'value' => $this->run->checks_total - ($this->run->checks_failed ?? 0), 'icon' => 'check', 'color' => 'bg-emerald-500', 'hint' => 'assertions that passed'],
                ];
            @endphp

            <div class="flex items-center justify-between">
                <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Checks</flux:text>
                <flux:text class="text-xs text-[#919191]">Assertions evaluated during the run</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($checksCards as $card)
                    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                        <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                            <div class="flex items-center gap-3 px-4 py-3.5">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $card['color'] }} text-white">
                                    <flux:icon :name="$card['icon']" class="size-4" />
                                </div>
                                <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ $card['label'] }}</flux:text>
                            </div>
                            <div class="px-4">
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $card['value'] }}</p>
                            </div>
                        </div>
                        <div class="px-4 py-2.5">
                            <p class="text-xs text-[#919191] dark:text-zinc-400">{{ $card['hint'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Thresholds --}}
        @if ($this->run->thresholds_summary !== null)
            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                <div class="flex items-center justify-between px-3 py-2.5">
                    <div>
                        <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Thresholds</flux:text>
                        <p class="text-xs text-[#919191] dark:text-zinc-400">Rate limiting and assertion thresholds</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white dark:bg-zinc-900">
                    <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
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
                            <div class="flex items-center justify-between gap-x-4 px-4 py-3">
                                <div class="flex min-w-0 flex-col">
                                    <flux:text class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $thresholdName }}</flux:text>
                                    @if ($thresholdCondition)
                                        <flux:text class="text-xs text-zinc-500">{{ $thresholdCondition }}</flux:text>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-x-3">
                                    @if ($valueLabel !== null)
                                        <flux:text class="text-xs text-zinc-500 tabular-nums">{{ $valueLabel }}</flux:text>
                                    @endif
                                    @if ($threshold['ok'])
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                            <flux:icon.check class="size-4" />
                                            Passed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500">
                                            <flux:icon.x-mark class="size-4" />
                                            Failed
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- InfluxDB time-series charts --}}
        @php $metrics = $this->influxMetrics; @endphp
        @if ($metrics)
            <div class="flex flex-col gap-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <flux:text class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Time-Series Charts</flux:text>
                            <p class="text-xs text-[#919191]">
                                @if ($this->selectedEndpoint)
                                    @php
                                        $activeGroup = $currentEndpointGroup ?? collect($this->endpoints)->firstWhere('pattern', $this->selectedEndpoint);
                                        $activeLabel = $activeGroup['label'] ?? $this->selectedEndpoint;
                                        $activeCount = $activeGroup['count'] ?? 1;
                                    @endphp
                                    Filtered to {{ $activeLabel }}{{ $activeCount > 1 ? ' (× '.$activeCount.')' : '' }} · VUs, Checks and Data Transfer are run-level only
                                @else
                                    Performance metrics sampled every 5 seconds
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:dropdown>
                                <flux:button variant="subtle" size="sm" icon-trailing="chevron-down">
                                    Charts
                                </flux:button>
                                <flux:menu>
                                    <flux:menu.item wire:click="toggleExtraChart('timing')">
                                        <div class="flex items-center gap-2">
                                            <flux:icon :icon="$this->hasExtraChart('timing') ? 'check' : 'plus'" class="size-4" />
                                            HTTP Timing
                                        </div>
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="toggleExtraChart('iteration-duration')">
                                        <div class="flex items-center gap-2">
                                            <flux:icon :icon="$this->hasExtraChart('iteration-duration') ? 'check' : 'plus'" class="size-4" />
                                            Iteration Duration
                                        </div>
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="toggleExtraChart('iterations')">
                                        <div class="flex items-center gap-2">
                                            <flux:icon :icon="$this->hasExtraChart('iterations') ? 'check' : 'plus'" class="size-4" />
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
                                    <flux:text class="mb-1 block text-[11px] font-medium tracking-wide text-zinc-500 uppercase">Endpoint</flux:text>
                                    <button
                                        type="button"
                                        x-on:click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
                                        class="flex h-10 w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-left text-sm text-zinc-900 shadow-xs transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                    >
                                        <flux:icon.globe-alt class="size-4 shrink-0 text-zinc-400" />
                                        <span class="min-w-0 flex-1 truncate" x-text="current ? current.label : 'All endpoints'">{{ $currentEndpointGroup['label'] ?? 'All endpoints' }}</span>
                                        <span x-show="current && current.count > 1" x-text="'× ' + current?.count" class="shrink-0 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-500 tabular-nums dark:bg-zinc-800 dark:text-zinc-400"></span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                                            class="shrink-0 text-zinc-400 transition-transform duration-200"
                                            :style="open ? 'transform: rotate(180deg)' : 'transform: rotate(0deg)'">
                                            <path d="M6 9l6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-on:click.outside="open = false"
                                        class="absolute right-0 z-50 mt-1.5 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <div class="border-b border-zinc-100 p-2 dark:border-zinc-800">
                                            <input
                                                x-ref="search"
                                                x-model="search"
                                                type="text"
                                                placeholder="Search endpoints…"
                                                class="w-full rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                                            />
                                        </div>
                                        <ul class="max-h-64 overflow-y-auto p-1">
                                            <li>
                                                <button
                                                    type="button"
                                                    x-on:click="pick('')"
                                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                                >
                                                    <span class="min-w-0 flex-1 truncate">All endpoints</span>
                                                    <svg x-show="!current" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-emerald-500">
                                                        <path d="M20 6L9 17l-5-5" />
                                                    </svg>
                                                </button>
                                            </li>
                                            <template x-for="group in visible" :key="group.pattern">
                                                <li>
                                                    <button
                                                        type="button"
                                                        x-on:click="pick(group.pattern)"
                                                        :title="group.pattern"
                                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                                    >
                                                        <span class="min-w-0 flex-1 truncate font-mono" x-text="group.label"></span>
                                                        <span x-show="group.count > 1" x-text="'× ' + group.count" class="shrink-0 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10.5px] font-medium text-zinc-500 tabular-nums dark:bg-zinc-800 dark:text-zinc-400"></span>
                                                        <svg x-show="current && current.pattern === group.pattern" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-emerald-500">
                                                            <path d="M20 6L9 17l-5-5" />
                                                        </svg>
                                                    </button>
                                                </li>
                                            </template>
                                            <li x-show="filtered.length === 0" class="px-3 py-2 text-xs text-zinc-400">
                                                No endpoints match.
                                            </li>
                                            <li x-show="filtered.length > visible.length" class="px-3 py-2 text-[11px] text-zinc-400 tabular-nums">
                                                Showing <span x-text="visible.length"></span> of <span x-text="filtered.length"></span> — refine search to narrow down.
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
                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                                <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                                    <div class="flex items-center gap-3 px-4 py-3.5">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-white">
                                            <flux:icon.globe-alt class="size-4" />
                                        </div>
                                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Total Requests</flux:text>
                                    </div>
                                    <div class="px-4">
                                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->formatMetric($summary['total_requests']) }}</p>
                                    </div>
                                </div>
                                <div class="px-4 py-2.5">
                                    <p class="text-xs text-[#919191] dark:text-zinc-400">hits on this endpoint</p>
                                </div>
                            </div>
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                                <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                                    <div class="flex items-center gap-3 px-4 py-3.5">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-white">
                                            <flux:icon.clock class="size-4" />
                                        </div>
                                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">P95 Duration</flux:text>
                                    </div>
                                    <div class="px-4">
                                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->formatMetric($summary['p95_ms'], 'ms') }}</p>
                                    </div>
                                </div>
                                <div class="px-4 py-2.5">
                                    <p class="text-xs text-[#919191] dark:text-zinc-400">95th percentile latency</p>
                                </div>
                            </div>
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                                <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                                    <div class="flex items-center gap-3 px-4 py-3.5">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-500 text-white">
                                            <flux:icon.clock class="size-4" />
                                        </div>
                                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">P99 Duration</flux:text>
                                    </div>
                                    <div class="px-4">
                                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->formatMetric($summary['p99_ms'], 'ms') }}</p>
                                    </div>
                                </div>
                                <div class="px-4 py-2.5">
                                    <p class="text-xs text-[#919191] dark:text-zinc-400">99th percentile latency</p>
                                </div>
                            </div>
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80">
                                <div class="flex flex-col rounded-xl bg-white pb-3.5 dark:bg-zinc-900">
                                    <div class="flex items-center gap-3 px-4 py-3.5">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white">
                                            <flux:icon.exclamation-triangle class="size-4" />
                                        </div>
                                        <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase">Error Rate</flux:text>
                                    </div>
                                    <div class="px-4">
                                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->formatMetric($summary['error_rate_percent'], '%') }}</p>
                                    </div>
                                </div>
                                <div class="px-4 py-2.5">
                                    <p class="text-xs text-[#919191] dark:text-zinc-400">failed requests on this endpoint</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                @php $hasChartData = ! empty($metrics['request_rate']['labels']); @endphp
                @if ($hasChartData)
                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                        @if ($this->isActive()) wire:poll.5s @endif
                    >
                        @if ($this->selectedEndpoint === null || $this->selectedEndpoint === '')
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                <div class="flex items-center justify-between px-3 py-2.5">
                                    <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Active VUs</flux:text>
                                </div>
                                <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                    <div
                                        wire:key="vus-chart-{{ md5(json_encode($metrics['vus'] ?? [])) }}"
                                        wire:ignore
                                        x-data="runVusChart(@js($metrics['vus'] ?? ['labels' => [], 'values' => []]))"
                                        class="relative h-56"
                                    >
                                        <canvas x-ref="canvas"></canvas>
                                        <x-chart-tooltip />
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                            <div class="flex items-center justify-between px-3 py-2.5">
                                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Request Rate</flux:text>
                            </div>
                            <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                <div
                                    wire:key="request-rate-chart-{{ md5(json_encode($metrics['request_rate'] ?? [])) }}"
                                    wire:ignore
                                    x-data="runRequestRateChart(@js($metrics['request_rate'] ?? ['labels' => [], 'values' => []]))"
                                    class="relative h-56"
                                >
                                    <canvas x-ref="canvas"></canvas>
                                    <x-chart-tooltip />
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                            <div class="flex items-center justify-between px-3 py-2.5">
                                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Response Time</flux:text>
                            </div>
                            <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                <div
                                    wire:key="response-time-chart-{{ md5(json_encode($metrics['response_time'] ?? [])) }}"
                                    wire:ignore
                                    x-data="runResponseTimeChart(@js($metrics['response_time'] ?? ['labels' => [], 'p95' => [], 'p99' => []]))"
                                    class="relative h-56"
                                >
                                    <canvas x-ref="canvas"></canvas>
                                    <x-chart-tooltip />
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                            <div class="flex items-center justify-between px-3 py-2.5">
                                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Error Rate</flux:text>
                            </div>
                            <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                <div
                                    wire:key="error-rate-chart-{{ md5(json_encode($metrics['error_rate'] ?? [])) }}"
                                    wire:ignore
                                    x-data="runErrorRateChart(@js($metrics['error_rate'] ?? ['labels' => [], 'values' => []]))"
                                    class="relative h-56"
                                >
                                    <canvas x-ref="canvas"></canvas>
                                    <x-chart-tooltip />
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                            <div class="flex items-center justify-between px-3 py-2.5">
                                <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Response Codes</flux:text>
                            </div>
                            <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                <div
                                    wire:key="response-codes-chart-{{ md5(json_encode($metrics['response_codes'] ?? [])) }}"
                                    wire:ignore
                                    x-data="runResponseCodesChart(@js($metrics['response_codes'] ?? ['labels' => [], '2xx' => [], '3xx' => [], '4xx' => [], '5xx' => []]))"
                                    class="relative h-56"
                                >
                                    <canvas x-ref="canvas"></canvas>
                                    <x-chart-tooltip />
                                </div>
                            </div>
                        </div>

                        @if ($this->hasExtraChart('timing'))
                            @php $timing = $this->httpTiming; @endphp
                            @if ($timing && ! empty($timing['labels']))
                                <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                    <div class="flex items-center justify-between px-3 py-2.5">
                                        <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">HTTP Timing</flux:text>
                                    </div>
                                    <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                        <div
                                            wire:key="http-timing-chart-{{ md5(json_encode($timing)) }}"
                                            wire:ignore
                                            x-data="runHttpTimingChart(@js($timing))"
                                            class="relative h-56"
                                        >
                                            <canvas x-ref="canvas"></canvas>
                                            <x-chart-tooltip />
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if ($this->selectedEndpoint === null || $this->selectedEndpoint === '')
                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                <div class="flex items-center justify-between px-3 py-2.5">
                                    <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Checks</flux:text>
                                </div>
                                <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                    <div
                                        wire:key="checks-chart-{{ md5(json_encode($metrics['checks'] ?? [])) }}"
                                        wire:ignore
                                        x-data="runChecksChart(@js($metrics['checks'] ?? ['labels' => [], 'passed' => [], 'failed' => []]))"
                                        class="relative h-56"
                                    >
                                        <canvas x-ref="canvas"></canvas>
                                        <x-chart-tooltip />
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                <div class="flex items-center justify-between px-3 py-2.5">
                                    <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Data Transfer</flux:text>
                                </div>
                                <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                    <div
                                        wire:key="data-transfer-chart-{{ md5(json_encode($metrics['data_transfer'] ?? [])) }}"
                                        wire:ignore
                                        x-data="runDataTransferChart(@js($metrics['data_transfer'] ?? ['labels' => [], 'sent' => [], 'received' => []]))"
                                        class="relative h-56"
                                    >
                                        <canvas x-ref="canvas"></canvas>
                                        <x-chart-tooltip />
                                    </div>
                                </div>
                            </div>

                            @if ($this->hasExtraChart('iteration-duration'))
                                @php $iterationDuration = $this->iterationDuration; @endphp
                                @if ($iterationDuration && ! empty($iterationDuration['labels']))
                                    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                        <div class="flex items-center justify-between px-3 py-2.5">
                                            <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Iteration Duration</flux:text>
                                        </div>
                                        <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                            <div
                                                wire:key="iteration-duration-chart-{{ md5(json_encode($iterationDuration)) }}"
                                                wire:ignore
                                                x-data="runIterationDurationChart(@js($iterationDuration))"
                                                class="relative h-56"
                                            >
                                                <canvas x-ref="canvas"></canvas>
                                                <x-chart-tooltip />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            @if ($this->hasExtraChart('iterations'))
                                @php $iterations = $this->iterations; @endphp
                                @if ($iterations && ! empty($iterations['labels']))
                                    <div class="overflow-hidden rounded-xl border border-[#EDEDED] dark:border-zinc-800 bg-[#F1F1F1] dark:bg-zinc-800/80 p-2">
                                        <div class="flex items-center justify-between px-3 py-2.5">
                                            <flux:text class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300 uppercase">Iterations</flux:text>
                                        </div>
                                        <div class="rounded-xl bg-white p-4 dark:bg-zinc-900">
                                            <div
                                                wire:key="iterations-chart-{{ md5(json_encode($iterations)) }}"
                                                wire:ignore
                                                x-data="runIterationsChart(@js($iterations))"
                                                class="relative h-56"
                                            >
                                                <canvas x-ref="canvas"></canvas>
                                                <x-chart-tooltip />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @endif
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl border border-[#EDEDED] bg-[#F1F1F1] py-14 dark:border-zinc-800 dark:bg-zinc-800/80">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-blue-50 text-blue-500 dark:bg-zinc-800">
                            <flux:icon.chart-bar class="size-6" />
                        </div>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No time-series data available for this run.</flux:text>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- Waiting for run --}}
    @if ($this->run->status === 'queued' || $this->run->status === 'running')
        <div class="flex flex-col items-center justify-center gap-y-3 rounded-xl border border-[#EDEDED] bg-[#F1F1F1] py-16 dark:border-zinc-800 dark:bg-zinc-800/80" wire:poll.5s>
            <x-plowing-bull />
            <flux:text class="text-zinc-500 dark:text-zinc-400">Plowing through your test run...</flux:text>
        </div>
    @endif

    {{-- Footer actions --}}
    <div class="flex gap-x-2">
        <flux:button wire:navigate :href="route('projects.runs', ['project' => $this->project])">
            Back to Runs
        </flux:button>
        <flux:button wire:navigate :href="route('projects.view-test-script', ['project' => $this->project, 'test' => $this->run->script->test, 'script' => $this->run->script])">
            View Script
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
