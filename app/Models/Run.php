<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    use HasUuids;

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $fillable = [
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
        'exit_code',
        'error_message',
    ];
}
