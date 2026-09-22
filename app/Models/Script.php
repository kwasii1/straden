<?php

namespace App\Models;

use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Ai\Concerns\HasConversations;

class Script extends Model
{
    use HasConversations;
    use HasFactory;
    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'test_id',
        'name',
        'slug',
        'description',
        'disk',
        'script_path',
        'ai_generated_script_path',
        'config',
        'is_default',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Script $script) {
            $script->slug = SlugGenerator::unique($script->name, self::class);
        });
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
