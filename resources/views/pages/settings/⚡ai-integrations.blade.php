<?php

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

    public string $activeProvider = '';

    public array $credentialValues = [];

    private array $providerMeta = [
        'openai' => [
            'name' => 'OpenAI',
            'description' => 'Advanced language models for text generation, image creation, and audio processing.',
            'sort_order' => 1,
            'website_url' => 'https://platform.openai.com',
            'logo' => 'openai',
            'fields' => [
                ['env' => 'OPENAI_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk-...'],
                ['env' => 'OPENAI_URL', 'label' => 'Base URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.openai.com/v1'],
            ],
            'models' => ['GPT-4.1', 'GPT-4.1 mini', 'GPT-4o', 'GPT-4o mini', 'o3', 'o4 mini'],
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'description' => 'Claude models focused on safety, reliability, and thoughtful reasoning.',
            'sort_order' => 2,
            'website_url' => 'https://console.anthropic.com',
            'logo' => 'anthropic',
            'fields' => [
                ['env' => 'ANTHROPIC_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk-ant-...'],
                ['env' => 'ANTHROPIC_URL', 'label' => 'Base URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.anthropic.com/v1'],
            ],
            'models' => ['Claude 4 Sonnet', 'Claude 3.5 Haiku', 'Claude Opus 4'],
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'description' => 'High-performance, cost-effective language models with strong reasoning capabilities.',
            'sort_order' => 3,
            'website_url' => 'https://platform.deepseek.com',
            'logo' => 'deepseek',
            'fields' => [
                ['env' => 'DEEPSEEK_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk-...'],
            ],
            'models' => ['DeepSeek V4', 'DeepSeek R1', 'DeepSeek Chat'],
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'description' => 'Multimodal AI models with native image, audio, and video understanding.',
            'sort_order' => 4,
            'website_url' => 'https://aistudio.google.com',
            'logo' => 'google-gemini',
            'fields' => [
                ['env' => 'GEMINI_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'AIza...'],
                ['env' => 'GEMINI_URL', 'label' => 'Base URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://generativelanguage.googleapis.com/v1beta/'],
            ],
            'models' => ['Gemini 2.5 Pro', 'Gemini 2.5 Flash', 'Gemini 2.0 Flash'],
        ],
        'groq' => [
            'name' => 'Groq',
            'description' => 'Ultra-fast inference for open-source models with LPU acceleration.',
            'sort_order' => 5,
            'website_url' => 'https://console.groq.com',
            'logo' => 'groq',
            'fields' => [
                ['env' => 'GROQ_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'gsk_...'],
            ],
            'models' => ['Llama 4 Maverick', 'Llama 3.3 70B', 'Mixtral 8x7B'],
        ],
        'xai' => [
            'name' => 'xAI',
            'description' => 'Grok models from xAI with real-time knowledge and reasoning.',
            'sort_order' => 6,
            'website_url' => 'https://console.x.ai',
            'logo' => 'x-ai',
            'fields' => [
                ['env' => 'XAI_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'xai-...'],
            ],
            'models' => ['Grok 3', 'Grok 3 Mini'],
        ],
        'mistral' => [
            'name' => 'Mistral',
            'description' => 'Open-weight and optimized models with top-tier performance.',
            'sort_order' => 7,
            'website_url' => 'https://console.mistral.ai',
            'logo' => 'mistral-ai',
            'fields' => [
                ['env' => 'MISTRAL_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
            ],
            'models' => ['Mistral Large', 'Mistral Small', 'Codestral'],
        ],
        'cohere' => [
            'name' => 'Cohere',
            'description' => 'Enterprise AI models for embeddings, reranking, and text generation.',
            'sort_order' => 8,
            'website_url' => 'https://dashboard.cohere.com',
            'logo' => 'cohere',
            'fields' => [
                ['env' => 'COHERE_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
            ],
            'models' => ['Command R+', 'Command R', 'Embed English v3', 'Rerank v3.5'],
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'description' => 'Unified API to access hundreds of models from various providers.',
            'sort_order' => 9,
            'website_url' => 'https://openrouter.ai',
            'logo' => 'openrouter',
            'fields' => [
                ['env' => 'OPENROUTER_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk-or-...'],
            ],
            'models' => ['Claude 4 Sonnet', 'GPT-4o', 'Gemini 2.5 Pro', 'DeepSeek R1', 'Llama 3.3 70B', 'Mistral Large'],
        ],
        'ollama' => [
            'name' => 'Ollama',
            'description' => 'Run open-source LLMs locally on your own infrastructure.',
            'sort_order' => 10,
            'website_url' => 'https://ollama.com',
            'logo' => 'ollama',
            'fields' => [
                ['env' => 'OLLAMA_URL', 'label' => 'Server URL', 'type' => 'url', 'required' => true, 'placeholder' => 'http://localhost:11434'],
                ['env' => 'OLLAMA_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => false, 'placeholder' => 'Optional'],
            ],
            'models' => ['Llama 3.3', 'Mistral', 'DeepSeek R1', 'Gemma 3', 'Phi-4'],
        ],
        'azure' => [
            'name' => 'Azure OpenAI',
            'description' => 'Microsoft Azure hosted OpenAI models with enterprise compliance.',
            'sort_order' => 11,
            'website_url' => 'https://azure.microsoft.com/products/ai-services/openai-service',
            'logo' => 'azure',
            'fields' => [
                ['env' => 'AZURE_OPENAI_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
                ['env' => 'AZURE_OPENAI_URL', 'label' => 'Endpoint URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://your-resource.openai.azure.com/'],
                ['env' => 'AZURE_OPENAI_API_VERSION', 'label' => 'API Version', 'type' => 'text', 'required' => false, 'placeholder' => '2025-04-01-preview'],
                ['env' => 'AZURE_OPENAI_DEPLOYMENT', 'label' => 'Deployment Name', 'type' => 'text', 'required' => false, 'placeholder' => 'gpt-4o'],
            ],
            'models' => ['GPT-4o', 'GPT-4.1', 'GPT-4o mini', 'o3 mini'],
        ],
        'bedrock' => [
            'name' => 'AWS Bedrock',
            'description' => 'Amazon Web Services managed foundation models with enterprise security.',
            'sort_order' => 12,
            'website_url' => 'https://aws.amazon.com/bedrock/',
            'logo' => 'aws',
            'fields' => [
                ['env' => 'AWS_ACCESS_KEY_ID', 'label' => 'AWS Access Key ID', 'type' => 'text', 'required' => true, 'placeholder' => 'AKIA...'],
                ['env' => 'AWS_SECRET_ACCESS_KEY', 'label' => 'AWS Secret Access Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
                ['env' => 'AWS_BEDROCK_REGION', 'label' => 'AWS Region', 'type' => 'text', 'required' => false, 'placeholder' => 'us-east-1'],
                ['env' => 'AWS_BEARER_TOKEN_BEDROCK', 'label' => 'Bearer Token (optional)', 'type' => 'password', 'required' => false, 'placeholder' => '...'],
            ],
            'models' => ['Claude 4 Sonnet', 'Claude 3.5 Haiku', 'Llama 3.3 70B', 'Titan Text'],
        ],
        'openai-compatible' => [
            'name' => 'OpenAI Compatible',
            'description' => 'Connect to any OpenAI-compatible API endpoint (LM Studio, vLLM, etc.).',
            'sort_order' => 13,
            'website_url' => null,
            'logo' => 'vllm',
            'fields' => [
                ['env' => 'OPENAI_COMPATIBLE_URL', 'label' => 'Endpoint URL', 'type' => 'url', 'required' => true, 'placeholder' => 'http://localhost:1234/v1'],
                ['env' => 'OPENAI_COMPATIBLE_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => false, 'placeholder' => 'Optional'],
            ],
            'models' => ['Custom model (depends on endpoint)'],
        ],
        'jina' => [
            'name' => 'Jina AI',
            'description' => 'Embeddings and reranking models for search and retrieval applications.',
            'sort_order' => 14,
            'website_url' => 'https://jina.ai',
            'logo' => 'jina-ai',
            'fields' => [
                ['env' => 'JINA_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'jina_...'],
            ],
            'models' => ['jina-embeddings-v3', 'jina-reranker-v2', 'jina-clip-v2'],
        ],
        'voyageai' => [
            'name' => 'Voyage AI',
            'description' => 'Specialized embedding and reranking models for semantic search.',
            'sort_order' => 15,
            'website_url' => 'https://www.voyageai.com',
            'logo' => 'voyage-ai',
            'fields' => [
                ['env' => 'VOYAGEAI_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
            ],
            'models' => ['voyage-3-large', 'voyage-code-3', 'voyage-3-lite', 'rerank-2'],
        ],
        'eleven' => [
            'name' => 'ElevenLabs',
            'description' => 'Industry-leading text-to-speech and speech-to-text AI models.',
            'sort_order' => 16,
            'website_url' => 'https://elevenlabs.io',
            'logo' => 'eleven-labs',
            'fields' => [
                ['env' => 'ELEVENLABS_API_KEY', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => '...'],
            ],
            'models' => ['Eleven Multilingual v2', 'Eleven Turbo v2.5', 'Eleven Flash v2.5', 'Scribe v1'],
        ],
    ];

    #[Computed]
    public function providers(): array
    {
        $configured = config('ai.providers');
        $result = [];

        foreach ($this->providerMeta as $slug => $meta) {
            if (! isset($configured[$slug])) {
                continue;
            }

            $result[] = [
                'slug' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'sort_order' => $meta['sort_order'],
                'website_url' => $meta['website_url'],
                'logo' => $meta['logo'],
                'fields' => $meta['fields'],
                'models' => $meta['models'],
                'is_connected' => $this->isProviderConnected($slug),
            ];
        }

        usort($result, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $result;
    }

    #[Computed]
    public function stats(): array
    {
        $providers = $this->providers;
        $connected = collect($providers)->where('is_connected', true)->count();
        $total = count($providers);

        return [
            'total' => $total,
            'connected' => $connected,
            'available' => $total - $connected,
        ];
    }

    public function isProviderConnected(string $slug): bool
    {
        return app(AiCredentialManager::class)->isConnected($slug);
    }

    public function keyHintFor(string $slug): ?string
    {
        return app(AiCredentialManager::class)->credentialFor($slug)?->key_hint;
    }

    public function startConnect(string $slug): void
    {
        $this->activeProvider = $slug;
        $this->credentialValues = [];
    }

    public function startConfigure(string $slug): void
    {
        $this->activeProvider = $slug;
        // Pre-fill only non-secret fields so saving without touching the
        // password inputs preserves the stored key.
        $this->credentialValues = $this->nonSecretValues($slug);
    }

    public function connectProvider(): void
    {
        $meta = $this->providerMeta[$this->activeProvider] ?? null;

        if (! $meta) {
            return;
        }

        $rules = [];
        foreach ($meta['fields'] as $field) {
            if ($field['required'] ?? false) {
                $rules['credentialValues.'.$field['env']] = 'required|string';
            }
        }
        $this->validate($rules);

        // Encrypted at rest in the database — never written to `.env`.
        app(AiCredentialManager::class)->put($this->activeProvider, $this->credentialValues);

        Flux::modal('connect-provider')->close();

        Flux::toast(variant: 'success', text: "{$meta['name']} connected successfully.");

        $this->activeProvider = '';
        $this->credentialValues = [];

        unset($this->providers, $this->stats);
    }

    public function updateProvider(): void
    {
        $meta = $this->providerMeta[$this->activeProvider] ?? null;

        if (! $meta) {
            return;
        }

        $values = $this->credentialValues;

        // Empty password inputs mean "keep the stored secret", not "clear it".
        // Clearing happens explicitly via disconnect.
        $passwordKeys = collect($meta['fields'])
            ->where('type', 'password')
            ->pluck('env')
            ->all();

        foreach ($passwordKeys as $key) {
            if (! filled($values[$key] ?? null)) {
                unset($values[$key]);
            }
        }

        app(AiCredentialManager::class)->put($this->activeProvider, $values);

        Flux::modal('configure-provider')->close();

        Flux::toast(variant: 'success', text: "{$meta['name']} updated successfully.");

        $this->activeProvider = '';
        $this->credentialValues = [];

        unset($this->providers, $this->stats);
    }

    public function disconnectProvider(string $slug): void
    {
        $meta = $this->providerMeta[$slug] ?? null;

        if (! $meta) {
            return;
        }

        app(AiCredentialManager::class)->remove($slug);

        Flux::modal('configure-provider')->close();

        Flux::toast(variant: 'success', text: "{$meta['name']} disconnected.");

        $this->activeProvider = '';

        unset($this->providers, $this->stats);
    }

    public function getActiveMeta(): ?array
    {
        return $this->providerMeta[$this->activeProvider] ?? null;
    }

    /**
     * Non-secret stored values for pre-filling the edit form.
     *
     * @return array<string, ?string>
     */
    private function nonSecretValues(string $slug): array
    {
        $manager = app(AiCredentialManager::class);
        $credential = $manager->credentialFor($slug);

        if (! $credential) {
            return [];
        }

        $map = AiCredentialManager::fieldMap()[$slug] ?? [];
        $values = [];

        foreach ($map as $inputKey => $target) {
            if (str_starts_with($target, 'extra.')) {
                $extraKey = substr($target, strlen('extra.'));
                $value = $credential->extra[$extraKey] ?? null;
                // Never pre-fill stored secrets (AWS secret key); only plain settings like region.
                if ($extraKey !== 'secret_access_key' && is_string($value)) {
                    $values[$inputKey] = $value;
                }
            } elseif ($target === 'base_url' && is_string($credential->base_url)) {
                $values[$inputKey] = $credential->base_url;
            }
        }

        return $values;
    }
};
?>

<x-pages::settings.ai-layout>
<div>
    <section class="grid gap-x-8 gap-y-5 border-t border-zinc-200 py-8 first:border-t-0 first:pt-0 lg:grid-cols-[18rem_1fr]">
        <div>
            <h2 class="text-sm font-medium text-zinc-900">AI providers</h2>
            <p class="mt-1 text-sm text-zinc-500">Manage API keys, endpoints, and credentials for language, reasoning, and multimodal providers.</p>
            <p class="mt-3 text-sm text-zinc-500">
                <span class="font-medium text-zinc-900 tabular-nums">{{ $this->stats['connected'] }}</span>
                of
                <span class="tabular-nums">{{ $this->stats['total'] }}</span>
                connected
            </p>
        </div>

        {{-- Filter & grid --}}
        <div
            x-data="{
                query: '',
                filter: 'all',
                matches(name, description, isConnected) {
                    const q = this.query.trim().toLowerCase();
                    const matchesText = !q || name.toLowerCase().includes(q) || (description ?? '').toLowerCase().includes(q);

                    if (this.filter === 'connected') return matchesText && isConnected;
                    if (this.filter === 'available') return matchesText && !isConnected;
                    return matchesText;
                }
            }"
            class="flex min-w-0 flex-col gap-4"
        >
            {{-- Toolbar --}}
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div class="relative w-full sm:w-72">
                    <flux:icon.magnifying-glass variant="micro" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400" />
                    <input
                        x-model="query"
                        type="text"
                        placeholder="Filter providers or models..."
                        aria-label="Filter providers"
                        class="ui-input pr-8 pl-9"
                    />
                    <button
                        type="button"
                        x-show="query"
                        x-cloak
                        @click="query = ''"
                        aria-label="Clear filter"
                        class="absolute top-1/2 right-2 flex size-5 -translate-y-1/2 items-center justify-center rounded text-zinc-400 transition-colors duration-150 hover:text-zinc-700"
                    >
                        <flux:icon.x-mark variant="micro" class="size-3.5" />
                    </button>
                </div>

                <div class="inline-flex self-start rounded-lg bg-zinc-100 p-0.5 sm:self-auto" role="group" aria-label="Filter by status">
                    <button
                        type="button"
                        @click="filter = 'all'"
                        :aria-pressed="filter === 'all'"
                        :class="filter === 'all' ? 'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' : 'text-zinc-500 hover:text-zinc-900'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors duration-150"
                    >All</button>
                    <button
                        type="button"
                        @click="filter = 'connected'"
                        :aria-pressed="filter === 'connected'"
                        :class="filter === 'connected' ? 'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' : 'text-zinc-500 hover:text-zinc-900'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors duration-150"
                    >Connected</button>
                    <button
                        type="button"
                        @click="filter = 'available'"
                        :aria-pressed="filter === 'available'"
                        :class="filter === 'available' ? 'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' : 'text-zinc-500 hover:text-zinc-900'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors duration-150"
                    >Available</button>
                </div>
            </div>

            {{-- Provider cards --}}
            <div x-ref="grid" class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,16rem),1fr))] gap-4">
                @foreach ($this->providers as $provider)
                    <div
                        wire:key="provider-{{ $provider['slug'] }}"
                        x-show="matches('{{ addslashes($provider['name']) }}', '{{ addslashes($provider['description'] ?? '') }}', {{ $provider['is_connected'] ? 'true' : 'false' }})"
                        x-cloak
                        class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white"
                    >
                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div @class([
                                        'flex size-9 shrink-0 items-center justify-center rounded-lg p-1.5 ring-1 ring-inset',
                                        $provider['logo'] === 'eleven-labs' ? 'bg-zinc-900 ring-zinc-900' : 'bg-white ring-zinc-200',
                                    ])>
                                        <img
                                            src="/images/providers/{{ $provider['logo'] }}.svg"
                                            alt="{{ $provider['name'] }} logo"
                                            loading="lazy"
                                            class="size-full object-contain"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        />
                                        <div class="hidden size-full items-center justify-center text-zinc-400">
                                            <flux:icon.sparkles variant="micro" />
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-medium text-zinc-900">{{ $provider['name'] }}</h3>
                                        @if ($provider['website_url'])
                                            <a href="{{ $provider['website_url'] }}" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-0.5 text-xs text-zinc-500 transition-colors duration-150 hover:text-zinc-900">
                                                Console
                                                <flux:icon.arrow-up-right variant="micro" class="size-3" />
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                @if ($provider['is_connected'])
                                    <x-status-badge status="connected" class="shrink-0" />
                                @endif
                            </div>

                            <p class="line-clamp-2 min-h-10 text-sm text-zinc-500">{{ $provider['description'] }}</p>

                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($provider['models'], 0, 3) as $model)
                                    <span class="inline-flex items-center rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600">{{ $model }}</span>
                                @endforeach
                                @if (count($provider['models']) > 3)
                                    <span class="inline-flex items-center px-1 py-0.5 text-xs text-zinc-500">+{{ count($provider['models']) - 3 }} more</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-4 py-2.5">
                            <span class="text-xs text-zinc-500 tabular-nums">
                                {{ count($provider['fields']) }} {{ Str::plural('field', count($provider['fields'])) }}
                            </span>

                            @if ($provider['is_connected'])
                                <flux:button
                                    x-on:click="$wire.startConfigure('{{ $provider['slug'] }}'); $flux.modal('configure-provider').show()"
                                    variant="ghost"
                                    size="sm"
                                    icon="cog-6-tooth"
                                >
                                    Settings
                                </flux:button>
                            @else
                                <flux:button
                                    x-on:click="$wire.startConnect('{{ $provider['slug'] }}'); $flux.modal('connect-provider').show()"
                                    size="sm"
                                    icon="plus"
                                >
                                    Connect
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Empty filter state --}}
            <x-empty-state
                x-show="! Array.from($refs.grid.children).some(el => el.style.display !== 'none')"
                x-cloak
                icon="magnifying-glass"
                title="No matching providers"
                description="Try a different name, or clear the filters."
                class="rounded-xl border border-dashed border-zinc-200"
            >
                <flux:button size="sm" variant="ghost" x-on:click="query = ''; filter = 'all'">Clear filters</flux:button>
            </x-empty-state>
        </div>
    </section>

    {{-- Connect modal --}}
    <flux:modal name="connect-provider" class="md:w-[28rem]">
        <div class="flex flex-col gap-6">
            <div class="flex items-start gap-3">
                <div @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-lg p-1.5 ring-1 ring-inset',
                    ($this->getActiveMeta()['logo'] ?? null) === 'eleven-labs' ? 'bg-zinc-900 ring-zinc-900' : 'bg-white ring-zinc-200',
                ])>
                    <img
                        src="/images/providers/{{ $this->getActiveMeta()['logo'] ?? 'sparkles' }}.svg"
                        alt=""
                        class="size-full object-contain"
                        onerror="this.style.display='none';"
                    />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">Connect {{ $this->getActiveMeta()['name'] ?? '' }}</flux:heading>
                    @if ($this->getActiveMeta()['description'] ?? null)
                        <flux:text class="mt-1">{{ $this->getActiveMeta()['description'] }}</flux:text>
                    @endif
                </div>
            </div>

            <form wire:submit="connectProvider" class="flex flex-col gap-6">
                @if ($this->getActiveMeta())
                    @foreach ($this->getActiveMeta()['fields'] as $field)
                        <flux:field>
                            <flux:label>
                                {{ $field['label'] }}
                                @if (! ($field['required'] ?? false))
                                    <span class="ms-1 font-normal text-zinc-500">(optional)</span>
                                @endif
                            </flux:label>
                            <flux:input
                                wire:model="credentialValues.{{ $field['env'] }}"
                                type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                autocomplete="off"
                            />
                        </flux:field>
                    @endforeach
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button x-on:click="$flux.modal('connect-provider').close()" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save connection</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Configure modal --}}
    <flux:modal name="configure-provider" class="md:w-[28rem]">
        <div class="flex flex-col gap-6">
            <div class="flex items-start gap-3">
                <div @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-lg p-1.5 ring-1 ring-inset',
                    ($this->getActiveMeta()['logo'] ?? null) === 'eleven-labs' ? 'bg-zinc-900 ring-zinc-900' : 'bg-white ring-zinc-200',
                ])>
                    <img
                        src="/images/providers/{{ $this->getActiveMeta()['logo'] ?? 'sparkles' }}.svg"
                        alt=""
                        class="size-full object-contain"
                        onerror="this.style.display='none';"
                    />
                </div>
                <div class="flex min-w-0 flex-col items-start gap-1">
                    <flux:heading size="lg">{{ $this->getActiveMeta()['name'] ?? '' }}</flux:heading>
                    <x-status-badge status="connected" />
                </div>
            </div>

            @if ($this->getActiveMeta())
                <div class="overflow-hidden rounded-lg border border-zinc-200">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-3.5 py-2">
                        <span class="text-xs font-medium text-zinc-700">Supported models</span>
                        <span class="text-xs text-zinc-500 tabular-nums">{{ count($this->getActiveMeta()['models']) }} total</span>
                    </div>
                    <ul class="max-h-48 divide-y divide-zinc-100 overflow-y-auto [scrollbar-width:thin]">
                        @foreach ($this->getActiveMeta()['models'] as $model)
                            <li class="px-3.5 py-2 text-sm text-zinc-700">{{ $model }}</li>
                        @endforeach
                    </ul>
                </div>

                <p class="ui-inset p-3 text-xs text-zinc-500">
                    Credentials are encrypted in the database
                    @if ($this->keyHintFor($this->activeProvider))
                        (key ending in <code class="ui-code">{{ $this->keyHintFor($this->activeProvider) }}</code>)
                    @endif
                    and applied immediately, with no <code class="ui-code">.env</code> edits needed.
                </p>

                <form id="configure-provider-form" wire:submit="updateProvider" class="flex flex-col gap-6">
                    @foreach ($this->getActiveMeta()['fields'] as $field)
                        <flux:field>
                            <flux:label>
                                {{ $field['label'] }}
                                @if (! ($field['required'] ?? false))
                                    <span class="ms-1 font-normal text-zinc-500">(optional)</span>
                                @endif
                            </flux:label>
                            <flux:input
                                wire:model="credentialValues.{{ $field['env'] }}"
                                type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                                placeholder="{{ $field['type'] === 'password' ? 'Leave blank to keep current secret' : ($field['placeholder'] ?? '') }}"
                                autocomplete="off"
                            />
                        </flux:field>
                    @endforeach
                </form>
            @endif

            <div class="flex items-center justify-between gap-2">
                <flux:button
                    wire:click="disconnectProvider('{{ $this->activeProvider }}')"
                    wire:confirm="Are you sure you want to disconnect {{ $this->getActiveMeta()['name'] ?? '' }}?"
                    variant="ghost"
                    icon="trash"
                    class="text-red-700! hover:bg-red-50!"
                >
                    Disconnect
                </flux:button>

                <div class="flex gap-2">
                    <flux:button x-on:click="$flux.modal('configure-provider').close()" variant="ghost">Done</flux:button>
                    @if ($this->getActiveMeta())
                        <flux:button type="submit" form="configure-provider-form" variant="primary">Save changes</flux:button>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>
</div>
</x-pages::settings.ai-layout>
