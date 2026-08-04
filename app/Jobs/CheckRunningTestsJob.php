<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\RunResultService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckRunningTestsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(): void
    {
        Run::query()
            ->where('status', 'running')
            ->whereNotNull('pid')
            ->orderBy('started_at')
            ->get()
            ->each(function (Run $run) {
                if (! RunResultService::isProcessAlive((int) $run->pid)) {
                    RunResultService::finalize($run);
                }
            });
    }
}
