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
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    /**
     * Predict optimal break times for a user based on historical patterns.
     */
    public function predictOptimalBreakTimes(int $userId): array
    {
        $cacheKey = "ml_break_prediction:{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // Get historical break data
            $breaks = PandaBreak::where('user_id', $userId)
                ->where('break_timestamp', '>=', Carbon::now()->subDays(30))
                ->get();

            if ($breaks->count() < 10) {
                return $this->getDefaultBreakRecommendations();
            }

            // Analyze break patterns
            $hourlyPatterns = $this->analyzeHourlyBreakPatterns($breaks);
            $productivityCorrelation = $this->analyzeBreakProductivityCorrelation($userId, $breaks);
            $workloadPatterns = $this->analyzeWorkloadPatterns($userId);

            // Generate predictions
            $predictions = [];
            for ($hour = 9; $hour <= 17; $hour++) {
                $score = $this->calculateBreakOptimalityScore($hour, $hourlyPatterns, $productivityCorrelation, $workloadPatterns);
                
                if ($score > 0.6) {
                    $predictions[] = [
                        'hour' => $hour,
                        'minute' => $this->predictOptimalMinute($hour, $breaks),
                        'confidence' => $score,
                        'reason' => $this->generateBreakReason($hour, $score, $hourlyPatterns),
                        'expected_benefit' => $this->calculateExpectedBenefit($score),
                    ];
                }
            }

            return [
                'predictions' => $predictions,
                'patterns' => $hourlyPatterns,
                'confidence_level' => $this->calculateOverallConfidence($breaks->count()),
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    /**
     * Analyze productivity trends and predict future performance.
     */
    public function analyzeProductivityTrends(int $userId, int $days = 30): array
    {
        $cacheKey = "ml_productivity_trends:{$userId}:{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId, $days) {
            $activities = Activity::where('user_id', $userId)
                ->where('started_at', '>=', Carbon::now()->subDays($days))
                ->with('category')
                ->get();

            if ($activities->count() < 20) {
                return ['error' => 'Insufficient data for analysis'];
            }

            // Daily productivity scores
            $dailyScores = $this->calculateDailyProductivityScores($activities);
            
            // Trend analysis
            $trend = $this->calculateTrend($dailyScores);
            $seasonality = $this->analyzeSeasonality($dailyScores);
            $volatility = $this->calculateVolatility($dailyScores);

            // Predictions
            $forecast = $this->forecastProductivity($dailyScores, 7); // 7-day forecast
            $recommendations = $this->generateProductivityRecommendations($dailyScores, $trend, $seasonality);

            return [
                'trend' => [
                    'direction' => $trend['direction'],
                    'strength' => $trend['strength'],
                    'slope' => $trend['slope'],
                ],
                'seasonality' => $seasonality,
                'volatility' => $volatility,
                'current_score' => end($dailyScores)['score'],
                'forecast' => $forecast,
                'recommendations' => $recommendations,
                'confidence' => $this->calculateForecastConfidence($activities->count(), $volatility),
            ];
        });
    }

    /**
     * Detect productivity anomalies and potential burnout indicators.
     */
    public function detectProductivityAnomalies(int $userId): array
    {
        $activities = Activity::where('user_id', $userId)
            ->where('started_at', '>=', Carbon::now()->subDays(14))
            ->get();

        $breaks = PandaBreak::where('user_id', $userId)
            ->where('break_timestamp', '>=', Carbon::now()->subDays(14))
            ->get();

        $anomalies = [];

        // Check for productivity drops
        $productivityDrop = $this->detectProductivityDrop($activities);
        if ($productivityDrop) {
            $anomalies[] = $productivityDrop;
        }

        // Check for break pattern changes
        $breakAnomaly = $this->detectBreakPatternAnomaly($breaks);
        if ($breakAnomaly) {
            $anomalies[] = $breakAnomaly;
        }

        // Check for overwork indicators
        $overworkIndicator = $this->detectOverworkPattern($activities);
        if ($overworkIndicator) {
            $anomalies[] = $overworkIndicator;
        }

        // Check for focus issues
        $focusIssue = $this->detectFocusIssues($activities);
        if ($focusIssue) {
            $anomalies[] = $focusIssue;
        }

        return [
            'anomalies' => $anomalies,
            'risk_level' => $this->calculateRiskLevel($anomalies),
            'recommendations' => $this->generateAnomalyRecommendations($anomalies),
            'detected_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate team productivity insights and recommendations.
     */
    public function analyzeTeamDynamics(int $teamId): array
    {
        $team = Team::with(['users'])->find($teamId);
        $memberAnalytics = [];
        $teamPatterns = [];

        foreach ($team->users as $user) {
            $userActivities = Activity::where('user_id', $user->id)
                ->where('started_at', '>=', Carbon::now()->subDays(30))
                ->get();

            $userBreaks = PandaBreak::where('user_id', $user->id)
                ->where('break_timestamp', '>=', Carbon::now()->subDays(30))
                ->get();

            $memberAnalytics[$user->id] = [
                'user_name' => $user->name,
                'productivity_score' => $this->calculateAverageProductivity($userActivities),
                'break_compliance' => $this->calculateBreakCompliance($userBreaks),
                'collaboration_score' => $this->calculateCollaborationScore($user->id, $teamId),
                'work_pattern' => $this->analyzeWorkPattern($userActivities),
            ];
        }

        // Team-level analysis
        $teamProductivity = $this->calculateTeamProductivity($memberAnalytics);
        $collaborationMatrix = $this->buildCollaborationMatrix($team->users);
        $teamHealth = $this->assessTeamHealth($memberAnalytics);

        return [
            'team_productivity' => $teamProductivity,
            'member_analytics' => $memberAnalytics,
            'collaboration_matrix' => $collaborationMatrix,
            'team_health' => $teamHealth,
            'recommendations' => $this->generateTeamRecommendations($memberAnalytics, $teamHealth),
            'insights' => $this->generateTeamInsights($memberAnalytics),
        ];
    }

    /**
     * Predict project completion time based on current velocity.
     */
    public function predictProjectCompletion(int $projectId): array
    {
        $activities = Activity::where('project_id', $projectId)
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->with('user')
            ->get();

        if ($activities->count() < 10) {
            return ['error' => 'Insufficient project data for prediction'];
        }

        // Calculate velocity metrics
        $dailyVelocity = $this->calculateDailyVelocity($activities);
        $teamVelocity = $this->calculateTeamVelocity($activities);
        $productivityTrend = $this->calculateProjectProductivityTrend($activities);

        // Estimate remaining work (this would typically come from project management data)
        $estimatedRemainingHours = $this->estimateRemainingWork($projectId);
        
        // Predict completion
        $completionPrediction = $this->predictCompletion($dailyVelocity, $estimatedRemainingHours, $productivityTrend);

        return [
            'estimated_completion_date' => $completionPrediction['date'],
            'confidence_interval' => $completionPrediction['confidence'],
            'current_velocity' => $dailyVelocity,
            'team_velocity' => $teamVelocity,
            'productivity_trend' => $productivityTrend,
            'risk_factors' => $this->identifyProjectRisks($activities, $productivityTrend),
            'recommendations' => $this->generateProjectRecommendations($completionPrediction, $teamVelocity),
        ];
    }

    /**
     * Generate personalized productivity recommendations using ML.
     */
    public function generatePersonalizedRecommendations(int $userId): array
    {
        $userProfile = $this->buildUserProductivityProfile($userId);
        $similarUsers = $this->findSimilarUsers($userId, $userProfile);
        $bestPractices = $this->extractBestPractices($similarUsers);

        return [
            'user_profile' => $userProfile,
            'recommendations' => [
                'break_timing' => $this->recommendBreakTiming($userProfile),
                'focus_periods' => $this->recommendFocusPeriods($userProfile),
                'activity_optimization' => $this->recommendActivityOptimization($userProfile),
                'collaboration_timing' => $this->recommendCollaborationTiming($userProfile),
            ],
            'best_practices' => $bestPractices,
            'success_probability' => $this->calculateSuccessProbability($userProfile, $bestPractices),
        ];
    }

    // Helper methods for ML calculations

    private function analyzeHourlyBreakPatterns(Collection $breaks): array
    {
        $hourlyFrequency = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyFrequency[$hour] = 0;
        }

        foreach ($breaks as $break) {
            $hour = $break->break_timestamp->hour;
            $hourlyFrequency[$hour]++;
        }

        $total = array_sum($hourlyFrequency);
        return array_map(fn($count) => $total > 0 ? $count / $total : 0, $hourlyFrequency);
    }

    private function calculateBreakOptimalityScore(int $hour, array $patterns, array $correlation, array $workload): float
    {
        $patternScore = $patterns[$hour] ?? 0;
        $correlationScore = $correlation[$hour] ?? 0.5;
        $workloadScore = 1 - ($workload[$hour] ?? 0.5);

        return ($patternScore * 0.4) + ($correlationScore * 0.4) + ($workloadScore * 0.2);
    }

    private function calculateDailyProductivityScores(Collection $activities): array
    {
        $dailyScores = [];
        $groupedByDate = $activities->groupBy(function ($activity) {
            return $activity->started_at->toDateString();
        });

        foreach ($groupedByDate as $date => $dayActivities) {
            $totalTime = $dayActivities->sum('duration_seconds');
            $productiveTime = $dayActivities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
            $score = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

            $dailyScores[] = [
                'date' => $date,
                'score' => $score,
                'total_time' => $totalTime,
                'activities_count' => $dayActivities->count(),
            ];
        }

        return $dailyScores;
    }

    private function calculateTrend(array $dailyScores): array
    {
        if (count($dailyScores) < 3) {
            return ['direction' => 'stable', 'strength' => 0, 'slope' => 0];
        }

        $scores = array_column($dailyScores, 'score');
        $n = count($scores);
        $x = range(1, $n);

        // Linear regression
        $sumX = array_sum($x);
        $sumY = array_sum($scores);
        $sumXY = array_sum(array_map(fn($i) => $x[$i] * $scores[$i], range(0, $n - 1)));
        $sumX2 = array_sum(array_map(fn($val) => $val * $val, $x));

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $strength = abs($slope) / max($scores) * 100;

        return [
            'direction' => $slope > 0.1 ? 'improving' : ($slope < -0.1 ? 'declining' : 'stable'),
            'strength' => min($strength, 100),
            'slope' => $slope,
        ];
    }

    private function getDefaultBreakRecommendations(): array
    {
        return [
            'predictions' => [
                ['hour' => 10, 'minute' => 30, 'confidence' => 0.7, 'reason' => 'Mid-morning energy dip'],
                ['hour' => 14, 'minute' => 0, 'confidence' => 0.8, 'reason' => 'Post-lunch focus restoration'],
                ['hour' => 16, 'minute' => 0, 'confidence' => 0.6, 'reason' => 'Afternoon productivity boost'],
            ],
            'patterns' => [],
            'confidence_level' => 0.5,
            'last_updated' => now()->toISOString(),
        ];
    }

    private function buildUserProductivityProfile(int $userId): array
    {
        $activities = Activity::where('user_id', $userId)
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->get();

        $breaks = PandaBreak::where('user_id', $userId)
            ->where('break_timestamp', '>=', Carbon::now()->subDays(30))
            ->get();

        return [
            'peak_hours' => $this->identifyPeakHours($activities),
            'preferred_break_times' => $this->identifyPreferredBreakTimes($breaks),
            'productivity_pattern' => $this->identifyProductivityPattern($activities),
            'focus_duration' => $this->calculateAverageFocusDuration($activities),
            'break_frequency' => $this->calculateBreakFrequency($breaks),
            'category_preferences' => $this->analyzeCategoryPreferences($activities),
        ];
    }

    private function identifyPeakHours(Collection $activities): array
    {
        $hourlyProductivity = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourActivities = $activities->filter(fn($activity) => $activity->started_at->hour === $hour);
            $avgProductivity = $hourActivities->avg('productivity_score') ?? 0;
            $hourlyProductivity[$hour] = $avgProductivity;
        }

        arsort($hourlyProductivity);
        return array_slice(array_keys($hourlyProductivity), 0, 3, true);
    }

    private function analyzeBreakProductivityCorrelation(int $userId, Collection $breaks): array
    {
        // Simplified correlation analysis
        $correlation = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $correlation[$hour] = 0.5; // Default neutral correlation
        }
        return $correlation;
    }

    private function analyzeWorkloadPatterns(int $userId): array
    {
        // Simplified workload analysis
        $workload = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $workload[$hour] = 0.5; // Default medium workload
        }
        return $workload;
    }

    private function predictOptimalMinute(int $hour, Collection $breaks): int
    {
        $hourBreaks = $breaks->filter(fn($break) => $break->break_timestamp->hour === $hour);
        if ($hourBreaks->isEmpty()) {
            return 0;
        }
        
        $avgMinute = $hourBreaks->avg(fn($break) => $break->break_timestamp->minute);
        return (int) round($avgMinute);
    }

    private function generateBreakReason(int $hour, float $score, array $patterns): string
    {
        if ($hour >= 10 && $hour <= 11) return 'Mid-morning energy optimization';
        if ($hour >= 14 && $hour <= 15) return 'Post-lunch focus restoration';
        if ($hour >= 16 && $hour <= 17) return 'Afternoon productivity boost';
        return 'Optimal break timing based on your patterns';
    }

    private function calculateExpectedBenefit(float $score): string
    {
        if ($score > 0.8) return 'High productivity boost expected';
        if ($score > 0.6) return 'Moderate productivity improvement';
        return 'Small but measurable benefit';
    }

    private function calculateOverallConfidence(int $dataPoints): float
    {
        return min(1.0, $dataPoints / 50); // Full confidence with 50+ data points
    }
}
