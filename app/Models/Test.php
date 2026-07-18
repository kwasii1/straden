<?php

namespace App\Models;

use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Test extends Model
{
    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'target_url',
        'project_id',
        'description',
        'slug'
    ];

    protected static function booted(): void
    {
        static::creating(function (Test $test) {
            $test->slug = SlugGenerator::unique($test->name, self::class);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
