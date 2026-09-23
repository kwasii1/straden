<?php

namespace App\Jobs;

use App\Events\RepositorySyncUpdated;
use App\Models\Repository;
use App\Models\User;
use App\Notifications\RepositorySyncCompleted;
use App\Notifications\RepositorySyncFailed;
use App\Services\RepositorySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Repository $repository,
        public ?string $userId = null,
    ) {}

    public function handle(RepositorySyncService $service): void
    {
        try {
            $result = $service->sync($this->repository);

            $data = [
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
                'last_commit_sha' => $result['last_commit_sha'],
                'file_tree' => $result['file_tree'],
            ];

            if ($this->repository->cloned_at === null && $this->repository->type === 'git') {
                $data['cloned_at'] = now();
            }

            $this->repository->update($data);

            $this->broadcastUpdate('synced');
            $this->notifyUser(new RepositorySyncCompleted($this->repository));
        } catch (\Throwable $e) {
            $status = 'failed';
            $message = $e->getMessage();

            if (str_contains($message, 'auth error')) {
                $status = 'auth_error';
            }

            $this->repository->update([
                'sync_status' => $status,
                'sync_error' => $message,
                'last_synced_at' => now(),
            ]);

            $this->broadcastUpdate($status);
            $this->notifyUser(new RepositorySyncFailed($this->repository, $message));

            throw $e;
        }
    }

    private function broadcastUpdate(string $status): void
    {
        broadcast(new RepositorySyncUpdated(
            projectId: $this->repository->project_id,
            repositoryId: $this->repository->id,
            status: $status,
        ));
    }

    private function notifyUser(Notification $notification): void
    {
        if ($this->userId === null) {
            return;
        }

        $user = User::find($this->userId);

        if ($user) {
            $user->notify($notification);
        }
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->repository->id))
                ->releaseAfter(30),
        ];
    }

    public function uniqueId(): string
    {
        return 'repository-sync-'.$this->repository->id;
    }
}
