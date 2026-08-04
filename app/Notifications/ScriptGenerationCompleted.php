<?php

namespace App\Notifications;

use App\Models\Script;

class ScriptGenerationCompleted extends BaseNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $scriptId,
        public string $testId,
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
        $script = Script::with(['test.project'])->find($this->scriptId);

        return [
            'type' => 'script',
            'icon' => 'sparkles',
            'title' => 'Script generation complete',
            'body' => $script
                ? "The AI agent finished working on script \"{$script->name}\"."
                : 'The AI agent finished working on a script.',
            'url' => $script?->test?->project
                ? route('projects.view-test-script', [
                    'project' => $script->test->project,
                    'test' => $script->test,
                    'script' => $script,
                ])
                : null,
        ];
    }
}
