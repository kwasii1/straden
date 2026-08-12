<?php

use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
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

    public function isProviderConnected(string $slug): bool
    {
        return ! empty($this->getConnectionStatusEnvValue($slug));
    }

    private function getConnectionStatusEnvValue(string $slug): ?string
    {
        $meta = $this->providerMeta[$slug] ?? null;

        if (! $meta) {
            return null;
        }

        $firstRequired = collect($meta['fields'])->firstWhere('required', true);

        if (! $firstRequired) {
            return null;
        }

        return env($firstRequired['env']);
    }

    public function startConnect(string $slug): void
    {
        $this->activeProvider = $slug;
        $this->credentialValues = [];
    }

    public function startConfigure(string $slug): void
    {
        $this->activeProvider = $slug;
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

        foreach ($meta['fields'] as $field) {
            $envKey = $field['env'];
            if (array_key_exists($envKey, $this->credentialValues) && $this->credentialValues[$envKey] !== null) {
                $this->setEnvValue($envKey, $this->credentialValues[$envKey]);
            }
        }

        Flux::modal('connect-provider')->close();

        Flux::toast(variant: 'success', text: "{$meta['name']} connected successfully.");

        $this->activeProvider = '';
        $this->credentialValues = [];

        unset($this->providers);
    }

    public function disconnectProvider(string $slug): void
    {
        $meta = $this->providerMeta[$slug] ?? null;

        if (! $meta) {
            return;
        }

        foreach ($meta['fields'] as $field) {
            $this->removeEnvValue($field['env']);
        }

        Flux::modal('configure-provider')->close();

        Flux::toast(variant: 'success', text: "{$meta['name']} disconnected.");

        $this->activeProvider = '';

        unset($this->providers);
    }

    public function getActiveMeta(): ?array
    {
        return $this->providerMeta[$this->activeProvider] ?? null;
    }

    private function setEnvValue(string $key, ?string $value): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return;
        }

        if ($value !== null && str_contains($value, ' ')) {
            $value = '"'.$value.'"';
        }

        $content = file_get_contents($path);

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            $content = rtrim($content)."\n{$key}={$value}\n";
        }

        file_put_contents($path, $content);

        Artisan::call('config:clear');
    }

    private function removeEnvValue(string $key): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}=", $content);
        }

        file_put_contents($path, $content);

        Artisan::call('config:clear');
    }
};
?>

