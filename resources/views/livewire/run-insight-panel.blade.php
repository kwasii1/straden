<div class="flex h-full flex-col gap-6 pt-8 font-sans">
    @php
        $insight = $this->insight;
        $isActive = $this->isActive();
    @endphp

    {{-- State 1: Generating / Queued --}}
    @if ($insight && in_array($insight->status, ['queued', 'generating'], true))
        <div class="flex flex-col gap-4" wire:poll.2s="refresh">
            <div class="ui-panel flex items-start gap-3 p-4">
                <x-spinner class="mt-0.5 size-4 shrink-0 text-zinc-500" />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-zinc-900">Generating insights</p>
                    <p class="mt-0.5 text-sm text-zinc-500">Analyzing load test metrics from InfluxDB and the linked repository…</p>
                </div>
            </div>

            {{-- Skeleton placeholders --}}
            <div class="ui-panel grid grid-cols-1 gap-px overflow-hidden bg-zinc-200 md:grid-cols-3 [&>*]:bg-white">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex flex-col gap-2.5 px-4 py-3.5">
                        <flux:skeleton.line class="h-3 w-1/2" />
                        <flux:skeleton.line class="h-6 w-3/4" />
                    </div>
                @endfor
            </div>

            <div class="ui-panel flex flex-col gap-2.5 p-4">
                <flux:skeleton.line class="h-3.5 w-full" />
                <flux:skeleton.line class="h-3.5 w-5/6" />
                <flux:skeleton.line class="h-3.5 w-2/3" />
            </div>
        </div>

    {{-- State 2: Completed Report --}}
    @elseif ($insight && $insight->status === 'completed' && $insight->report)
        @php
            $report = $insight->report;
            $health = $report['overall_health'] ?? 'acceptable';
            [$healthStatus, $healthLabel] = match ($health) {
                'healthy' => ['passed', 'Healthy'],
                'acceptable' => ['warning', 'Acceptable'],
                'poor' => ['failed', 'Poor'],
                default => [$health, ucfirst((string) $health)],
            };
        @endphp

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="lg">AI insights</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">
                    Synthesized from InfluxDB telemetry, updated {{ $insight->updated_at->diffForHumans() }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <flux:button
                    wire:click="export"
                    wire:loading.attr="disabled"
                    wire:target="export"
                    variant="ghost"
                    size="sm"
                    icon="arrow-down-tray"
                >
                    <span wire:loading.remove wire:target="export">Export</span>
                    <span wire:loading wire:target="export">Exporting…</span>
                </flux:button>

                <flux:button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="arrow-path"
                >
                    <span wire:loading.remove wire:target="generate">Regenerate</span>
                    <span wire:loading wire:target="generate">Analyzing…</span>
                </flux:button>
            </div>
        </div>

        {{-- Headline numbers --}}
        <div class="ui-panel grid grid-cols-1 gap-px overflow-hidden bg-zinc-200 sm:grid-cols-3 [&>*]:bg-white">
            <div class="min-w-0 px-4 py-3.5">
                <p class="truncate text-sm text-zinc-500">System health</p>
                <div class="mt-2"><x-status-badge :status="$healthStatus" :label="$healthLabel" /></div>
            </div>
            <x-stat label="Key findings" :value="count($report['key_findings'] ?? [])" hint="Issues detected" />
            <x-stat label="Recommendations" :value="count($report['recommendations'] ?? [])" hint="Suggested optimizations" />
        </div>

        {{-- Executive summary --}}
        @if (! empty($report['summary']))
            <section class="ui-panel">
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">Executive summary</h2>
                </header>
                <p class="p-4 text-sm leading-relaxed text-zinc-700">{{ $report['summary'] }}</p>
            </section>
        @endif

        {{-- Bottlenecks & script observations --}}
        @if (! empty($report['what_is_slow']) || ! empty($report['script_observations']))
            <section class="ui-panel divide-y divide-zinc-200">
                @if (! empty($report['what_is_slow']))
                    <div class="flex flex-col gap-1.5 p-4">
                        <h2 class="ui-panel-title">Performance bottlenecks</h2>
                        <p class="text-sm leading-relaxed text-zinc-600">{{ $report['what_is_slow'] }}</p>
                    </div>
                @endif

                @if (! empty($report['script_observations']))
                    <div class="flex flex-col gap-1.5 p-4">
                        <h2 class="ui-panel-title">Script observations</h2>
                        <p class="text-sm leading-relaxed text-zinc-600">{{ $report['script_observations'] }}</p>
                    </div>
                @endif
            </section>
        @endif

        {{-- Key findings --}}
        @if (! empty($report['key_findings']))
            <section class="ui-panel overflow-hidden">
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">Key diagnostic findings</h2>
                    <span class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count item|:count items', count($report['key_findings'])) }}</span>
                </header>

                <div class="ui-list">
                    @foreach ($report['key_findings'] as $finding)
                        @php
                            $severity = $finding['severity'] ?? 'medium';
                            $severityClasses = match ($severity) {
                                'critical', 'high' => 'bg-red-50 text-red-700 ring-red-600/15',
                                'medium' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                                default => 'bg-zinc-100 text-zinc-600 ring-zinc-500/15',
                            };
                        @endphp
                        <div class="ui-list-row items-start">
                            <span class="inline-flex h-5.5 w-16 shrink-0 items-center justify-center rounded-md text-xs font-medium ring-1 ring-inset {{ $severityClasses }}">
                                {{ ucfirst($severity) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900">{{ $finding['title'] ?? '' }}</p>
                                <p class="mt-0.5 text-sm leading-relaxed text-zinc-600">{{ $finding['detail'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Recommendations --}}
        @if (! empty($report['recommendations']))
            <section class="ui-panel overflow-hidden">
                <header class="ui-panel-header">
                    <h2 class="ui-panel-title">Recommended actions</h2>
                    <span class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count item|:count items', count($report['recommendations'])) }}</span>
                </header>

                <div class="ui-list">
                    @foreach ($report['recommendations'] as $recommendation)
                        <div class="flex flex-col gap-1 px-4 py-3">
                            <p class="text-sm font-medium text-zinc-900">{{ $recommendation['title'] ?? '' }}</p>
                            @if (! empty($recommendation['impact']))
                                <p class="text-xs font-medium text-emerald-700">{{ $recommendation['impact'] }}</p>
                            @endif
                            <p class="text-sm leading-relaxed text-zinc-600">{{ $recommendation['detail'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    {{-- State 3: Failed Generation --}}
    @elseif ($insight && $insight->status === 'failed')
        <div class="ui-panel flex flex-col items-start gap-4 p-4">
            <div>
                <p class="text-sm font-medium text-zinc-900">Analysis failed</p>
                <p class="mt-0.5 text-sm text-zinc-500">The performance report couldn't be compiled. The error below says why; fix it, then retry.</p>
            </div>

            <div class="ui-inset w-full p-3 font-mono text-xs break-words text-zinc-700">
                {{ $insight->error ?: 'An unknown error occurred.' }}
            </div>

            <flux:button wire:click="generate" variant="primary" size="sm" icon="arrow-path">
                Retry analysis
            </flux:button>
        </div>

    {{-- State 4: Initial / Idle State --}}
    @else
        <div class="ui-panel flex flex-col items-center justify-center gap-4 px-6 py-10 text-center">
            <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                <flux:icon.sparkles class="size-4.5" />
            </div>

            <div class="max-w-md">
                <p class="text-sm font-medium text-zinc-900">AI load test diagnostics</p>
                <p class="mt-1 text-sm text-zinc-500">
                    Generate a diagnostic report that identifies latency spikes, memory bottlenecks, and script issues using InfluxDB metrics and source code context.
                </p>
            </div>

            @if ($isActive)
                <div class="inline-flex items-center gap-2 text-xs text-zinc-600">
                    <x-spinner class="size-3.5 text-zinc-500" />
                    Load test still running. Diagnostics are available once it finishes.
                </div>
            @endif

            @if (! $this->insightsReady)
                <div class="w-full max-w-md rounded-lg border border-amber-200 bg-amber-50 p-3.5 text-left">
                    <p class="text-sm font-medium text-amber-900">
                        {{ $this->insightsSelection ? 'Insights provider not connected' : 'No insights model selected' }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-800">
                        {{ $this->insightsSelection
                            ? 'The selected provider is no longer connected. Reconnect it or pick another model in Settings.'
                            : 'Choose which AI model should generate insight reports in Settings before running your first analysis.' }}
                    </p>
                    @if (! auth()->user()?->isAdmin())
                        <p class="mt-1 text-xs text-amber-800">Ask a Straden admin to configure it.</p>
                    @elseif ($this->project)
                        <flux:button
                            wire:navigate
                            :href="route('settings.insights-model')"
                            size="sm"
                            icon="cog-6-tooth"
                            class="mt-3"
                        >
                            Go to Settings
                        </flux:button>
                    @endif
                </div>
            @endif

            <flux:button
                wire:click="generate"
                wire:loading.attr="disabled"
                variant="primary"
                :disabled="$isActive || ! $this->insightsReady"
            >
                <span wire:loading.remove wire:target="generate">{{ $isActive ? 'Run in progress' : 'Generate insights' }}</span>
                <span wire:loading wire:target="generate">Initializing…</span>
            </flux:button>

            @if ($this->insightsReady && $this->insightsSelection)
                <p class="text-xs text-zinc-500">
                    Using {{ $this->insightsSelection['model'] }}. Change it anytime in
                    @if ($this->project && auth()->user()?->isAdmin())
                        <a wire:navigate href="{{ route('settings.insights-model') }}" class="ui-link font-normal text-zinc-700">Settings</a>.
                    @else
                        Settings.
                    @endif
                </p>
            @endif
        </div>
    @endif
</div>
