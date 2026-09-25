<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    public bool $generatePassword = true;

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $revealedPassword = null;

    public ?string $revealedFor = null;

    public function boot(): void
    {
        // Enforced on every Livewire request, not just the initial page load.
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderByDesc('is_admin')->orderBy('name')->get();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function startEdit(string $userId): void
    {
        $user = User::findOrFail($userId);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isAdmin = $user->is_admin;

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $editing = $this->editingId ? User::findOrFail($this->editingId) : null;

        $rules = $this->profileRules($editing?->id);

        if (! $editing && ! $this->generatePassword) {
            $rules['password'] = $this->passwordRules();
        }

        $validated = $this->validate($rules);

        if ($editing && ! $this->isAdmin && $editing->is_admin) {
            if ($editing->is(Auth::user())) {
                $this->addError('isAdmin', __('You cannot remove your own admin access.'));

                return;
            }

            if ($this->isLastAdmin($editing)) {
                $this->addError('isAdmin', __('At least one admin is required.'));

                return;
            }
        }

        if ($editing) {
            $editing->fill(['name' => $validated['name'], 'email' => $validated['email']]);
            $editing->forceFill(['is_admin' => $this->isAdmin])->save();

            Flux::toast(variant: 'success', text: __('User updated.'));
        } else {
            $password = $this->generatePassword ? Str::password(16, symbols: false) : $this->password;

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $password,
            ]);

            $user->forceFill(['is_admin' => $this->isAdmin, 'email_verified_at' => now()])->save();

            if ($this->generatePassword) {
                $this->reveal($user, $password);
            } else {
                Flux::toast(variant: 'success', text: __('User created.'));
            }
        }

        Flux::modal('user-form')->close();
        $this->resetForm();
        unset($this->users);
    }

    public function resetPassword(string $userId): void
    {
        $user = User::findOrFail($userId);
        $password = Str::password(16, symbols: false);

        $user->forceFill(['password' => $password])->save();

        $this->reveal($user, $password);
    }

    public function deleteUser(string $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account here.'));

            return;
        }

        if ($user->is_admin && $this->isLastAdmin($user)) {
            Flux::toast(variant: 'danger', text: __('At least one admin is required.'));

            return;
        }

        $user->tokens()->delete();
        $user->delete();

        unset($this->users);
        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    private function isLastAdmin(User $user): bool
    {
        return ! User::query()->where('is_admin', true)->whereKeyNot($user->id)->exists();
    }

    private function reveal(User $user, string $password): void
    {
        $this->revealedFor = $user->email;
        $this->revealedPassword = $password;

        Flux::modal('user-password')->show();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'isAdmin', 'password', 'password_confirmation');
        $this->generatePassword = true;
        $this->resetValidation();
    }
}; ?>

<x-pages::settings.ai-layout>
    <div class="flex flex-col gap-y-6">
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
            <div>
                <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Users') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Manage who can sign in to this Straden instance.') }}</flux:text>
            </div>

            <flux:button wire:click="startCreate" variant="primary" size="sm" icon="plus">{{ __('Add user') }}</flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('User') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Two-factor') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Last sign-in') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $user->name }}
                                            @if ($user->is(auth()->user()))
                                                <span class="text-xs font-normal text-zinc-400">({{ __('you') }})</span>
                                            @endif
                                        </div>
                                        <div class="truncate text-xs text-zinc-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->is_admin)
                                    <flux:badge size="sm" color="zinc">{{ __('Admin') }}</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-500">{{ __('Member') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500">
                                {{ $user->two_factor_confirmed_at ? __('Enabled') : __('Off') }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500">
                                {{ $user->last_login_at?->diffForHumans() ?? __('Never') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="startEdit('{{ $user->id }}')">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="key" wire:click="resetPassword('{{ $user->id }}')" wire:confirm="{{ __('Generate a new password for :name? Their current password stops working immediately.', ['name' => $user->name]) }}">{{ __('Reset password') }}</flux:menu.item>
                                        @unless ($user->is(auth()->user()))
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="deleteUser('{{ $user->id }}')" wire:confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $user->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <flux:modal name="user-form" class="md:w-lg">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit user') : __('Add user') }}</flux:heading>
                <flux:text class="mt-1 text-xs">{{ $editingId ? __('Update this user\'s details and role.') : __('The user can sign in straight away with the email and password below.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" :label="__('Email address')" type="email" required />

            @unless ($editingId)
                <flux:switch wire:model.live="generatePassword" :label="__('Generate a password')" :description="__('A random password is shown once after creating the user.')" />

                @unless ($generatePassword)
                    <flux:input wire:model="password" :label="__('Password')" type="password" viewable required />
                    <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" viewable required />
                @endunless
            @endunless

            <flux:field variant="inline">
                <flux:checkbox wire:model="isAdmin" />
                <flux:label>{{ __('Administrator') }}</flux:label>
                <flux:description>{{ __('Admins can manage users.') }}</flux:description>
                <flux:error name="isAdmin" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" size="sm">{{ $editingId ? __('Save changes') : __('Create user') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="user-password" class="md:w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Password for :email', ['email' => $revealedFor]) }}</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Share this password securely. It won\'t be shown again — the user can change it under Settings → Security.') }}</flux:text>
            </div>

            <flux:input :value="$revealedPassword" readonly copyable />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" size="sm">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</x-pages::settings.ai-layout>
