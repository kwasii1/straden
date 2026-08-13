<?php

namespace App\Models;

use App\Services\SlugGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Run extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'slug',
        'script_id',
        'status',
        'triggered_by',
        'triggered_by_user_id',
        'started_at',
        'completed_at',
        'duration_seconds',

        // k6 summary metrics
        'vus_max',
        'requests_total',
        'requests_per_second',
        'req_duration_p95_ms',
        'req_duration_p99_ms',
        'error_rate',
        'checks_total',
        'checks_failed',

        'thresholds_passed',
        'thresholds_summary',
        'run_config',

        'k6_container_id',
        'pid',
        'exit_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'thresholds_passed' => 'boolean',
            'thresholds_summary' => 'json',
            'run_config' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Run $run) {
            $scriptName = Script::find($run->script_id)?->name ?? 'run';
            $run->slug = SlugGenerator::unique($scriptName.' '.now()->format('YmdHis'), self::class);
        });

        static::created(function (Run $run) {
            Script::query()->where('id', $run->script_id)->update(['last_run_at' => now()]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function insight(): HasOne
    {
        return $this->hasOne(RunInsight::class);
    }
}
