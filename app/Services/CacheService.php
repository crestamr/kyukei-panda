<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use App\Models\Activity;
use App\Models\PandaBreak;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    private const TTL_SHORT = 300; // 5 minutes
    private const TTL_MEDIUM = 1800; // 30 minutes
    private const TTL_LONG = 3600; // 1 hour
    private const TTL_DAILY = 86400; // 24 hours

    /**
     * Cache user's daily productivity stats.
     */
    public function cacheUserDailyStats(int $userId, Carbon $date): array
    {
        $cacheKey = "user_daily_stats:{$userId}:{$date->toDateString()}";
        
        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($userId, $date) {
            $activities = Activity::where('user_id', $userId)
                ->whereDate('started_at', $date)
                ->with('category')
                ->get();

            $pandaBreaks = PandaBreak::where('user_id', $userId)
                ->whereDate('break_timestamp', $date)
                ->get();

            $totalTime = $activities->sum('duration_seconds');
            $productiveTime = $activities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
            $productivityScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

            return [
                'total_time' => $totalTime,
                'productive_time' => $productiveTime,
                'productivity_score' => round($productivityScore, 1),
                'activities_count' => $activities->count(),
                'pandas_used' => $pandaBreaks->sum('panda_count'),
                'break_minutes' => $pandaBreaks->sum('break_duration'),
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    /**
     * Cache team productivity comparison.
     */
    public function cacheTeamProductivity(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "team_productivity:{$teamId}:{$startDate->toDateString()}:{$endDate->toDateString()}";
        
        return Cache::remember($cacheKey, self::TTL_LONG, function () use ($teamId, $startDate, $endDate) {
            $team = Team::with(['users'])->find($teamId);
            $comparison = [];

            foreach ($team->users as $user) {
                $activities = Activity::where('user_id', $user->id)
                    ->whereBetween('started_at', [$startDate, $endDate])
                    ->get();

                $pandaBreaks = PandaBreak::where('user_id', $user->id)
                    ->whereBetween('break_timestamp', [$startDate, $endDate])
                    ->get();

                $totalTime = $activities->sum('duration_seconds');
                $productiveTime = $activities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
                $productivityScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

                $comparison[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'productivity_score' => round($productivityScore, 1),
                    'total_time' => $totalTime,
                    'pandas_used' => $pandaBreaks->sum('panda_count'),
                    'activities_count' => $activities->count(),
                ];
            }

            usort($comparison, fn($a, $b) => $b['productivity_score'] <=> $a['productivity_score']);

            return $comparison;
        });
    }

    /**
     * Cache project time tracking data.
     */
    public function cacheProjectTimeData(int $projectId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "project_time:{$projectId}:{$startDate->toDateString()}:{$endDate->toDateString()}";
        
        return Cache::remember($cacheKey, self::TTL_LONG, function () use ($projectId, $startDate, $endDate) {
            $activities = Activity::where('project_id', $projectId)
                ->whereBetween('started_at', [$startDate, $endDate])
                ->with(['user', 'category'])
                ->get();

            $totalTime = $activities->sum('duration_seconds');
            $userStats = $activities->groupBy('user_id')->map(function ($userActivities) {
                $user = $userActivities->first()->user;
                $userTime = $userActivities->sum('duration_seconds');
                
                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'total_time' => $userTime,
                    'activities_count' => $userActivities->count(),
                    'avg_productivity' => $userActivities->avg('productivity_score'),
                ];
            })->sortByDesc('total_time')->values();

            return [
                'total_time' => $totalTime,
                'activities_count' => $activities->count(),
                'user_stats' => $userStats,
                'avg_productivity' => $activities->avg('productivity_score'),
            ];
        });
    }

    /**
     * Cache user's active projects.
     */
    public function cacheUserActiveProjects(int $userId): array
    {
        $cacheKey = "user_active_projects:{$userId}";
        
        return Cache::remember($cacheKey, self::TTL_LONG, function () use ($userId) {
            $user = User::with(['teams.projects' => function ($query) {
                $query->where('is_active', true)->with('client');
            }])->find($userId);

            $projects = [];
            foreach ($user->teams as $team) {
                foreach ($team->projects as $project) {
                    $projects[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'client_name' => $project->client?->name,
                        'color' => $project->color,
                        'team_name' => $team->name,
                    ];
                }
            }

            return $projects;
        });
    }

    /**
     * Cache real-time activity feed for a team.
     */
    public function cacheTeamActivityFeed(int $teamId, int $limit = 20): array
    {
        $cacheKey = "team_activity_feed:{$teamId}";
        
        return Cache::remember($cacheKey, self::TTL_SHORT, function () use ($teamId, $limit) {
            // Get recent panda breaks
            $pandaBreaks = PandaBreak::whereHas('user.teams', function ($query) use ($teamId) {
                    $query->where('teams.id', $teamId);
                })
                ->with('user')
                ->where('break_timestamp', '>=', now()->subHours(24))
                ->orderBy('break_timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($break) {
                    return [
                        'type' => 'panda_break',
                        'user_name' => $break->user->name,
                        'message' => "took a {$break->break_duration} minute break",
                        'timestamp' => $break->break_timestamp->toISOString(),
                        'data' => [
                            'panda_count' => $break->panda_count,
                            'channel_name' => $break->channel_name,
                        ],
                    ];
                });

            // Get recent activities
            $activities = Activity::whereHas('user.teams', function ($query) use ($teamId) {
                    $query->where('teams.id', $teamId);
                })
                ->with(['user', 'project', 'category'])
                ->where('started_at', '>=', now()->subHours(4))
                ->where('duration_seconds', '>=', 300) // Only activities longer than 5 minutes
                ->orderBy('started_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'type' => 'activity',
                        'user_name' => $activity->user->name,
                        'message' => "worked on {$activity->application_name}" . 
                                   ($activity->project ? " for {$activity->project->name}" : ""),
                        'timestamp' => $activity->started_at->toISOString(),
                        'data' => [
                            'duration' => $activity->duration_seconds,
                            'productivity_score' => $activity->productivity_score,
                            'category' => $activity->category?->name,
                        ],
                    ];
                });

            // Merge and sort by timestamp
            $feed = $pandaBreaks->merge($activities)
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values();

            return $feed->toArray();
        });
    }

    /**
     * Invalidate user-related caches.
     */
    public function invalidateUserCaches(int $userId): void
    {
        $patterns = [
            "user_daily_stats:{$userId}:*",
            "user_active_projects:{$userId}",
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateCachePattern($pattern);
        }
    }

    /**
     * Invalidate team-related caches.
     */
    public function invalidateTeamCaches(int $teamId): void
    {
        $patterns = [
            "team_productivity:{$teamId}:*",
            "team_activity_feed:{$teamId}",
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateCachePattern($pattern);
        }
    }

    /**
     * Invalidate project-related caches.
     */
    public function invalidateProjectCaches(int $projectId): void
    {
        $patterns = [
            "project_time:{$projectId}:*",
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateCachePattern($pattern);
        }
    }

    /**
     * Invalidate cache keys matching a pattern.
     */
    private function invalidateCachePattern(string $pattern): void
    {
        if (config('cache.default') === 'redis') {
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } else {
            // For non-Redis cache drivers, we can't use patterns
            // This is a limitation, but we can implement specific key invalidation
            Cache::flush(); // Nuclear option - only use in development
        }
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        if (config('cache.default') === 'redis') {
            $info = Redis::info();
            
            return [
                'driver' => 'redis',
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'total_keys' => $info['db0']['keys'] ?? 0,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $info['keyspace_hits'] && $info['keyspace_misses'] ? 
                    round(($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2) : 0,
            ];
        }

        return [
            'driver' => config('cache.default'),
            'message' => 'Cache statistics not available for this driver',
        ];
    }

    /**
     * Warm up essential caches for a user.
     */
    public function warmUpUserCaches(int $userId): void
    {
        $today = Carbon::today();
        
        // Warm up daily stats
        $this->cacheUserDailyStats($userId, $today);
        
        // Warm up active projects
        $this->cacheUserActiveProjects($userId);
        
        // Warm up team caches if user is part of teams
        $user = User::with('teams')->find($userId);
        foreach ($user->teams as $team) {
            $this->cacheTeamActivityFeed($team->id);
        }
    }
}
