<?php

namespace App\Notifications;

use App\Models\Run;

class RunInsightFailed extends BaseNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $runId,
        public ?string $error = null,
    ) {
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
            'icon' => 'exclamation-triangle',
            'title' => 'Run insights failed',
            'body' => $run?->script
                ? "AI insights could not be generated for run \"{$run->slug}\"."
                : 'AI insights could not be generated.',
            'url' => $run?->script?->test?->project
                ? route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run])
                : null,
            'error' => $this->error,
        ];
    }
}
