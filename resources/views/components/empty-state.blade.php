@props([
    'icon' => null,
    'title',
    'description' => null,
    'compact' => false,
])

<div {{ $attributes->class(['flex flex-col items-center justify-center text-center', 'px-6 py-14' => ! $compact, 'px-4 py-8' => $compact]) }}>
    @if ($icon)
        <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
            <flux:icon :name="$icon" class="size-4.5" />
        </div>
    @endif

    <p class="text-sm font-medium text-zinc-900">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
