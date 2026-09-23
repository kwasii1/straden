<?php

namespace Database\Factories;

use App\Models\AiProviderCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderCredential>
 */
class AiProviderCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'sk-test-'.fake()->lexify('????????????????');

        return [
            'provider' => fake()->unique()->randomElement(['openai', 'anthropic', 'deepseek', 'gemini']),
            'api_key' => $key,
            'base_url' => null,
            'extra' => null,
            'is_active' => true,
            'key_hint' => '••••'.substr($key, -4),
        ];
    }
}
