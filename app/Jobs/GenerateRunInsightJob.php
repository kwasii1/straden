<?php

namespace App\Jobs;

use App\Ai\Agents\RunInsightAgent;
use App\Models\Run;
use App\Models\RunInsight;
use App\Notifications\RunInsightFailed;
use App\Notifications\RunInsightReady;
use App\Services\AiCredentialManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;

class GenerateRunInsightJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $runId,
        public ?string $runInsightId = null,
    ) {}

    public function handle(): void
    {
        app(AiCredentialManager::class)->syncConfig();

        $run = Run::find($this->runId);

        if (! $run) {
            return;
        }

        $insight = $this->resolveInsight($run);

        if ($insight->status === 'completed') {
            return;
        }

        if (in_array($run->status, ['queued', 'running'], true)) {
            $insight->update([
                'status' => 'failed',
                'error' => 'Cannot generate insights while the run is still active.',
            ]);

            return;
        }

        $insight->update(['status' => 'generating', 'error' => null]);

        $manager = app(AiCredentialManager::class);
        $selection = $manager->getInsightsSelection();

        if ($selection && ! $manager->isConnected($selection['provider'])) {
            $insight->update([
                'status' => 'failed',
                'error' => "The selected insights provider [{$selection['provider']}] is not connected. Reconnect it or pick another model in Settings → AI Integrations.",
            ]);

            return;
        }

        try {
            $response = (new RunInsightAgent($run))
                ->prompt(
                    'Analyze this load test run and generate the structured performance report.',
                    provider: $selection['provider'] ?? null,
                    model: $selection['model'] ?? null,
                );

            $insight->update([
                'status' => 'completed',
                'report' => $this->toReport($response),
                'error' => null,
            ]);

            $this->notifyUser($run, new RunInsightReady($run->id));
        } catch (\Throwable $e) {
            report($e);

            $insight->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 65535),
            ]);

            $this->notifyUser($run, new RunInsightFailed($run->id, $e->getMessage()));
        }
    }

    private function notifyUser(Run $run, Notification $notification): void
    {
        $run->loadMissing('triggeredByUser');

        $run->triggeredByUser?->notify($notification);
    }

    private function resolveInsight(Run $run): RunInsight
    {
        $insight = $this->runInsightId
            ? RunInsight::find($this->runInsightId)
            : $run->insight;

        if ($insight) {
            return $insight;
        }

        return RunInsight::create([
            'run_id' => $run->id,
            'status' => 'queued',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toReport(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        return [
            'summary' => (string) $response,
            'overall_health' => 'acceptable',
            'what_is_slow' => '',
            'key_findings' => [],
            'recommendations' => [],
            'script_observations' => '',
        ];
    }
}
