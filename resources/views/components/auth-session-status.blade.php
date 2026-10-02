@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 ring-1 ring-emerald-600/15 ring-inset']) }}>
        {{ $status }}
    </div>
@endif
