<?php

namespace App\Models;

use App\Enums\ConnectorType;
use Database\Factories\ConnectorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'is_system',
        'host',
        'port',
        'database',
        'ssl_enabled',
        'verify_ssl',
        'timeout',
        'username',
        'password',
        'token',
        'settings',
        'last_tested_at',
        'last_test_successful',
        'last_test_error',
    ];

    protected $hidden = [
        'username',
        'password',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConnectorType::class,
            'is_system' => 'boolean',
            'ssl_enabled' => 'boolean',
            'verify_ssl' => 'boolean',
            'last_test_successful' => 'boolean',
            'last_tested_at' => 'datetime',
            'settings' => 'array',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'token' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<Test, $this> */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'connector_test');
    }

    /** @param  Builder<self>  $query */
    public function scopeOfType(Builder $query, ConnectorType $type): void
    {
        $query->where('type', $type);
    }

    /** @param  Builder<self>  $query */
    public function scopeSystem(Builder $query): void
    {
        $query->where('is_system', true);
    }

    public static function influxDb(): self
    {
        return static::ofType(ConnectorType::InfluxDb)
            ->system()
            ->firstOrFail();
    }

    /**
     * The host to dial. Inside Docker, "localhost" means the container itself, so
     * loopback hosts are mapped to the Docker host where users run their services.
     */
    public function connectionHost(): ?string
    {
        if (in_array($this->host, ['localhost', '127.0.0.1', '::1'], true) && is_file('/.dockerenv')) {
            return 'host.docker.internal';
        }

        return $this->host;
    }

    public function isInfluxDb(): bool
    {
        return $this->type === ConnectorType::InfluxDb;
    }

    public function isPrometheus(): bool
    {
        return $this->type === ConnectorType::Prometheus;
    }

    public function isObservability(): bool
    {
        return $this->type->isObservability();
    }

    public function isDatabase(): bool
    {
        return $this->type->isDatabase();
    }

    public function defaultPort(): ?int
    {
        return $this->type->defaultPort();
    }

    public function isGitProvider(): bool
    {
        return $this->type->isGitProvider();
    }

    public function gitProviderTypeLabel(): string
    {
        return $this->type->label();
    }
}
