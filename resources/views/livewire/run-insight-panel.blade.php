<div class="flex flex-col h-full gap-y-5 font-sans pt-8">
    @php
        $insight = $this->insight;
        $isActive = $this->isActive();
    @endphp

    {{-- State 1: Generating / Queued --}}
    @if ($insight && in_array($insight->status, ['queued', 'generating'], true))
        <div class="flex flex-col gap-y-5" wire:poll.2s="refresh">
            {{-- Top Banner --}}
            <div class="flex items-center gap-x-3 p-4 rounded-xl border border-[#EDEDED] bg-white">
                <span class="size-2 rounded-full bg-zinc-900 animate-pulse shrink-0"></span>
                <div>
                    <flux:heading size="sm" class="font-medium text-zinc-900">Generating insights</flux:heading>
                    <flux:text class="text-xs text-[#919191] mt-0.5">Analyzing load test metrics from InfluxDB and the linked repository…</flux:text>
                </div>
            </div>

            {{-- Skeleton Placeholders --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @for ($i = 0; $i < 3; $i++)
                    <div class="p-4 rounded-xl border border-[#EDEDED] bg-white space-y-3">
                        <flux:skeleton.group animate="shimmer">
                            <flux:skeleton.line class="w-1/2 h-3" />
                            <flux:skeleton.line class="w-3/4 h-6" />
                        </flux:skeleton.group>
                    </div>
                @endfor
            </div>

            <div class="p-4 rounded-xl border border-[#EDEDED] bg-white space-y-3">
                <flux:skeleton.group animate="shimmer">
                    <flux:skeleton.line class="w-full h-4" />
                    <flux:skeleton.line class="w-5/6 h-4" />
                    <flux:skeleton.line class="w-2/3 h-4" />
                </flux:skeleton.group>
            </div>
        </div>

    {{-- State 2: Completed Report --}}
    @elseif ($insight && $insight->status === 'completed' && $insight->report)
        @php $report = $insight->report; @endphp

        {{-- Section Header --}}
        <div class="flex items-center justify-between pb-1">
            <div>
                <flux:heading size="lg" class="font-semibold text-zinc-900 tracking-tight">AI Insights & Diagnostics</flux:heading>
                <flux:text class="text-xs text-[#919191] mt-0.5">
                    Synthesized from InfluxDB telemetry · Updated {{ $insight->updated_at->diffForHumans() }}
                </flux:text>
            </div>

            <div class="flex items-center gap-1.5">
                <flux:button
                    wire:click="export"
                    wire:loading.attr="disabled"
                    wire:target="export"
                    variant="subtle"
                    size="sm"
                    icon="arrow-down-tray"
                    class="rounded-lg text-xs font-medium text-[#4A4A4A]"
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
                    class="rounded-lg bg-[#1A1A1A] hover:bg-black text-xs font-medium"
                >
                    <span wire:loading.remove wire:target="generate">Regenerate</span>
                    <span wire:loading wire:target="generate">Analyzing…</span>
                </flux:button>
            </div>
        </div>

        {{-- KPI Row (flat, no icon chips) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-[#EDEDED] rounded-xl border border-[#EDEDED] bg-white overflow-hidden">
            {{-- System Health --}}
            <div class="p-4">
                <span class="text-[11px] font-medium uppercase tracking-wide text-[#919191]">System health</span>
                <div class="mt-1.5 flex items-center gap-x-1.5">
                    <span class="size-1.5 rounded-full {{ match ($report['overall_health'] ?? 'acceptable') {
                        'healthy' => 'bg-emerald-500',
                        'acceptable' => 'bg-amber-500',
                        'poor' => 'bg-red-500',
                        default => 'bg-zinc-400',
                    } }}"></span>
                    <span class="text-lg font-semibold text-zinc-900 capitalize">
                        {{ $report['overall_health'] ?? 'Acceptable' }}
                    </span>
                </div>
            </div>

            {{-- Key Findings --}}
            <div class="p-4">
                <span class="text-[11px] font-medium uppercase tracking-wide text-[#919191]">Key findings</span>
                <div class="mt-1.5 flex items-baseline gap-x-1.5">
                    <span class="text-lg font-semibold text-zinc-900 font-mono">
                        {{ count($report['key_findings'] ?? []) }}
                    </span>
                    <span class="text-xs text-[#919191]">issues detected</span>
                </div>
            </div>

            {{-- Recommendations --}}
            <div class="p-4">
                <span class="text-[11px] font-medium uppercase tracking-wide text-[#919191]">Recommendations</span>
                <div class="mt-1.5 flex items-baseline gap-x-1.5">
                    <span class="text-lg font-semibold text-zinc-900 font-mono">
                        {{ count($report['recommendations'] ?? []) }}
                    </span>
                    <span class="text-xs text-[#919191]">optimizations</span>
                </div>
            </div>
        </div>

        {{-- Executive Summary --}}
        @if (! empty($report['summary']))
            <div class="p-4 rounded-xl border border-[#EDEDED] bg-white space-y-1.5">
                <span class="text-[11px] font-medium uppercase tracking-wide text-[#919191]">Executive summary</span>
                <p class="text-sm text-zinc-700 leading-relaxed">
                    {{ $report['summary'] }}
                </p>
            </div>
        @endif

        {{-- Bottleneck & Script Observations --}}
        @if (! empty($report['what_is_slow']) || ! empty($report['script_observations']))
            <div class="rounded-xl border border-[#EDEDED] bg-white divide-y divide-[#EDEDED]">
                @if (! empty($report['what_is_slow']))
                    <div class="p-4 space-y-1.5">
                        <flux:heading size="sm" class="font-medium text-zinc-900">Performance bottlenecks</flux:heading>
                        <p class="text-xs text-[#4A4A4A] leading-relaxed">
                            {{ $report['what_is_slow'] }}
                        </p>
                    </div>
                @endif

                @if (! empty($report['script_observations']))
                    <div class="p-4 space-y-1.5">
                        <flux:heading size="sm" class="font-medium text-zinc-900">Script observations</flux:heading>
                        <p class="text-xs text-[#4A4A4A] leading-relaxed">
                            {{ $report['script_observations'] }}
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Key Findings Section --}}
        @if (! empty($report['key_findings']))
            <div class="rounded-xl border border-[#EDEDED] bg-white overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#EDEDED]">
                    <flux:heading size="sm" class="font-medium text-zinc-900">Key diagnostic findings</flux:heading>
                    <span class="text-[11px] text-[#919191] font-mono">{{ count($report['key_findings']) }} items</span>
                </div>

                <div class="divide-y divide-[#EDEDED]">
                    @foreach ($report['key_findings'] as $finding)
                        @php
                            $severity = $finding['severity'] ?? 'medium';
                            $dotColor = match($severity) {
                                'critical' => 'bg-red-500',
                                'high' => 'bg-orange-500',
                                'medium' => 'bg-amber-500',
                                'low' => 'bg-zinc-400',
                                default => 'bg-zinc-400',
                            };
                            $textColor = match($severity) {
                                'critical' => 'text-red-600',
                                'high' => 'text-orange-600',
                                'medium' => 'text-amber-600',
                                'low' => 'text-[#919191]',
                                default => 'text-[#919191]',
                            };
                        @endphp
                        <div class="px-4 py-3 flex items-start gap-2.5">
                            <span class="mt-1.5 size-1.5 rounded-full shrink-0 {{ $dotColor }}"></span>
                            <div class="space-y-1 flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide {{ $textColor }}">
                                        {{ $severity }}
                                    </span>
                                    <span class="text-[13px] font-medium text-zinc-900">
                                        {{ $finding['title'] ?? '' }}
                                    </span>
                                </div>
                                <p class="text-xs text-[#919191] leading-relaxed">
                                    {{ $finding['detail'] ?? '' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recommendations Section --}}
        @if (! empty($report['recommendations']))
            <div class="rounded-xl border border-[#EDEDED] bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-[#EDEDED]">
                    <flux:heading size="sm" class="font-medium text-zinc-900">Recommended actions</flux:heading>
                </div>

                <div class="divide-y divide-[#EDEDED]">
                    @foreach ($report['recommendations'] as $recommendation)
                        <div class="px-4 py-3 space-y-1">
                            <div class="flex flex-col  gap-1">
                                <span class="text-[13px] font-medium text-zinc-900">
                                    {{ $recommendation['title'] ?? '' }}
                                </span>

                                @if (! empty($recommendation['impact']))
                                    <span class="shrink-0 text-[11px] font-medium text-emerald-600">
                                        {{ $recommendation['impact'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs text-[#919191] leading-relaxed">
                                {{ $recommendation['detail'] ?? '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    {{-- State 3: Failed Generation --}}
    @elseif ($insight && $insight->status === 'failed')
        <div class="p-5 rounded-xl border border-[#EDEDED] bg-white space-y-3">
            <div>
                <flux:heading size="sm" class="font-medium text-zinc-900">Analysis failed</flux:heading>
                <flux:text class="text-xs text-[#919191]">An error occurred while compiling the performance report.</flux:text>
            </div>

            <div class="p-3 rounded-lg bg-[#F1F1F1] font-mono text-xs text-[#4A4A4A]">
                {{ $insight->error ?: 'An unknown error occurred.' }}
            </div>

            <flux:button wire:click="generate" variant="primary" size="sm" icon="arrow-path" class="bg-[#1A1A1A] hover:bg-black rounded-lg">
                Retry analysis
            </flux:button>
        </div>

    {{-- State 4: Initial / Idle State --}}
    @else
        <div class="p-8 rounded-xl border border-[#EDEDED] bg-white text-center flex flex-col items-center justify-center gap-y-3">
            <div class="max-w-md space-y-1">
                <flux:heading size="md" class="font-semibold text-zinc-900">AI load test diagnostics</flux:heading>
                <flux:text class="text-xs text-[#919191] leading-relaxed">
                    Generate a diagnostic report that identifies latency spikes, memory bottlenecks, and script issues using InfluxDB metrics and source code context.
                </flux:text>
            </div>

            @if ($isActive)
                <div class="inline-flex items-center gap-x-1.5 text-xs font-medium text-[#4A4A4A]">
                    <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Load test run in progress — diagnostics available on completion
                </div>
            @endif

            @if (! $this->insightsReady)
                <div class="w-full max-w-md p-3.5 rounded-lg border border-amber-200 bg-amber-50 text-left space-y-1.5">
                    <flux:heading size="sm" class="font-medium text-amber-900">
                        {{ $this->insightsSelection ? 'Insights provider not connected' : 'No insights model selected' }}
                    </flux:heading>
                    <flux:text class="text-xs text-amber-700 leading-relaxed">
                        {{ $this->insightsSelection
                            ? 'The selected provider is no longer connected. Reconnect it or pick another model in Settings.'
                            : 'Choose which AI model should generate insight reports in Settings before running your first analysis.' }}
                    </flux:text>
                    @if ($this->project)
                        <flux:button
                            wire:navigate
                            :href="route('settings.insights-model')"
                            variant="primary"
                            size="sm"
                            icon="cog-6-tooth"
                            class="rounded-lg mt-1"
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
                size="base"
                :disabled="$isActive || ! $this->insightsReady"
                class="rounded-lg bg-[#1A1A1A] hover:bg-black px-5"
            >
                <span wire:loading.remove wire:target="generate">{{ $isActive ? 'Run in progress' : 'Generate insights' }}</span>
                <span wire:loading wire:target="generate">Initializing…</span>
            </flux:button>

            @if ($this->insightsReady && $this->insightsSelection)
                <flux:text class="text-[11px] text-[#919191]">
                    Using {{ $this->insightsSelection['model'] }} — change anytime in
                    @if ($this->project)
                        <a wire:navigate href="{{ route('settings.insights-model') }}" class="underline underline-offset-2 hover:text-[#4A4A4A]">Settings</a>
                    @else
                        Settings
                    @endif
                    .
                </flux:text>
            @endif
        </div>
    @endif
</div>
