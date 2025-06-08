<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ActivityTrackingController extends Controller
{
    public function __construct(
        private TimeTrackingService $timeTrackingService
    ) {}

    /**
     * Record a new activity from external time tracking applications.
     */
    public function recordActivity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_email' => 'required|email|exists:users,email',
            'application_name' => 'required|string|max:255',
            'window_title' => 'nullable|string|max:500',
            'url' => 'nullable|url|max:500',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'duration_seconds' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Find user
        $user = User::where('email', $validated['user_email'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Calculate duration if not provided
        $startedAt = Carbon::parse($validated['started_at']);
        $endedAt = $validated['ended_at'] ? Carbon::parse($validated['ended_at']) : null;
        $durationSeconds = $validated['duration_seconds'] ??
            ($endedAt ? $endedAt->diffInSeconds($startedAt) : 0);

        try {
            // Process the activity
            $activity = $this->timeTrackingService->processActivity([
                'user_id' => $user->id,
                'application_name' => $validated['application_name'],
                'window_title' => $validated['window_title'],
                'url' => $validated['url'],
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'is_manual' => false,
                'description' => $validated['description'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Activity recorded successfully',
                'data' => [
                    'activity_id' => $activity->id,
                    'category' => $activity->category?->name,
                    'productivity_score' => $activity->productivity_score,
                    'project' => $activity->project?->name,
                    'auto_assigned_project' => $activity->project_id !== null,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record activity',
                'error' => $e->getMessage()
            ], 500);
        }
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
