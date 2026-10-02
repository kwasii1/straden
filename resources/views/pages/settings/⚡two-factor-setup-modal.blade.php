<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<flux:modal
    name="two-factor-setup-modal"
    class="md:w-[28rem]"
    @close="closeModal"
>
    <div class="flex flex-col gap-6">
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                <flux:icon.qr-code class="size-4.5" />
            </span>

            <div class="min-w-0">
                <flux:heading size="lg">{{ $this->modalConfig['title'] }}</flux:heading>
                <flux:text class="mt-1">{{ $this->modalConfig['description'] }}</flux:text>
            </div>
        </div>

        @if ($showVerificationStep)
            <div class="flex flex-col gap-6">
                <div
                    class="flex flex-col items-center justify-center gap-3"
                    x-data
                    x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                >
                    <flux:otp
                        name="code"
                        wire:model="code"
                        length="6"
                        label="OTP Code"
                        label:sr-only
                        class="mx-auto"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button
                        variant="ghost"
                        wire:click="resetVerification"
                    >
                        {{ __('Back') }}
                    </flux:button>

                    <flux:button
                        variant="primary"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    >
                        {{ __('Confirm') }}
                    </flux:button>
                </div>
            </div>
        @else
            @error('setupData')
                <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
            @enderror

            <div class="flex justify-center">
                <div class="relative aspect-square w-56 overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex items-center justify-center bg-zinc-50">
                            <x-spinner class="size-5 text-zinc-400" />
                        </div>
                    @else
                        <div class="animate-fade flex h-full items-center justify-center p-4 [&_svg]:size-full">
                            {!! $qrCodeSvg !!}
                        </div>
                    @endempty
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-xs text-zinc-500">{{ __('Or enter the setup key manually') }}</p>

                <div
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {
                                console.warn('Could not copy to clipboard');
                            }
                        }
                    }"
                >
                    <div class="flex h-9 items-stretch overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xs">
                        @empty($manualSetupKey)
                            <div class="flex w-full items-center justify-center bg-zinc-50">
                                <x-spinner class="size-4 text-zinc-400" />
                            </div>
                        @else
                            <input
                                type="text"
                                readonly
                                value="{{ $manualSetupKey }}"
                                aria-label="{{ __('Setup key') }}"
                                class="w-full bg-transparent px-3 font-mono text-sm text-zinc-900 outline-none"
                            />

                            <button
                                type="button"
                                @click="copy()"
                                class="ui-pressable flex w-10 shrink-0 cursor-pointer items-center justify-center border-l border-zinc-200 text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900"
                                :aria-label="copied ? @js(__('Copied')) : @js(__('Copy setup key'))"
                            >
                                <flux:icon.document-duplicate x-show="!copied" variant="micro" />
                                <flux:icon.check x-show="copied" x-cloak variant="micro" class="text-emerald-600" />
                            </button>
                        @endempty
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button
                    :disabled="$errors->has('setupData')"
                    variant="primary"
                    wire:click="showVerificationIfNecessary"
                >
                    {{ $this->modalConfig['buttonText'] }}
                </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
