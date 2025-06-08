<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\Client;
use App\Models\PandaBreak;
use App\Models\Activity;
use App\Models\DailyPandaLimit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $user = User::firstOrCreate(
            ['email' => 'demo@kyukei-panda.com'],
            [
                'name' => 'Kyukei-Panda Demo User',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'timezone' => 'UTC',
            ]
        );

        // Create demo team
        $team = Team::firstOrCreate(
            ['name' => 'Demo Team'],
            [
                'name' => 'Demo Team',
                'slug' => 'demo-team',
                'description' => 'A demo team for Kyukei-Panda showcase',
            ]
        );

        // Add user to team
        if (!$team->users()->where('user_id', $user->id)->exists()) {
            $team->users()->attach($user->id, ['role' => 'admin']);
        }

        // Create demo client
        $client = Client::firstOrCreate(
            ['name' => 'Demo Client'],
            [
                'name' => 'Demo Client',
                'email' => 'client@demo.com',
                'team_id' => $team->id,
            ]
        );

        // Create demo project
        $project = Project::firstOrCreate(
            ['name' => 'Kyukei-Panda Demo Project'],
            [
                'name' => 'Kyukei-Panda Demo Project',
                'description' => 'A demo project showcasing productivity tracking',
                'team_id' => $team->id,
                'client_id' => $client->id,
                'hourly_rate' => 75.00,
                'is_active' => true,
            ]
        );

        // Create some demo activities for today
        $today = Carbon::today();
        $activities = [
            [
                'application_name' => 'Visual Studio Code',
                'window_title' => 'Kyukei-Panda - main.php',
                'started_at' => $today->copy()->addHours(9),
                'ended_at' => $today->copy()->addHours(10)->addMinutes(30),
                'duration_seconds' => 5400, // 1.5 hours
                'productivity_score' => 0.95,
            ],
            [
                'application_name' => 'Google Chrome',
                'window_title' => 'Laravel Documentation',
                'url' => 'https://laravel.com/docs',
                'started_at' => $today->copy()->addHours(11),
                'ended_at' => $today->copy()->addHours(11)->addMinutes(45),
                'duration_seconds' => 2700, // 45 minutes
                'productivity_score' => 0.85,
            ],
            [
                'application_name' => 'PhpStorm',
                'window_title' => 'PandaDashboardController.php',
                'started_at' => $today->copy()->addHours(14),
                'ended_at' => $today->copy()->addHours(16),
                'duration_seconds' => 7200, // 2 hours
                'productivity_score' => 0.92,
            ],
        ];

        foreach ($activities as $activityData) {
            Activity::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'application_name' => $activityData['application_name'],
                    'started_at' => $activityData['started_at'],
                ],
                array_merge($activityData, [
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                ])
            );
        }

        // Create some demo panda breaks
        $breaks = [
            [
                'break_timestamp' => $today->copy()->addHours(10)->addMinutes(30),
                'break_duration' => 15,
                'panda_count' => 1,
                'channel_name' => 'general',
                'slack_user_id' => 'U123456789',
                'slack_channel_id' => 'C123456789',
                'slack_message_ts' => '1640995800.001',
                'message_text' => '🐼 Taking a 15-minute break!',
            ],
            [
                'break_timestamp' => $today->copy()->addHours(13),
                'break_duration' => 30,
                'panda_count' => 2,
                'channel_name' => 'general',
                'slack_user_id' => 'U123456789',
                'slack_channel_id' => 'C123456789',
                'slack_message_ts' => '1641006600.002',
                'message_text' => '🐼🐼 Lunch break time!',
            ],
            [
                'break_timestamp' => $today->copy()->addHours(16),
                'break_duration' => 10,
                'panda_count' => 1,
                'channel_name' => 'general',
                'slack_user_id' => 'U123456789',
                'slack_channel_id' => 'C123456789',
                'slack_message_ts' => '1641017400.003',
                'message_text' => '🐼 Quick afternoon break!',
            ],
        ];

        foreach ($breaks as $breakData) {
            PandaBreak::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'slack_message_ts' => $breakData['slack_message_ts'],
                ],
                array_merge($breakData, [
                    'user_id' => $user->id,
                ])
            );
        }

        // Create daily panda limit record
        DailyPandaLimit::firstOrCreate(
            [
                'user_id' => $user->id,
                'date' => $today,
            ],
            [
                'user_id' => $user->id,
                'date' => $today,
                'pandas_used' => 4,
                'total_break_minutes' => 55,
                'last_break_at' => $today->copy()->addHours(16),
            ]
        );

        $this->command->info('Demo user and data created successfully!');
        $this->command->info('Email: demo@kyukei-panda.com');
        $this->command->info('Password: password');
    }
}
