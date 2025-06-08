<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $teamId = $request->input('team_id');

        // Get user's teams
        $userTeams = $user->teams;

        // If team_id specified, filter by that team, otherwise show all user's clients
        $query = Client::query();

        if ($teamId) {
            $team = $userTeams->find($teamId);
            if (!$team) {
                abort(403, 'You are not a member of this team.');
            }
            $query->where('team_id', $teamId);
        } else {
            $query->whereIn('team_id', $userTeams->pluck('id'));
        }

        $clients = $query->with(['team', 'projects' => function ($q) {
                $q->withCount('activities');
            }])
            ->withCount('projects')
            ->get()
            ->map(function ($client) {
                // Calculate total time across all client projects
                $totalTime = 0;
                $totalBillable = 0;

                foreach ($client->projects as $project) {
                    $projectTime = $project->activities()->sum('duration_seconds');
                    $totalTime += $projectTime;

                    if ($project->hourly_rate) {
                        $totalBillable += ($projectTime / 3600) * $project->hourly_rate;
                    }
                }

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'address' => $client->address,
                    'notes' => $client->notes,
                    'team_name' => $client->team->name,
                    'is_active' => $client->is_active,
                    'projects_count' => $client->projects_count,
                    'active_projects_count' => $client->projects->where('is_active', true)->count(),
                    'total_time_seconds' => $totalTime,
                    'total_time_formatted' => $this->formatDuration($totalTime),
                    'total_billable' => $totalBillable,
                    'last_activity' => $client->projects->flatMap->activities->sortByDesc('started_at')->first()?->started_at,
                ];
            });

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
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
     * Show the form for creating a new client.
     */
    public function create(Request $request): Response
    {
        $user = $request->user() ?? User::first();
        $teamId = $request->input('team_id');

        $userTeams = $user->teams()->where(function ($query) {
            $query->wherePivot('role', 'admin')->orWherePivot('role', 'manager');
        })->get();

        if ($userTeams->isEmpty()) {
            abort(403, 'You do not have permission to create clients.');
        }

        return Inertia::render('Clients/Create', [
            'teams' => $userTeams->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'selected_team_id' => $teamId,
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user() ?? User::first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'team_id' => 'required|exists:teams,id',
        ]);

        // Check if user can create clients for this team
        $team = Team::find($validated['team_id']);
        $userRole = $team->users()->where('user_id', $user->id)->first()?->pivot->role;

        if (!in_array($userRole, ['admin', 'manager'])) {
            abort(403, 'You do not have permission to create clients for this team.');
        }

        Client::create($validated);

        return redirect()->route('clients.index', ['team_id' => $validated['team_id']])
            ->with('success', 'Client created successfully!');
    }

    /**
     * Display the specified client with billing details.
     */
    public function show(Request $request, Client $client): Response
    {
        $user = $request->user() ?? User::first();

        // Check if user has access to this client
        if (!$client->team->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You do not have access to this client.');
        }

        $period = $request->input('period', 'month');
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Load client with relationships
        $client->load(['team', 'projects.activities' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('started_at', [$startDate, $endDate])->with(['user', 'category']);
        }]);

        // Calculate client statistics
        $totalTime = 0;
        $totalBillable = 0;
        $allActivities = collect();

        foreach ($client->projects as $project) {
            $projectTime = $project->activities->sum('duration_seconds');
            $totalTime += $projectTime;

            if ($project->hourly_rate) {
                $totalBillable += ($projectTime / 3600) * $project->hourly_rate;
            }

            $allActivities = $allActivities->merge($project->activities);
        }

        // Project breakdown
        $projectStats = $client->projects->map(function ($project) use ($period) {
            $projectTime = $project->activities->sum('duration_seconds');
            $projectBillable = $project->hourly_rate ? ($projectTime / 3600) * $project->hourly_rate : 0;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'hourly_rate' => $project->hourly_rate,
                'is_active' => $project->is_active,
                'total_time' => $projectTime,
                'total_time_formatted' => $this->formatDuration($projectTime),
                'total_billable' => $projectBillable,
                'activities_count' => $project->activities->count(),
            ];
        })->sortByDesc('total_time')->values();

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'notes' => $client->notes,
                'team_name' => $client->team->name,
                'is_active' => $client->is_active,
            ],
            'stats' => [
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'total_billable' => $totalBillable,
                'projects_count' => $client->projects->count(),
                'active_projects_count' => $client->projects->where('is_active', true)->count(),
                'activities_count' => $allActivities->count(),
            ],
            'project_stats' => $projectStats,
            'period' => $period,
            'user_role' => $client->team->users()->where('user_id', $user->id)->first()?->pivot->role,
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
