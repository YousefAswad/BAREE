<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Broadcast over Laravel Reverb so the studio updates without polling. */
final class AgentRunFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public ?string $runId, public string $status, public ?string $message = null) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId.'.runs');
    }

    public function broadcastAs(): string
    {
        return 'run.finished';
    }
}
