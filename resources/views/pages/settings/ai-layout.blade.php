@php
    $returnTo = session('settings.return_to');
@endphp

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px] md:shrink-0">
        @if ($returnTo)
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="$returnTo" wire:navigate class="mb-4">
                {{ __('Back') }}
            </flux:button>
        @endif

        <flux:heading size="lg" class="mb-3 px-3">{{ __('Settings') }}</flux:heading>

        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item icon="cpu-chip" :href="route('settings.ai-integrations')" :current="request()->routeIs('settings.ai-integrations')" wire:navigate>
                {{ __('AI Integrations') }}
            </flux:navlist.item>
            <flux:navlist.item icon="sparkles" :href="route('settings.insights-model')" :current="request()->routeIs('settings.insights-model')" wire:navigate>
                {{ __('AI Insights Model') }}
            </flux:navlist.item>
            <flux:navlist.item icon="key" :href="route('settings.api-tokens')" :current="request()->routeIs('settings.api-tokens')" wire:navigate>
                {{ __('API Tokens') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="min-w-0 flex-1 self-stretch max-md:pt-6">
        {{ $slot }}
    </div>
</div>
