<?php

namespace App\Notifications;

use App\Models\Run;

class RunInsightReady extends BaseNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public string $runId)
    {
        parent::__construct();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $run = Run::with(['script.test.project'])->find($this->runId);

        return [
            'type' => 'insight',
            'icon' => 'sparkles',
            'title' => 'Run insights ready',
            'body' => $run?->script
                ? "AI performance insights are ready for run \"{$run->slug}\"."
                : 'AI performance insights are ready.',
            'url' => $run?->script?->test?->project
                ? route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run])
                : null,
        ];
    }
}
