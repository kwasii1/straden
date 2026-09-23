<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\RunProcessManager;
use App\Services\RunResultService;
use App\Services\ScriptOptionsResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

class RunTestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Run $run,
    ) {}

    public function handle(): void
    {
        $this->run->loadMissing('script.test');

        if ($this->run->status !== 'queued') {
            return;
        }

        $script = $this->run->script;
        $scriptDir = Storage::disk('local')->path('scripts/'.$script->test_id.'/'.$script->id);

        try {
            $this->run->update([
                'run_config' => ScriptOptionsResolver::fromStorage(
                    'scripts/'.$script->test_id.'/'.$script->id.'/script.js'
                )?->toRunConfig() ?? null,
            ]);

            $result = app(RunProcessManager::class)->start($this->run, $scriptDir, $script->test->target_url);

            $this->run->update([
                'status' => 'running',
                'started_at' => now(),
                'pid' => $result['pid'],
            ]);

            if (! $result['running']) {
                RunResultService::finalize($this->run);
            }
        } catch (\Throwable $e) {
            $this->run->update([
                'status' => 'error',
                'completed_at' => now(),
                'duration_seconds' => 0,
                'error_message' => mb_substr($e->getMessage(), 0, 65535),
            ]);

            throw $e;
        }
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->run->script_id))
                ->releaseAfter(30),
        ];
    }

    public function uniqueId(): string
    {
        return 'run-test-'.$this->run->script_id;
    }
}
