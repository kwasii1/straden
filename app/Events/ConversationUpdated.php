<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public string $testId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('test.'.$this->testId);
    }

    public function broadcastAs(): string
    {
        return 'ConversationUpdated';
    }
}
