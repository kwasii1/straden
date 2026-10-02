<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout>
        <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
            <div>
                <h2 class="text-sm font-medium text-zinc-900">{{ __('Theme') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Straden uses a single light theme, tuned for reading charts, logs and scripts.') }}</p>
            </div>

            <div class="max-w-xl">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-fit">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                </flux:radio.group>
            </div>
        </section>
    </x-pages::settings.layout>
</section>
