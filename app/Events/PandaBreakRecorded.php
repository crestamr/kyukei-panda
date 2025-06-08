<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PandaBreak;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PandaBreakRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PandaBreak $pandaBreak;
    public User $user;
    public array $dailyStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(PandaBreak $pandaBreak, User $user, array $dailyStatus)
    {
        $this->pandaBreak = $pandaBreak;
        $this->user = $user;
        $this->dailyStatus = $dailyStatus;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.' . $this->user->id),
        ];

        // Also broadcast to team channels if user is part of a team
        foreach ($this->user->teams as $team) {
            $channels[] = new PrivateChannel('team.' . $team->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'panda_break' => [
                'id' => $this->pandaBreak->id,
                'user_name' => $this->user->name,
                'panda_count' => $this->pandaBreak->panda_count,
                'break_duration' => $this->pandaBreak->break_duration,
                'break_timestamp' => $this->pandaBreak->break_timestamp->toISOString(),
                'channel_name' => $this->pandaBreak->channel_name,
                'panda_emojis' => $this->pandaBreak->panda_emojis,
            ],
            'daily_status' => $this->dailyStatus,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'panda.break.recorded';
    }
}
