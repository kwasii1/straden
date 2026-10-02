@props(['noPadding' => false])
<x-layouts::app.main-sidebar :title="$title ?? null">
    <flux:main @class(['p-0!' => $noPadding, 'px-4! py-6! sm:px-6! lg:px-8! lg:py-8!' => ! $noPadding])>
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
            <div class="mx-auto w-full max-w-7xl">
                {{ $slot }}
            </div>
        @endif
    </flux:main>
</x-layouts::app.main-sidebar>
