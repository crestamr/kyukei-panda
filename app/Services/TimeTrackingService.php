<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Models\Team;
use App\Events\ActivityCategorized;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TimeTrackingService
{
    public function __construct(
        private ActivityCategorizationService $categorizationService
    ) {}

    /**
     * Process and categorize a new activity with automatic project assignment.
     */
    public function processActivity(array $activityData): Activity
    {
        $user = User::find($activityData['user_id']);
        
        // Categorize the activity
        $categorization = $this->categorizationService->categorizeActivity(
            $activityData['application_name'],
            $activityData['window_title'] ?? null,
            $activityData['url'] ?? null
        );

        // Auto-assign project based on context
        $projectId = $this->autoAssignProject($user, $activityData, $categorization);

        // Create the activity
        $activity = Activity::create([
            'user_id' => $activityData['user_id'],
            'project_id' => $projectId,
            'application_name' => $activityData['application_name'],
            'window_title' => $activityData['window_title'] ?? null,
            'url' => $activityData['url'] ?? null,
            'category_id' => $categorization['category_id'],
            'started_at' => $activityData['started_at'],
            'ended_at' => $activityData['ended_at'] ?? null,
            'duration_seconds' => $activityData['duration_seconds'] ?? 0,
            'productivity_score' => $categorization['productivity_score'],
            'is_manual' => $activityData['is_manual'] ?? false,
            'description' => $activityData['description'] ?? null,
        ]);

        // Calculate productivity update for real-time broadcasting
        $productivityUpdate = $this->calculateProductivityUpdate($user, $activity);

        // Broadcast the activity categorization event
        broadcast(new ActivityCategorized($activity, $user, $productivityUpdate))->toOthers();

        Log::info("Activity processed and categorized", [
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'project_id' => $projectId,
            'category' => $categorization['category_name'],
            'productivity_score' => $categorization['productivity_score'],
        ]);

        return $activity;
    }

    /**
     * Auto-assign project based on activity context and user patterns.
     */
    private function autoAssignProject(User $user, array $activityData, array $categorization): ?int
    {
        // Get user's active projects
        $userTeams = $user->teams;
        $activeProjects = Project::whereIn('team_id', $userTeams->pluck('id'))
            ->where('is_active', true)
            ->get();

        if ($activeProjects->isEmpty()) {
            return null;
        }

        // Strategy 1: Match by window title or URL keywords
        $projectByKeywords = $this->matchProjectByKeywords($activeProjects, $activityData);
        if ($projectByKeywords) {
            return $projectByKeywords->id;
        }

        // Strategy 2: Use recent activity patterns
        $projectByPattern = $this->matchProjectByPattern($user, $activeProjects, $activityData);
        if ($projectByPattern) {
            return $projectByPattern->id;
        }

        // Strategy 3: Use most active project for productive activities
        if ($categorization['productivity_score'] >= 0.7) {
            $mostActiveProject = $this->getMostActiveProject($user, $activeProjects);
            if ($mostActiveProject) {
                return $mostActiveProject->id;
            }
        }

        return null;
    }

    /**
     * Match project by keywords in window title or URL.
     */
    private function matchProjectByKeywords($projects, array $activityData): ?Project
    {
        $searchText = strtolower(
            ($activityData['window_title'] ?? '') . ' ' . 
            ($activityData['url'] ?? '') . ' ' . 
            ($activityData['application_name'] ?? '')
        );

        foreach ($projects as $project) {
            $projectKeywords = [
                strtolower($project->name),
                strtolower($project->client?->name ?? ''),
            ];

            // Add project-specific keywords from description
            if ($project->description) {
                $projectKeywords = array_merge($projectKeywords, 
                    explode(' ', strtolower($project->description))
                );
            }

            foreach ($projectKeywords as $keyword) {
                if (strlen($keyword) > 3 && str_contains($searchText, $keyword)) {
                    return $project;
                }
            }
        }

        return null;
    }

    /**
     * Match project based on recent activity patterns.
     */
    private function matchProjectByPattern(User $user, $projects, array $activityData): ?Project
    {
        // Get recent activities (last 2 hours) for the same application
        $recentActivities = Activity::where('user_id', $user->id)
            ->where('application_name', $activityData['application_name'])
            ->where('started_at', '>=', Carbon::now()->subHours(2))
            ->whereNotNull('project_id')
            ->with('project')
            ->get();

        if ($recentActivities->isEmpty()) {
            return null;
        }

        // Find the most frequently used project for this application
        $projectCounts = $recentActivities->groupBy('project_id')
            ->map->count()
            ->sortDesc();

        $mostUsedProjectId = $projectCounts->keys()->first();
        
        return $projects->find($mostUsedProjectId);
    }

    /**
     * Get the most active project for the user in the last week.
     */
    private function getMostActiveProject(User $user, $projects): ?Project
    {
        $projectActivity = Activity::where('user_id', $user->id)
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('started_at', '>=', Carbon::now()->subWeek())
            ->selectRaw('project_id, SUM(duration_seconds) as total_time')
            ->groupBy('project_id')
            ->orderByDesc('total_time')
            ->first();

        if (!$projectActivity) {
            return null;
        }

        return $projects->find($projectActivity->project_id);
    }

    /**
     * Calculate productivity update for broadcasting.
     */
    private function calculateProductivityUpdate(User $user, Activity $activity): array
    {
        $today = Carbon::today();
        
        // Get today's activities
        $todayActivities = Activity::where('user_id', $user->id)
            ->whereDate('started_at', $today)
            ->get();

        $totalTime = $todayActivities->sum('duration_seconds');
        $productiveTime = $todayActivities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
        $productivityScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

        return [
            'daily_productivity_score' => round($productivityScore, 1),
            'total_time_today' => $totalTime,
            'productive_time_today' => $productiveTime,
            'activities_count_today' => $todayActivities->count(),
            'current_activity' => [
                'application_name' => $activity->application_name,
                'category_name' => $activity->category?->name,
                'productivity_score' => $activity->productivity_score,
                'project_name' => $activity->project?->name,
            ],
        ];
    }

    /**
     * Generate time tracking report for a user and period.
     */
    public function generateTimeReport(User $user, Carbon $startDate, Carbon $endDate, ?int $projectId = null): array
    {
        $query = Activity::where('user_id', $user->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->with(['project.client', 'category']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $activities = $query->get();

        // Group by project
        $projectBreakdown = $activities->groupBy('project_id')->map(function ($projectActivities, $projectId) {
            $project = $projectActivities->first()->project;
            $totalTime = $projectActivities->sum('duration_seconds');
            $billableAmount = $project && $project->hourly_rate ? 
                ($totalTime / 3600) * $project->hourly_rate : 0;

            return [
                'project_id' => $projectId,
                'project_name' => $project?->name ?? 'No Project',
                'client_name' => $project?->client?->name,
                'hourly_rate' => $project?->hourly_rate,
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'billable_amount' => $billableAmount,
                'activities_count' => $projectActivities->count(),
                'avg_productivity' => $projectActivities->avg('productivity_score'),
            ];
        })->sortByDesc('total_time')->values();

        // Daily breakdown
        $dailyBreakdown = $activities->groupBy(function ($activity) {
            return $activity->started_at->toDateString();
        })->map(function ($dayActivities, $date) {
            return [
                'date' => $date,
                'total_time' => $dayActivities->sum('duration_seconds'),
                'billable_time' => $dayActivities->whereNotNull('project.hourly_rate')->sum('duration_seconds'),
                'activities_count' => $dayActivities->count(),
                'avg_productivity' => $dayActivities->avg('productivity_score'),
            ];
        })->sortBy('date')->values();

        $totalTime = $activities->sum('duration_seconds');
        $totalBillable = $projectBreakdown->sum('billable_amount');

        return [
            'summary' => [
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'total_billable' => $totalBillable,
                'activities_count' => $activities->count(),
                'avg_productivity' => $activities->avg('productivity_score'),
                'projects_count' => $projectBreakdown->count(),
            ],
            'project_breakdown' => $projectBreakdown,
            'daily_breakdown' => $dailyBreakdown,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Format duration in seconds to human readable format.
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}
