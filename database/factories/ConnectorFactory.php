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
}
