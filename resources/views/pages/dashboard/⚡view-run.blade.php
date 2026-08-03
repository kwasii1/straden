<?php

use App\Models\Project;
use App\Models\Run;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public Run $run;

    public function mount(): void
    {
        $this->run->load('script.test');
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
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Run Detail</flux:heading>
        <flux:text>View test run results and performance metrics.</flux:text>
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

    @if ($this->run->status === 'passed' || $this->run->status === 'failed')
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
    @endif

    @if ($this->run->status === 'queued' || $this->run->status === 'running')
        <div class="flex items-center justify-center p-10 text-zinc-500">
            <div class="flex flex-col items-center gap-y-3">
                <flux:icon.clock class="size-10 animate-spin" />
                <flux:text>Waiting for test run to complete...</flux:text>
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
</div>
