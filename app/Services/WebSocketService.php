<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebSocketService
{
    private const CHANNEL_PREFIX = 'kyukei-panda:';
    
    /**
     * Broadcast real-time productivity update.
     */
    public function broadcastProductivityUpdate(int $userId, int $teamId, array $data): bool
    {
        try {
            $message = [
                'event' => 'productivity.updated',
                'data' => [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'productivity_data' => $data,
                    'timestamp' => now()->toISOString(),
                ],
            ];

            // Broadcast to user channel
            $this->publishToChannel("user.{$userId}", $message);
            
            // Broadcast to team channel
            $this->publishToChannel("team.{$teamId}", $message);
            
            // Broadcast to global channel
            $this->publishToChannel('global-productivity', $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'user_id' => $userId,
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Broadcast panda break notification.
     */
    public function broadcastPandaBreak(int $userId, int $teamId, array $breakData): bool
    {
        try {
            $message = [
                'event' => 'panda.break',
                'data' => [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'break_data' => $breakData,
                    'panda_emoji' => str_repeat('🐼', $breakData['panda_count'] ?? 1),
                    'timestamp' => now()->toISOString(),
                ],
            ];

            // Broadcast to team channel
            $this->publishToChannel("team.{$teamId}", $message);
            
            // Store in cache for offline users
            $this->storeOfflineMessage($userId, $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Panda break broadcast failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Broadcast team collaboration event.
     */
    public function broadcastTeamEvent(int $teamId, string $eventType, array $data): bool
    {
        try {
            $message = [
                'event' => "team.{$eventType}",
                'data' => array_merge($data, [
                    'team_id' => $teamId,
                    'timestamp' => now()->toISOString(),
                ]),
            ];

            $this->publishToChannel("team.{$teamId}", $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Team event broadcast failed', [
                'team_id' => $teamId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Broadcast AI insight notification.
     */
    public function broadcastAIInsight(int $userId, array $insight): bool
    {
        try {
            $message = [
                'event' => 'ai.insight',
                'data' => [
                    'user_id' => $userId,
                    'insight' => $insight,
                    'timestamp' => now()->toISOString(),
                ],
            ];

            $this->publishToChannel("user.{$userId}", $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('AI insight broadcast failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get real-time user presence.
     */
    public function getUserPresence(int $userId): array
    {
        $presenceKey = "presence:user:{$userId}";
        $presence = Cache::get($presenceKey, [
            'status' => 'offline',
            'last_seen' => null,
        ]);

        return $presence;
    }

    /**
     * Update user presence.
     */
    public function updateUserPresence(int $userId, string $status, array $metadata = []): bool
    {
        try {
            $presenceKey = "presence:user:{$userId}";
            $presence = [
                'status' => $status,
                'last_seen' => now()->toISOString(),
                'metadata' => $metadata,
            ];

            Cache::put($presenceKey, $presence, 300); // 5 minutes

            // Broadcast presence update to user's teams
            $this->broadcastPresenceUpdate($userId, $presence);

            return true;
            
        } catch (\Exception $e) {
            Log::error('Presence update failed', [
                'user_id' => $userId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get team members' presence.
     */
    public function getTeamPresence(int $teamId): array
    {
        try {
            $team = \App\Models\Team::with('users')->find($teamId);
            $presence = [];

            foreach ($team->users as $user) {
                $presence[$user->id] = $this->getUserPresence($user->id);
            }

            return $presence;
            
        } catch (\Exception $e) {
            Log::error('Team presence fetch failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Send real-time notification.
     */
    public function sendNotification(int $userId, array $notification): bool
    {
        try {
            $message = [
                'event' => 'notification',
                'data' => array_merge($notification, [
                    'user_id' => $userId,
                    'id' => uniqid('notif_'),
                    'timestamp' => now()->toISOString(),
                ]),
            ];

            $this->publishToChannel("user.{$userId}", $message);
            
            // Store notification for offline users
            $this->storeOfflineMessage($userId, $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get offline messages for user.
     */
    public function getOfflineMessages(int $userId): array
    {
        try {
            $messagesKey = "offline_messages:user:{$userId}";
            $messages = Cache::get($messagesKey, []);
            
            // Clear messages after retrieval
            Cache::forget($messagesKey);
            
            return $messages;
            
        } catch (\Exception $e) {
            Log::error('Offline messages retrieval failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    // Private helper methods

    private function publishToChannel(string $channel, array $message): void
    {
        $channelName = self::CHANNEL_PREFIX . $channel;
        
        try {
            // Try Redis pub/sub first
            if ($this->isRedisAvailable()) {
                Redis::publish($channelName, json_encode($message));
                return;
            }
            
            // Fallback to cache-based messaging
            $this->publishToCache($channelName, $message);
            
        } catch (\Exception $e) {
            Log::warning('Channel publish failed, using fallback', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            
            $this->publishToCache($channelName, $message);
        }
    }

    private function publishToCache(string $channel, array $message): void
    {
        $cacheKey = "channel_messages:{$channel}";
        $messages = Cache::get($cacheKey, []);
        
        $messages[] = $message;
        
        // Keep only last 100 messages
        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }
        
        Cache::put($cacheKey, $messages, 300); // 5 minutes
    }

    private function isRedisAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function storeOfflineMessage(int $userId, array $message): void
    {
        $messagesKey = "offline_messages:user:{$userId}";
        $messages = Cache::get($messagesKey, []);
        
        $messages[] = $message;
        
        // Keep only last 50 offline messages
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }
        
        Cache::put($messagesKey, $messages, 86400); // 24 hours
    }

    private function broadcastPresenceUpdate(int $userId, array $presence): void
    {
        try {
            // Get user's teams
            $user = \App\Models\User::with('teams')->find($userId);
            
            foreach ($user->teams as $team) {
                $message = [
                    'event' => 'presence.updated',
                    'data' => [
                        'user_id' => $userId,
                        'presence' => $presence,
                        'timestamp' => now()->toISOString(),
                    ],
                ];
                
                $this->publishToChannel("team.{$team->id}", $message);
            }
            
        } catch (\Exception $e) {
            Log::error('Presence broadcast failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a simple WebSocket server for development.
     */
    public function startDevelopmentWebSocketServer(): void
    {
        if (app()->environment('local')) {
            Log::info('Starting development WebSocket server on port 6001');
            
            // This would start a simple WebSocket server for development
            // In production, use Laravel Echo Server or Soketi
        }
    }

    /**
     * Health check for WebSocket service.
     */
    public function healthCheck(): array
    {
        return [
            'websocket_service' => 'operational',
            'redis_available' => $this->isRedisAvailable(),
            'cache_available' => $this->isCacheAvailable(),
            'channels_active' => $this->getActiveChannelsCount(),
            'last_check' => now()->toISOString(),
        ];
    }

    private function isCacheAvailable(): bool
    {
        try {
            Cache::put('websocket_health_check', true, 1);
            return Cache::get('websocket_health_check', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getActiveChannelsCount(): int
    {
        try {
            // Count active channels from cache
            $pattern = 'channel_messages:' . self::CHANNEL_PREFIX . '*';
            $keys = Cache::getRedis()->keys($pattern);
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
