<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $teamId = $request->input('team_id');

        // Get user's teams
        $userTeams = $user->teams;

        // If team_id specified, filter by that team, otherwise show all user's projects
        $query = Project::query();

        if ($teamId) {
            $team = $userTeams->find($teamId);
            if (!$team) {
                abort(403, 'You are not a member of this team.');
            }
            $query->where('team_id', $teamId);
        } else {
            $query->whereIn('team_id', $userTeams->pluck('id'));
        }

        $projects = $query->with(['team', 'client', 'activities' => function ($q) {
                $q->where('started_at', '>=', Carbon::now()->subDays(30));
            }])
            ->withCount('activities')
            ->get()
            ->map(function ($project) {
                $totalTime = $project->activities->sum('duration_seconds');
                $totalBillable = $project->hourly_rate ? ($totalTime / 3600) * $project->hourly_rate : 0;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'color' => $project->color,
                    'team_name' => $project->team->name,
                    'client_name' => $project->client?->name,
                    'hourly_rate' => $project->hourly_rate,
                    'is_active' => $project->is_active,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'activities_count' => $project->activities_count,
                    'total_time_seconds' => $totalTime,
                    'total_time_formatted' => $this->formatDuration($totalTime),
                    'total_billable' => $totalBillable,
                    'recent_activity' => $project->activities->isNotEmpty() ? $project->activities->last()->started_at : null,
                ];
            });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'teams' => $userTeams->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'selected_team_id' => $teamId,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $teamId = $request->input('team_id');

        $userTeams = $user->teams()->where(function ($query) {
            $query->wherePivot('role', 'admin')->orWherePivot('role', 'manager');
        })->get();

        if ($userTeams->isEmpty()) {
            abort(403, 'You do not have permission to create projects.');
        }

        $clients = collect();
        if ($teamId) {
            $team = $userTeams->find($teamId);
            if ($team) {
                $clients = $team->clients()->where('is_active', true)->get();
            }
        }

        return Inertia::render('Projects/Create', [
            'teams' => $userTeams->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'clients' => $clients->map(fn($client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]),
            'selected_team_id' => $teamId,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user() ?? User::first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'team_id' => 'required|exists:teams,id',
            'client_id' => 'nullable|exists:clients,id',
            'hourly_rate' => 'nullable|numeric|min:0|max:999999.99',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Check if user can create projects for this team
        $team = Team::find($validated['team_id']);
        $userRole = $team->users()->where('user_id', $user->id)->first()?->pivot->role;

        if (!in_array($userRole, ['admin', 'manager'])) {
            abort(403, 'You do not have permission to create projects for this team.');
        }

        // Verify client belongs to the same team if specified
        if ($validated['client_id']) {
            $client = Client::find($validated['client_id']);
            if ($client->team_id !== $validated['team_id']) {
                return back()->withErrors(['client_id' => 'Client must belong to the same team.']);
            }
        }

        Project::create($validated);

        return redirect()->route('projects.index', ['team_id' => $validated['team_id']])
            ->with('success', 'Project created successfully!');
    }

    /**
     * Display the specified project with time tracking details.
     */
    public function show(Request $request, Project $project): Response
    {
        $user = $request->user() ?? User::first();

        // Check if user has access to this project
        if (!$project->team->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You do not have access to this project.');
        }

        $period = $request->input('period', 'month');
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Load project with relationships
        $project->load(['team', 'client']);

        // Get project activities for the period
        $activities = $project->activities()
            ->with(['user', 'category'])
            ->whereBetween('started_at', [$startDate, $endDate])
            ->orderBy('started_at', 'desc')
            ->get();

        // Calculate project statistics
        $totalTime = $activities->sum('duration_seconds');
        $totalBillable = $project->hourly_rate ? ($totalTime / 3600) * $project->hourly_rate : 0;
        $averageProductivity = $activities->avg('productivity_score') ?? 0;

        // Group activities by user
        $userStats = $activities->groupBy('user_id')->map(function ($userActivities, $userId) use ($project) {
            $user = $userActivities->first()->user;
            $userTime = $userActivities->sum('duration_seconds');
            $userBillable = $project->hourly_rate ? ($userTime / 3600) * $project->hourly_rate : 0;

            return [
                'user_id' => $userId,
                'user_name' => $user->name,
                'total_time' => $userTime,
                'total_time_formatted' => $this->formatDuration($userTime),
                'total_billable' => $userBillable,
                'activities_count' => $userActivities->count(),
                'avg_productivity' => $userActivities->avg('productivity_score'),
            ];
        })->sortByDesc('total_time')->values();

        // Daily breakdown
        $dailyStats = $activities->groupBy(function ($activity) {
            return $activity->started_at->toDateString();
        })->map(function ($dayActivities, $date) {
            return [
                'date' => $date,
                'total_time' => $dayActivities->sum('duration_seconds'),
                'activities_count' => $dayActivities->count(),
                'avg_productivity' => $dayActivities->avg('productivity_score'),
            ];
        })->sortBy('date')->values();

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'color' => $project->color,
                'team_name' => $project->team->name,
                'client_name' => $project->client?->name,
                'hourly_rate' => $project->hourly_rate,
                'is_active' => $project->is_active,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
            ],
            'stats' => [
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'total_billable' => $totalBillable,
                'average_productivity' => round($averageProductivity, 1),
                'activities_count' => $activities->count(),
            ],
            'user_stats' => $userStats,
            'daily_stats' => $dailyStats,
            'recent_activities' => $activities->take(20)->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user_name' => $activity->user->name,
                    'application_name' => $activity->application_name,
                    'window_title' => $activity->window_title,
                    'category_name' => $activity->category?->name,
                    'duration_formatted' => $this->formatDuration($activity->duration_seconds),
                    'productivity_score' => $activity->productivity_score,
                    'started_at' => $activity->started_at->toISOString(),
                ];
            }),
            'period' => $period,
            'user_role' => $project->team->users()->where('user_id', $user->id)->first()?->pivot->role,
        ]);
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
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }
}
