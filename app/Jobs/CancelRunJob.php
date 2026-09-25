<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\RunResultService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CancelRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public Run $run,
    ) {
        $this->onQueue('runs');
    }

    public function handle(): void
    {
        if ($this->run->status === 'running') {
            RunResultService::terminate($this->run);
        }
    }
}
