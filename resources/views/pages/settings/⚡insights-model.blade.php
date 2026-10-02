<?php

use App\Ai\Providers\AvailableModelMap;
use App\Services\AiCredentialManager;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::app')]
class extends Component
{
    public function boot(): void
    {
        // Enforced on every Livewire request, not just the initial page load.
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public string $insightsProvider = '';

    public string $insightsModel = '';

    public function mount(): void
    {
        $this->loadSelection();
    }

    private function loadSelection(): void
    {
        $selection = app(AiCredentialManager::class)->getInsightsSelection();

        $this->insightsProvider = $selection['provider'] ?? '';
        $this->insightsModel = $selection['model'] ?? '';
    }

    public function updatedInsightsProvider(): void
    {
        // A stale model from another provider must never be saved by accident.
        $this->insightsModel = '';
    }

    #[Computed]
    public function insightsSelection(): ?array
    {
        $selection = app(AiCredentialManager::class)->getInsightsSelection();

        if (! $selection) {
            return null;
        }

        return [
            'provider' => $selection['provider'],
            'provider_label' => AvailableModelMap::labelFor($selection['provider']),
            'model' => $selection['model'],
            'usable' => app(AiCredentialManager::class)->isConnected($selection['provider']),
        ];
    }

    #[Computed]
    public function insightProviderOptions(): array
    {
        $manager = app(AiCredentialManager::class);

        $options = array_map(fn (string $slug) => [
            'value' => $slug,
            'label' => AvailableModelMap::labelFor($slug),
            'connected' => $manager->isConnected($slug),
        ], AiCredentialManager::supportedProviders());

        usort($options, fn ($a, $b) => [$b['connected'], $a['label']] <=> [$a['connected'], $b['label']]);

        return $options;
    }

    #[Computed]
    public function insightModelSuggestions(): array
    {
        if ($this->insightsProvider === '') {
            return [];
        }

        return AvailableModelMap::allModelsFor($this->insightsProvider);
    }

    #[Computed]
    public function insightsModelPlaceholder(): string
    {
        if ($this->insightsProvider === '') {
            return 'Select a provider first';
        }

        $first = $this->insightModelSuggestions[0] ?? null;

        return $first ? "e.g. {$first}" : 'Type a model name';
    }

    public function saveInsightsModel(): void
    {
        $this->validate([
            'insightsProvider' => ['required', 'string', 'in:'.implode(',', AiCredentialManager::supportedProviders())],
            'insightsModel' => ['required', 'string', 'max:255'],
        ]);

        app(AiCredentialManager::class)->setInsightsSelection($this->insightsProvider, $this->insightsModel);

        Flux::toast(variant: 'success', text: 'Insights model updated.');

        unset($this->insightsSelection);
    }

    public function resetForm(): void
    {
        $this->loadSelection();
        $this->resetValidation();
    }
};
?>

<x-pages::settings.ai-layout>
    <div>
        <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
            <div>
                <h2 class="text-sm font-medium text-zinc-900">AI insights model</h2>
                <p class="mt-1 text-sm text-zinc-500">Choose which provider and model powers load-test insight reports.</p>
            </div>

            <div class="flex w-full max-w-xl flex-col gap-6">
                <div class="ui-inset flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-xs text-zinc-500">Current model</p>
                        @if ($this->insightsSelection)
                            <p class="mt-0.5 truncate text-sm text-zinc-900">
                                <span class="font-mono">{{ $this->insightsSelection['model'] }}</span>
                                <span class="text-zinc-500">via {{ $this->insightsSelection['provider_label'] }}</span>
                            </p>
                        @else
                            <p class="mt-0.5 text-sm text-zinc-500">No model selected</p>
                        @endif
                    </div>

                    @if ($this->insightsSelection)
                        @if ($this->insightsSelection['usable'])
                            <x-status-badge status="connected" />
                        @else
                            <x-status-badge status="warning" label="Provider not connected" />
                        @endif
                    @endif
                </div>

                @if ($this->insightsSelection && ! $this->insightsSelection['usable'])
                    <p class="-mt-3 text-sm text-zinc-500">
                        Connect {{ $this->insightsSelection['provider_label'] }} in
                        <a wire:navigate href="{{ route('settings.ai-integrations') }}" class="ui-link">AI integrations</a>
                        or pick another model.
                    </p>
                @endif

                <form wire:submit="saveInsightsModel" class="flex flex-col gap-6">
                    <flux:field>
                        <flux:label>Provider</flux:label>
                        <x-combobox
                            wire:model.live="insightsProvider"
                            :options="collect($this->insightProviderOptions)->map(fn ($option) => [
                                'value' => $option['value'],
                                'label' => $option['label'],
                                'hint' => $option['connected'] ? null : 'not connected',
                            ])"
                            placeholder="Choose provider..."
                            search-placeholder="Search providers..."
                            empty-text="No matching providers."
                        />
                        <flux:error name="insightsProvider" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Model</flux:label>
                        <x-combobox
                            wire:model="insightsModel"
                            wire:key="insights-model-{{ $insightsProvider }}"
                            :options="$this->insightModelSuggestions"
                            :placeholder="$this->insightsModelPlaceholder"
                            search-placeholder="Search or type a model name..."
                            empty-text="Type a model name to use it."
                            :allow-custom="true"
                            :disabled="$insightsProvider === ''"
                        />
                        <flux:description>Pick a suggested model or type any model name your provider supports.</flux:description>
                        <flux:error name="insightsModel" />
                    </flux:field>

                    <div class="flex items-center gap-2">
                        <flux:button type="submit" variant="primary">Save model</flux:button>
                        <flux:button wire:click="resetForm" variant="ghost">Reset</flux:button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</x-pages::settings.ai-layout>