<div class="flex flex-col gap-y-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div class="flex flex-col">
            <flux:heading size="xl">AI Providers</flux:heading>
            <flux:text>Connect AI providers by adding their API keys. These connections are available across all projects.</flux:text>
        </div>
    </div>

    <div
        x-data="{
            query: '',
            get filtered() {
                const q = this.query.trim().toLowerCase();
                if (! q) return this.$refs.grid.children.length;
                return null; // filtering is handled via x-show per-card below
            },
            matches(name, description) {
                const q = this.query.trim().toLowerCase();
                if (! q) return true;
                return name.toLowerCase().includes(q) || (description ?? '').toLowerCase().includes(q);
            }
        }"
        class="flex flex-col gap-y-5"
    >
        {{-- Client-side search — filters the already-rendered cards, no server round trip --}}
        <div class="relative max-w-sm">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400" />
            <input
                x-model="query"
                type="text"
                placeholder="Search providers..."
                class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pl-9 pr-8 py-2 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-300 dark:focus:ring-zinc-700"
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

        <div x-ref="grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($this->providers as $provider)
                <div
                    wire:key="provider-{{ $provider['slug'] }}"
                    x-show="matches('{{ addslashes($provider['name']) }}', '{{ addslashes($provider['description'] ?? '') }}')"
                    x-cloak
                    class="flex flex-col rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-white dark:bg-zinc-900"
                >
                    <div class="flex items-start gap-x-4 p-4">
                        {{-- Real brand logo, with graceful fallback to a generic icon if the SVG fails to load --}}
                        <div @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-lg border p-2',
                            $provider['logo'] === 'eleven-labs'
                                ? 'bg-black border-zinc-700'
                                : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-100 dark:border-zinc-700',
                        ])>
                            <img
                                src="/storage/images/providers/{{ $provider['logo'] }}.svg"
                                alt="{{ $provider['name'] }} logo"
                                loading="lazy"
                                class="size-full object-contain dark:invert-0"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            />
                            <div class="hidden size-full items-center justify-center text-zinc-400 dark:text-zinc-500">
                                <flux:icon.sparkles class="size-5" />
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-x-2">
                                <flux:heading class="font-medium">{{ $provider['name'] }}</flux:heading>
                                @if ($provider['is_connected'])
                                    <flux:badge size="sm" variant="subtle" color="emerald">Connected</flux:badge>
                                @endif
                            </div>
                            @if ($provider['description'])
                                <flux:text class="text-xs mt-1">
                                    {{ $provider['description'] }}
                                </flux:text>
                            @endif
                            <flux:text class="text-xs text-zinc-400 mt-1">
                                {{ count($provider['models']) }} {{ Str::plural('model', count($provider['models'])) }}
                            </flux:text>
                        </div>

                        <div class="flex shrink-0">
                            @if ($provider['is_connected'])
                                <flux:button
                                    x-on:click="$wire.startConfigure('{{ $provider['slug'] }}'); $flux.modal('configure-provider').show()"
                                    variant="ghost"
                                    size="sm"
                                    icon="cog-6-tooth"
                                >
                                    Configure
                                </flux:button>
                            @else
                                <flux:button
                                    x-on:click="$wire.startConnect('{{ $provider['slug'] }}'); $flux.modal('connect-provider').show()"
                                    variant="primary"
                                    size="sm"
                                >
                                    Connect
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Empty search state --}}
        <div
            x-show="query && ! Array.from($refs.grid.children).some(el => el.style.display !== 'none')"
            x-cloak
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <flux:icon.magnifying-glass class="size-6 text-zinc-300 dark:text-zinc-600 mb-2" />
            <flux:text class="text-sm text-zinc-500">No providers match "<span x-text="query"></span>".</flux:text>
        </div>
    </div>

    <flux:modal name="connect-provider" class="md:w-1/3">
        <div class="space-y-6">
            <div class="flex items-center gap-x-3">
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
                        <flux:text class="mt-0.5 text-xs">{{ $this->getActiveMeta()['description'] }}</flux:text>
                    @endif
                </div>
            </div>

            <form wire:submit="connectProvider" class="space-y-6">
                @if ($this->getActiveMeta())
                    @foreach ($this->getActiveMeta()['fields'] as $field)
                        <flux:field>
                            <flux:label>
                                {{ $field['label'] }}
                                @if (! ($field['required'] ?? false))
                                    <span class="text-xs text-zinc-400">(optional)</span>
                                @endif
                            </flux:label>
                            <flux:input
                                wire:model="credentialValues.{{ $field['env'] }}"
                                type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                            />
                        </flux:field>
                    @endforeach
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Connect</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="configure-provider" class="md:w-1/3">
        <div class="space-y-6">
            <div class="flex items-center gap-x-3">
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
                    <flux:text class="text-xs">Connected &middot; {{ count($this->getActiveMeta()['fields'] ?? []) }} credential(s) configured</flux:text>
                </div>
            </div>

            @if ($this->getActiveMeta())
                <div class="border rounded-xl dark:border-zinc-700 overflow-hidden">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border-b dark:border-zinc-700">
                        <flux:text class="text-xs font-medium">Available Models</flux:text>
                    </div>
                    <div class="divide-y dark:divide-zinc-700">
                        @foreach ($this->getActiveMeta()['models'] as $model)
                            <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-x-2">
                                    <div class="size-1.5 rounded-full bg-emerald-500"></div>
                                    <flux:text class="text-sm">{{ $model }}</flux:text>
                                </div>
                                <flux:text class="text-xs text-zinc-400">Available</flux:text>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3">
                    <flux:text class="text-xs text-zinc-500">
                        Credentials stored in <code class="text-xs bg-zinc-200 dark:bg-zinc-700 px-1 rounded">.env</code>.
                        Run <code class="text-xs bg-zinc-200 dark:bg-zinc-700 px-1 rounded">php artisan config:cache</code> in production after making changes.
                    </flux:text>
                </div>
            @endif

            <div class="flex items-center justify-between pt-2">
                <flux:button
                    wire:click="disconnectProvider('{{ $this->activeProvider }}')"
                    wire:confirm="Are you sure you want to disconnect {{ $this->getActiveMeta()['name'] ?? '' }}?"
                    variant="ghost"
                    size="sm"
                    icon="trash"
                    class="text-red-500 hover:text-red-600"
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
