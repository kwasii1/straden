<?php

namespace App\Models;

use App\Services\SlugGenerator;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUuids;

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->slug = SlugGenerator::unique($project->name, self::class);
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('name') && ! $project->isDirty('slug')) {
                $project->slug = SlugGenerator::unique($project->name, self::class, $project->id);
            }
        });
    }

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'persist_run_logs',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'persist_run_logs' => 'boolean',
            'last_accessed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Test, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'project_id');
    }

    /** @return HasMany<Repository, $this> */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /** @return HasManyThrough<Script, Test, $this> */
    public function scripts(): HasManyThrough
    {
        return $this->hasManyThrough(Script::class, Test::class);
    }

    /** @return HasMany<Connector, $this> */
    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class);
    }
}
