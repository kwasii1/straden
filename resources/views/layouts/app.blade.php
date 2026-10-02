<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="px-4! py-6! sm:px-6! lg:px-8! lg:py-8!">
        <div class="mx-auto w-full max-w-7xl">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
