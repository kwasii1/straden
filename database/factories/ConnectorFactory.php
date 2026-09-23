<?php

namespace Database\Factories;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(3, true),
            'type' => ConnectorType::Database,
            'is_system' => false,
            'host' => fake()->ipv4(),
            'port' => 5432,
            'database' => fake()->word(),
            'ssl_enabled' => false,
            'verify_ssl' => false,
            'timeout' => 5,
            'username' => fake()->userName(),
            'password' => fake()->password(),
        ];
    }

    public function influxDb(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::InfluxDb,
            'is_system' => true,
            'host' => 'influxdb',
            'port' => 8086,
            'database' => 'k6',
            'username' => null,
            'password' => null,
            'token' => fake()->uuid(),
            'project_id' => null,
        ]);
    }

    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Database,
        ]);
    }

    public function prometheus(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Prometheus,
            'host' => fake()->domainName(),
            'port' => 9090,
            'database' => null,
        ]);
    }

    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::MySQL,
            'host' => fake()->ipv4(),
            'port' => 3306,
        ]);
    }

    public function postgres(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Postgres,
            'host' => fake()->ipv4(),
            'port' => 5432,
        ]);
    }

    public function mongodb(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::MongoDB,
            'host' => fake()->ipv4(),
            'port' => 27017,
        ]);
    }

    public function redis(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Redis,
            'host' => fake()->ipv4(),
            'port' => 6379,
            'database' => null,
            'username' => null,
        ]);
    }

    public function repository(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Repository,
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
        ]);
    }

    public function grafana(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Grafana,
            'host' => fake()->domainName(),
            'port' => 3000,
            'username' => fake()->userName(),
            'password' => fake()->password(),
        ]);
    }

    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::GitHub,
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
            'token' => 'ghp_test_token',
            'settings' => [
                'cached_repos' => [
                    [
                        'full_name' => 'test-owner/test-repo',
                        'clone_url' => 'https://github.com/test-owner/test-repo.git',
                        'default_branch' => 'main',
                        'private' => false,
                        'provider_id' => 123456,
                    ],
                ],
                'repos_fetched_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function gitlab(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::GitLab,
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
            'token' => 'glpat-test-token',
            'settings' => [
                'cached_repos' => [
                    [
                        'full_name' => 'test-group/test-project',
                        'clone_url' => 'https://gitlab.com/test-group/test-project.git',
                        'default_branch' => 'main',
                        'private' => false,
                        'provider_id' => 789012,
                    ],
                ],
                'repos_fetched_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function bitbucket(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::Bitbucket,
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
            'token' => 'ATBB-test-token',
            'settings' => [
                'workspace' => 'test-workspace',
                'cached_repos' => [
                    [
                        'full_name' => 'test-workspace/test-repo',
                        'clone_url' => 'https://bitbucket.org/test-workspace/test-repo.git',
                        'default_branch' => 'main',
                        'private' => false,
                        'provider_id' => 'test-workspace/test-repo',
                    ],
                ],
                'repos_fetched_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function azureDevOps(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectorType::AzureDevOps,
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
            'token' => 'ado-test-token',
            'settings' => [
                'organization' => 'test-org',
                'cached_projects' => [
                    ['name' => 'Test Project', 'description' => 'A test project'],
                ],
                'repos_fetched_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
