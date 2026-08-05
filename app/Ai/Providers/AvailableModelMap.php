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
                'deepseek-chat',
                'deepseek-reasoner',
                'deepseek-v4-flash',
                'deepseek-v4-pro',
            ],
            'openai' => [
                'gpt-5.4',
                'gpt-5.4-nano',
                'gpt-5.4-pro',
                'gpt-4o',
                'gpt-4o-mini',
                'o1',
                'o1-mini',
                'o3',
                'o3-mini',
            ],
            'anthropic' => [
                'claude-sonnet-5',
                'claude-haiku-4-5-20251001',
                'claude-opus-4-5',
                'claude-sonnet-4',
                'claude-haiku-4',
            ],
            'gemini' => [
                'gemini-3-flash',
                'gemini-3-pro',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
            ],
            'groq' => [
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
                'mixtral-8x7b-32768',
                'gemma2-9b-it',
            ],
            'xai' => [
                'grok-3',
                'grok-2',
                'grok-2-vision',
            ],
            'mistral' => [
                'mistral-large-latest',
                'mistral-medium-latest',
                'mistral-small-latest',
                'codestral-latest',
            ],
            'ollama' => [
                'llama3',
                'mistral',
                'gemma2',
                'phi3',
                'codellama',
            ],
            'openrouter' => [
                'openai/gpt-4o',
                'anthropic/claude-sonnet-5',
                'google/gemini-3-flash',
                'meta-llama/llama-3.3-70b-instruct',
            ],
            'azure' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-5.4',
            ],
            'bedrock' => [
                'anthropic.claude-sonnet-5',
                'anthropic.claude-haiku-4-5',
                'meta.llama3-70b-instruct',
            ],
        ];
    }
}
