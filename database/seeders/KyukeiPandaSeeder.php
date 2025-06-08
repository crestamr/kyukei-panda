<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use App\Models\Client;
use App\Models\Project;
use App\Models\Category;
use App\Models\PandaBreak;
use App\Models\DailyPandaLimit;
use App\Models\SlackIntegration;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class KyukeiPandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo team
        $team = Team::create([
            'name' => 'Development Team',
            'slug' => 'dev-team',
            'description' => 'Main development team for Kyukei-Panda',
            'is_active' => true,
        ]);

        // Create demo users
        $users = [
            [
                'name' => 'Tanaka Hiroshi',
                'email' => 'tanaka@kyukei-panda.com',
                'password' => bcrypt('password'),
                'slack_user_id' => 'U123456789',
                'slack_username' => 'tanaka.hiroshi',
                'timezone' => 'Asia/Tokyo',
            ],
            [
                'name' => 'Sato Yuki',
                'email' => 'sato@kyukei-panda.com',
                'password' => bcrypt('password'),
                'slack_user_id' => 'U987654321',
                'slack_username' => 'sato.yuki',
                'timezone' => 'Asia/Tokyo',
            ],
            [
                'name' => 'Yamada Kenji',
                'email' => 'yamada@kyukei-panda.com',
                'password' => bcrypt('password'),
                'slack_user_id' => 'U456789123',
                'slack_username' => 'yamada.kenji',
                'timezone' => 'Asia/Tokyo',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create($userData);

            // Attach user to team
            $team->users()->attach($user->id, [
                'role' => $user->email === 'tanaka@kyukei-panda.com' ? 'admin' : 'member',
                'joined_at' => now(),
            ]);
        }

        // Create demo client
        $client = Client::create([
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.com',
            'phone' => '+81-3-1234-5678',
            'team_id' => $team->id,
            'is_active' => true,
        ]);

        // Create demo project
        $project = Project::create([
            'name' => 'Kyukei-Panda Enhancement',
            'description' => 'Adding enterprise features to Kyukei-Panda',
            'color' => '#3B82F6',
            'team_id' => $team->id,
            'client_id' => $client->id,
            'hourly_rate' => 8000.00, // 8000 JPY per hour
            'start_date' => Carbon::now()->subDays(30),
            'is_active' => true,
        ]);

        // Create demo categories
        $categories = [
            [
                'name' => 'Development',
                'color' => '#10B981',
                'productivity_score' => 1.00,
                'is_productive' => true,
                'keywords' => ['code', 'programming', 'development', 'IDE'],
                'team_id' => $team->id,
            ],
            [
                'name' => 'Meetings',
                'color' => '#F59E0B',
                'productivity_score' => 0.70,
                'is_productive' => true,
                'keywords' => ['meeting', 'zoom', 'teams', 'slack'],
                'team_id' => $team->id,
            ],
            [
                'name' => 'Break Time',
                'color' => '#EF4444',
                'productivity_score' => 0.50,
                'is_productive' => false,
                'keywords' => ['break', 'lunch', 'coffee'],
                'team_id' => $team->id,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }

        // Create Slack integration
        SlackIntegration::create([
            'team_id' => $team->id,
            'slack_team_id' => 'T123456789',
            'slack_channel_id' => 'C123456789',
            'channel_name' => 'general',
            'is_panda_enabled' => true,
            'is_active' => true,
        ]);

        // Create sample panda breaks for today
        $today = Carbon::today();
        $users = User::all();
        $messageCounter = 0;

        foreach ($users as $userIndex => $user) {
            // Create some panda breaks for today
            $breakCount = rand(1, 4);
            $totalPandas = 0;
            $totalMinutes = 0;

            for ($i = 0; $i < $breakCount; $i++) {
                $pandas = rand(1, 2);
                $totalPandas += $pandas;
                $totalMinutes += $pandas * 10;

                if ($totalPandas <= 6) { // Don't exceed daily limit
                    $messageCounter++;
                    PandaBreak::create([
                        'user_id' => $user->id,
                        'slack_user_id' => $user->slack_user_id,
                        'slack_channel_id' => 'C123456789',
                        'slack_message_ts' => (string) (time() + $messageCounter * 100), // Unique timestamp
                        'channel_name' => 'general',
                        'panda_count' => $pandas,
                        'break_duration' => $pandas * 10,
                        'break_timestamp' => $today->copy()->addHours(9 + $i * 2),
                        'message_text' => str_repeat('🐼', $pandas) . ' Taking a break!',
                    ]);
                }
            }

            // Create daily panda limit record
            DailyPandaLimit::create([
                'user_id' => $user->id,
                'date' => $today,
                'pandas_used' => min($totalPandas, 6),
                'total_break_minutes' => min($totalMinutes, 60),
                'first_break_at' => $today->copy()->addHours(9),
                'last_break_at' => $today->copy()->addHours(9 + ($breakCount - 1) * 2),
            ]);
        }

        $this->command->info('Kyukei-Panda demo data created successfully!');
        $this->command->info('Demo users:');
        $this->command->info('- tanaka@kyukei-panda.com (Admin)');
        $this->command->info('- sato@kyukei-panda.com (Member)');
        $this->command->info('- yamada@kyukei-panda.com (Member)');
        $this->command->info('Password for all users: password');
    }
}
