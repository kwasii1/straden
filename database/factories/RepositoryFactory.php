<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
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
            'type' => 'git',
            'git_url' => 'https://github.com/'.fake()->userName().'/'.fake()->slug(),
            'git_branch' => 'main',
            'git_auth_type' => 'none',
            'sync_status' => 'pending',
        ];
    }

    /**
     * Git repository state.
     */
    public function git(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'git',
            'local_path' => null,
        ]);
    }

    /**
     * Local path repository state.
     */
    public function localPath(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'local_path',
            'git_url' => null,
            'git_auth_type' => null,
            'git_branch' => 'main',
            'local_path' => '/var/www/'.fake()->slug(),
        ]);
    }

    /**
     * Synced repository state.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'file_tree' => [
                [
                    'name' => 'src',
                    'children' => [
                        ['name' => 'index.js'],
                        ['name' => 'utils.js'],
                    ],
                ],
                ['name' => 'README.md'],
                ['name' => 'package.json'],
            ],
        ]);
    }

    /**
     * Failed sync state.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'failed',
            'sync_error' => 'Repository not found or inaccessible.',
        ]);
    }
}
