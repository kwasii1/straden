<?php

namespace Database\Factories;

use App\Models\Run;
use App\Models\RunInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunInsight>
 */
class RunInsightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'status' => 'completed',
            'report' => null,
            'error' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'report' => null,
            'error' => null,
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'generating',
            'report' => null,
            'error' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'report' => [
                'summary' => fake()->sentence(),
                'overall_health' => fake()->randomElement(['healthy', 'acceptable', 'poor']),
                'what_is_slow' => fake()->sentence(),
                'key_findings' => [
                    ['title' => fake()->words(3, true), 'severity' => 'medium', 'detail' => fake()->sentence()],
                ],
                'recommendations' => [
                    ['title' => fake()->words(3, true), 'impact' => fake()->sentence(), 'detail' => fake()->sentence()],
                ],
                'script_observations' => fake()->sentence(),
            ],
            'error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'report' => null,
            'error' => fake()->sentence(),
        ]);
    }
}
