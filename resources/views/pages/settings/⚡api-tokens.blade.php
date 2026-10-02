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
    <div>
        <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
            <div>
                <h2 class="text-sm font-medium text-zinc-900">{{ __('Create API token') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Create tokens that let AI coding agents use Straden through the MCP server.') }}</p>
            </div>

            <form wire:submit="createToken" class="flex w-full max-w-xl flex-col gap-6">
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

                <div>
                    <flux:button variant="primary" type="submit">{{ __('Create token') }}</flux:button>
                </div>
            </form>
        </section>

        <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
            <div>
                <h2 class="text-sm font-medium text-zinc-900">{{ __('Active tokens') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Revoke a token to cut off the agent using it straight away.') }}</p>
            </div>

            <div class="ui-panel min-w-0 overflow-hidden">
                @if ($this->tokens->isEmpty())
                    <x-empty-state compact icon="key" :title="__('No API tokens yet')" :description="__('Create a token to connect an AI coding agent.')" />
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Permissions') }}</th>
                                    <th class="text-right!">{{ __('Last used') }}</th>
                                    <th class="text-right!">{{ __('Expires') }}</th>
                                    <th><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->tokens as $token)
                                    <tr wire:key="token-{{ $token->id }}">
                                        <td class="max-w-56 truncate font-medium text-zinc-900">{{ $token->name }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($token->abilities as $ability)
                                                    <span class="ui-code text-xs">{{ $ability }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="text-right whitespace-nowrap text-zinc-500">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                        <td class="text-right whitespace-nowrap text-zinc-500">{{ $token->expires_at?->toFormattedDateString() ?? __('Never') }}</td>
                                        <td class="w-px text-right">
                                            <button
                                                type="button"
                                                class="ui-icon-button hover:text-red-600"
                                                wire:click="revokeToken('{{ $token->id }}')"
                                                wire:confirm="{{ __('Revoke this token? Agents using it will lose access immediately.') }}"
                                                aria-label="{{ __('Revoke') }}"
                                                title="{{ __('Revoke') }}"
                                            >
                                                <flux:icon.trash variant="micro" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <flux:modal name="token-created" class="md:w-xl">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Token created') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Copy this token now. You will not be able to see it again.') }}</flux:text>
            </div>

            <flux:input :value="$plainTextToken" readonly copyable />

            <div>
                <p class="mb-2 text-xs text-zinc-500">{{ __('Add Straden to Claude Code') }}</p>
                <pre class="ui-inset overflow-x-auto p-3 font-mono text-xs text-zinc-800"><code>claude mcp add --transport http straden {{ $this->mcpUrl() }} --header "Authorization: Bearer {{ $plainTextToken }}"</code></pre>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</x-pages::settings.ai-layout>
