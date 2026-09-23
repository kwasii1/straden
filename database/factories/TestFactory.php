<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(3, true),
            'target_url' => fake()->url(),
            'description' => fake()->sentence(),
        ];
    }
}
