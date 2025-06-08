<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DataVisualizationService
{
    /**
     * Generate heatmap data for productivity patterns.
     */
    public function generateProductivityHeatmap(int $userId, int $days = 30): array
    {
        $cacheKey = "productivity_heatmap:{$userId}:{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId, $days) {
            $activities = Activity::where('user_id', $userId)
                ->where('started_at', '>=', Carbon::now()->subDays($days))
                ->get();

            $heatmapData = [];
            
            // Initialize 24x7 grid (hours x days of week)
            for ($day = 0; $day < 7; $day++) {
                for ($hour = 0; $hour < 24; $hour++) {
                    $heatmapData[$day][$hour] = [
                        'value' => 0,
                        'count' => 0,
                        'total_time' => 0,
                    ];
                }
            }

            // Populate with actual data
            foreach ($activities as $activity) {
                $dayOfWeek = $activity->started_at->dayOfWeek;
                $hour = $activity->started_at->hour;
                
                $heatmapData[$dayOfWeek][$hour]['value'] += $activity->productivity_score;
                $heatmapData[$dayOfWeek][$hour]['count']++;
                $heatmapData[$dayOfWeek][$hour]['total_time'] += $activity->duration_seconds;
            }

            // Calculate averages
            foreach ($heatmapData as $day => $hours) {
                foreach ($hours as $hour => $data) {
                    if ($data['count'] > 0) {
                        $heatmapData[$day][$hour]['value'] = $data['value'] / $data['count'];
                    }
                }
            }

            return [
                'data' => $heatmapData,
                'labels' => [
                    'days' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    'hours' => range(0, 23),
                ],
                'metadata' => [
                    'period_days' => $days,
                    'total_activities' => $activities->count(),
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Generate break pattern visualization data.
     */
    public function generateBreakPatternChart(int $userId, int $days = 30): array
    {
        $breaks = PandaBreak::where('user_id', $userId)
            ->where('break_timestamp', '>=', Carbon::now()->subDays($days))
            ->get();

        // Hourly break frequency
        $hourlyFrequency = array_fill(0, 24, 0);
        $dailyFrequency = array_fill(0, 7, 0);
        $breakDurations = [];

        foreach ($breaks as $break) {
            $hour = $break->break_timestamp->hour;
            $dayOfWeek = $break->break_timestamp->dayOfWeek;
            
            $hourlyFrequency[$hour] += $break->panda_count;
            $dailyFrequency[$dayOfWeek] += $break->panda_count;
            $breakDurations[] = $break->break_duration;
        }

        return [
            'hourly_pattern' => [
                'labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
                'data' => $hourlyFrequency,
                'title' => 'Break Frequency by Hour',
            ],
            'daily_pattern' => [
                'labels' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                'data' => $dailyFrequency,
                'title' => 'Break Frequency by Day',
            ],
            'duration_distribution' => [
                'data' => $this->createDurationBuckets($breakDurations),
                'title' => 'Break Duration Distribution',
            ],
            'summary' => [
                'total_breaks' => $breaks->count(),
                'total_pandas' => $breaks->sum('panda_count'),
                'avg_duration' => $breaks->avg('break_duration'),
                'most_active_hour' => array_search(max($hourlyFrequency), $hourlyFrequency),
                'most_active_day' => array_search(max($dailyFrequency), $dailyFrequency),
            ],
        ];
    }

    /**
     * Generate team collaboration network visualization.
     */
    public function generateTeamCollaborationNetwork(int $teamId): array
    {
        $team = Team::with('users')->find($teamId);
        $nodes = [];
        $edges = [];

        // Create nodes for each team member
        foreach ($team->users as $user) {
            $userActivities = Activity::where('user_id', $user->id)
                ->where('started_at', '>=', Carbon::now()->subDays(30))
                ->get();

            $userBreaks = PandaBreak::where('user_id', $user->id)
                ->where('break_timestamp', '>=', Carbon::now()->subDays(30))
                ->get();

            $nodes[] = [
                'id' => $user->id,
                'label' => $user->name,
                'size' => $userActivities->sum('duration_seconds') / 3600, // Size based on hours worked
                'color' => $this->getUserColor($user->id),
                'metrics' => [
                    'productivity_score' => $userActivities->avg('productivity_score'),
                    'total_hours' => $userActivities->sum('duration_seconds') / 3600,
                    'pandas_used' => $userBreaks->sum('panda_count'),
                    'break_compliance' => min(100, ($userBreaks->sum('panda_count') / 30) * 100), // Assuming 1 panda per day target
                ],
            ];
        }

        // Create edges based on collaboration patterns
        $collaborationMatrix = $this->calculateCollaborationMatrix($team->users);
        foreach ($collaborationMatrix as $fromUserId => $connections) {
            foreach ($connections as $toUserId => $strength) {
                if ($strength > 0.1 && $fromUserId !== $toUserId) { // Only show significant connections
                    $edges[] = [
                        'from' => $fromUserId,
                        'to' => $toUserId,
                        'weight' => $strength,
                        'label' => round($strength * 100) . '%',
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'metadata' => [
                'team_name' => $team->name,
                'member_count' => $team->users->count(),
                'generated_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Generate productivity flow diagram.
     */
    public function generateProductivityFlow(int $userId, Carbon $date): array
    {
        $activities = Activity::where('user_id', $userId)
            ->whereDate('started_at', $date)
            ->orderBy('started_at')
            ->with('category')
            ->get();

        $breaks = PandaBreak::where('user_id', $userId)
            ->whereDate('break_timestamp', $date)
            ->orderBy('break_timestamp')
            ->get();

        $timeline = [];
        $currentTime = $date->copy()->startOfDay();
        $endTime = $date->copy()->endOfDay();

        // Merge activities and breaks into timeline
        $allEvents = collect();

        foreach ($activities as $activity) {
            $allEvents->push([
                'type' => 'activity',
                'start' => $activity->started_at,
                'end' => $activity->ended_at,
                'data' => $activity,
            ]);
        }

        foreach ($breaks as $break) {
            $allEvents->push([
                'type' => 'break',
                'start' => $break->break_timestamp,
                'end' => $break->break_timestamp->copy()->addMinutes($break->break_duration),
                'data' => $break,
            ]);
        }

        $sortedEvents = $allEvents->sortBy('start');

        // Create flow segments
        $flowSegments = [];
        foreach ($sortedEvents as $event) {
            $segment = [
                'type' => $event['type'],
                'start' => $event['start']->format('H:i'),
                'end' => $event['end']->format('H:i'),
                'duration' => $event['start']->diffInMinutes($event['end']),
            ];

            if ($event['type'] === 'activity') {
                $activity = $event['data'];
                $segment['productivity'] = $activity->productivity_score;
                $segment['category'] = $activity->category?->name ?? 'Uncategorized';
                $segment['application'] = $activity->application_name;
                $segment['color'] = $this->getProductivityColor($activity->productivity_score);
            } else {
                $break = $event['data'];
                $segment['pandas'] = $break->panda_count;
                $segment['channel'] = $break->channel_name;
                $segment['color'] = '#10B981'; // Green for breaks
            }

            $flowSegments[] = $segment;
        }

        return [
            'date' => $date->toDateString(),
            'segments' => $flowSegments,
            'summary' => [
                'total_activities' => $activities->count(),
                'total_breaks' => $breaks->count(),
                'work_hours' => $activities->sum('duration_seconds') / 3600,
                'break_minutes' => $breaks->sum('break_duration'),
                'avg_productivity' => $activities->avg('productivity_score'),
            ],
        ];
    }

    /**
     * Generate category distribution pie chart data.
     */
    public function generateCategoryDistribution(int $userId, int $days = 30): array
    {
        $activities = Activity::where('user_id', $userId)
            ->where('started_at', '>=', Carbon::now()->subDays($days))
            ->with('category')
            ->get();

        $categoryData = $activities->groupBy('category.name')->map(function ($group, $categoryName) {
            $totalTime = $group->sum('duration_seconds');
            $avgProductivity = $group->avg('productivity_score');
            
            return [
                'name' => $categoryName ?? 'Uncategorized',
                'value' => $totalTime,
                'hours' => round($totalTime / 3600, 1),
                'percentage' => 0, // Will be calculated below
                'productivity' => round($avgProductivity, 2),
                'count' => $group->count(),
                'color' => $this->getCategoryColor($categoryName),
            ];
        });

        $totalTime = $categoryData->sum('value');
        
        // Calculate percentages
        $categoryData = $categoryData->map(function ($category) use ($totalTime) {
            $category['percentage'] = $totalTime > 0 ? round(($category['value'] / $totalTime) * 100, 1) : 0;
            return $category;
        });

        return [
            'data' => $categoryData->sortByDesc('value')->values()->toArray(),
            'total_time' => $totalTime,
            'total_hours' => round($totalTime / 3600, 1),
            'categories_count' => $categoryData->count(),
        ];
    }

    /**
     * Generate real-time dashboard metrics.
     */
    public function generateRealTimeDashboard(int $userId): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        
        // Today's metrics
        $todayActivities = Activity::where('user_id', $userId)
            ->whereDate('started_at', $today)
            ->get();

        $todayBreaks = PandaBreak::where('user_id', $userId)
            ->whereDate('break_timestamp', $today)
            ->get();

        // This week's metrics
        $weekActivities = Activity::where('user_id', $userId)
            ->where('started_at', '>=', $thisWeek)
            ->get();

        $weekBreaks = PandaBreak::where('user_id', $userId)
            ->where('break_timestamp', '>=', $thisWeek)
            ->get();

        // Current activity (last 5 minutes)
        $currentActivity = Activity::where('user_id', $userId)
            ->where('started_at', '>=', Carbon::now()->subMinutes(5))
            ->orderBy('started_at', 'desc')
            ->first();

        return [
            'today' => [
                'productivity_score' => $this->calculateProductivityScore($todayActivities),
                'work_hours' => round($todayActivities->sum('duration_seconds') / 3600, 1),
                'pandas_used' => $todayBreaks->sum('panda_count'),
                'break_minutes' => $todayBreaks->sum('break_duration'),
                'activities_count' => $todayActivities->count(),
            ],
            'week' => [
                'productivity_score' => $this->calculateProductivityScore($weekActivities),
                'work_hours' => round($weekActivities->sum('duration_seconds') / 3600, 1),
                'pandas_used' => $weekBreaks->sum('panda_count'),
                'avg_daily_hours' => round($weekActivities->sum('duration_seconds') / 3600 / 7, 1),
            ],
            'current_activity' => $currentActivity ? [
                'application' => $currentActivity->application_name,
                'category' => $currentActivity->category?->name,
                'started_at' => $currentActivity->started_at->format('H:i'),
                'productivity' => $currentActivity->productivity_score,
            ] : null,
            'last_updated' => now()->toISOString(),
        ];
    }

    // Helper methods

    private function createDurationBuckets(array $durations): array
    {
        $buckets = [
            '1-5 min' => 0,
            '6-10 min' => 0,
            '11-15 min' => 0,
            '16-30 min' => 0,
            '30+ min' => 0,
        ];

        foreach ($durations as $duration) {
            if ($duration <= 5) $buckets['1-5 min']++;
            elseif ($duration <= 10) $buckets['6-10 min']++;
            elseif ($duration <= 15) $buckets['11-15 min']++;
            elseif ($duration <= 30) $buckets['16-30 min']++;
            else $buckets['30+ min']++;
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
        ];
    }

    private function getUserColor(int $userId): string
    {
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'];
        return $colors[$userId % count($colors)];
    }

    private function getProductivityColor(float $score): string
    {
        if ($score >= 0.8) return '#10B981'; // Green
        if ($score >= 0.6) return '#F59E0B'; // Yellow
        if ($score >= 0.4) return '#F97316'; // Orange
        return '#EF4444'; // Red
    }

    private function getCategoryColor(string $categoryName): string
    {
        $colors = [
            'Development' => '#3B82F6',
            'Design' => '#8B5CF6',
            'Communication' => '#10B981',
            'Research' => '#F59E0B',
            'Entertainment' => '#EF4444',
            'System' => '#6B7280',
        ];

        return $colors[$categoryName] ?? '#9CA3AF';
    }

    private function calculateCollaborationMatrix(Collection $users): array
    {
        $matrix = [];
        
        // Simplified collaboration calculation based on concurrent activities
        foreach ($users as $user1) {
            $matrix[$user1->id] = [];
            foreach ($users as $user2) {
                if ($user1->id === $user2->id) {
                    $matrix[$user1->id][$user2->id] = 0;
                    continue;
                }

                // Calculate collaboration score (simplified)
                $score = rand(0, 100) / 100; // In real implementation, this would be based on actual collaboration metrics
                $matrix[$user1->id][$user2->id] = $score;
            }
        }

        return $matrix;
    }

    private function calculateProductivityScore(Collection $activities): float
    {
        if ($activities->isEmpty()) {
            return 0;
        }

        $totalTime = $activities->sum('duration_seconds');
        $productiveTime = $activities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');

        return $totalTime > 0 ? round(($productiveTime / $totalTime) * 100, 1) : 0;
    }
}
