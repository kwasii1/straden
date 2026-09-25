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
<div class="flex flex-col gap-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-5">
        <div>
            <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">AI Integrations</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Manage API keys, endpoints, and credentials for language, reasoning, and multimodal providers.</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="filled" size="sm" icon="question-mark-circle" class="shrink-0">Docs</flux:button>
        </div>
    </div>

    {{-- Stats Bar Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex items-center justify-between p-3.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white/60 dark:bg-zinc-900/60 backdrop-blur-xs">
            <span class="text-xs text-zinc-500 font-medium">Total Supported</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $this->stats['total'] }}</span>
        </div>
        <div class="flex items-center justify-between p-3.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10">
            <div class="flex items-center gap-2">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                </span>
                <span class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">Active & Connected</span>
            </div>
            <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 font-mono">{{ $this->stats['connected'] }}</span>
        </div>
        <div class="flex items-center justify-between p-3.5 rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white/60 dark:bg-zinc-900/60 backdrop-blur-xs">
            <span class="text-xs text-zinc-500 font-medium">Available to Setup</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $this->stats['available'] }}</span>
        </div>
    </div>

    {{-- Filter & Grid Control --}}
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
        class="flex flex-col gap-y-5"
    >
        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
                <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400" />
                <input
                    x-model="query"
                    type="text"
                    placeholder="Filter providers or models..."
                    class="w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/80 pl-9 pr-8 py-1.5 text-xs text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-400 dark:focus:ring-zinc-600 transition-all"
                />
                <button
                    type="button"
                    x-show="query"
                    x-cloak
                    @click="query = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                >
                    <flux:icon.x-mark class="size-3.5" />
                </button>
            </div>

            <div class="inline-flex rounded-lg border border-zinc-200 dark:border-zinc-800 p-0.5 bg-zinc-100/60 dark:bg-zinc-900/60 self-start sm:self-auto">
                <button
                    @click="filter = 'all'"
                    :class="filter === 'all' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200'"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition-all"
                >All</button>
                <button
                    @click="filter = 'connected'"
                    :class="filter === 'connected' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200'"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition-all"
                >Connected</button>
                <button
                    @click="filter = 'available'"
                    :class="filter === 'available' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200'"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition-all"
                >Available</button>
            </div>
        </div>

        {{-- Cards Grid --}}
        <div x-ref="grid" class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,18rem),1fr))] gap-4">
            @foreach ($this->providers as $provider)
                <div
                    wire:key="provider-{{ $provider['slug'] }}"
                    x-show="matches('{{ addslashes($provider['name']) }}', '{{ addslashes($provider['description'] ?? '') }}', {{ $provider['is_connected'] ? 'true' : 'false' }})"
                    x-cloak
                    class="group relative flex flex-col justify-between rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900/90 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all duration-200 hover:shadow-xs overflow-hidden"
                >
                    <div class="p-4 space-y-3.5">
                        {{-- Top Meta: Logo, Name & Status --}}
                        <div class="flex items-start justify-between gap-x-3">
                            <div class="flex min-w-0 items-center gap-x-3">
                                <div @class([
                                    'flex size-9 shrink-0 items-center justify-center rounded-lg border p-1.5 transition-colors',
                                    $provider['logo'] === 'eleven-labs'
                                        ? 'bg-black border-zinc-700'
                                        : 'bg-zinc-50 dark:bg-zinc-800/80 border-zinc-100 dark:border-zinc-700/60 group-hover:border-zinc-300 dark:group-hover:border-zinc-600',
                                ])>
                                    <img
                                        src="/storage/images/providers/{{ $provider['logo'] }}.svg"
                                        alt="{{ $provider['name'] }} logo"
                                        loading="lazy"
                                        class="size-full object-contain dark:invert-0"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    />
                                    <div class="hidden size-full items-center justify-center text-zinc-400 dark:text-zinc-500">
                                        <flux:icon.sparkles class="size-4" />
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <flux:heading class="truncate font-medium text-sm text-zinc-900 dark:text-zinc-100 leading-tight">
                                        {{ $provider['name'] }}
                                    </flux:heading>
                                    @if ($provider['website_url'])
                                        <a href="{{ $provider['website_url'] }}" target="_blank" rel="noopener" class="text-[11px] text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 inline-flex items-center gap-0.5 mt-0.5">
                                            Console
                                            <flux:icon.arrow-up-right class="size-2.5" />
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0">
                                @if ($provider['is_connected'])
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium whitespace-nowrap text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Connected
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800/80 px-2 py-0.5 text-[11px] font-medium whitespace-nowrap text-zinc-500 dark:text-zinc-400 border border-zinc-200/50 dark:border-zinc-700/50">
                                        Not Configured
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Description --}}
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 leading-relaxed min-h-[2.25rem]">
                            {{ $provider['description'] }}
                        </p>

                        {{-- Model Tags Preview --}}
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach (array_slice($provider['models'], 0, 3) as $model)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-200/50 dark:border-zinc-700/50">
                                    {{ $model }}
                                </span>
                            @endforeach
                            @if (count($provider['models']) > 3)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-zinc-50 dark:bg-zinc-800/40 text-zinc-400 border border-zinc-200/30 dark:border-zinc-700/30">
                                    +{{ count($provider['models']) - 3 }} more
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Card Footer Action --}}
                    <div class="flex items-center justify-between px-4 py-2.5 bg-zinc-50/80 dark:bg-zinc-800/30 border-t border-zinc-100 dark:border-zinc-800/80">
                        <span class="text-[11px] text-zinc-400 font-mono">
                            {{ count($provider['fields']) }} {{ Str::plural('param', count($provider['fields'])) }}
                        </span>

                        @if ($provider['is_connected'])
                            <flux:button
                                x-on:click="$wire.startConfigure('{{ $provider['slug'] }}'); $flux.modal('configure-provider').show()"
                                variant="ghost"
                                size="sm"
                                icon="cog-6-tooth"
                                class="text-xs h-7"
                            >
                                Settings
                            </flux:button>
                        @else
                            <flux:button
                                x-on:click="$wire.startConnect('{{ $provider['slug'] }}'); $flux.modal('connect-provider').show()"
                                variant="primary"
                                size="sm"
                                icon="plus"
                                class="text-xs h-7"
                            >
                                Connect
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Empty Search State --}}
        <div
            x-show="query && ! Array.from($refs.grid.children).some(el => el.style.display !== 'none')"
            x-cloak
            class="flex flex-col items-center justify-center py-16 text-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800"
        >
            <flux:icon.magnifying-glass class="size-8 text-zinc-300 dark:text-zinc-600 mb-3" />
            <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">No matching AI providers</flux:heading>
            <flux:text class="text-xs text-zinc-400 mt-1">No provider results found for "<span x-text="query"></span>".</flux:text>
        </div>
    </div>

    {{-- Connect Modal --}}
    <flux:modal name="connect-provider" class="md:w-1/3">
        <div class="space-y-6">
            <div class="flex items-center gap-x-3 border-b dark:border-zinc-800 pb-4">
                <div @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-lg border p-1.5',
                    ($this->getActiveMeta()['logo'] ?? null) === 'eleven-labs'
                        ? 'bg-black border-zinc-700'
                        : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-100 dark:border-zinc-700',
                ])>
                    <img
                        src="/storage/images/providers/{{ $this->getActiveMeta()['logo'] ?? 'sparkles' }}.svg"
                        alt=""
                        class="size-full object-contain"
                        onerror="this.style.display='none';"
                    />
                </div>
                <div>
                    <flux:heading size="lg">Connect {{ $this->getActiveMeta()['name'] ?? '' }}</flux:heading>
                    @if ($this->getActiveMeta()['description'] ?? null)
                        <flux:text class="mt-0.5 text-xs text-zinc-500">{{ $this->getActiveMeta()['description'] }}</flux:text>
                    @endif
                </div>
            </div>

            <form wire:submit="connectProvider" class="space-y-4">
                @if ($this->getActiveMeta())
                    @foreach ($this->getActiveMeta()['fields'] as $field)
                        <flux:field>
                            <flux:label class="text-xs">
                                {{ $field['label'] }}
                                @if (! ($field['required'] ?? false))
                                    <span class="text-xs text-zinc-400 font-normal">(optional)</span>
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

                <div class="flex items-center justify-end gap-2 pt-4 border-t dark:border-zinc-800">
                    <flux:button x-on:click="$flux.modal('connect-provider').close()" variant="ghost" size="sm">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" size="sm">Save Connection</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Configure Modal --}}
    <flux:modal name="configure-provider" class="md:w-1/3 scrollbar-none [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-zinc-300 dark:[&::-webkit-scrollbar-thumb]:bg-zinc-600">
        <div class="space-y-5">
            <div class="flex items-center gap-x-3 border-b dark:border-zinc-800 pb-4">
                <div @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-lg border p-1.5',
                    ($this->getActiveMeta()['logo'] ?? null) === 'eleven-labs'
                        ? 'bg-black border-zinc-700'
                        : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-100 dark:border-zinc-700',
                ])>
                    <img
                        src="/storage/images/providers/{{ $this->getActiveMeta()['logo'] ?? 'sparkles' }}.svg"
                        alt=""
                        class="size-full object-contain"
                        onerror="this.style.display='none';"
                    />
                </div>
                <div>
                    <flux:heading size="lg">{{ $this->getActiveMeta()['name'] ?? '' }}</flux:heading>
                    <flux:text class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Connected & Active</flux:text>
                </div>
            </div>

            @if ($this->getActiveMeta())
                <div class="border rounded-xl dark:border-zinc-800 overflow-hidden bg-white dark:bg-zinc-900">
                    <div class="px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-800/50 border-b dark:border-zinc-800 flex items-center justify-between">
                        <flux:text class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Supported Models</flux:text>
                        <span class="text-[10px] font-mono text-zinc-400">{{ count($this->getActiveMeta()['models']) }} total</span>
                    </div>
                    <div class="divide-y dark:divide-zinc-800 max-h-48 overflow-y-auto [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-zinc-300 dark:[&::-webkit-scrollbar-thumb]:bg-zinc-600">
                        @foreach ($this->getActiveMeta()['models'] as $model)
                            <div class="flex items-center justify-between px-3.5 py-2">
                                <div class="flex items-center gap-x-2">
                                    <div class="size-1.5 rounded-full bg-emerald-500"></div>
                                    <flux:text class="text-xs font-mono">{{ $model }}</flux:text>
                                </div>
                                <span class="text-[10px] text-zinc-400">Ready</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-800/40 rounded-xl p-3 border border-zinc-200/60 dark:border-zinc-800">
                    <flux:text class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">
                        Credentials are encrypted in the database
                        @if ($this->keyHintFor($this->activeProvider))
                            (key ending in <code class="text-[10px] bg-zinc-200/70 dark:bg-zinc-700 px-1 py-0.5 rounded font-mono text-zinc-800 dark:text-zinc-200">{{ $this->keyHintFor($this->activeProvider) }}</code>)
                        @endif
                        and applied immediately — no <code class="text-[10px] bg-zinc-200/70 dark:bg-zinc-700 px-1 py-0.5 rounded font-mono text-zinc-800 dark:text-zinc-200">.env</code> edits needed.
                    </flux:text>
                </div>

                <form wire:submit="updateProvider" class="space-y-4">
                    @foreach ($this->getActiveMeta()['fields'] as $field)
                        <flux:field>
                            <flux:label class="text-xs">
                                {{ $field['label'] }}
                                @if (! ($field['required'] ?? false))
                                    <span class="text-xs text-zinc-400 font-normal">(optional)</span>
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

                    <flux:button type="submit" variant="primary" size="sm" class="w-full">Save Changes</flux:button>
                </form>
            @endif

            <div class="flex items-center justify-between pt-2 border-t dark:border-zinc-800">
                <flux:button
                    wire:click="disconnectProvider('{{ $this->activeProvider }}')"
                    wire:confirm="Are you sure you want to disconnect {{ $this->getActiveMeta()['name'] ?? '' }}?"
                    variant="ghost"
                    size="sm"
                    icon="trash"
                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 text-xs"
                >
                    Disconnect
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('configure-provider').close()"
                    variant="primary"
                    size="sm"
                >
                    Done
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
</x-pages::settings.ai-layout>
