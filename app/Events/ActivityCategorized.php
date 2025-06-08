<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityCategorized implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Activity $activity;
    public User $user;
    public array $productivityUpdate;

    /**
     * Create a new event instance.
     */
    public function __construct(Activity $activity, User $user, array $productivityUpdate)
    {
        $this->activity = $activity;
        $this->user = $user;
        $this->productivityUpdate = $productivityUpdate;
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
            'activity' => [
                'id' => $this->activity->id,
                'user_name' => $this->user->name,
                'application_name' => $this->activity->application_name,
                'window_title' => $this->activity->window_title,
                'category_name' => $this->activity->category?->name,
                'productivity_score' => $this->activity->productivity_score,
                'duration_seconds' => $this->activity->duration_seconds,
                'started_at' => $this->activity->started_at->toISOString(),
                'ended_at' => $this->activity->ended_at?->toISOString(),
            ],
            'productivity_update' => $this->productivityUpdate,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'activity.categorized';
    }
}
