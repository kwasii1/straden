<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'connector_id',
        'name',
        'type',
        'full_name',
        'git_url',
        'git_branch',
        'local_path',
        'sync_status',
        'sync_error',
        'last_synced_at',
        'last_commit_sha',
        'cloned_at',
        'file_tree',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'cloned_at' => 'datetime',
            'file_tree' => 'array',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Connector, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    /** @return BelongsToMany<Test, $this> */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'repository_test')->withTimestamps();
    }
}
