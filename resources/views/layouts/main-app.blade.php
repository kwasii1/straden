@props(['noPadding' => false])
<x-layouts::app.main-sidebar :title="$title ?? null">
    <flux:main @class(['p-0!' => $noPadding])>
        {{ $slot }}
    </flux:main>
</x-layouts::app.main-sidebar>
