<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component {
    /** @var array<string, string> */
    public const ABILITIES = [
        'mcp:read' => 'Read projects, scripts, runs and metrics',
        'mcp:write' => 'Create and edit scripts',
        'mcp:run' => 'Start and cancel runs',
    ];

    public string $name = '';

    /** @var array<int, string> */
    public array $abilities = ['mcp:read', 'mcp:write', 'mcp:run'];

    public string $expiresIn = '90';

    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', array_keys(self::ABILITIES))],
            'expiresIn' => ['required', 'in:30,90,365,never'],
        ]);

        $expiresAt = $validated['expiresIn'] === 'never' ? null : now()->addDays((int) $validated['expiresIn']);

        $this->plainTextToken = Auth::user()
            ->createToken($validated['name'], array_values($validated['abilities']), $expiresAt)
            ->plainTextToken;

        $this->reset('name');
        unset($this->tokens);

        Flux::modal('token-created')->show();
    }

    public function revokeToken(string $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();
        unset($this->tokens);

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }

    /** @return \Illuminate\Support\Collection<int, PersonalAccessToken> */
    #[Computed]
    public function tokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function mcpUrl(): string
    {
        return route('mcp');
    }
}; ?>

<x-pages::settings.ai-layout>
    <div class="flex flex-col gap-y-6">
        <div class="border-b border-zinc-200 pb-5 dark:border-zinc-800">
            <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('API Tokens') }}</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Create tokens that let AI coding agents use Straden through the MCP server.') }}</flux:text>
        </div>

        <form wire:submit="createToken" class="max-w-lg space-y-6">
            <flux:input wire:model="name" :label="__('Token name')" placeholder="Claude Code on my laptop" required />

            <flux:checkbox.group wire:model="abilities" :label="__('Permissions')">
                @foreach ($this::ABILITIES as $ability => $label)
                    <flux:checkbox :value="$ability" :label="$label" :description="$ability" />
                @endforeach
            </flux:checkbox.group>

            <flux:select wire:model="expiresIn" :label="__('Expires')">
                <flux:select.option value="30">{{ __('In 30 days') }}</flux:select.option>
                <flux:select.option value="90">{{ __('In 90 days') }}</flux:select.option>
                <flux:select.option value="365">{{ __('In 1 year') }}</flux:select.option>
                <flux:select.option value="never">{{ __('Never') }}</flux:select.option>
            </flux:select>

            <flux:button variant="primary" type="submit">{{ __('Create token') }}</flux:button>
        </form>

        <div>
        <flux:heading size="lg">{{ __('Active tokens') }}</flux:heading>

        <div class="mt-4 space-y-3">
            @forelse ($this->tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="min-w-0">
                        <flux:text class="font-medium text-zinc-800 dark:text-white">{{ $token->name }}</flux:text>
                        <flux:text size="sm">{{ implode(', ', $token->abilities) }}</flux:text>
                        <flux:text size="sm">
                            {{ __('Last used') }}: {{ $token->last_used_at?->diffForHumans() ?? __('Never') }}
                            &middot;
                            {{ __('Expires') }}: {{ $token->expires_at?->toFormattedDateString() ?? __('Never') }}
                        </flux:text>
                    </div>

                    <flux:button size="sm" variant="danger" wire:click="revokeToken('{{ $token->id }}')" wire:confirm="{{ __('Revoke this token? Agents using it will lose access immediately.') }}">
                        {{ __('Revoke') }}
                    </flux:button>
                </div>
            @empty
                <flux:text>{{ __('You have no API tokens yet.') }}</flux:text>
            @endforelse
        </div>
        </div>
    </div>

    <flux:modal name="token-created" class="md:w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Token created') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Copy this token now. You will not be able to see it again.') }}</flux:text>
            </div>

            <flux:input :value="$plainTextToken" readonly copyable />

            <div>
                <flux:text class="mb-2">{{ __('Add Straden to Claude Code:') }}</flux:text>
                <pre class="overflow-x-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-zinc-800"><code>claude mcp add --transport http straden {{ $this->mcpUrl() }} --header "Authorization: Bearer {{ $plainTextToken }}"</code></pre>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</x-pages::settings.ai-layout>
