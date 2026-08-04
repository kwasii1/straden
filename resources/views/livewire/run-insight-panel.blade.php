<div class="flex flex-col h-full">
    @php
        $insight = $this->insight;
        $isActive = $this->isActive();
    @endphp

    @if ($insight && in_array($insight->status, ['queued', 'generating'], true))
        <div class="flex flex-col gap-y-4" wire:poll.2s="refresh">
            <flux:heading size="lg">Generating AI Insights</flux:heading>
            <flux:text class="text-zinc-400">
                Analyzing run metrics from InfluxDB and the linked repository. This usually takes less than a minute.
            </flux:text>

            <div class="flex flex-col gap-y-3">
                <flux:skeleton.group animate="shimmer">
                    <flux:skeleton.line class="w-full" />
                    <flux:skeleton.line class="w-3/4" />
                    <flux:skeleton.line class="w-5/6" />
                </flux:skeleton.group>
            </div>

            <div class="flex flex-col gap-y-3">
                <flux:skeleton class="h-24 w-full" />
                <flux:skeleton class="h-24 w-full" />
            </div>
        </div>
    @elseif ($insight && $insight->status === 'completed' && $insight->report)
        @php $report = $insight->report; @endphp

        <div class="flex items-start justify-between gap-x-4">
            <div class="flex flex-col gap-y-1">
                <flux:heading size="lg">AI Insights</flux:heading>
                <flux:text class="text-sm text-zinc-500">Generated {{ $insight->updated_at->diffForHumans() }}</flux:text>
            </div>
            <flux:button wire:click="generate" variant="subtle" size="sm" icon="arrow-path">
                Regenerate
            </flux:button>
        </div>

        <flux:separator class="my-4" />

        <div class="flex flex-col gap-y-5">
            <div class="flex items-center gap-x-3">
                <flux:badge size="sm" color="{{ match ($report['overall_health'] ?? 'acceptable') {
                    'healthy' => 'green',
                    'acceptable' => 'amber',
                    'poor' => 'red',
                    default => 'zinc',
                } }}">
                    {{ ucfirst($report['overall_health'] ?? 'acceptable') }}
                </flux:badge>
                <flux:text class="text-sm">{{ $report['summary'] ?? '' }}</flux:text>
            </div>

            @if (! empty($report['what_is_slow']))
                <div class="flex flex-col gap-y-1">
                    <flux:heading size="sm">What's Slow</flux:heading>
                    <flux:text class="text-zinc-300">{{ $report['what_is_slow'] }}</flux:text>
                </div>
            @endif

            @if (! empty($report['script_observations']))
                <div class="flex flex-col gap-y-1">
                    <flux:heading size="sm">Script Observations</flux:heading>
                    <flux:text class="text-zinc-300">{{ $report['script_observations'] }}</flux:text>
                </div>
            @endif

            @if (! empty($report['key_findings']))
                <div class="flex flex-col gap-y-2">
                    <flux:heading size="sm">Key Findings</flux:heading>
                    @foreach ($report['key_findings'] as $finding)
                        <div class="flex gap-x-3 p-3 border rounded-lg">
                            <flux:badge
                                size="sm"
                                class="shrink-0 mt-0.5"
                                color="{{ match ($finding['severity'] ?? 'medium') {
                                    'critical' => 'red',
                                    'high' => 'orange',
                                    'medium' => 'amber',
                                    'low' => 'zinc',
                                    default => 'zinc',
                                } }}"
                            >
                                {{ ucfirst($finding['severity'] ?? 'medium') }}
                            </flux:badge>
                            <div class="flex flex-col gap-y-1">
                                <flux:heading size="sm">{{ $finding['title'] ?? '' }}</flux:heading>
                                <flux:text class="text-sm text-zinc-400">{{ $finding['detail'] ?? '' }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($report['recommendations']))
                <div class="flex flex-col gap-y-2">
                    <flux:heading size="sm">Recommendations</flux:heading>
                    @foreach ($report['recommendations'] as $recommendation)
                        <div class="flex flex-col gap-y-1 p-3 border rounded-lg">
                            <div class="flex items-center justify-between gap-x-3">
                                <flux:heading size="sm">{{ $recommendation['title'] ?? '' }}</flux:heading>
                            </div>
                            <flux:text class="text-sm text-zinc-400">{{ $recommendation['detail'] ?? '' }}</flux:text>
                            @if (! empty($recommendation['impact']))
                                <flux:text class="text-xs text-emerald-400">Impact: {{ $recommendation['impact'] }}</flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($insight && $insight->status === 'failed')
        <div class="flex flex-col gap-y-4">
            <flux:heading size="lg">AI Insights</flux:heading>
            <div class="flex flex-col gap-y-2 p-4 border border-red-800 bg-red-950/30 rounded-lg">
                <flux:heading size="sm" class="text-red-400">Generation failed</flux:heading>
                <flux:text class="text-sm text-red-300">{{ $insight->error ?: 'An unknown error occurred.' }}</flux:text>
            </div>
            <div>
                <flux:button wire:click="generate" variant="primary" icon="arrow-path">Retry</flux:button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-y-4">
            <flux:heading size="lg">AI Insights</flux:heading>

            @if ($isActive)
                <flux:callout variant="warning" icon="clock" heading="Run still in progress">
                    This run has not finished yet, so there are no metrics to analyze. Check back after the run completes.
                </flux:callout>
            @else
                <flux:text class="text-zinc-400">
                    Generate an AI-powered report that explains what was slow in this load test, why it happened, and how to improve performance. The agent reads the run's InfluxDB metrics and the linked repository as context.
                </flux:text>
            @endif

            <div>
                <flux:button
                    wire:click="generate"
                    variant="primary"
                    icon="sparkles"
                    :disabled="$isActive"
                >
                    {{ $isActive ? 'Run in progress' : 'Generate AI Insights' }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
