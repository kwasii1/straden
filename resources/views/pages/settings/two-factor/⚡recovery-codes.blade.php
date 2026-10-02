<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="ui-panel"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <header class="ui-panel-header">
        <h3 class="ui-panel-title">{{ __('2FA recovery codes') }}</h3>

        <div class="flex items-center gap-2">
            @if (filled($recoveryCodes))
                <flux:button
                    x-show="showRecoveryCodes"
                    x-cloak
                    icon="arrow-path"
                    variant="ghost"
                    size="sm"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </flux:button>
            @endif

            <flux:button
                x-show="!showRecoveryCodes"
                icon="eye"
                icon:variant="outline"
                size="sm"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                {{ __('View recovery codes') }}
            </flux:button>

            <flux:button
                x-show="showRecoveryCodes"
                x-cloak
                icon="eye-slash"
                icon:variant="outline"
                size="sm"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                {{ __('Hide recovery codes') }}
            </flux:button>
        </div>
    </header>

    <div class="ui-panel-body flex flex-col gap-4">
        <p class="text-sm text-zinc-500">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>

        <div
            x-show="showRecoveryCodes"
            x-cloak
            x-transition:enter="transition ease-snappy duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-out duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            id="recovery-codes-section"
            class="flex flex-col gap-3"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            @error('recoveryCodes')
                <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
            @enderror

            @if (filled($recoveryCodes))
                <div
                    class="ui-inset grid grid-cols-1 gap-x-6 gap-y-1.5 p-4 font-mono text-sm text-zinc-800 sm:grid-cols-2"
                    role="list"
                    aria-label="{{ __('Recovery codes') }}"
                >
                    @foreach ($recoveryCodes as $code)
                        <div
                            role="listitem"
                            class="select-text transition-opacity duration-150"
                            wire:loading.class="opacity-50"
                        >
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-zinc-500">
                    {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                </p>
            @endif
        </div>
    </div>
</div>
