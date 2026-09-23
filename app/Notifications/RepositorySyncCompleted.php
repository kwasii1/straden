<?php

namespace App\Notifications;

use App\Models\Repository;

class RepositorySyncCompleted extends BaseNotification
{
    public function __construct(public Repository $repository)
    {
        parent::__construct();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'repository',
            'icon' => 'check-circle',
            'title' => "{$this->repository->name} synced",
            'body' => 'Repository synced successfully.',
            'url' => $this->repository->project
                ? route('projects.repository-browse', ['project' => $this->repository->project, 'repository' => $this->repository])
                : null,
        ];
    }
}
