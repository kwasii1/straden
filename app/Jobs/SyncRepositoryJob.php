<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\RepositorySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Repository $repository,
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

            throw $e;
        }
    }

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
