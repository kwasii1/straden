<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class RepositorySyncUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $projectId,
        public string $repositoryId,
        public string $status,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('project.'.$this->projectId);
    }

    public function broadcastAs(): string
    {
        return 'RepositorySyncUpdated';
    }
}
