<?php

namespace App\Notifications;

use App\Models\Repository;

class RepositorySyncFailed extends BaseNotification
{
    public function __construct(
        public Repository $repository,
        public ?string $error = null,
    ) {
        parent::__construct();
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->repository->sync_status;

        return [
            'type' => 'repository',
            'icon' => 'exclamation-triangle',
            'title' => match ($status) {
                'auth_error' => "{$this->repository->name} sync failed (auth)",
                default => "{$this->repository->name} sync failed",
            },
            'body' => $this->error ?? 'Repository sync failed.',
            'url' => $this->repository->project
                ? route('projects.repository-browse', ['project' => $this->repository->project, 'repository' => $this->repository])
                : null,
            'error' => $this->error,
        ];
    }
}
