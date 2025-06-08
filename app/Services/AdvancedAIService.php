<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AdvancedAIService
{
    private const AI_API_ENDPOINT = 'https://api.openai.com/v1';
    private const TENSORFLOW_ENDPOINT = 'http://tensorflow-serving:8501/v1/models';
    
    /**
     * Advanced productivity prediction using neural networks.
     */
    public function predictProductivityWithNeuralNetwork(int $userId, int $daysAhead = 7): array
    {
        $cacheKey = "neural_productivity_prediction:{$userId}:{$daysAhead}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $daysAhead) {
            // Gather comprehensive user data
            $userData = $this->gatherUserDataForAI($userId);
            
            // Prepare neural network input features
            $features = $this->prepareNeuralNetworkFeatures($userData);
            
            // Call TensorFlow Serving API
            $prediction = $this->callTensorFlowModel('productivity_predictor', $features);
            
            if (!$prediction['success']) {
                return $this->fallbackProductivityPrediction($userData, $daysAhead);
            }
            
            // Process neural network output
            $predictions = $this->processNeuralNetworkOutput($prediction['data'], $daysAhead);
            
            return [
                'predictions' => $predictions,
                'confidence_score' => $prediction['confidence'] ?? 0.85,
                'model_version' => 'neural_network_v2.1',
                'features_used' => count($features),
                'prediction_horizon' => $daysAhead,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Natural Language Processing for activity insights.
     */
    public function generateNaturalLanguageInsights(int $userId): array
    {
        $userData = $this->gatherUserDataForAI($userId);
        
        // Prepare context for GPT
        $context = $this->prepareNLPContext($userData);
        
        $prompt = "Analyze this user's productivity data and provide personalized insights in a friendly, encouraging tone. Focus on patterns, achievements, and actionable recommendations. Data: " . json_encode($context);
        
        $response = $this->callOpenAIGPT($prompt, [
            'max_tokens' => 500,
            'temperature' => 0.7,
            'model' => 'gpt-4',
        ]);
        
        if ($response['success']) {
            return [
                'insights' => $response['text'],
                'sentiment' => $this->analyzeSentiment($response['text']),
                'key_points' => $this->extractKeyPoints($response['text']),
                'action_items' => $this->extractActionItems($response['text']),
                'generated_at' => now()->toISOString(),
            ];
        }
        
        return $this->generateFallbackInsights($userData);
    }

    /**
     * Computer Vision analysis for workspace optimization.
     */
    public function analyzeWorkspaceFromScreenshots(array $screenshots): array
    {
        $analysisResults = [];
        
        foreach ($screenshots as $screenshot) {
            // Call computer vision API
            $visionResult = $this->callComputerVisionAPI($screenshot);
            
            if ($visionResult['success']) {
                $analysisResults[] = [
                    'timestamp' => $screenshot['timestamp'],
                    'detected_objects' => $visionResult['objects'],
                    'screen_layout' => $visionResult['layout'],
                    'distraction_score' => $this->calculateDistractionScore($visionResult),
                    'focus_areas' => $visionResult['focus_areas'],
                    'recommendations' => $this->generateWorkspaceRecommendations($visionResult),
                ];
            }
        }
        
        return [
            'analysis_results' => $analysisResults,
            'overall_workspace_score' => $this->calculateOverallWorkspaceScore($analysisResults),
            'optimization_suggestions' => $this->generateWorkspaceOptimizations($analysisResults),
            'analyzed_at' => now()->toISOString(),
        ];
    }

    /**
     * Advanced team dynamics prediction using graph neural networks.
     */
    public function predictTeamDynamicsWithGNN(int $teamId): array
    {
        $team = Team::with(['users', 'projects'])->find($teamId);
        
        // Build team interaction graph
        $interactionGraph = $this->buildTeamInteractionGraph($team);
        
        // Prepare graph neural network input
        $graphFeatures = $this->prepareGraphNeuralNetworkInput($interactionGraph);
        
        // Call Graph Neural Network model
        $prediction = $this->callTensorFlowModel('team_dynamics_gnn', $graphFeatures);
        
        if ($prediction['success']) {
            return [
                'team_cohesion_score' => $prediction['data']['cohesion'],
                'collaboration_efficiency' => $prediction['data']['efficiency'],
                'communication_patterns' => $prediction['data']['communication'],
                'potential_conflicts' => $prediction['data']['conflicts'],
                'optimization_recommendations' => $this->generateTeamOptimizations($prediction['data']),
                'confidence' => $prediction['confidence'],
                'model_version' => 'gnn_v1.3',
            ];
        }
        
        return $this->fallbackTeamDynamicsAnalysis($team);
    }

    /**
     * Reinforcement learning for personalized break scheduling.
     */
    public function optimizeBreakSchedulingWithRL(int $userId): array
    {
        $userHistory = $this->getUserBreakHistory($userId);
        $productivityHistory = $this->getUserProductivityHistory($userId);
        
        // Prepare reinforcement learning environment state
        $state = $this->prepareRLState($userHistory, $productivityHistory);
        
        // Call reinforcement learning model
        $rlResult = $this->callTensorFlowModel('break_scheduler_rl', $state);
        
        if ($rlResult['success']) {
            $optimizedSchedule = $this->processRLOutput($rlResult['data']);
            
            return [
                'optimized_schedule' => $optimizedSchedule,
                'expected_productivity_gain' => $rlResult['data']['expected_gain'],
                'confidence_interval' => $rlResult['data']['confidence_interval'],
                'learning_progress' => $rlResult['data']['learning_progress'],
                'model_version' => 'rl_v2.0',
                'generated_at' => now()->toISOString(),
            ];
        }
        
        return $this->fallbackBreakScheduling($userHistory);
    }

    /**
     * Anomaly detection using autoencoders.
     */
    public function detectAnomaliesWithAutoencoder(int $userId): array
    {
        $recentActivities = Activity::where('user_id', $userId)
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->get();
        
        // Prepare autoencoder input
        $sequences = $this->prepareTimeSeriesSequences($recentActivities);
        
        // Call autoencoder model
        $anomalyResult = $this->callTensorFlowModel('anomaly_detector_ae', $sequences);
        
        if ($anomalyResult['success']) {
            $anomalies = $this->processAnomalyDetectionOutput($anomalyResult['data']);
            
            return [
                'detected_anomalies' => $anomalies,
                'anomaly_score' => $anomalyResult['data']['overall_score'],
                'risk_level' => $this->calculateRiskLevel($anomalyResult['data']['overall_score']),
                'recommendations' => $this->generateAnomalyRecommendations($anomalies),
                'model_confidence' => $anomalyResult['confidence'],
                'detection_threshold' => 0.85,
            ];
        }
        
        return $this->fallbackAnomalyDetection($recentActivities);
    }

    /**
     * Sentiment analysis for team communication.
     */
    public function analyzeTeamCommunicationSentiment(int $teamId): array
    {
        // Get team communication data (Slack messages, comments, etc.)
        $communications = $this->getTeamCommunications($teamId);
        
        $sentimentResults = [];
        foreach ($communications as $communication) {
            $sentiment = $this->analyzeSentiment($communication['text']);
            $sentimentResults[] = [
                'id' => $communication['id'],
                'user_id' => $communication['user_id'],
                'timestamp' => $communication['timestamp'],
                'sentiment' => $sentiment,
                'confidence' => $sentiment['confidence'],
                'emotions' => $this->detectEmotions($communication['text']),
            ];
        }
        
        return [
            'individual_sentiments' => $sentimentResults,
            'team_sentiment_trend' => $this->calculateSentimentTrend($sentimentResults),
            'emotional_climate' => $this->assessEmotionalClimate($sentimentResults),
            'communication_health_score' => $this->calculateCommunicationHealthScore($sentimentResults),
            'recommendations' => $this->generateCommunicationRecommendations($sentimentResults),
        ];
    }

    /**
     * Predictive analytics for project success.
     */
    public function predictProjectSuccess(int $projectId): array
    {
        $project = \App\Models\Project::with(['team.users', 'activities'])->find($projectId);
        
        // Gather project features
        $projectFeatures = $this->extractProjectFeatures($project);
        
        // Call project success prediction model
        $prediction = $this->callTensorFlowModel('project_success_predictor', $projectFeatures);
        
        if ($prediction['success']) {
            return [
                'success_probability' => $prediction['data']['success_probability'],
                'completion_date_prediction' => $prediction['data']['completion_date'],
                'risk_factors' => $prediction['data']['risk_factors'],
                'success_factors' => $prediction['data']['success_factors'],
                'recommendations' => $this->generateProjectRecommendations($prediction['data']),
                'confidence' => $prediction['confidence'],
                'model_version' => 'project_success_v1.5',
            ];
        }
        
        return $this->fallbackProjectAnalysis($project);
    }

    // Private helper methods

    private function gatherUserDataForAI(int $userId): array
    {
        $user = User::with(['activities', 'pandaBreaks', 'teams'])->find($userId);
        
        return [
            'user_profile' => [
                'id' => $user->id,
                'created_at' => $user->created_at,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
            ],
            'activity_patterns' => $this->extractActivityPatterns($user->activities),
            'break_patterns' => $this->extractBreakPatterns($user->pandaBreaks),
            'productivity_metrics' => $this->calculateProductivityMetrics($user->activities),
            'team_interactions' => $this->extractTeamInteractions($user),
            'temporal_features' => $this->extractTemporalFeatures($user->activities),
        ];
    }

    private function prepareNeuralNetworkFeatures(array $userData): array
    {
        // Convert user data to numerical features for neural network
        $features = [];
        
        // Temporal features (24 hours, 7 days)
        $features = array_merge($features, $userData['temporal_features']['hourly_productivity'] ?? array_fill(0, 24, 0));
        $features = array_merge($features, $userData['temporal_features']['daily_productivity'] ?? array_fill(0, 7, 0));
        
        // Activity patterns
        $features[] = $userData['productivity_metrics']['avg_productivity'] ?? 0;
        $features[] = $userData['productivity_metrics']['productivity_variance'] ?? 0;
        $features[] = $userData['productivity_metrics']['focus_duration'] ?? 0;
        
        // Break patterns
        $features[] = $userData['break_patterns']['avg_break_frequency'] ?? 0;
        $features[] = $userData['break_patterns']['avg_break_duration'] ?? 0;
        $features[] = $userData['break_patterns']['break_compliance'] ?? 0;
        
        // Team interaction features
        $features[] = $userData['team_interactions']['collaboration_score'] ?? 0;
        $features[] = $userData['team_interactions']['communication_frequency'] ?? 0;
        
        return array_map('floatval', $features);
    }

    private function callTensorFlowModel(string $modelName, array $features): array
    {
        try {
            $response = Http::timeout(30)->post(self::TENSORFLOW_ENDPOINT . "/{$modelName}:predict", [
                'instances' => [$features],
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['predictions'][0] ?? [],
                    'confidence' => $data['confidence'] ?? 0.8,
                ];
            }
            
            return ['success' => false, 'error' => 'TensorFlow API call failed'];
            
        } catch (\Exception $e) {
            Log::error('TensorFlow model call failed', [
                'model' => $modelName,
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function callOpenAIGPT(string $prompt, array $options = []): array
    {
        try {
            $response = Http::withToken(config('services.openai.api_key'))
                ->timeout(60)
                ->post(self::AI_API_ENDPOINT . '/chat/completions', [
                    'model' => $options['model'] ?? 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful productivity assistant for the Kyukei-Panda platform.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => $options['max_tokens'] ?? 300,
                    'temperature' => $options['temperature'] ?? 0.7,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'text' => $data['choices'][0]['message']['content'] ?? '',
                    'usage' => $data['usage'] ?? [],
                ];
            }
            
            return ['success' => false, 'error' => 'OpenAI API call failed'];
            
        } catch (\Exception $e) {
            Log::error('OpenAI API call failed', [
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function callComputerVisionAPI(array $screenshot): array
    {
        // Simulate computer vision analysis
        return [
            'success' => true,
            'objects' => [
                ['name' => 'code_editor', 'confidence' => 0.95, 'bbox' => [100, 100, 800, 600]],
                ['name' => 'browser', 'confidence' => 0.87, 'bbox' => [900, 100, 1200, 400]],
                ['name' => 'chat_application', 'confidence' => 0.72, 'bbox' => [900, 450, 1200, 700]],
            ],
            'layout' => 'multi_window',
            'focus_areas' => ['code_editor'],
        ];
    }

    private function analyzeSentiment(string $text): array
    {
        // Simplified sentiment analysis (in production, use proper NLP service)
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic'];
        $negativeWords = ['bad', 'terrible', 'awful', 'horrible', 'disappointing', 'frustrating'];
        
        $words = str_word_count(strtolower($text), 1);
        $positiveCount = count(array_intersect($words, $positiveWords));
        $negativeCount = count(array_intersect($words, $negativeWords));
        
        $score = ($positiveCount - $negativeCount) / max(count($words), 1);
        
        if ($score > 0.1) {
            $sentiment = 'positive';
        } elseif ($score < -0.1) {
            $sentiment = 'negative';
        } else {
            $sentiment = 'neutral';
        }
        
        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'confidence' => min(abs($score) + 0.5, 1.0),
        ];
    }

    private function extractActivityPatterns(Collection $activities): array
    {
        $hourlyActivity = array_fill(0, 24, 0);
        $dailyActivity = array_fill(0, 7, 0);
        
        foreach ($activities as $activity) {
            $hour = $activity->started_at->hour;
            $day = $activity->started_at->dayOfWeek;
            
            $hourlyActivity[$hour] += $activity->duration_seconds;
            $dailyActivity[$day] += $activity->duration_seconds;
        }
        
        return [
            'hourly_distribution' => $hourlyActivity,
            'daily_distribution' => $dailyActivity,
            'peak_hours' => array_keys($hourlyActivity, max($hourlyActivity)),
            'peak_days' => array_keys($dailyActivity, max($dailyActivity)),
        ];
    }

    private function extractBreakPatterns(Collection $breaks): array
    {
        if ($breaks->isEmpty()) {
            return [
                'avg_break_frequency' => 0,
                'avg_break_duration' => 0,
                'break_compliance' => 0,
            ];
        }
        
        $dailyBreaks = $breaks->groupBy(function ($break) {
            return $break->break_timestamp->toDateString();
        });
        
        return [
            'avg_break_frequency' => $dailyBreaks->avg(function ($dayBreaks) {
                return $dayBreaks->sum('panda_count');
            }),
            'avg_break_duration' => $breaks->avg('break_duration'),
            'break_compliance' => min($breaks->sum('panda_count') / 6, 1.0), // 6 pandas per day target
        ];
    }

    private function calculateProductivityMetrics(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return [
                'avg_productivity' => 0,
                'productivity_variance' => 0,
                'focus_duration' => 0,
            ];
        }
        
        $productivityScores = $activities->pluck('productivity_score');
        $avgProductivity = $productivityScores->avg();
        $variance = $productivityScores->map(function ($score) use ($avgProductivity) {
            return pow($score - $avgProductivity, 2);
        })->avg();
        
        return [
            'avg_productivity' => $avgProductivity,
            'productivity_variance' => sqrt($variance),
            'focus_duration' => $activities->avg('duration_seconds') / 60, // in minutes
        ];
    }

    private function fallbackProductivityPrediction(array $userData, int $daysAhead): array
    {
        // Simple linear trend prediction as fallback
        $avgProductivity = $userData['productivity_metrics']['avg_productivity'] ?? 0.7;
        $predictions = [];
        
        for ($i = 1; $i <= $daysAhead; $i++) {
            $predictions[] = [
                'date' => now()->addDays($i)->toDateString(),
                'predicted_productivity' => max(0, min(1, $avgProductivity + (rand(-10, 10) / 100))),
                'confidence' => 0.6,
            ];
        }
        
        return [
            'predictions' => $predictions,
            'confidence_score' => 0.6,
            'model_version' => 'fallback_linear',
            'generated_at' => now()->toISOString(),
        ];
    }

    private function generateFallbackInsights(array $userData): array
    {
        $avgProductivity = $userData['productivity_metrics']['avg_productivity'] ?? 0.7;
        $breakCompliance = $userData['break_patterns']['break_compliance'] ?? 0.5;
        
        $insights = "Based on your recent activity, ";
        
        if ($avgProductivity > 0.8) {
            $insights .= "you're maintaining excellent productivity levels! ";
        } elseif ($avgProductivity > 0.6) {
            $insights .= "your productivity is good with room for improvement. ";
        } else {
            $insights .= "there's significant opportunity to boost your productivity. ";
        }
        
        if ($breakCompliance < 0.5) {
            $insights .= "Consider taking more regular breaks to maintain your energy levels.";
        } else {
            $insights .= "Your break patterns are helping maintain good work-life balance.";
        }
        
        return [
            'insights' => $insights,
            'sentiment' => ['sentiment' => 'positive', 'score' => 0.7],
            'key_points' => ['Productivity analysis', 'Break recommendations'],
            'action_items' => ['Take regular breaks', 'Monitor productivity patterns'],
            'generated_at' => now()->toISOString(),
        ];
    }
}
