@props(['noPadding' => false])
<x-layouts::app.main-sidebar :title="$title ?? null">
    <flux:main @class(['p-0!' => $noPadding, 'p-5' => ! $noPadding])>
        @if ($noPadding)
            <div
                x-data
                x-init="
                    const setHeight = () => $el.style.height = `${window.innerHeight - $el.getBoundingClientRect().top}px`;
                    setHeight();
                    window.addEventListener('resize', setHeight);
                "
                class="flex flex-col overflow-hidden"
            >
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </flux:main>
</x-layouts::app.main-sidebar>
