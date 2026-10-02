@props([
    'status' => null,
    'label' => null,
])

@php
    $status = strtolower((string) $status);

    [$defaultLabel, $tone, $icon] = match ($status) {
        'passed', 'completed', 'success', 'succeeded', 'synced', 'connected', 'active', 'ready' => [ucfirst($status), 'success', 'check'],
        'running', 'syncing', 'generating', 'in_progress', 'processing' => [ucfirst(str_replace('_', ' ', $status)), 'info', 'spinner'],
        'queued', 'pending', 'waiting' => [ucfirst($status), 'neutral', 'clock'],
        'failed', 'error', 'timeout', 'errored' => [$status === 'timeout' ? 'Timed out' : ucfirst($status), 'danger', 'x-mark'],
        'warning', 'degraded' => [ucfirst($status), 'warning', 'exclamation-triangle'],
        default => [$status === '' ? 'Unknown' : ucfirst(str_replace('_', ' ', $status)), 'neutral', null],
    };

    $toneClasses = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'info' => 'bg-brand-50 text-brand-700 ring-brand-600/15',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/15',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        default => 'bg-zinc-100 text-zinc-600 ring-zinc-500/15',
    };
@endphp

<span {{ $attributes->class(['inline-flex h-5.5 items-center gap-1 rounded-md px-1.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset', $toneClasses]) }} data-status="{{ $status }}">
    @if ($icon === 'spinner')
        <x-spinner class="size-3" />
    @elseif ($icon)
        <flux:icon :name="$icon" variant="micro" class="size-3 opacity-80" />
    @endif
    {{ $label ?? $defaultLabel }}
</span>
