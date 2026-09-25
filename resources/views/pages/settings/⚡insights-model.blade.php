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

        return AvailableModelMap::modelsFor($this->insightsProvider);
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
    <div class="flex flex-col gap-y-6">
        <div class="border-b border-zinc-200 pb-5 dark:border-zinc-800">
            <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">AI Insights Model</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Choose which provider and model powers load-test insight reports.</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200/80 bg-white/60 p-4 backdrop-blur-xs sm:p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
            <flux:text class="text-xs font-medium text-zinc-500">Current model</flux:text>

            <div class="mt-2">
                @if ($this->insightsSelection)
                    @if ($this->insightsSelection['usable'])
                        <span class="inline-flex items-center gap-x-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            {{ $this->insightsSelection['model'] }} via {{ $this->insightsSelection['provider_label'] }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-x-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                            {{ $this->insightsSelection['model'] }} — {{ $this->insightsSelection['provider_label'] }} not connected
                        </span>
                        <flux:text class="mt-2 text-xs">
                            Connect the provider in
                            <flux:link wire:navigate :href="route('settings.ai-integrations')">AI Integrations</flux:link>
                            or pick another model.
                        </flux:text>
                    @endif
                @else
                    <span class="inline-flex items-center rounded-full border border-zinc-200/50 bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500 dark:border-zinc-700/50 dark:bg-zinc-800/80 dark:text-zinc-400">
                        No model selected
                    </span>
                @endif
            </div>
        </div>

        <form wire:submit="saveInsightsModel" class="max-w-lg space-y-4">
            <flux:field>
                <flux:label class="text-xs">Provider</flux:label>
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
                <flux:label class="text-xs">Model</flux:label>
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
                <flux:description class="text-xs">Pick a suggested model or type any model name your provider supports.</flux:description>
                <flux:error name="insightsModel" />
            </flux:field>

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary" size="sm">Save Model</flux:button>
                <flux:button wire:click="resetForm" variant="ghost" size="sm">Reset</flux:button>
            </div>
        </form>
    </div>
</x-pages::settings.ai-layout>
