<div class="flex flex-col h-full gap-y-5 font-sans">
    @php
        $insight = $this->insight;
        $isActive = $this->isActive();
    @endphp

    {{-- State 1: Generating / Queued --}}
    @if ($insight && in_array($insight->status, ['queued', 'generating'], true))
        <div class="flex flex-col gap-y-5" wire:poll.2s="refresh">
            {{-- Top Banner --}}
            <div class="flex items-center justify-between p-5 rounded-xl border border-[#EDEDED] bg-white">
                <div class="flex items-center gap-x-4">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-[#1A1A1A] text-white">
                        <flux:icon.sparkles class="size-4 animate-spin" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="md" class="font-semibold text-zinc-900">Generating AI Insights</flux:heading>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-[#F1F1F1] text-[#4A4A4A]">
                                In Progress
                            </span>
                        </div>
                        <flux:text class="text-xs text-[#919191] mt-0.5">Analyzing load test metrics from InfluxDB and linked source code repository...</flux:text>
                    </div>
                </div>
            </div>

            {{-- Skeleton Placeholders --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @for ($i = 0; $i < 3; $i++)
                    <div class="p-5 rounded-xl border border-[#EDEDED] bg-white space-y-3">
                        <flux:skeleton.group animate="shimmer">
                            <flux:skeleton.line class="w-1/2 h-3" />
                            <flux:skeleton.line class="w-3/4 h-6" />
                        </flux:skeleton.group>
                    </div>
                @endfor
            </div>

            <div class="p-5 rounded-xl border border-[#EDEDED] bg-white space-y-4">
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

        {{-- Section Header (Atlas-style: greeting + context line + actions row) --}}
        <div class="flex items-center justify-between pb-1">
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 tracking-tight">AI Insights & Diagnostics</flux:heading>
                <flux:text class="text-xs text-[#919191] mt-0.5">
                    Synthesized from InfluxDB telemetry &bull; Updated {{ $insight->updated_at->diffForHumans() }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="arrow-down-tray"
                    class="rounded-lg border border-[#EDEDED] bg-white text-xs font-medium text-[#4A4A4A]"
                >
                    Export
                </flux:button>

                <flux:button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="arrow-path"
                    class="rounded-lg bg-[#1A1A1A] hover:bg-black text-xs font-medium"
                >
                    <span wire:loading.remove wire:target="generate">Regenerate Analysis</span>
                    <span wire:loading wire:target="generate">Analyzing...</span>
                </flux:button>
            </div>
        </div>

        {{-- KPI Cards Row (Atlas-style: icon chip top-right, big number, trend row) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- System Health --}}
            <div class="p-4 rounded-xl border border-[#EDEDED] bg-white flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[#919191]">System Health</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-[#F1F1F1] text-[#4A4A4A]">
                        <flux:icon.heart class="size-4" />
                    </div>
                </div>

                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-zinc-900 capitalize">
                        {{ $report['overall_health'] ?? 'Acceptable' }}
                    </span>
                    <span class="inline-flex items-center gap-x-1 text-xs font-semibold {{ match ($report['overall_health'] ?? 'acceptable') {
                        'healthy' => 'text-emerald-600',
                        'acceptable' => 'text-amber-600',
                        'poor' => 'text-red-600',
                        default => 'text-[#919191]',
                    } }}">
                        <span class="size-1.5 rounded-full {{ match ($report['overall_health'] ?? 'acceptable') {
                            'healthy' => 'bg-emerald-500',
                            'acceptable' => 'bg-amber-500',
                            'poor' => 'bg-red-500',
                            default => 'bg-zinc-400',
                        } }}"></span>
                        Status
                    </span>
                </div>
            </div>

            {{-- Key Findings --}}
            <div class="p-4 rounded-xl border border-[#EDEDED] bg-white flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[#919191]">Key Findings</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-[#F1F1F1] text-[#4A4A4A]">
                        <flux:icon.exclamation-triangle class="size-4" />
                    </div>
                </div>

                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-zinc-900 font-mono">
                        {{ count($report['key_findings'] ?? []) }}
                    </span>
                    <span class="text-xs text-[#919191] font-medium">Issues detected</span>
                </div>
            </div>

            {{-- Recommendations --}}
            <div class="p-4 rounded-xl border border-[#EDEDED] bg-white flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[#919191]">Recommendations</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-[#F1F1F1] text-[#4A4A4A]">
                        <flux:icon.light-bulb class="size-4" />
                    </div>
                </div>

                <div class="mt-4 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-zinc-900 font-mono">
                        {{ count($report['recommendations'] ?? []) }}
                    </span>
                    <span class="text-xs text-[#919191] font-medium">Optimizations</span>
                </div>
            </div>
        </div>

        {{-- Executive Summary --}}
        @if (! empty($report['summary']))
            <div class="p-5 rounded-xl border border-[#EDEDED] bg-white space-y-2">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-[#919191]">Executive Summary</span>
                <p class="text-sm text-zinc-700 leading-relaxed">
                    {{ $report['summary'] }}
                </p>
            </div>
        @endif

        {{-- Bottleneck & Script Observations (stacked, single panel) --}}
        @if (! empty($report['what_is_slow']) || ! empty($report['script_observations']))
            <div class="rounded-xl border border-[#EDEDED] bg-white divide-y divide-[#EDEDED]">
                @if (! empty($report['what_is_slow']))
                    <div class="p-5 space-y-2.5">
                        <div class="flex items-center gap-x-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg bg-[#F1F1F1] text-[#4A4A4A]">
                                <flux:icon.clock class="size-4" />
                            </div>
                            <flux:heading size="sm" class="font-semibold text-zinc-900">Performance Bottlenecks</flux:heading>
                        </div>
                        <p class="text-xs text-[#4A4A4A] leading-relaxed">
                            {{ $report['what_is_slow'] }}
                        </p>
                    </div>
                @endif

                @if (! empty($report['script_observations']))
                    <div class="p-5 space-y-2.5">
                        <div class="flex items-center gap-x-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg bg-[#F1F1F1] text-[#4A4A4A]">
                                <flux:icon.code-bracket class="size-4" />
                            </div>
                            <flux:heading size="sm" class="font-semibold text-zinc-900">Script Observations</flux:heading>
                        </div>
                        <p class="text-xs text-[#4A4A4A] leading-relaxed">
                            {{ $report['script_observations'] }}
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Key Findings Section (Atlas-style: table-like rows, status pill on the left) --}}
        @if (! empty($report['key_findings']))
            <div class="rounded-xl border border-[#EDEDED] bg-white overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 bg-[#F1F1F1]/60 border-b border-[#EDEDED]">
                    <div class="flex items-center gap-x-2">
                        <flux:icon.magnifying-glass class="size-3.5 text-[#4A4A4A]" />
                        <flux:heading size="sm" class="font-semibold text-zinc-900">Key Diagnostic Findings</flux:heading>
                    </div>
                    <span class="text-xs text-[#919191] font-mono">{{ count($report['key_findings']) }} items</span>
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
                        <div class="px-5 py-3.5 flex items-start gap-3">
                            <span class="mt-1.5 size-1.5 rounded-full shrink-0 {{ $dotColor }}"></span>
                            <div class="space-y-1 flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $textColor }}">
                                        {{ $severity }}
                                    </span>
                                    <flux:heading size="sm" class="font-medium text-zinc-900">
                                        {{ $finding['title'] ?? '' }}
                                    </flux:heading>
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
                <div class="flex items-center gap-x-2 px-5 py-4 bg-[#F1F1F1]/60 border-b border-[#EDEDED]">
                    <flux:icon.wrench-screwdriver class="size-3.5 text-[#4A4A4A]" />
                    <flux:heading size="sm" class="font-semibold text-zinc-900">Recommended Actions</flux:heading>
                </div>

                <div class="divide-y divide-[#EDEDED]">
                    @foreach ($report['recommendations'] as $recommendation)
                        <div class="px-5 py-4 hover:bg-[#F1F1F1]/40 transition space-y-2">
                            <div class="flex flex-col items-start justify-between gap-4">
                                <flux:heading size="sm" class="font-semibold text-zinc-900">
                                    {{ $recommendation['title'] ?? '' }}
                                </flux:heading>

                                @if (! empty($recommendation['impact']))
                                    <span class="shrink-0 inline-flex items-center gap-x-1 text-xs font-semibold text-emerald-600">
                                        <flux:icon.arrow-trending-up class="size-3" />
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
        <div class="p-6 rounded-xl border border-[#EDEDED] bg-white space-y-4">
            <div class="flex items-center gap-x-3">
                <div class="flex size-9 items-center justify-center rounded-lg bg-red-50 text-red-600">
                    <flux:icon.exclamation-circle class="size-4" />
                </div>
                <div>
                    <flux:heading size="md" class="font-semibold text-zinc-900">Analysis Failed</flux:heading>
                    <flux:text class="text-xs text-[#919191]">An error occurred while compiling the performance report.</flux:text>
                </div>
            </div>

            <div class="p-3 rounded-lg bg-[#F1F1F1] border border-[#EDEDED] font-mono text-xs text-[#4A4A4A]">
                {{ $insight->error ?: 'An unknown error occurred.' }}
            </div>

            <div>
                <flux:button wire:click="generate" variant="primary" size="sm" icon="arrow-path" class="bg-[#1A1A1A] hover:bg-black text-white rounded-lg">
                    Retry Analysis
                </flux:button>
            </div>
        </div>

    {{-- State 4: Initial / Idle State --}}
    @else
        <div class="p-10 rounded-xl border border-[#EDEDED] bg-white text-center flex flex-col items-center justify-center space-y-4">
            <div class="flex size-12 items-center justify-center rounded-xl bg-[#F1F1F1] text-[#4A4A4A]">
                <flux:icon.sparkles class="size-6" />
            </div>

            <div class="max-w-md space-y-1">
                <flux:heading size="lg" class="font-bold text-zinc-900">AI Load Test Diagnostics</flux:heading>
                <flux:text class="text-xs text-[#919191] leading-relaxed">
                    Generate an automated diagnostic report that identifies latency spikes, memory bottlenecks, and script issues using InfluxDB metrics and source code context.
                </flux:text>
            </div>

            @if ($isActive)
                <div class="inline-flex items-center gap-x-2 px-3 py-1.5 rounded-full bg-[#F1F1F1] text-xs font-medium text-[#4A4A4A]">
                    <flux:icon.clock class="size-3.5 animate-spin" />
                    Load test run in progress. Diagnostics available upon completion.
                </div>
            @endif

            @if (! $this->insightsReady)
                <div class="w-full max-w-md p-4 rounded-xl border border-amber-200 bg-amber-50 text-left flex items-start gap-x-3">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <flux:icon.exclamation-triangle class="size-4" />
                    </div>
                    <div class="space-y-1.5">
                        <flux:heading size="sm" class="font-semibold text-amber-900">
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
                                :href="route('projects.settings', ['project' => $this->project])"
                                variant="primary"
                                size="sm"
                                icon="cog-6-tooth"
                                class="rounded-lg mt-1"
                            >
                                Go to Settings
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

            <flux:button
                wire:click="generate"
                wire:loading.attr="disabled"
                variant="primary"
                size="base"
                icon="sparkles"
                :disabled="$isActive || ! $this->insightsReady"
                class="rounded-lg bg-[#1A1A1A] hover:bg-black px-5"
            >
                <span wire:loading.remove wire:target="generate">{{ $isActive ? 'Run In Progress' : 'Generate AI Insights' }}</span>
                <span wire:loading wire:target="generate">Initializing Agent...</span>
            </flux:button>

            @if ($this->insightsReady && $this->insightsSelection)
                <flux:text class="text-[11px] text-[#919191]">
                    Using {{ $this->insightsSelection['model'] }} — change anytime in
                    @if ($this->project)
                        <a wire:navigate href="{{ route('projects.settings', ['project' => $this->project]) }}" class="underline underline-offset-2 hover:text-[#4A4A4A]">Settings</a>
                    @else
                        Settings
                    @endif
                    .
                </flux:text>
            @endif
        </div>
    @endif
</div>
