@php
    $variant = $variant ?? 'Drive'; // Options: 'Drive', 'Dots', 'Orbit'
    $label = $label ?? 'Agent analyzing context & executing steps';

    // Patterns matrix calculation
    $chevron = array_map(function($i) {
        $r = floor($i / 3);
        $c = $i % 3;
        return ($c + abs($r - 1)) * 90;
    }, range(0, 8));

    $orbitOrder = [0, 1, 2, 5, 8, 7, 6, 3];
    $orbit = array_map(function($i) use ($orbitOrder) {
        $k = array_search($i, $orbitOrder);
        return $k === false ? null : $k * 110;
    }, range(0, 8));

    $patterns = [
        'Drive' => ['delays' => $chevron, 'dur' => 650, 'round' => false],
        'Dots'  => ['delays' => $chevron, 'dur' => 650, 'round' => true],
        'Orbit' => ['delays' => $orbit,   'dur' => 950, 'round' => false],
    ];

    $config = $patterns[$variant] ?? $patterns['Drive'];
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
    class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-zinc-100/90 dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/60 shadow-xs"
>
    {{-- 3x3 Pixel Grid Loader --}}
    <span aria-hidden="true" class="grid grid-cols-[repeat(3,4px)] gap-[1.5px] shrink-0">
        @foreach ($config['delays'] as $delay)
            <span
                class="size-[4px] bg-zinc-900 dark:bg-zinc-100 {{ $config['round'] ? 'rounded-full' : 'rounded-[1px]' }} {{ $delay !== null ? 'animate-pixel' : '' }}"
                style="opacity: {{ $delay === null ? '0.07' : '0.15' }}; --dur: {{ $config['dur'] }}ms; --delay: {{ $delay ?? 0 }}ms;"
            ></span>
        @endforeach
    </span>

    {{-- Shimmering Label Text --}}
    <span
        class="bg-clip-text text-[13px] font-medium text-transparent animate-shimmer"
        style="
            background-image: linear-gradient(90deg, rgba(161, 161, 170, 0.4) 35%, rgba(255, 255, 255, 1) 50%, rgba(161, 161, 170, 0.4) 65%);
            background-size: 200% 100%;
        "
    >
        {{ $label }}
    </span>

    {{-- Monospace Tabular Elapsed Timer --}}
    <span class="font-mono text-[12px] text-zinc-500 dark:text-zinc-400 tabular-nums" x-text="formatElapsed()">
        0.0s
    </span>
</div>
