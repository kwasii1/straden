<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout>
        <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
            <div>
                <h2 class="text-sm font-medium text-zinc-900">{{ __('Update password') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Use a long, random password to keep your account secure.') }}</p>
            </div>

            <form method="POST" wire:submit="updatePassword" class="flex w-full max-w-xl flex-col gap-6">
                <flux:input
                    wire:model="current_password"
                    :label="__('Current password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
                <flux:input
                    wire:model="password"
                    :label="__('New password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <div>
                    <flux:button variant="primary" type="submit" data-test="update-password-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </section>

        @if ($canManageTwoFactor)
            <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-medium text-zinc-900">{{ __('Two-factor authentication') }}</h2>
                        @if ($twoFactorEnabled)
                            <x-status-badge status="active" :label="__('Enabled')" />
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Ask for a one-time code from an authenticator app when you sign in.') }}</p>
                </div>

                <div class="flex w-full max-w-xl flex-col gap-6" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="flex flex-col items-start gap-4">
                            <p class="text-sm text-zinc-700">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>

                            <flux:button variant="danger" wire:click="disable">
                                {{ __('Disable 2FA') }}
                            </flux:button>
                        </div>

                        <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                    @else
                        <div class="flex flex-col items-start gap-4">
                            <p class="text-sm text-zinc-700">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>

                            <flux:modal.trigger name="two-factor-setup-modal">
                                <flux:button
                                    variant="filled"
                                    icon="shield-check"
                                    wire:click="$dispatch('start-two-factor-setup')"
                                >
                                    {{ __('Enable 2FA') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($canManagePasskeys)
            <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
                <div>
                    <h2 class="text-sm font-medium text-zinc-900">{{ __('Passkeys') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Manage your passkeys for passwordless sign-in') }}.</p>
                </div>

                <div class="flex w-full max-w-xl flex-col gap-4" wire:cloak>
                    <div class="ui-panel overflow-hidden">
                        @if (empty($passkeys))
                            <x-empty-state compact icon="key" :title="__('No passkeys yet')" :description="__('Add a passkey to sign in without a password') . '.'" />
                        @else
                            <div class="ui-list">
                                @foreach ($passkeys as $passkey)
                                    <div class="ui-list-row" wire:key="passkey-{{ $passkey['id'] }}">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                                            <flux:icon.key variant="micro" />
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="truncate text-sm font-medium text-zinc-900">{{ $passkey['name'] }}</p>
                                                @if ($passkey['authenticator'])
                                                    <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                                @endif
                                            </div>
                                            <p class="text-xs text-zinc-500">
                                                {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}@if ($passkey['last_used_at_diff']), {{ __('last used :time', ['time' => $passkey['last_used_at_diff']]) }}@endif
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="ui-icon-button hover:text-red-600"
                                            wire:click="confirmDelete({{ $passkey['id'] }})"
                                            aria-label="{{ __('Remove passkey') }}"
                                            title="{{ __('Remove passkey') }}"
                                        >
                                            <flux:icon.trash variant="micro" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <x-passkey-registration />
                </div>
            </section>
        @endif

        @if ($canManageTwoFactor && ! $twoFactorEnabled)
            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
        @endif
    </x-pages::settings.layout>

    <flux:modal
        name="delete-passkey-modal"
        class="md:w-[28rem]"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Remove passkey') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deletePasskey">
                    {{ __('Remove passkey') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
