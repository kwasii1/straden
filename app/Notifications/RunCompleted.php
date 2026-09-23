<?php

namespace App\Notifications;

use App\Models\Run;

class RunCompleted extends BaseNotification
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

        $status = $run->status ?? 'error';

        return [
            'type' => 'run',
            'icon' => $this->icon($status),
            'title' => match ($status) {
                'passed' => 'Run passed',
                'failed' => 'Run failed',
                'aborted' => 'Run aborted',
                default => 'Run errored',
            },
            'body' => $run && $run->script
                ? "Test run \"{$run->script->name}\" finished with status \"{$status}\"."
                : 'A test run has finished.',
            'url' => $run?->script?->test?->project
                ? route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run])
                : null,
        ];
    }

    private function icon(string $status): string
    {
        return match ($status) {
            'passed' => 'check-circle',
            'aborted' => 'hand-raised',
            default => 'x-circle',
        };
    }
}
