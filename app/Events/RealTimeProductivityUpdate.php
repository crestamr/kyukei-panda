<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealTimeProductivityUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $teamId,
        public array $productivityData,
        public string $eventType = 'productivity_update'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}"),
            new PresenceChannel("team.{$this->teamId}"),
            new Channel('global-productivity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'productivity.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
            'event_type' => $this->eventType,
            'data' => $this->productivityData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
