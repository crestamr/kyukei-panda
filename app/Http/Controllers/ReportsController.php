<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use App\Models\Client;
use App\Models\Project;
use App\Services\BillingService;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportsController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private TimeTrackingService $timeTrackingService
    ) {}

    /**
     * Display the reports dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user() ?? User::first();
        $userTeams = $user->teams;

        return Inertia::render('Reports/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'teams' => $userTeams->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'role' => $team->pivot->role,
            ]),
        ]);
    }

    /**
     * Generate time tracking report.
     */
    public function timeTracking(Request $request): InertiaResponse
    {
        $user = $request->user() ?? User::first();

        $validated = $request->validate([
            'period' => 'string|in:today,week,month,quarter,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'nullable|exists:projects,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        // Determine date range
        if ($validated['start_date'] && $validated['end_date']) {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($validated['period'] ?? 'month');
        }

        // Determine target user (for managers/admins)
        $targetUser = $user;
        if (isset($validated['user_id']) && $this->canViewUserReports($user, $validated['user_id'])) {
            $targetUser = User::find($validated['user_id']);
        }

        // Generate report
        $report = $this->timeTrackingService->generateTimeReport(
            $targetUser,
            $startDate,
            $endDate,
            $validated['project_id'] ?? null
        );

        // Get available projects for filter
        $userTeams = $targetUser->teams;
        $availableProjects = Project::whereIn('team_id', $userTeams->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client?->name,
            ]);

        return Inertia::render('Reports/TimeTracking', [
            'report' => $report,
            'filters' => [
                'period' => $validated['period'] ?? 'month',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'project_id' => $validated['project_id'] ?? null,
                'user_id' => $targetUser->id,
            ],
            'available_projects' => $availableProjects,
            'target_user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
            ],
            'can_view_all_users' => $this->canViewAllUsers($user),
        ]);
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

    /**
     * Check if user can view reports for another user.
     */
    private function canViewUserReports(User $user, int $targetUserId): bool
    {
        if ($user->id === $targetUserId) {
            return true;
        }

        // Check if user is admin/manager of any team that the target user belongs to
        $targetUser = User::find($targetUserId);
        $sharedTeams = $user->teams()->whereIn('teams.id', $targetUser->teams->pluck('id'))->get();

        foreach ($sharedTeams as $team) {
            $userRole = $team->pivot->role;
            if (in_array($userRole, ['admin', 'manager'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can view all users' reports.
     */
    private function canViewAllUsers(User $user): bool
    {
        return $user->teams()->wherePivotIn('role', ['admin', 'manager'])->exists();
    }
}
