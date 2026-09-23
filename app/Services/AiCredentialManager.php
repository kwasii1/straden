<?php

namespace App\Services;

use App\Models\AiProviderCredential;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Encrypted database store for AI provider credentials.
 *
 * Single source of truth: values are read from the `ai_provider_credentials`
 * table (encrypted at rest) and injected into `config('ai.providers.*')` at
 * runtime. Environment variables are intentionally NOT consulted, so keys never
 * need to live in plaintext `.env` files.
 */
class AiCredentialManager
{
    public const INSIGHTS_PROVIDER_KEY = 'insights.provider';

    public const INSIGHTS_MODEL_KEY = 'insights.model';

    /**
     * Map of provider => [env-style input key => storage target].
     *
     * Storage targets are `api_key`, `base_url`, or `extra.<key>`. The env-style
     * keys match the field names already used by the settings UI.
     *
     * @return array<string, array<string, string>>
     */
    public static function fieldMap(): array
    {
        return [
            'openai' => [
                'OPENAI_API_KEY' => 'api_key',
                'OPENAI_URL' => 'base_url',
            ],
            'anthropic' => [
                'ANTHROPIC_API_KEY' => 'api_key',
                'ANTHROPIC_URL' => 'base_url',
            ],
            'deepseek' => [
                'DEEPSEEK_API_KEY' => 'api_key',
            ],
            'gemini' => [
                'GEMINI_API_KEY' => 'api_key',
                'GEMINI_URL' => 'base_url',
            ],
            'groq' => [
                'GROQ_API_KEY' => 'api_key',
            ],
            'xai' => [
                'XAI_API_KEY' => 'api_key',
            ],
            'mistral' => [
                'MISTRAL_API_KEY' => 'api_key',
            ],
            'cohere' => [
                'COHERE_API_KEY' => 'api_key',
            ],
            'openrouter' => [
                'OPENROUTER_API_KEY' => 'api_key',
            ],
            'ollama' => [
                'OLLAMA_URL' => 'base_url',
                'OLLAMA_API_KEY' => 'api_key',
            ],
            'azure' => [
                'AZURE_OPENAI_API_KEY' => 'api_key',
                'AZURE_OPENAI_URL' => 'base_url',
                'AZURE_OPENAI_API_VERSION' => 'extra.api_version',
                'AZURE_OPENAI_DEPLOYMENT' => 'extra.deployment',
            ],
            'bedrock' => [
                'AWS_ACCESS_KEY_ID' => 'extra.access_key_id',
                'AWS_SECRET_ACCESS_KEY' => 'extra.secret_access_key',
                'AWS_BEDROCK_REGION' => 'extra.region',
                'AWS_BEARER_TOKEN_BEDROCK' => 'api_key',
            ],
            'openai-compatible' => [
                'OPENAI_COMPATIBLE_URL' => 'base_url',
                'OPENAI_COMPATIBLE_API_KEY' => 'api_key',
            ],
            'jina' => [
                'JINA_API_KEY' => 'api_key',
            ],
            'voyageai' => [
                'VOYAGEAI_API_KEY' => 'api_key',
            ],
            'eleven' => [
                'ELEVENLABS_API_KEY' => 'api_key',
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public static function supportedProviders(): array
    {
        return array_keys(static::fieldMap());
    }

    public function credentialFor(string $provider): ?AiProviderCredential
    {
        if (! in_array($provider, static::supportedProviders(), true)) {
            return null;
        }

        try {
            if (! Schema::hasTable((new AiProviderCredential)->getTable())) {
                return null;
            }

            return AiProviderCredential::query()
                ->where('provider', $provider)
                ->where('is_active', true)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function getKey(string $provider): ?string
    {
        return $this->credentialFor($provider)?->api_key;
    }

    /**
     * Whether the provider has enough stored secrets to be usable.
     */
    public function isConnected(string $provider): bool
    {
        $credential = $this->credentialFor($provider);

        if (! $credential) {
            return false;
        }

        return match ($provider) {
            'ollama', 'openai-compatible' => filled($credential->base_url),
            'azure' => filled($credential->api_key) && filled($credential->base_url),
            'bedrock' => (filled($credential->extra['access_key_id'] ?? null) && filled($credential->extra['secret_access_key'] ?? null))
                || filled($credential->api_key),
            default => filled($credential->api_key),
        };
    }

    /**
     * Store env-style input values encrypted, then inject them into runtime config.
     *
     * @param  array<string, ?string>  $values  e.g. ['OPENAI_API_KEY' => 'sk-...']
     */
    public function put(string $provider, array $values): AiProviderCredential
    {
        $mapping = static::fieldMap()[$provider] ?? null;

        if (! $mapping) {
            abort(422, "Unsupported AI provider [{$provider}].");
        }

        $apiKey = null;
        $baseUrl = null;
        $extra = $this->credentialFor($provider)?->extra ?? [];

        foreach ($mapping as $inputKey => $target) {
            if (! array_key_exists($inputKey, $values)) {
                continue;
            }

            $value = $values[$inputKey];
            $value = is_string($value) ? trim($value) : $value;
            $value = $value === '' ? null : $value;

            if ($target === 'api_key') {
                $apiKey = $value;
            } elseif ($target === 'base_url') {
                $baseUrl = $value;
            } elseif (str_starts_with($target, 'extra.')) {
                $extraKey = substr($target, strlen('extra.'));
                if ($value === null) {
                    unset($extra[$extraKey]);
                } else {
                    $extra[$extraKey] = $value;
                }
            }
        }

        $attributes = ['provider' => $provider];
        $payload = ['is_active' => true];

        // Only overwrite columns that were part of this submission so partial
        // updates (e.g. rotating just the key) preserve the other fields.
        if (array_key_exists('api_key', $this->submittedTargets($provider, $values))) {
            $payload['api_key'] = $apiKey;
            $payload['key_hint'] = filled($apiKey) ? '••••'.mb_substr($apiKey, -4) : null;
        }

        if (array_key_exists('base_url', $this->submittedTargets($provider, $values))) {
            $payload['base_url'] = $baseUrl;
        }

        if ($this->submittedExtra($provider, $values)) {
            $payload['extra'] = empty($extra) ? null : $extra;
        }

        $credential = AiProviderCredential::updateOrCreate($attributes, $payload);

        $this->syncProvider($provider);

        return $credential->refresh();
    }

    public function remove(string $provider): void
    {
        AiProviderCredential::query()->where('provider', $provider)->delete();

        $this->syncProvider($provider);
    }

    /**
     * The user-selected insights model, if one has been chosen in Settings.
     *
     * @return array{provider: string, model: string}|null
     */
    public function getInsightsSelection(): ?array
    {
        try {
            if (! Schema::hasTable((new AiSetting)->getTable())) {
                return null;
            }

            $settings = AiSetting::query()
                ->whereIn('key', [self::INSIGHTS_PROVIDER_KEY, self::INSIGHTS_MODEL_KEY])
                ->pluck('value', 'key');

            $provider = $settings[self::INSIGHTS_PROVIDER_KEY] ?? null;
            $model = $settings[self::INSIGHTS_MODEL_KEY] ?? null;

            if (! is_string($provider) || ! in_array($provider, static::supportedProviders(), true)) {
                return null;
            }

            if (! is_string($model) || trim($model) === '') {
                return null;
            }

            return ['provider' => $provider, 'model' => trim($model)];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether an insights model is selected AND its provider is connected.
     */
    public function isInsightsSelectionUsable(): bool
    {
        $selection = $this->getInsightsSelection();

        return $selection !== null && $this->isConnected($selection['provider']);
    }

    /**
     * Persist the user-selected insights model.
     */
    public function setInsightsSelection(string $provider, string $model): void
    {
        Validator::make(
            ['provider' => $provider, 'model' => $model],
            [
                'provider' => ['required', 'string', 'in:'.implode(',', static::supportedProviders())],
                'model' => ['required', 'string', 'max:255'],
            ]
        )->validate();

        AiSetting::updateOrCreate(['key' => self::INSIGHTS_PROVIDER_KEY], ['value' => $provider]);
        AiSetting::updateOrCreate(['key' => self::INSIGHTS_MODEL_KEY], ['value' => trim($model)]);
    }

    public function clearInsightsSelection(): void
    {
        AiSetting::query()
            ->whereIn('key', [self::INSIGHTS_PROVIDER_KEY, self::INSIGHTS_MODEL_KEY])
            ->delete();
    }

    /**
     * Inject every stored credential into runtime config.
     *
     * Safe to call before migrations have run (e.g. fresh install, config:cache).
     */
    public function syncConfig(): void
    {
        foreach (static::supportedProviders() as $provider) {
            $this->syncProvider($provider);
        }
    }

    public function syncProvider(string $provider): void
    {
        try {
            if (! Schema::hasTable((new AiProviderCredential)->getTable())) {
                return;
            }

            $credential = AiProviderCredential::query()
                ->where('provider', $provider)
                ->where('is_active', true)
                ->first();

            $this->applyToConfig($provider, $credential);
        } catch (Throwable) {
            // Never break boot/queue workers when the table is missing or locked.
        }
    }

    /**
     * @return array<string, string> storage target => flag
     */
    private function submittedTargets(string $provider, array $values): array
    {
        $mapping = static::fieldMap()[$provider] ?? [];
        $submitted = [];

        foreach ($mapping as $inputKey => $target) {
            if (array_key_exists($inputKey, $values) && ! str_starts_with($target, 'extra.')) {
                $submitted[$target] = $target;
            }
        }

        return $submitted;
    }

    private function submittedExtra(string $provider, array $values): bool
    {
        $mapping = static::fieldMap()[$provider] ?? [];

        foreach ($mapping as $inputKey => $target) {
            if (array_key_exists($inputKey, $values) && str_starts_with($target, 'extra.')) {
                return true;
            }
        }

        return false;
    }

    private function applyToConfig(string $provider, ?AiProviderCredential $credential): void
    {
        $extra = $credential?->extra ?? [];

        match ($provider) {
            'openai' => config([
                'ai.providers.openai.key' => $credential?->api_key,
                'ai.providers.openai.url' => $credential?->base_url ?? 'https://api.openai.com/v1',
            ]),
            'anthropic' => config([
                'ai.providers.anthropic.key' => $credential?->api_key,
                'ai.providers.anthropic.url' => $credential?->base_url ?? 'https://api.anthropic.com/v1',
            ]),
            'gemini' => config([
                'ai.providers.gemini.key' => $credential?->api_key,
                'ai.providers.gemini.url' => $credential?->base_url ?? 'https://generativelanguage.googleapis.com/v1beta/',
            ]),
            'ollama' => config([
                'ai.providers.ollama.key' => $credential?->api_key,
                'ai.providers.ollama.url' => $credential?->base_url ?? 'http://localhost:11434',
            ]),
            'openai-compatible' => config([
                'ai.providers.openai-compatible.key' => $credential?->api_key,
                'ai.providers.openai-compatible.url' => $credential?->base_url,
            ]),
            'azure' => config([
                'ai.providers.azure.key' => $credential?->api_key,
                'ai.providers.azure.url' => $credential?->base_url,
                'ai.providers.azure.api_version' => $extra['api_version'] ?? '2025-04-01-preview',
                'ai.providers.azure.deployment' => $extra['deployment'] ?? 'gpt-4o',
            ]),
            'bedrock' => config([
                'ai.providers.bedrock.key' => $credential?->api_key,
                'ai.providers.bedrock.region' => $extra['region'] ?? 'us-east-1',
                'ai.providers.bedrock.access_key_id' => $extra['access_key_id'] ?? null,
                'ai.providers.bedrock.secret_access_key' => $extra['secret_access_key'] ?? null,
            ]),
            'deepseek' => config(['ai.providers.deepseek.key' => $credential?->api_key]),
            'groq' => config(['ai.providers.groq.key' => $credential?->api_key]),
            'xai' => config(['ai.providers.xai.key' => $credential?->api_key]),
            'mistral' => config(['ai.providers.mistral.key' => $credential?->api_key]),
            'cohere' => config(['ai.providers.cohere.key' => $credential?->api_key]),
            'openrouter' => config(['ai.providers.openrouter.key' => $credential?->api_key]),
            'jina' => config(['ai.providers.jina.key' => $credential?->api_key]),
            'voyageai' => config(['ai.providers.voyageai.key' => $credential?->api_key]),
            'eleven' => config(['ai.providers.eleven.key' => $credential?->api_key]),
            default => null,
        };
    }
}
