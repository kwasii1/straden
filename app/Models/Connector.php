<?php

namespace App\Models;

use App\Enums\ConnectorType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connector extends Model
{
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeOfType($query, ConnectorType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public static function influxDb(): self
    {
        return static::ofType(ConnectorType::InfluxDb)
            ->system()
            ->firstOrFail();
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
