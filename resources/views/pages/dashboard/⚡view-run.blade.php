<?php

use App\Models\Project;
use App\Models\Run;
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

    public function mount(): void
    {
        $this->run->load('script.test');
    }

    public function isActive(): bool
    {
        return in_array($this->run->status, ['queued', 'running'], true);
    }

    #[Computed]
    public function endpoints(): array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->endpointsForRun($this->run->id);
        } catch (\Throwable) {
            return [];
        }
    }

    #[Computed]
    public function influxMetrics(): ?array
    {
        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->metricsForRun($this->run->id, $this->endpointFilter());
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function selectedEndpointSummary(): ?array
    {
        if ($this->selectedEndpoint === null || $this->selectedEndpoint === '') {
            return null;
        }

        try {
            return (new InfluxDbService(\App\Models\Connector::influxDb()))
                ->endpointSummary($this->run->id, $this->selectedEndpoint);
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

    private function endpointFilter(): ?string
    {
        return $this->selectedEndpoint !== '' && $this->selectedEndpoint !== null
            ? $this->selectedEndpoint
            : null;
    }

    public function endpointLabel(string $endpoint): string
    {
        if (! preg_match('#^https?://#i', $endpoint)) {
            return $endpoint;
        }

        $segments = array_values(array_filter(
            explode('/', (string) parse_url($endpoint, PHP_URL_PATH)),
            fn (string $segment) => $segment !== ''
        ));

        if (count($segments) >= 2) {
            return implode('/', array_slice($segments, -2));
        }

        return $segments[0] ?? $endpoint;
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'passed' => '#16a34a',
            'running' => '#ca8a04',
            'queued' => '#6b7280',
            'failed', 'error' => '#dc2626',
            default => '#6b7280',
        };
    }

    public function statusIcon(string $status): string
    {
        return match ($status) {
            'passed' => 'check-circle',
            'running' => 'clock',
            'queued' => 'clock',
            'failed' => 'x-circle',
            'error' => 'exclamation-triangle',
            default => 'question-mark-circle',
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

<div class="flex flex-col gap-y-10">
    <div class="flex items-start justify-between gap-x-4">
        <div class="flex flex-col">
            <flux:heading size="xl">Run Detail</flux:heading>
            <flux:text>View test run results and performance metrics.</flux:text>
        </div>
        <flux:modal.trigger name="run-insights">
            <flux:button variant="primary" icon="sparkles">AI Insights</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex items-center gap-x-2 text-sm text-zinc-500">
        <a wire:navigate href="{{ route('projects.overview', ['project' => $this->project]) }}" class="hover:text-zinc-300 transition-colors">
            {{ $this->project->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <a wire:navigate href="{{ route('projects.view-test', ['project' => $this->project, 'test' => $this->run->script->test]) }}" class="hover:text-zinc-300 transition-colors">
            {{ $this->run->script->test->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <a wire:navigate href="{{ route('projects.view-test-script', ['project' => $this->project, 'test' => $this->run->script->test, 'script' => $this->run->script]) }}" class="hover:text-zinc-300 transition-colors">
            {{ $this->run->script->name }}
        </a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-zinc-300">{{ $this->run->slug }}</span>
    </div>

    <div class="flex items-center gap-x-4">
        <div class="flex items-center gap-x-2">
            <flux:icon :icon="$this->statusIcon($this->run->status)" class="size-5" style="color: {{ $this->statusColor($this->run->status) }}" />
            <flux:heading size="lg" style="color: {{ $this->statusColor($this->run->status) }}">
                {{ ucfirst($this->run->status) }}
            </flux:heading>
        </div>
        @if ($this->run->triggered_by_user_id)
            <flux:text class="text-zinc-500">
                Triggered by {{ $this->run->triggeredByUser?->name ?? 'Unknown' }} ({{ $this->run->triggered_by }})
            </flux:text>
        @else
            <flux:text class="text-zinc-500">
                Triggered by {{ $this->run->triggered_by }}
            </flux:text>
        @endif
    </div>

    <div class="grid grid-cols-3 border divide-x">
        <div class="flex flex-col p-3">
            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">STARTED AT</flux:text>
            <flux:text>{{ $this->run->started_at?->format('M j, Y H:i:s') ?? 'N/A' }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">COMPLETED AT</flux:text>
            <flux:text>{{ $this->run->completed_at?->format('M j, Y H:i:s') ?? 'N/A' }}</flux:text>
        </div>
        <div class="flex flex-col p-3">
            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">DURATION</flux:text>
            <flux:text>{{ $this->formatDuration($this->run->duration_seconds) }}</flux:text>
        </div>
    </div>

    @if ($this->run->status === 'error' && $this->run->error_message)
        <div class="flex flex-col gap-y-2 p-4 border border-red-800 bg-red-950/30 rounded-lg">
            <flux:heading size="sm" class="text-red-400">Error</flux:heading>
            <pre class="text-sm text-red-300 whitespace-pre-wrap">{{ $this->run->error_message }}</pre>
        </div>
    @endif

    @if (in_array($this->run->status, ['passed', 'failed', 'error']))
        <flux:heading size="lg">Performance Metrics</flux:heading>

        <div class="grid grid-cols-3 gap-4">
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">VUs Max</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->vus_max) }}</flux:heading>
            </div>
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Total Requests</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->requests_total) }}</flux:heading>
            </div>
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Requests / Second</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->requests_per_second) }}</flux:heading>
            </div>
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">P95 Duration</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->req_duration_p95_ms, 'ms') }}</flux:heading>
            </div>
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">P99 Duration</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->req_duration_p99_ms, 'ms') }}</flux:heading>
            </div>
            <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Error Rate</flux:text>
                <flux:heading size="xl">{{ $this->formatMetric($this->run->error_rate, '%') }}</flux:heading>
            </div>
        </div>

        @php $codeSummary = $this->responseCodeSummary; @endphp
        @if ($codeSummary && $codeSummary['total'] > 0)
            <flux:heading size="lg">Response Codes</flux:heading>
            <div class="grid grid-cols-4 gap-4">
                @foreach (['2xx' => ['Success', '#16a34a'], '3xx' => ['Redirect', '#ca8a04'], '4xx' => ['Client Error', '#ea580c'], '5xx' => ['Server Error', '#dc2626']] as $group => [$label, $color])
                    @php $g = $codeSummary['groups'][$group] ?? ['count' => 0, 'percent' => 0]; @endphp
                    <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                        <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">{{ $group }} {{ $label }}</flux:text>
                        <div class="flex items-baseline gap-x-2">
                            <flux:heading size="xl" style="color: {{ $color }}">{{ number_format($g['count']) }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500">{{ $g['percent'] }}%</flux:text>
                        </div>
                        <div class="w-full bg-zinc-800 rounded-full h-1.5 mt-1">
                            <div class="h-1.5 rounded-full" style="width: {{ max($g['percent'], 2) }}%; background-color: {{ $color }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($this->run->checks_total !== null)
            <flux:heading size="lg">Checks</flux:heading>
            <div class="grid grid-cols-3 gap-4">
                <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                    <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Total Checks</flux:text>
                    <flux:heading size="xl">{{ $this->run->checks_total }}</flux:heading>
                </div>
                <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                    <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Failed</flux:text>
                    <flux:heading size="xl" class="{{ ($this->run->checks_failed ?? 0) > 0 ? 'text-red-400' : 'text-green-500' }}">
                        {{ $this->run->checks_failed ?? 0 }}
                    </flux:heading>
                </div>
                <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                    <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Passed</flux:text>
                    <flux:heading size="xl" class="text-green-500">
                        {{ $this->run->checks_total - ($this->run->checks_failed ?? 0) }}
                    </flux:heading>
                </div>
            </div>
        @endif

        @if ($this->run->thresholds_summary !== null)
            <flux:heading size="lg">Thresholds</flux:heading>
            <div class="flex flex-col border divide-y rounded-lg">
                @foreach ($this->run->thresholds_summary as $threshold)
                    <div class="flex items-center justify-between p-3">
                        <flux:text>{{ $threshold['name'] }}</flux:text>
                        @if ($threshold['ok'])
                            <div class="flex items-center gap-x-1 text-green-500">
                                <flux:icon.check class="size-4" />
                                <flux:text class="text-sm">Passed</flux:text>
                            </div>
                        @else
                            <div class="flex items-center gap-x-1 text-red-400">
                                <flux:icon.x-mark class="size-4" />
                                <flux:text class="text-sm">Failed</flux:text>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- InfluxDB Time-Series Charts --}}
        @php $metrics = $this->influxMetrics; @endphp
        @if ($metrics)
            <div class="flex items-start justify-between gap-x-4">
                <flux:heading size="lg">Time-Series Charts</flux:heading>
                @if (! empty($this->endpoints))
                    <flux:select wire:model.live="selectedEndpoint" class="w-72" label="Endpoint">
                        <flux:select.option value="">All endpoints</flux:select.option>
                        @foreach ($this->endpoints as $endpoint)
                            <flux:select.option value="{{ $endpoint }}" title="{{ $endpoint }}">
                                {{ $this->endpointLabel($endpoint) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            @if ($this->selectedEndpoint)
                @php $summary = $this->selectedEndpointSummary; @endphp
                @if ($summary)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Total Requests</flux:text>
                            <flux:heading size="xl">{{ $this->formatMetric($summary['total_requests']) }}</flux:heading>
                        </div>
                        <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">P95 Duration</flux:text>
                            <flux:heading size="xl">{{ $this->formatMetric($summary['p95_ms'], 'ms') }}</flux:heading>
                        </div>
                        <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">P99 Duration</flux:text>
                            <flux:heading size="xl">{{ $this->formatMetric($summary['p99_ms'], 'ms') }}</flux:heading>
                        </div>
                        <div class="flex flex-col gap-y-1 p-4 border rounded-lg">
                            <flux:text class="text-zinc-500 text-xs uppercase tracking-wider">Error Rate</flux:text>
                            <flux:heading size="xl">{{ $this->formatMetric($summary['error_rate_percent'], '%') }}</flux:heading>
                        </div>
                    </div>
                @endif
            @endif

            <script type="application/json" data-run-metrics>
                @json($metrics)
            </script>

            <div
                class="grid grid-cols-1 lg:grid-cols-2 gap-6"
                @if ($this->isActive()) wire:poll.5s @endif
                x-data="{
                    charts: {},

                    init() {
                        this.buildAllCharts();
                    },

                    updated() {
                        this.buildAllCharts();
                    },

                    destroyAll() {
                        Object.values(this.charts).forEach(c => c.destroy());
                        this.charts = {};
                    },

                    getMetrics() {
                        const el = document.querySelector('[data-run-metrics]');
                        return el ? JSON.parse(el.textContent) : {};
                    },

                    buildAllCharts() {
                        this.destroyAll();
                        const m = this.getMetrics();

                        this.buildChart('vusChart', 'line', m.vus.labels, [{ label: 'VUs', data: m.vus.values, borderColor: '#a78bfa', backgroundColor: 'rgba(167, 139, 250, 0.1)', fill: true, tension: 0.3, pointRadius: 0 }], 'VUs');
                        this.buildChart('requestRateChart', 'line', m.request_rate.labels, [{ label: 'req/s', data: m.request_rate.values, borderColor: '#34d399', backgroundColor: 'rgba(52, 211, 153, 0.1)', fill: true, tension: 0.3, pointRadius: 0 }], 'req/s');
                        this.buildChart('responseTimeChart', 'line', m.response_time.labels, [{ label: 'p95', data: m.response_time.p95, borderColor: '#60a5fa', backgroundColor: 'rgba(96, 165, 250, 0.1)', fill: true, tension: 0.3, pointRadius: 0 }, { label: 'p99', data: m.response_time.p99, borderColor: '#f87171', backgroundColor: 'rgba(248, 113, 113, 0.05)', fill: true, tension: 0.3, pointRadius: 0 }], 'ms');
                        this.buildChart('errorRateChart', 'line', m.error_rate.labels, [{ label: '%', data: m.error_rate.values, borderColor: '#fbbf24', backgroundColor: 'rgba(251, 191, 36, 0.1)', fill: true, tension: 0.3, pointRadius: 0 }], '%');
                        this.buildChart('responseCodesChart', 'line', m.response_codes.labels, [
                            { label: '2xx', data: m.response_codes['2xx'], borderColor: '#16a34a', backgroundColor: 'rgba(22, 163, 74, 0.5)', fill: true, tension: 0.3, pointRadius: 0 },
                            { label: '3xx', data: m.response_codes['3xx'], borderColor: '#ca8a04', backgroundColor: 'rgba(202, 138, 4, 0.5)', fill: true, tension: 0.3, pointRadius: 0 },
                            { label: '4xx', data: m.response_codes['4xx'], borderColor: '#ea580c', backgroundColor: 'rgba(234, 88, 12, 0.5)', fill: true, tension: 0.3, pointRadius: 0 },
                            { label: '5xx', data: m.response_codes['5xx'], borderColor: '#dc2626', backgroundColor: 'rgba(220, 38, 38, 0.5)', fill: true, tension: 0.3, pointRadius: 0 },
                        ], 'Requests', true);
                        this.buildChart('checksChart', 'line', m.checks.labels, [{ label: 'Passed', data: m.checks.passed, borderColor: '#34d399', fill: false, tension: 0.3, pointRadius: 0 }, { label: 'Failed', data: m.checks.failed, borderColor: '#f87171', fill: false, tension: 0.3, pointRadius: 0 }], 'Count');
                        this.buildChart('dataTransferChart', 'line', m.data_transfer.labels, [{ label: 'Sent', data: m.data_transfer.sent, borderColor: '#60a5fa', fill: false, tension: 0.3, pointRadius: 0 }, { label: 'Received', data: m.data_transfer.received, borderColor: '#a78bfa', fill: false, tension: 0.3, pointRadius: 0 }], 'Bytes');
                    },

                    buildChart(ref, type, labels, datasets, unit, stacked = false) {
                        const canvas = this.$refs[ref];
                        if (!canvas || !labels || labels.length === 0) return;

                        this.charts[ref] = new Chart(canvas.getContext('2d'), {
                            type: type,
                            data: {
                                labels: labels,
                                datasets: datasets,
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                interaction: {
                                    intersect: false,
                                    mode: 'index',
                                },
                                scales: {
                                    y: {
                                        stacked,
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: unit,
                                        },
                                        grid: {
                                            color: 'rgba(255,255,255,0.06)',
                                        },
                                    },
                                    x: {
                                        grid: {
                                            display: false,
                                        },
                                    },
                                },
                                plugins: {
                                    legend: {
                                        display: datasets.length > 1,
                                        position: 'bottom',
                                        labels: {
                                            padding: 12,
                                            usePointStyle: true,
                                            color: '#a1a1aa',
                                        },
                                    },
                                },
                            },
                        });
                    },
                }"
            >
                @if ($this->selectedEndpoint === null || $this->selectedEndpoint === '')
                    <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                        <flux:heading size="sm" class="mb-3">Active VUs</flux:heading>
                        <div class="relative h-56">
                            <canvas x-ref="vusChart"></canvas>
                        </div>
                    </div>
                @endif

                <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                    <flux:heading size="sm" class="mb-3">Request Rate</flux:heading>
                    <div class="relative h-56">
                        <canvas x-ref="requestRateChart"></canvas>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                    <flux:heading size="sm" class="mb-3">Response Time</flux:heading>
                    <div class="relative h-56">
                        <canvas x-ref="responseTimeChart"></canvas>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                    <flux:heading size="sm" class="mb-3">Error Rate</flux:heading>
                    <div class="relative h-56">
                        <canvas x-ref="errorRateChart"></canvas>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                    <flux:heading size="sm" class="mb-3">Response Codes</flux:heading>
                    <div class="relative h-56">
                        <canvas x-ref="responseCodesChart"></canvas>
                    </div>
                </div>

                @if ($this->selectedEndpoint === null || $this->selectedEndpoint === '')
                    <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                        <flux:heading size="sm" class="mb-3">Checks</flux:heading>
                        <div class="relative h-56">
                            <canvas x-ref="checksChart"></canvas>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">
                        <flux:heading size="sm" class="mb-3">Data Transfer</flux:heading>
                        <div class="relative h-56">
                            <canvas x-ref="dataTransferChart"></canvas>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    @if ($this->run->status === 'queued' || $this->run->status === 'running')
        <div class="flex items-center justify-center p-10 text-zinc-500" wire:poll.5s>
            <div class="flex flex-col items-center gap-y-3">
                <flux:icon.clock class="size-10 animate-spin" />
                <flux:text>Waiting for test run to complete...</flux:text>
                <flux:button wire:click="cancelRun" variant="danger" size="sm">Cancel Run</flux:button>
            </div>
        </div>
    @endif

    <div class="flex gap-x-2">
        <flux:button wire:navigate :href="route('projects.runs', ['project' => $this->project])">
            Back to Runs
        </flux:button>
        <flux:button wire:navigate :href="route('projects.view-test-script', ['project' => $this->project, 'test' => $this->run->script->test, 'script' => $this->run->script])">
            View Script
        </flux:button>
    </div>

    <flux:modal name="run-insights" flyout class="md:w-2xl">
        <livewire:run-insight-panel :run="$run" />
    </flux:modal>
</div>
