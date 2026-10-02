<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="border-t border-zinc-200 py-8 first:border-t-0 first:pt-0">
    <div class="grid gap-x-8 gap-y-5 lg:grid-cols-[18rem_1fr]">
        <div>
            <h2 class="text-sm font-medium text-zinc-900">{{ __('Delete account') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Delete your account and all of its resources.') }}</p>
        </div>

        <div class="flex max-w-xl flex-col items-start gap-4">
            <p class="text-sm text-zinc-700">{{ __('This permanently removes your account, tokens and data. It cannot be undone.') }}</p>

            <flux:modal.trigger name="confirm-user-deletion">
                <flux:button variant="danger" data-test="delete-user-button">
                    {{ __('Delete account') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
