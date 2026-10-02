@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @isset($breadcrumbs)
            <div class="mb-2 text-sm text-zinc-500">{{ $breadcrumbs }}</div>
        @endisset
        <h1 class="truncate text-xl font-semibold tracking-tight text-zinc-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-prose text-sm text-zinc-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
