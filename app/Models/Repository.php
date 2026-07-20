<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'git_url',
        'git_branch',
        'git_auth_type',
        'git_credentials',
        'local_path',
        'sync_status',
        'sync_error',
        'last_synced_at',
        'last_commit_sha',
        'file_tree',
    ];

    protected function casts(): array
    {
        return [
            'git_credentials' => 'encrypted',
            'last_synced_at' => 'datetime',
            'file_tree' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
