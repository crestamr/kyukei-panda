<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of teams.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?? User::first();

        $teams = $user->teams()
            ->withCount(['users', 'projects', 'clients'])
            ->with(['users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email')
                      ->withPivot('role', 'joined_at');
            }])
            ->get()
            ->map(function ($team) use ($user) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'description' => $team->description,
                    'is_active' => $team->is_active,
                    'users_count' => $team->users_count,
                    'projects_count' => $team->projects_count,
                    'clients_count' => $team->clients_count,
                    'user_role' => $team->users->where('id', $user->id)->first()?->pivot->role,
                    'joined_at' => $team->users->where('id', $user->id)->first()?->pivot->joined_at,
                    'members' => $team->users->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'email' => $member->email,
                            'role' => $member->pivot->role,
                            'joined_at' => $member->pivot->joined_at,
                        ];
                    }),
                ];
            });

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Show the form for creating a new team.
     */
    public function create(): Response
    {
        return Inertia::render('Teams/Create');
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = $request->user() ?? User::first();

        DB::transaction(function () use ($validated, $user) {
            $team = Team::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'],
                'is_active' => true,
            ]);

            // Add creator as admin
            $team->users()->attach($user->id, [
                'role' => 'admin',
                'joined_at' => now(),
            ]);
        });

        return redirect()->route('teams.index')
            ->with('success', 'Team created successfully!');
    }

    /**
     * Display the specified team.
     */
    public function show(Request $request, Team $team): Response
    {
        $user = $request->user() ?? User::first();

        // Check if user is member of this team
        if (!$team->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not a member of this team.');
        }

        $team->load([
            'users' => function ($query) {
                $query->withPivot('role', 'joined_at');
            },
            'projects.client',
            'clients',
            'categories',
            'slackIntegrations'
        ]);

        // Get team statistics
        $stats = [
            'total_members' => $team->users->count(),
            'total_projects' => $team->projects->count(),
            'active_projects' => $team->projects->where('is_active', true)->count(),
            'total_clients' => $team->clients->count(),
            'total_categories' => $team->categories->count(),
        ];

        // Get recent team activity (panda breaks)
        $recentActivity = DB::table('panda_breaks')
            ->join('users', 'panda_breaks.user_id', '=', 'users.id')
            ->join('team_user', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $team->id)
            ->where('panda_breaks.break_timestamp', '>=', now()->subDays(7))
            ->select('panda_breaks.*', 'users.name as user_name')
            ->orderBy('panda_breaks.break_timestamp', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'is_active' => $team->is_active,
                'settings' => $team->settings,
                'members' => $team->users->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->pivot->role,
                        'joined_at' => $member->pivot->joined_at,
                        'slack_user_id' => $member->slack_user_id,
                        'slack_username' => $member->slack_username,
                    ];
                }),
                'projects' => $team->projects->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'color' => $project->color,
                        'client_name' => $project->client?->name,
                        'is_active' => $project->is_active,
                        'hourly_rate' => $project->hourly_rate,
                    ];
                }),
                'clients' => $team->clients->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                        'is_active' => $client->is_active,
                    ];
                }),
                'slack_integrations' => $team->slackIntegrations->map(function ($integration) {
                    return [
                        'id' => $integration->id,
                        'channel_name' => $integration->channel_name,
                        'is_panda_enabled' => $integration->is_panda_enabled,
                        'is_active' => $integration->is_active,
                    ];
                }),
            ],
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'user_role' => $team->users->where('id', $user->id)->first()?->pivot->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Invite a user to the team.
     */
    public function invite(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user() ?? User::first();

        // Check if user is admin or manager
        $userRole = $team->users()->where('user_id', $user->id)->first()?->pivot->role;
        if (!in_array($userRole, ['admin', 'manager'])) {
            abort(403, 'You do not have permission to invite users.');
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,manager,member',
        ]);

        $invitedUser = User::where('email', $validated['email'])->first();

        if (!$invitedUser) {
            return back()->withErrors(['email' => 'User not found with this email address.']);
        }

        if ($team->users()->where('user_id', $invitedUser->id)->exists()) {
            return back()->withErrors(['email' => 'User is already a member of this team.']);
        }

        $team->users()->attach($invitedUser->id, [
            'role' => $validated['role'],
            'joined_at' => now(),
        ]);

        return back()->with('success', "User {$invitedUser->name} has been added to the team!");
    }

    /**
     * Remove a user from the team.
     */
    public function removeMember(Request $request, Team $team, User $member): RedirectResponse
    {
        $user = $request->user() ?? User::first();

        // Check if user is admin or manager
        $userRole = $team->users()->where('user_id', $user->id)->first()?->pivot->role;
        if (!in_array($userRole, ['admin', 'manager'])) {
            abort(403, 'You do not have permission to remove users.');
        }

        // Can't remove yourself if you're the only admin
        if ($user->id === $member->id) {
            $adminCount = $team->users()->wherePivot('role', 'admin')->count();
            if ($adminCount === 1 && $userRole === 'admin') {
                return back()->withErrors(['error' => 'You cannot remove yourself as the only admin.']);
            }
        }

        $team->users()->detach($member->id);

        return back()->with('success', "User {$member->name} has been removed from the team.");
    }
}
