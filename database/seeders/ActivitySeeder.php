<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Project;
use App\Services\ActivityCategorizationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorizationService = app(ActivityCategorizationService::class);
        $users = User::all();
        $project = Project::first();

        // Sample applications and their typical window titles
        $sampleActivities = [
            ['app' => 'Visual Studio Code', 'title' => 'kyukei-panda - main.php', 'url' => null],
            ['app' => 'Google Chrome', 'title' => 'Stack Overflow - How to use Laravel', 'url' => 'https://stackoverflow.com'],
            ['app' => 'Slack', 'title' => 'Development Team', 'url' => null],
            ['app' => 'PhpStorm', 'title' => 'ActivityController.php', 'url' => null],
            ['app' => 'Terminal', 'title' => 'php artisan migrate', 'url' => null],
            ['app' => 'Figma', 'title' => 'Dashboard Design', 'url' => null],
            ['app' => 'YouTube', 'title' => 'Laravel Tutorial', 'url' => 'https://youtube.com'],
            ['app' => 'Notion', 'title' => 'Project Documentation', 'url' => null],
            ['app' => 'Zoom', 'title' => 'Daily Standup Meeting', 'url' => null],
            ['app' => 'Spotify', 'title' => 'Focus Music Playlist', 'url' => null],
        ];

        foreach ($users as $user) {
            $this->command->info("Creating activities for user: {$user->name}");

            // Create activities for the past 7 days
            for ($day = 0; $day < 7; $day++) {
                $date = Carbon::today()->subDays($day);

                // Create 8-12 activities per day
                $activitiesPerDay = rand(8, 12);
                $currentTime = $date->copy()->addHours(9); // Start at 9 AM

                for ($i = 0; $i < $activitiesPerDay; $i++) {
                    $activityData = $sampleActivities[array_rand($sampleActivities)];

                    // Categorize the activity
                    $categorization = $categorizationService->categorizeActivity(
                        $activityData['app'],
                        $activityData['title'],
                        $activityData['url']
                    );

                    // Find or create category
                    $category = null;
                    if ($categorization['category_id']) {
                        $category = Category::find($categorization['category_id']);
                    } else {
                        $category = Category::firstOrCreate([
                            'name' => $categorization['category_name'],
                            'is_global' => true,
                        ], [
                            'color' => $categorization['color'],
                            'productivity_score' => $categorization['productivity_score'],
                            'is_productive' => $categorization['is_productive'],
                            'description' => "Auto-generated category for {$categorization['category_name']} activities",
                        ]);
                    }

                    // Random duration between 5 minutes and 2 hours
                    $durationMinutes = rand(5, 120);
                    $durationSeconds = $durationMinutes * 60;

                    $startedAt = $currentTime->copy();
                    $endedAt = $startedAt->copy()->addSeconds($durationSeconds);

                    Activity::create([
                        'user_id' => $user->id,
                        'project_id' => rand(0, 1) ? $project?->id : null,
                        'application_name' => $activityData['app'],
                        'window_title' => $activityData['title'],
                        'url' => $activityData['url'],
                        'category_id' => $category->id,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'duration_seconds' => $durationSeconds,
                        'productivity_score' => $categorization['productivity_score'],
                        'is_manual' => false,
                        'description' => "Sample activity for testing",
                    ]);

                    // Move to next activity with some gap
                    $currentTime = $endedAt->copy()->addMinutes(rand(1, 15));

                    // Don't go past 6 PM
                    if ($currentTime->hour >= 18) {
                        break;
                    }
                }
            }
        }

        $this->command->info('Sample activities created successfully!');
        $this->command->info('Categories created:');

        $categories = Category::withCount('activities')->get();
        foreach ($categories as $category) {
            $this->command->info("- {$category->name}: {$category->activities_count} activities");
        }
    }
}
