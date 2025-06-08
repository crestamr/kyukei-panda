<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PandaBreak;
use App\Models\DailyPandaLimit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PandaDashboardController extends Controller
{
    /**
     * Display the Kyukei-Panda dashboard.
     */
    public function index(Request $request): Response
    {
        try {
            $user = $request->user() ?? $this->getOrCreateDemoUser();

            // Debug: Log user information
            \Log::info('PandaDashboard: User found', [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
            ]);

            if (!$user) {
                \Log::error('PandaDashboard: No user found after getOrCreateDemoUser()');
                throw new \Exception('Unable to find or create user');
            }

            $today = Carbon::today();

        // Get daily usage
        $dailyUsage = DailyPandaLimit::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // Get recent breaks
        $recentBreaks = PandaBreak::where('user_id', $user->id)
            ->where('break_timestamp', '>=', $today)
            ->orderBy('break_timestamp', 'desc')
            ->limit(10)
            ->get();

        // Get team breaks (if user is part of a team)
        $teamBreaks = [];
        if ($user->teams()->exists()) {
            $team = $user->teams()->first();
            $teamBreaks = $team->users()
                ->with(['dailyPandaLimits' => function ($query) use ($today) {
                    $query->where('date', $today);
                }])
                ->get()
                ->map(function ($member) {
                    $dailyLimit = $member->dailyPandaLimits->first();
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'pandas_used' => $dailyLimit?->pandas_used ?? 0,
                        'total_minutes' => $dailyLimit?->total_break_minutes ?? 0,
                    ];
                });
        }

            return Inertia::render('PandaDashboard', [
                'userId' => $user->id,
                'userName' => $user->name,
                'teamId' => $user->teams()->first()?->id,
                'dailyUsage' => [
                    'pandas_used' => $dailyUsage?->pandas_used ?? 0,
                    'total_break_minutes' => $dailyUsage?->total_break_minutes ?? 0,
                    'recent_breaks' => $recentBreaks->map(function ($break) {
                        return [
                            'id' => $break->id,
                            'panda_count' => $break->panda_count,
                            'break_duration' => $break->break_duration,
                            'break_timestamp' => $break->break_timestamp->toISOString(),
                            'channel_name' => $break->channel_name ?? 'general',
                            'panda_emojis' => $break->panda_emojis,
                        ];
                    })
                ],
                'teamBreaks' => $teamBreaks
            ]);
        } catch (\Exception $e) {
            \Log::error('PandaDashboard error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a safe error response
            return Inertia::render('PandaDashboard', [
                'error' => 'Unable to load dashboard. Please try again.',
                'userId' => null,
                'userName' => 'Demo User',
                'teamId' => null,
                'dailyUsage' => [
                    'pandas_used' => 0,
                    'total_break_minutes' => 0,
                    'recent_breaks' => [],
                ],
                'teamBreaks' => []
            ]);
        }
    }

    /**
     * Get real-time panda status for API calls.
     */
    public function status(Request $request)
    {
        $user = $request->user() ?? $this->getOrCreateDemoUser();
        $today = Carbon::today();

        $dailyUsage = DailyPandaLimit::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        return response()->json([
            'pandas_used' => $dailyUsage?->pandas_used ?? 0,
            'total_break_minutes' => $dailyUsage?->total_break_minutes ?? 0,
            'remaining_pandas' => 6 - ($dailyUsage?->pandas_used ?? 0),
            'remaining_minutes' => (6 - ($dailyUsage?->pandas_used ?? 0)) * 10,
            'panda_visualization' => str_repeat('🐼', $dailyUsage?->pandas_used ?? 0) . str_repeat('⚪', 6 - ($dailyUsage?->pandas_used ?? 0)),
            'last_break' => $dailyUsage?->last_break_at?->toISOString(),
        ]);
    }

    /**
     * Get or create a demo user for testing purposes.
     */
    private function getOrCreateDemoUser(): User
    {
        try {
            $user = User::first();
            \Log::info('getOrCreateDemoUser: User::first() result', [
                'user_found' => $user ? true : false,
                'user_count' => User::count(),
            ]);

            if (!$user) {
                \Log::info('getOrCreateDemoUser: Creating new demo user');
                // Create a demo user if none exists
                $user = User::create([
                    'name' => 'Kyukei-Panda Demo User',
                    'email' => 'demo@kyukei-panda.com',
                    'email_verified_at' => now(),
                    'password' => bcrypt('password'),
                    'timezone' => 'UTC',
                ]);
                \Log::info('getOrCreateDemoUser: Demo user created', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
            }

            return $user;
        } catch (\Exception $e) {
            \Log::error('getOrCreateDemoUser failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
