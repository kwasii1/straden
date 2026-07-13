<?php

namespace App\Models;

use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->slug = SlugGenerator::unique($project->name, self::class);
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('name') && !$project->isDirty('slug')) {
                $project->slug = SlugGenerator::unique($project->name, self::class, $project->id);
            }
        });
    }

    public $incrementing = true;
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'slug',
        'description'
    ];
}
