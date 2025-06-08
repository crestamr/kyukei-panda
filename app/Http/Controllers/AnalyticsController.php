<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Models\DailyPandaLimit;
use App\Services\ActivityCategorizationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private ActivityCategorizationService $categorizationService
    ) {}

    /**
     * Display the main analytics dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $period = $request->input('period', 'week');
        $teamId = $request->input('team_id');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get productivity analytics
        $productivityData = $this->categorizationService->calculateProductivityScore(
            $user->id,
            $startDate,
            $endDate
        );

        // Get panda break analytics
        $pandaAnalytics = $this->getPandaBreakAnalytics($user->id, $startDate, $endDate);

        // Get team comparison if user is part of a team
        $teamComparison = [];
        if ($teamId || $user->teams()->exists()) {
            $team = $teamId ? $user->teams()->find($teamId) : $user->teams()->first();
            if ($team) {
                $teamComparison = $this->getTeamComparison($team->id, $startDate, $endDate);
            }
        }

        // Get daily breakdown
        $dailyBreakdown = $this->getDailyBreakdown($user->id, $startDate, $endDate);

        // Get activity trends
        $activityTrends = $this->getActivityTrends($user->id, $startDate, $endDate);

        return Inertia::render('Analytics/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'teams' => $user->teams->map(fn($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                ])
            ],
            'period' => $period,
            'dateRange' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'productivity' => $productivityData,
            'pandaAnalytics' => $pandaAnalytics,
            'teamComparison' => $teamComparison,
            'dailyBreakdown' => $dailyBreakdown,
            'activityTrends' => $activityTrends,
        ]);
    }

    /**
     * Get panda break analytics for a user.
     */
    private function getPandaBreakAnalytics(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $breaks = PandaBreak::where('user_id', $userId)
            ->whereBetween('break_timestamp', [$startDate, $endDate])
            ->get();

        $dailyLimits = DailyPandaLimit::where('user_id', $userId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $totalBreaks = $breaks->count();
        $totalPandas = $breaks->sum('panda_count');
        $totalMinutes = $breaks->sum('break_duration');
        $averageBreakLength = $totalBreaks > 0 ? $totalMinutes / $totalBreaks : 0;

        // Break frequency by day of week
        $breaksByDayOfWeek = $breaks->groupBy(function ($break) {
            return $break->break_timestamp->dayOfWeek;
        })->map->count();

        // Break frequency by hour
        $breaksByHour = $breaks->groupBy(function ($break) {
            return $break->break_timestamp->hour;
        })->map->count();

        // Compliance rate (days with breaks vs total days)
        $daysWithBreaks = $dailyLimits->where('pandas_used', '>', 0)->count();
        $totalDays = $dailyLimits->count();
        $complianceRate = $totalDays > 0 ? ($daysWithBreaks / $totalDays) * 100 : 0;

        return [
            'total_breaks' => $totalBreaks,
            'total_pandas' => $totalPandas,
            'total_minutes' => $totalMinutes,
            'average_break_length' => round($averageBreakLength, 1),
            'compliance_rate' => round($complianceRate, 1),
            'breaks_by_day_of_week' => $breaksByDayOfWeek->toArray(),
            'breaks_by_hour' => $breaksByHour->toArray(),
            'daily_usage' => $dailyLimits->map(function ($limit) {
                return [
                    'date' => $limit->date->toDateString(),
                    'pandas_used' => $limit->pandas_used,
                    'total_minutes' => $limit->total_break_minutes,
                    'compliance' => $limit->pandas_used >= 3, // At least 3 breaks per day
                ];
            })->values(),
        ];
    }

    /**
     * Get date range for the specified period.
     */
    private function getPeriodDates(string $period): array
    {
        return match($period) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'quarter' => [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()],
            'year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
        };
    }

    /**
     * Get team comparison data.
     */
    private function getTeamComparison(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        $teamMembers = User::whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.id', $teamId);
        })->get();

        $comparison = [];
        foreach ($teamMembers as $member) {
            $memberProductivity = $this->categorizationService->calculateProductivityScore(
                $member->id,
                $startDate,
                $endDate
            );

            $memberPandas = PandaBreak::where('user_id', $member->id)
                ->whereBetween('break_timestamp', [$startDate, $endDate])
                ->sum('panda_count');

            $comparison[] = [
                'user_id' => $member->id,
                'name' => $member->name,
                'productivity_score' => $memberProductivity['score'],
                'total_pandas' => $memberPandas,
                'productive_time' => $memberProductivity['productive_time'],
                'total_time' => $memberProductivity['total_time'],
            ];
        }

        // Sort by productivity score
        usort($comparison, fn($a, $b) => $b['productivity_score'] <=> $a['productivity_score']);

        return $comparison;
    }

    /**
     * Get daily breakdown of activities and breaks.
     */
    private function getDailyBreakdown(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $breakdown = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayActivities = Activity::where('user_id', $userId)
                ->whereDate('started_at', $current)
                ->with('category')
                ->get();

            $dayBreaks = PandaBreak::where('user_id', $userId)
                ->whereDate('break_timestamp', $current)
                ->get();

            $productiveTime = $dayActivities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
            $totalTime = $dayActivities->sum('duration_seconds');
            $productivityScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

            $breakdown[] = [
                'date' => $current->toDateString(),
                'day_name' => $current->format('l'),
                'productivity_score' => round($productivityScore, 1),
                'total_time' => $totalTime,
                'productive_time' => $productiveTime,
                'break_time' => $dayBreaks->sum('break_duration') * 60, // Convert to seconds
                'pandas_used' => $dayBreaks->sum('panda_count'),
                'activities_count' => $dayActivities->count(),
                'breaks_count' => $dayBreaks->count(),
            ];

            $current->addDay();
        }

        return $breakdown;
    }

    /**
     * Get activity trends over time.
     */
    private function getActivityTrends(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $activities = Activity::where('user_id', $userId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->with('category')
            ->get();

        // Group by category and calculate trends
        $categoryTrends = $activities->groupBy('category.name')->map(function ($group, $categoryName) {
            $totalTime = $group->sum('duration_seconds');
            $avgProductivity = $group->avg('productivity_score');

            return [
                'category' => $categoryName ?? 'Uncategorized',
                'total_time' => $totalTime,
                'percentage' => 0, // Will be calculated after
                'avg_productivity' => round($avgProductivity, 2),
                'activity_count' => $group->count(),
            ];
        });

        $totalTime = $activities->sum('duration_seconds');

        // Calculate percentages
        $categoryTrends = $categoryTrends->map(function ($trend) use ($totalTime) {
            $trend['percentage'] = $totalTime > 0 ? round(($trend['total_time'] / $totalTime) * 100, 1) : 0;
            return $trend;
        });

        // Sort by total time
        $categoryTrends = $categoryTrends->sortByDesc('total_time')->values();

        return [
            'categories' => $categoryTrends->toArray(),
            'total_activities' => $activities->count(),
            'total_time' => $totalTime,
            'most_productive_category' => $categoryTrends->sortByDesc('avg_productivity')->first(),
            'most_used_category' => $categoryTrends->first(),
        ];
    }

    /**
     * Display the analytics dashboard (alias for index).
     */
    public function dashboard(Request $request): Response
    {
        return $this->index($request);
    }

    /**
     * Display team analytics.
     */
    public function team(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $teamId = $request->input('team_id');
        $period = $request->input('period', 'week');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get user's teams
        $teams = $user->teams;
        $selectedTeam = $teamId ? $teams->find($teamId) : $teams->first();

        if (!$selectedTeam) {
            return Inertia::render('Analytics/Team', [
                'error' => 'No team found. Please join a team to view team analytics.',
                'teams' => [],
                'teamAnalytics' => null,
            ]);
        }

        // Get team analytics
        $teamComparison = $this->getTeamComparison($selectedTeam->id, $startDate, $endDate);

        // Get team panda statistics
        $teamPandaStats = $this->getTeamPandaStatistics($selectedTeam->id, $startDate, $endDate);

        return Inertia::render('Analytics/Team', [
            'teams' => $teams->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'member_count' => $team->users()->count(),
            ]),
            'selectedTeam' => [
                'id' => $selectedTeam->id,
                'name' => $selectedTeam->name,
                'description' => $selectedTeam->description,
            ],
            'period' => $period,
            'dateRange' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'teamComparison' => $teamComparison,
            'teamPandaStats' => $teamPandaStats,
        ]);
    }

    /**
     * Display productivity analytics.
     */
    public function productivity(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $period = $request->input('period', 'month');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get detailed productivity analytics
        $productivityData = $this->categorizationService->calculateProductivityScore(
            $user->id,
            $startDate,
            $endDate
        );

        // Get productivity trends over time
        $productivityTrends = $this->getProductivityTrends($user->id, $startDate, $endDate);

        // Get focus session analytics
        $focusAnalytics = $this->getFocusSessionAnalytics($user->id, $startDate, $endDate);

        return Inertia::render('Analytics/Productivity', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'period' => $period,
            'dateRange' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'productivity' => $productivityData,
            'trends' => $productivityTrends,
            'focusAnalytics' => $focusAnalytics,
        ]);
    }

    /**
     * Get team panda statistics.
     */
    private function getTeamPandaStatistics(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        $teamMembers = User::whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.id', $teamId);
        })->get();

        $totalPandas = 0;
        $totalBreaks = 0;
        $totalBreakTime = 0;
        $memberStats = [];

        foreach ($teamMembers as $member) {
            $memberBreaks = PandaBreak::where('user_id', $member->id)
                ->whereBetween('break_timestamp', [$startDate, $endDate])
                ->get();

            $memberPandas = $memberBreaks->sum('panda_count');
            $memberBreakTime = $memberBreaks->sum('break_duration');

            $totalPandas += $memberPandas;
            $totalBreaks += $memberBreaks->count();
            $totalBreakTime += $memberBreakTime;

            $memberStats[] = [
                'user_id' => $member->id,
                'name' => $member->name,
                'pandas' => $memberPandas,
                'breaks' => $memberBreaks->count(),
                'break_time' => $memberBreakTime,
            ];
        }

        return [
            'total_pandas' => $totalPandas,
            'total_breaks' => $totalBreaks,
            'total_break_time' => $totalBreakTime,
            'average_pandas_per_member' => count($memberStats) > 0 ? round($totalPandas / count($memberStats), 1) : 0,
            'member_stats' => $memberStats,
            'team_size' => count($memberStats),
        ];
    }

    /**
     * Get productivity trends over time.
     */
    private function getProductivityTrends(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayActivities = Activity::where('user_id', $userId)
                ->whereDate('started_at', $current)
                ->get();

            $productiveTime = $dayActivities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
            $totalTime = $dayActivities->sum('duration_seconds');
            $productivityScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

            $trends[] = [
                'date' => $current->toDateString(),
                'productivity_score' => round($productivityScore, 1),
                'total_time' => $totalTime,
                'productive_time' => $productiveTime,
            ];

            $current->addDay();
        }

        return $trends;
    }

    /**
     * Get focus session analytics.
     */
    private function getFocusSessionAnalytics(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $activities = Activity::where('user_id', $userId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->where('productivity_score', '>=', 0.8) // High productivity activities
            ->where('duration_seconds', '>=', 1800) // At least 30 minutes
            ->get();

        $focusSessions = $activities->count();
        $totalFocusTime = $activities->sum('duration_seconds');
        $averageFocusLength = $focusSessions > 0 ? $totalFocusTime / $focusSessions : 0;

        return [
            'focus_sessions' => $focusSessions,
            'total_focus_time' => $totalFocusTime,
            'average_focus_length' => round($averageFocusLength / 60, 1), // Convert to minutes
            'longest_session' => $activities->max('duration_seconds'),
            'focus_by_hour' => $activities->groupBy(function ($activity) {
                return $activity->started_at->hour;
            })->map->count()->toArray(),
        ];
    }

    /**
     * Export analytics data as JSON.
     */
    public function export(Request $request)
    {
        $user = $request->user() ?? User::first();
        $period = $request->input('period', 'month');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        $data = [
            'user' => $user->only(['id', 'name', 'email']),
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'productivity' => $this->categorizationService->calculateProductivityScore($user->id, $startDate, $endDate),
            'panda_analytics' => $this->getPandaBreakAnalytics($user->id, $startDate, $endDate),
            'daily_breakdown' => $this->getDailyBreakdown($user->id, $startDate, $endDate),
            'activity_trends' => $this->getActivityTrends($user->id, $startDate, $endDate),
            'exported_at' => now()->toISOString(),
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="kyukei-panda-analytics-' . $period . '.json"');
    }
}
