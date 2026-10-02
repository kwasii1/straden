@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->class('min-w-0 px-4 py-3.5') }}>
    <p class="truncate text-sm text-zinc-500">{{ $label }}</p>
    <p class="ui-metric mt-1">{{ $value }}</p>
    @if ($hint || isset($footer))
        <div class="mt-1 truncate text-xs text-zinc-500">{{ $footer ?? $hint }}</div>
    @endif
</div>
