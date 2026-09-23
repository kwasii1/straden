<?php

namespace App\Models;

use Database\Factories\RunInsightFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunInsight extends Model
{
    /** @use HasFactory<RunInsightFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
        'run_id',
        'status',
        'report',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'report' => 'array',
        ];
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
