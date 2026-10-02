@php
    $label = $label ?? 'Analyzing context and running steps';
@endphp

<div
    x-data="{
        ds: 0,
        timer: null,
        formatElapsed() {
            let total = (this.ds / 10);
            if (total < 60) return total.toFixed(1) + 's';
            let mins = Math.floor(total / 60);
            let secs = (total % 60).toFixed(1);
            return `${mins}m ${secs}s`;
        }
    }"
    x-init="timer = setInterval(() => ds++, 100)"
    x-on:unmount.window="clearInterval(timer)"
    role="status"
    class="inline-flex items-center gap-2 animate-fade"
>
    <x-spinner class="size-3.5 text-zinc-400" />

    <span
        class="bg-clip-text text-xs font-medium text-transparent animate-shimmer"
        style="background-image: linear-gradient(90deg, #71717a 35%, #d4d4d8 50%, #71717a 65%); background-size: 200% 100%;"
    >
        {{ $label }}
    </span>

    <span class="text-xs text-zinc-400 tabular-nums" x-text="formatElapsed()">0.0s</span>
</div>
