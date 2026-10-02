@php
    $backUrl ??= null;
@endphp

<x-page-header :title="__('Settings')" :description="__('Manage your account, access tokens and this Straden instance.')">
    @if ($backUrl)
        <x-slot:breadcrumbs>
            <a href="{{ $backUrl }}" wire:navigate class="inline-flex items-center gap-1 text-zinc-500 transition-colors duration-150 hover:text-zinc-900">
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </a>
        </x-slot:breadcrumbs>
    @endif
</x-page-header>
