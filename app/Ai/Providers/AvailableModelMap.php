<?php

namespace App\Ai\Providers;

class AvailableModelMap
{
    /**
     * Get the list of provider keys that support text generation.
     *
     * @return array<string>
     */
    public static function textProviders(): array
    {
        return [
            'anthropic',
            'azure',
            'bedrock',
            'deepseek',
            'gemini',
            'groq',
            'mistral',
            'ollama',
            'openai',
            'openai-compatible',
            'openrouter',
            'xai',
        ];
    }

    /**
     * Get the known text models for a given provider.
     *
     * @return array<string>
     */
    public static function modelsFor(string $provider): array
    {
        $configModels = static::modelsFromConfig($provider);
        $known = static::knownModels()[$provider] ?? [];

        return array_values(array_unique(array_merge($configModels, $known)));
    }

    /**
     * Extract models from the provider's ai.php configuration.
     *
     * @return array<string>
     */
    protected static function modelsFromConfig(string $provider): array
    {
        $models = [];
        $config = config("ai.providers.{$provider}.models.text", []);

        foreach (['default', 'cheapest', 'smartest'] as $key) {
            if (! empty($config[$key])) {
                $models[] = $config[$key];
            }
        }

        return $models;
    }

    /**
     * Get every known model for a provider from the synced models.dev catalog.
     *
     * @return array<string>
     */
    public static function catalogFor(string $provider): array
    {
        static $catalog = null;

        if ($catalog === null) {
            $path = resource_path('data/ai-models.json');
            $contents = is_file($path) ? file_get_contents($path) : false;
            $catalog = $contents === false ? [] : (json_decode($contents, true) ?: []);
        }

        return $catalog[$provider] ?? [];
    }

    /**
     * Get the featured models followed by the rest of the catalog.
     *
     * @return array<string>
     */
    public static function allModelsFor(string $provider): array
    {
        return array_values(array_unique(array_merge(
            static::modelsFor($provider),
            static::catalogFor($provider),
        )));
    }

    /**
     * Determine whether a model is featured or present in the full catalog.
     */
    public static function isKnownModel(string $provider, ?string $model): bool
    {
        return $model !== null
            && (in_array($model, static::modelsFor($provider), true)
                || in_array($model, static::catalogFor($provider), true));
    }

    /**
     * Get the display label for a provider key.
     */
    public static function labelFor(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Gemini',
            'azure' => 'Azure OpenAI',
            'groq' => 'Groq',
            'xai' => 'xAI',
            'deepseek' => 'DeepSeek',
            'mistral' => 'Mistral',
            'ollama' => 'Ollama',
            'openrouter' => 'OpenRouter',
            'openai-compatible' => 'OpenAI Compatible',
            'bedrock' => 'AWS Bedrock',
            default => ucfirst($provider),
        };
    }

    /**
     * Curated list of well-known text models per provider.
     *
     * @return array<string, array<string>>
     */
    protected static function knownModels(): array
    {
        return [
            'deepseek' => [
                'deepseek-flash',
                'deepseek-v4-pro',
            ],
            'openai' => [
                'gpt-6.1-sol',
                'gpt-6-sol',
                'gpt-6-luna',
                'gpt-6-astra',
                'gpt-5.6',
                'gpt-5.6-sol',
                'gpt-5.6-terra',
                'gpt-5.6-luna',
                'gpt-5.5',
                'gpt-5.5-pro',
                'gpt-5.4-mini',
                'gpt-5.4-nano',
            ],
            'anthropic' => [
                'claude-sonnet-5-5',
                'claude-opus-5-5',
                'claude-fable-5-1',
                'claude-opus-5',
                'claude-sonnet-5',
                'claude-fable-5',
                'claude-opus-4-8',
                'claude-opus-4-7',
                'claude-sonnet-4-6',
                'claude-opus-4-6',
                'claude-haiku-4-5-20251001',
            ],
            'gemini' => [
                'gemini-3.8-flash',
                'gemini-3.7-flash',
                'gemini-3.6-flash',
                'gemini-3.5-flash',
                'gemini-3.5-flash-lite',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
                'gemini-2.5-flash-lite',
            ],
            'groq' => [
                'qwen/qwen3.8-27b',
                'qwen/qwen3.6-27b',
                'openai/gpt-oss-120b',
                'openai/gpt-oss-20b',
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
            ],
            'xai' => [
                'grok-4.7',
                'grok-4.6',
                'grok-4.5',
                'grok-4.3',
                'grok-4.20-0309-reasoning',
                'grok-4.20-0309-non-reasoning',
            ],
            'mistral' => [
                'mistral-medium-latest',
                'mistral-small-latest',
                'mistral-large-latest',
                'magistral-medium-latest',
            ],
            'ollama' => [
                'llama3',
                'mistral',
                'gemma2',
                'phi3',
                'codellama',
            ],
            'openrouter' => [
                'openai/gpt-6.1-sol',
                'openai/gpt-6-luna',
                'anthropic/claude-sonnet-5.5',
                'anthropic/claude-opus-5.5',
                'x-ai/grok-4.7',
                'deepseek/deepseek-v4.1-flash',
                'qwen/qwen3.8-max-prime',
            ],
            'azure' => [
                'gpt-6.1-sol',
                'gpt-6-sol',
                'gpt-6-luna',
                'gpt-6-astra',
                'gpt-5.6-sol',
                'gpt-5.5',
                'gpt-5.4',
                'gpt-5.4-mini',
                'gpt-5.4-nano',
            ],
            'bedrock' => [
                'anthropic.claude-sonnet-5-5',
                'anthropic.claude-opus-5-5',
                'anthropic.claude-fable-5-1',
                'anthropic.claude-opus-5',
                'anthropic.claude-sonnet-5',
                'openai.gpt-6-sol',
                'openai.gpt-6-luna',
            ],
        ];
    }
}
