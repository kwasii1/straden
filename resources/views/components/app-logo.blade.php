@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand {{ $attributes }}>
        <x-slot name="logo" class="flex h-8 min-w-0 items-center">
            <x-app-logo-icon class="h-6 w-auto text-zinc-900 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-white" />
            <x-app-logo-icon variant="mark" class="hidden size-8 text-zinc-900 in-data-flux-sidebar-collapsed-desktop:block dark:text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo" class="flex h-8 items-center">
            <x-app-logo-icon class="h-6 w-auto text-zinc-900 dark:text-white" />
        </x-slot>
    </flux:brand>
@endif
