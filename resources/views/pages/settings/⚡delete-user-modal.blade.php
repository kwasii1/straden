<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        if ($user->isAdmin() && ! User::query()->where('is_admin', true)->whereKeyNot($user->id)->exists()) {
            $this->addError('password', __('You are the only admin. Make another user an admin before deleting your account.'));

            return;
        }

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="md:w-[28rem]">
    <form method="POST" wire:submit="deleteUser" class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg">{{ __('Delete your account?') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('Your account and all of its data will be permanently deleted. Enter your password to confirm.') }}
            </flux:text>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
