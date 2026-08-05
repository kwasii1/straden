<?php

use App\Enums\ConnectorType;
use App\GitProviders\GitProviderResolver;
use App\Models\Connector;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public string $activeTab = 'ai-providers';

    public string $activeProvider = '';

    public array $credentialValues = [];

    public string $gitName = '';

    public string $gitType = '';

    public string $gitToken = '';

    public string $gitWorkspace = '';

    public string $gitOrganization = '';

    private array $providerMeta = [
        'openai' => [
            'name' => 'OpenAI',
            'description' => 'Advanced language models for text generation, image creation, and audio processing.',
            'sort_order' => 1,
            'website_url' => 'https://platform.openai.com',
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
                'fields' => $meta['fields'],
                'models' => $meta['models'],
                'is_connected' => $this->isProviderConnected($slug),
            ];
        }

        usort($result, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $result;
    }

    #[Computed]
    public function gitConnectors()
    {
        return Connector::query()
            ->where('project_id', $this->project->id)
            ->whereIn('type', [
                ConnectorType::GitHub->value,
                ConnectorType::GitLab->value,
                ConnectorType::Bitbucket->value,
                ConnectorType::AzureDevOps->value,
            ])
            ->orderBy('created_at', 'desc')
            ->get();
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

    public function getProviderIconColor(string $slug): string
    {
        return match ($slug) {
            'openai' => 'text-emerald-600 dark:text-emerald-400',
            'anthropic' => 'text-orange-600 dark:text-orange-400',
            'deepseek' => 'text-blue-600 dark:text-blue-400',
            'gemini' => 'text-amber-600 dark:text-amber-400',
            'groq' => 'text-rose-600 dark:text-rose-400',
            'xai' => 'text-zinc-600 dark:text-zinc-400',
            'mistral' => 'text-indigo-600 dark:text-indigo-400',
            'cohere' => 'text-teal-600 dark:text-teal-400',
            'openrouter' => 'text-sky-600 dark:text-sky-400',
            'ollama' => 'text-purple-600 dark:text-purple-400',
            'azure' => 'text-cyan-600 dark:text-cyan-400',
            'bedrock' => 'text-orange-700 dark:text-orange-500',
            'openai-compatible' => 'text-slate-600 dark:text-slate-400',
            'jina' => 'text-pink-600 dark:text-pink-400',
            'voyageai' => 'text-violet-600 dark:text-violet-400',
            'eleven' => 'text-lime-600 dark:text-lime-400',
            default => 'text-zinc-500 dark:text-zinc-400',
        };
    }

    public function getProviderIconBg(string $slug): string
    {
        return match ($slug) {
            'openai' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'anthropic' => 'bg-orange-100 dark:bg-orange-900/30',
            'deepseek' => 'bg-blue-100 dark:bg-blue-900/30',
            'gemini' => 'bg-amber-100 dark:bg-amber-900/30',
            'groq' => 'bg-rose-100 dark:bg-rose-900/30',
            'xai' => 'bg-zinc-100 dark:bg-zinc-700',
            'mistral' => 'bg-indigo-100 dark:bg-indigo-900/30',
            'cohere' => 'bg-teal-100 dark:bg-teal-900/30',
            'openrouter' => 'bg-sky-100 dark:bg-sky-900/30',
            'ollama' => 'bg-purple-100 dark:bg-purple-900/30',
            'azure' => 'bg-cyan-100 dark:bg-cyan-900/30',
            'bedrock' => 'bg-orange-100 dark:bg-orange-900/30',
            'openai-compatible' => 'bg-slate-100 dark:bg-slate-800',
            'jina' => 'bg-pink-100 dark:bg-pink-900/30',
            'voyageai' => 'bg-violet-100 dark:bg-violet-900/30',
            'eleven' => 'bg-lime-100 dark:bg-lime-900/30',
            default => 'bg-zinc-100 dark:bg-zinc-700',
        };
    }

    public function getGitTypeIconColor(string $type): string
    {
        return match ($type) {
            'github' => 'text-zinc-600 dark:text-zinc-400',
            'gitlab' => 'text-orange-600 dark:text-orange-400',
            'bitbucket' => 'text-blue-600 dark:text-blue-400',
            'azure_devops' => 'text-sky-600 dark:text-sky-400',
            default => 'text-zinc-500 dark:text-zinc-400',
        };
    }

    public function getTokenLabel(): string
    {
        return $this->gitType === 'bitbucket' ? 'App Password' : 'Personal Access Token';
    }

    public function getScopesHelpText(): string
    {
        if (! $this->gitType) {
            return '';
        }

        try {
            $provider = GitProviderResolver::for(ConnectorType::from($this->gitType));

            return $provider->requiredScopesHelpText();
        } catch (\Throwable) {
            return '';
        }
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

    public function addGitConnector(): void
    {
        $rules = [
            'gitName' => 'required|string|max:255',
            'gitType' => 'required|in:github,gitlab,bitbucket,azure_devops',
            'gitToken' => 'required|string|max:1024',
        ];

        if ($this->gitType === 'bitbucket') {
            $rules['gitWorkspace'] = 'required|string|max:255';
        }

        if ($this->gitType === 'azure_devops') {
            $rules['gitOrganization'] = 'required|string|max:255';
        }

        $this->validate($rules);

        $connectorType = ConnectorType::from($this->gitType);
        $provider = GitProviderResolver::for($connectorType);

        $context = [];
        if ($this->gitType === 'bitbucket') {
            $context['workspace'] = $this->gitWorkspace;
        }
        if ($this->gitType === 'azure_devops') {
            $context['organization'] = $this->gitOrganization;
        }

        try {
            $valid = $provider->validateToken($this->gitToken, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Token validation failed: '.$e->getMessage());

            return;
        }

        if (! $valid) {
            Flux::toast(variant: 'error', text: 'Invalid token. Please check your credentials and try again.');

            return;
        }

        try {
            $repos = $provider->listRepositories($this->gitToken, $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to list repositories: '.$e->getMessage());

            return;
        }

        $settings = [
            'repos_fetched_at' => now()->toIso8601String(),
        ];

        if ($this->gitType === 'bitbucket') {
            $settings['workspace'] = $this->gitWorkspace;
            $settings['cached_repos'] = $repos;
        } elseif ($this->gitType === 'azure_devops') {
            $settings['organization'] = $this->gitOrganization;
            $settings['cached_projects'] = $repos;
        } else {
            $settings['cached_repos'] = $repos;
        }

        $connector = Connector::create([
            'project_id' => $this->project->id,
            'name' => $this->gitName,
            'type' => $connectorType,
            'token' => $this->gitToken,
            'settings' => $settings,
        ]);

        Flux::modal('create-git-connector')->close();

        Flux::toast(variant: 'success', text: 'Git provider connected successfully.');

        $this->resetGitConnectorForm();

        unset($this->gitConnectors);

        $this->redirect(route('projects.repository-picker', [
            'project' => $this->project,
            'connector' => $connector,
        ]), navigate: true);
    }

    public function deleteGitConnector(Connector $connector): void
    {
        $connector->delete();

        Flux::toast(variant: 'success', text: 'Git provider removed.');

        unset($this->gitConnectors);
    }

    public function refreshGitConnectorRepos(Connector $connector): void
    {
        $provider = GitProviderResolver::for($connector->type);

        $context = [];
        $settings = $connector->settings ?? [];

        if ($connector->type === ConnectorType::Bitbucket) {
            $context['workspace'] = $settings['workspace'] ?? '';
        }
        if ($connector->type === ConnectorType::AzureDevOps) {
            $context['organization'] = $settings['organization'] ?? '';
        }

        try {
            $repos = $provider->listRepositories(decrypt($connector->token), $context);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'error', text: 'Failed to refresh repositories: '.$e->getMessage());

            return;
        }

        $settings['repos_fetched_at'] = now()->toIso8601String();

        if ($connector->type === ConnectorType::AzureDevOps) {
            $settings['cached_projects'] = $repos;
        } else {
            $settings['cached_repos'] = $repos;
        }

        $connector->update(['settings' => $settings]);

        Flux::toast(variant: 'success', text: 'Repositories refreshed successfully.');

        unset($this->gitConnectors);
    }

    public function updatedGitType(): void
    {
        $this->reset(['gitWorkspace', 'gitOrganization']);
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

    private function resetGitConnectorForm(): void
    {
        $this->reset(['gitName', 'gitType', 'gitToken', 'gitWorkspace', 'gitOrganization']);
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Project Settings</flux:heading>
        <flux:text>Configure AI providers and git provider connections for this project.</flux:text>
    </div>

    <x-pages::dashboard.settings-layout :activeTab="$activeTab">
        @if ($activeTab === 'ai-providers')
            <div class="space-y-10">
                <div class="flex flex-col">
                    <flux:heading size="lg">AI Providers</flux:heading>
                    <flux:text>Connect AI providers by adding their API keys. These connections are available across all projects.</flux:text>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($this->providers as $provider)
                        <div
                            wire:key="provider-{{ $provider['slug'] }}"
                            class="flex flex-col rounded-xl border dark:border-zinc-700 overflow-hidden"
                        >
                            <div class="flex items-start gap-x-4 p-4">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $this->getProviderIconBg($provider['slug']) }}">
                                    <flux:icon.sparkles class="size-5 {{ $this->getProviderIconColor($provider['slug']) }}" />
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

                <flux:modal name="connect-provider" class="md:w-1/3">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Connect {{ $this->getActiveMeta()['name'] ?? '' }}</flux:heading>
                            @if ($this->getActiveMeta()['description'] ?? null)
                                <flux:text class="mt-2">{{ $this->getActiveMeta()['description'] }}</flux:text>
                            @endif
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
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $this->getProviderIconBg($this->activeProvider) }}">
                                <flux:icon.sparkles class="size-4 {{ $this->getProviderIconColor($this->activeProvider) }}" />
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
        @else
            <div class="space-y-10">
                <div class="flex flex-col">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">Git Providers</flux:heading>
                            <flux:text>Connect to GitHub, GitLab, Bitbucket, or Azure DevOps to browse and sync repositories.</flux:text>
                        </div>
                        <flux:modal.trigger name="create-git-connector">
                            <flux:button variant="primary" icon="plus">Add Git Provider</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
                    @forelse ($this->gitConnectors as $connector)
                        <div wire:key="git-connector-{{ $connector->id }}" class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <div class="flex items-center gap-x-4">
                                <div class="flex items-center gap-x-3">
                                    <flux:icon.folder-git-2 class="size-5 {{ $this->getGitTypeIconColor($connector->type->value) }}" />

                                    <div>
                                        <flux:heading class="font-medium">{{ $connector->name }}</flux:heading>
                                        <div class="flex items-center gap-x-2 mt-0.5">
                                            <flux:badge size="sm" variant="subtle" color="zinc">
                                                {{ $connector->type->label() }}
                                            </flux:badge>

                                            @php
                                                $settings = $connector->settings ?? [];
                                                $repoCount = count($settings['cached_repos'] ?? []);
                                                $projectCount = count($settings['cached_projects'] ?? []);
                                            @endphp

                                            @if ($repoCount > 0)
                                                <flux:text class="text-xs">{{ $repoCount }} {{ Str::plural('repository', $repoCount) }} available</flux:text>
                                            @elseif ($projectCount > 0)
                                                <flux:text class="text-xs">{{ $projectCount }} {{ Str::plural('project', $projectCount) }} available</flux:text>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-x-2">
                                <flux:button
                                    wire:click="refreshGitConnectorRepos('{{ $connector->id }}')"
                                    variant="ghost"
                                    size="sm"
                                    icon="arrow-path"
                                >
                                    Refresh
                                </flux:button>

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="magnifying-glass"
                                    :href="route('projects.repository-picker', ['project' => $this->project, 'connector' => $connector])"
                                    wire:navigate
                                >
                                    Browse Repos
                                </flux:button>

                                <flux:button
                                    wire:click="deleteGitConnector('{{ $connector->id }}')"
                                    wire:confirm="Are you sure you want to remove this git provider connection?"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    class="text-red-500 hover:text-red-600"
                                />
                            </div>
                        </div>
                    @empty
                        <div class="flex h-40 w-full items-center justify-center">
                            <div class="flex flex-col items-center gap-y-2">
                                <flux:icon.folder-git-2 class="size-10 text-zinc-400" />
                                <flux:text class="text-center">
                                    No git providers connected.
                                </flux:text>
                                <flux:text class="text-center text-sm">
                                    Connect a git provider to browse and sync repositories.
                                </flux:text>
                            </div>
                        </div>
                    @endforelse
                </div>

                <flux:modal name="create-git-connector" class="md:w-1/2">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Add Git Provider</flux:heading>
                            <flux:text class="mt-2">Connect to a git provider using a personal access token.</flux:text>
                        </div>

                        <form wire:submit="addGitConnector" class="space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <flux:input wire:model="gitName" label="Connection Name" placeholder="Personal GitHub" />

                                <flux:select wire:model.live="gitType" label="Provider">
                                    <flux:select.option value="">Select a provider...</flux:select.option>
                                    <flux:select.option value="github">GitHub</flux:select.option>
                                    <flux:select.option value="gitlab">GitLab</flux:select.option>
                                    <flux:select.option value="bitbucket">Bitbucket</flux:select.option>
                                    <flux:select.option value="azure_devops">Azure DevOps</flux:select.option>
                                </flux:select>
                            </div>

                            @if ($gitType)
                                <flux:field>
                                    <flux:label>{{ $this->getTokenLabel() }}</flux:label>
                                    <flux:input wire:model="gitToken" type="password" placeholder="{{ $gitType === 'bitbucket' ? 'Enter your app password...' : 'Enter your personal access token...' }}" />
                                    <flux:description>{{ $this->getScopesHelpText() }}</flux:description>
                                </flux:field>

                                @if ($gitType === 'bitbucket')
                                    <flux:input wire:model="gitWorkspace" label="Workspace" placeholder="my-workspace" />
                                    <flux:description>Bitbucket requires a workspace to list repositories.</flux:description>
                                @endif

                                @if ($gitType === 'azure_devops')
                                    <flux:input wire:model="gitOrganization" label="Organization" placeholder="my-org" />
                                    <flux:description>Your Azure DevOps organization name (e.g. dev.azure.com/my-org).</flux:description>
                                @endif
                            @endif

                            <div class="flex">
                                <flux:spacer />
                                <flux:button type="submit" variant="primary">Connect</flux:button>
                            </div>
                        </form>
                    </div>
                </flux:modal>
            </div>
        @endif
    </x-pages::dashboard.settings-layout>
</div>
