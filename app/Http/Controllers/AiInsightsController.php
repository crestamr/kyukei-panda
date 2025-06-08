<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Services\MachineLearningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiInsightsController extends Controller
{
    public function __construct(
        private MachineLearningService $mlService
    ) {}

    /**
     * Display the AI insights dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?? User::first();

        // Get basic insights for the dashboard
        $breakPredictions = $this->mlService->predictOptimalBreakTimes($user->id);
        $productivityTrends = $this->mlService->analyzeProductivityTrends($user->id);
        $anomalies = $this->mlService->detectProductivityAnomalies($user->id);
        $recommendations = $this->mlService->generatePersonalizedRecommendations($user->id);

        return Inertia::render('AI/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'insights' => [
                'break_predictions' => $breakPredictions,
                'productivity_trends' => $productivityTrends,
                'anomalies' => $anomalies,
                'recommendations' => $recommendations,
            ],
            'last_updated' => now()->toISOString(),
        ]);
    }

    /**
     * Get break time predictions for a user.
     */
    public function getBreakPredictions(Request $request): JsonResponse
    {
        $user = $request->user() ?? User::first();
        $predictions = $this->mlService->predictOptimalBreakTimes($user->id);

        return response()->json([
            'success' => true,
            'data' => $predictions,
        ]);
    }

    /**
     * Get productivity trend analysis.
     */
    public function getProductivityTrends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'integer|min:7|max:90',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = $request->user() ?? User::first();
        $targetUserId = $validated['user_id'] ?? $user->id;
        $days = $validated['days'] ?? 30;

        // Check permissions for viewing other users' data
        if ($targetUserId !== $user->id && !$this->canViewUserInsights($user, $targetUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to view this user\'s insights',
            ], 403);
        }

        $trends = $this->mlService->analyzeProductivityTrends($targetUserId, $days);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Check if user can view insights for another user.
     */
    private function canViewUserInsights(User $user, int $targetUserId): bool
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
}
