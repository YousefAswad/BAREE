<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live text while a run is generating. Sent immediately (not queued) to the owner's private channel only.
 * The text is in redacted form ([PERSON_1]); the final, restored output comes from the run endpoint.
 */
final class AgentRunDelta implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $userId, public string $streamId, public string $text, public ?string $step = null) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId.'.runs');
    }

    public function broadcastAs(): string
    {
        return 'run.delta';
    }
}
