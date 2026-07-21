<?php

namespace Database\Factories;

use App\Models\Run;
use App\Models\Script;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'script_id' => Script::factory(),
            'status' => 'queued',
            'triggered_by' => 'manual',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'duration_seconds' => fake()->numberBetween(30, 300),
            'vus_max' => fake()->numberBetween(10, 100),
            'requests_total' => fake()->numberBetween(1000, 50000),
            'requests_per_second' => fake()->randomFloat(2, 50, 500),
            'req_duration_p95_ms' => fake()->randomFloat(2, 10, 500),
            'req_duration_p99_ms' => fake()->randomFloat(2, 50, 1000),
            'error_rate' => fake()->randomFloat(2, 0, 5),
            'checks_total' => fake()->numberBetween(10, 100),
            'checks_failed' => 0,
            'thresholds_passed' => true,
        ];
    }

    /**
     * Run has passed.
     */
    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'passed',
            'thresholds_passed' => true,
            'error_rate' => fake()->randomFloat(2, 0, 1),
            'exit_code' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Run has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'thresholds_passed' => false,
            'error_rate' => fake()->randomFloat(2, 5, 20),
            'exit_code' => 1,
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Run is currently running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now()->subMinute(),
            'completed_at' => null,
            'duration_seconds' => null,
            'error_rate' => null,
            'exit_code' => null,
        ]);
    }

    /**
     * Run is queued (not yet started).
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'started_at' => null,
            'completed_at' => null,
            'duration_seconds' => null,
            'error_rate' => null,
            'vus_max' => null,
            'requests_total' => null,
            'requests_per_second' => null,
            'req_duration_p95_ms' => null,
            'req_duration_p99_ms' => null,
            'checks_total' => null,
            'checks_failed' => null,
            'thresholds_passed' => null,
            'exit_code' => null,
        ]);
    }

    /**
     * Run errored out.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'exit_code' => 2,
            'error_message' => fake()->sentence(),
        ]);
    }
}
