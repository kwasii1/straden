<?php

namespace Database\Factories;

use App\Models\Script;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Script>
 */
class ScriptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'disk' => 'local',
            'is_default' => false,
        ];
    }

    /**
     * Mark the script as the default for its test.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
