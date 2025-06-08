<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Category;
use App\Models\ActivityHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ActivityCategorizationService
{
    private array $productivityKeywords = [
        'development' => [
            'keywords' => ['code', 'programming', 'development', 'ide', 'vscode', 'phpstorm', 'intellij', 'sublime', 'atom', 'vim', 'emacs', 'git', 'github', 'gitlab', 'terminal', 'console', 'bash', 'powershell'],
            'score' => 1.0,
            'color' => '#10B981'
        ],
        'design' => [
            'keywords' => ['figma', 'sketch', 'photoshop', 'illustrator', 'canva', 'design', 'ui', 'ux', 'wireframe', 'prototype'],
            'score' => 0.95,
            'color' => '#8B5CF6'
        ],
        'documentation' => [
            'keywords' => ['notion', 'confluence', 'wiki', 'docs', 'documentation', 'readme', 'markdown', 'word', 'google docs'],
            'score' => 0.85,
            'color' => '#3B82F6'
        ],
        'communication' => [
            'keywords' => ['slack', 'teams', 'zoom', 'meet', 'skype', 'discord', 'email', 'outlook', 'gmail', 'chat'],
            'score' => 0.75,
            'color' => '#F59E0B'
        ],
        'project_management' => [
            'keywords' => ['jira', 'trello', 'asana', 'monday', 'clickup', 'basecamp', 'project', 'task', 'kanban'],
            'score' => 0.80,
            'color' => '#06B6D4'
        ],
        'research' => [
            'keywords' => ['browser', 'chrome', 'firefox', 'safari', 'edge', 'google', 'stackoverflow', 'documentation', 'research'],
            'score' => 0.70,
            'color' => '#84CC16'
        ],
        'entertainment' => [
            'keywords' => ['youtube', 'netflix', 'spotify', 'music', 'game', 'gaming', 'steam', 'twitch', 'social media', 'facebook', 'twitter', 'instagram'],
            'score' => 0.10,
            'color' => '#EF4444'
        ],
        'system' => [
            'keywords' => ['finder', 'explorer', 'system', 'settings', 'control panel', 'activity monitor', 'task manager'],
            'score' => 0.30,
            'color' => '#6B7280'
        ]
    ];

    /**
     * Categorize an activity based on application name and window title.
     */
    public function categorizeActivity(string $appName, ?string $windowTitle = null, ?string $url = null): array
    {
        $text = strtolower($appName . ' ' . ($windowTitle ?? '') . ' ' . ($url ?? ''));
        
        // First, try to match against existing custom categories
        $customCategory = $this->matchCustomCategories($text);
        if ($customCategory) {
            return [
                'category_id' => $customCategory->id,
                'category_name' => $customCategory->name,
                'productivity_score' => $customCategory->productivity_score,
                'is_productive' => $customCategory->is_productive,
                'color' => $customCategory->color,
                'confidence' => 0.9
            ];
        }

        // Use built-in AI categorization
        $bestMatch = $this->findBestMatch($text);
        
        return [
            'category_id' => null,
            'category_name' => $bestMatch['name'],
            'productivity_score' => $bestMatch['score'],
            'is_productive' => $bestMatch['score'] >= 0.5,
            'color' => $bestMatch['color'],
            'confidence' => $bestMatch['confidence']
        ];
    }

    /**
     * Match against custom team categories.
     */
    private function matchCustomCategories(string $text): ?Category
    {
        $categories = Category::where('is_global', true)
            ->orWhereNotNull('keywords')
            ->get();

        foreach ($categories as $category) {
            if ($category->matchesKeywords($text)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Find the best matching built-in category.
     */
    private function findBestMatch(string $text): array
    {
        $scores = [];
        
        foreach ($this->productivityKeywords as $categoryName => $data) {
            $score = 0;
            $matchCount = 0;
            
            foreach ($data['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score += strlen($keyword) / strlen($text); // Weight by keyword length
                    $matchCount++;
                }
            }
            
            if ($matchCount > 0) {
                $scores[$categoryName] = [
                    'score' => $score,
                    'match_count' => $matchCount,
                    'productivity_score' => $data['score'],
                    'color' => $data['color']
                ];
            }
        }

        if (empty($scores)) {
            // Default category for unknown activities
            return [
                'name' => 'Unknown',
                'score' => 0.50,
                'color' => '#6B7280',
                'confidence' => 0.1
            ];
        }

        // Find category with highest score
        $bestCategory = array_keys($scores, max($scores))[0];
        $bestData = $scores[$bestCategory];

        return [
            'name' => ucfirst(str_replace('_', ' ', $bestCategory)),
            'score' => $bestData['productivity_score'],
            'color' => $bestData['color'],
            'confidence' => min(0.95, $bestData['score'] * 10) // Convert to confidence score
        ];
    }

    /**
     * Categorize existing ActivityHistory records.
     */
    public function categorizeActivityHistory(ActivityHistory $activityHistory): void
    {
        $result = $this->categorizeActivity(
            $activityHistory->app_name,
            null, // ActivityHistory doesn't have window title
            null
        );

        // Create or find category
        $category = null;
        if (!$result['category_id']) {
            $category = Category::firstOrCreate([
                'name' => $result['category_name'],
                'is_global' => true,
            ], [
                'color' => $result['color'],
                'productivity_score' => $result['productivity_score'],
                'is_productive' => $result['is_productive'],
                'description' => "Auto-generated category for {$result['category_name']} activities",
            ]);
        } else {
            $category = Category::find($result['category_id']);
        }

        // Create Activity record from ActivityHistory
        Activity::create([
            'user_id' => 1, // Default user for migration
            'application_name' => $activityHistory->app_name,
            'window_title' => null,
            'url' => null,
            'category_id' => $category->id,
            'started_at' => $activityHistory->started_at,
            'ended_at' => $activityHistory->ended_at,
            'duration_seconds' => $activityHistory->duration,
            'productivity_score' => $result['productivity_score'],
            'is_manual' => false,
            'description' => "Migrated from activity history",
        ]);

        Log::info("Categorized activity: {$activityHistory->app_name} -> {$result['category_name']} (Score: {$result['productivity_score']})");
    }

    /**
     * Batch categorize all existing ActivityHistory records.
     */
    public function categorizeAllActivityHistory(): void
    {
        $activityHistories = ActivityHistory::whereDoesntHave('activities')->get();
        
        foreach ($activityHistories as $activityHistory) {
            $this->categorizeActivityHistory($activityHistory);
        }

        Log::info("Categorized {$activityHistories->count()} activity history records");
    }

    /**
     * Calculate productivity score for a time period.
     */
    public function calculateProductivityScore(int $userId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $activities = Activity::where('user_id', $userId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->with('category')
            ->get();

        if ($activities->isEmpty()) {
            return [
                'score' => 0,
                'total_time' => 0,
                'productive_time' => 0,
                'break_time' => 0,
                'categories' => []
            ];
        }

        $totalTime = $activities->sum('duration_seconds');
        $productiveTime = $activities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
        $breakTime = $activities->where('productivity_score', '<', 0.5)->sum('duration_seconds');

        $categoryBreakdown = $activities->groupBy('category.name')->map(function ($group) use ($totalTime) {
            $categoryTime = $group->sum('duration_seconds');
            return [
                'time' => $categoryTime,
                'percentage' => round(($categoryTime / $totalTime) * 100, 1),
                'productivity_score' => $group->avg('productivity_score')
            ];
        });

        $overallScore = $totalTime > 0 ? ($productiveTime / $totalTime) * 100 : 0;

        return [
            'score' => round($overallScore, 1),
            'total_time' => $totalTime,
            'productive_time' => $productiveTime,
            'break_time' => $breakTime,
            'categories' => $categoryBreakdown->toArray()
        ];
    }
}
