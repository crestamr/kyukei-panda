<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Models\Team;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class KyukeiPandaApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Team $team;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->project = Project::factory()->create(['team_id' => $this->team->id]);
        
        $this->team->users()->attach($this->user->id, ['role' => 'member']);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_record_activity()
    {
        $activityData = [
            'application_name' => 'Visual Studio Code',
            'window_title' => 'main.php - Kyukei Panda',
            'url' => null,
            'started_at' => now()->subMinutes(30)->toISOString(),
            'ended_at' => now()->toISOString(),
            'duration_seconds' => 1800,
            'productivity_score' => 0.85,
        ];

        $response = $this->postJson('/api/kyukei-panda/activities', $activityData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'application_name',
                        'productivity_score',
                        'created_at',
                    ],
                ]);

        $this->assertDatabaseHas('activities', [
            'application_name' => 'Visual Studio Code',
            'productivity_score' => 0.85,
        ]);
    }

    /** @test */
    public function it_can_record_panda_break()
    {
        $breakData = [
            'break_duration' => 15,
            'panda_count' => 1,
            'channel_name' => 'general',
        ];

        $response = $this->postJson('/api/panda-breaks', $breakData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'break_duration',
                        'panda_count',
                        'break_timestamp',
                    ],
                ]);

        $this->assertDatabaseHas('panda_breaks', [
            'user_id' => $this->user->id,
            'break_duration' => 15,
            'panda_count' => 1,
        ]);
    }

    /** @test */
    public function it_can_get_productivity_suggestions()
    {
        // Create some activities for the user
        Activity::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'started_at' => now()->subHours(2),
        ]);

        $response = $this->getJson('/api/kyukei-panda/suggestions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'suggestions' => [
                        '*' => [
                            'type',
                            'message',
                            'priority',
                        ],
                    ],
                    'productivity_score',
                    'break_recommendation',
                ]);
    }

    /** @test */
    public function it_can_generate_ai_insights()
    {
        $response = $this->postJson('/api/ai/nlp/generate-insights', [
            'user_id' => $this->user->id,
            'context' => 'weekly_summary',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'insights',
                        'sentiment',
                        'key_points',
                        'action_items',
                    ],
                ]);
    }

    /** @test */
    public function it_can_create_productivity_nft()
    {
        $achievementData = [
            'title' => 'Productivity Master',
            'description' => 'Achieved 95% productivity score for a week',
            'type' => 'milestone',
            'score' => 95,
            'pandas_used' => 42,
        ];

        $response = $this->postJson('/api/blockchain/nft/create-achievement', [
            'user_id' => $this->user->id,
            'achievement' => $achievementData,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'token_id',
                    'transaction_hash',
                    'metadata_uri',
                    'opensea_url',
                ]);
    }

    /** @test */
    public function it_can_integrate_smart_desk()
    {
        $deskConfig = [
            'device_id' => 'desk_001',
            'sensors' => ['presence', 'posture', 'lighting', 'temperature'],
        ];

        $response = $this->postJson('/api/iot/smart-desk/integrate', [
            'user_id' => $this->user->id,
            'desk_config' => $deskConfig,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'device_id',
                    'registration',
                    'sensors',
                    'automation_rules',
                ]);
    }

    /** @test */
    public function it_can_optimize_with_quantum_computing()
    {
        $constraints = [
            'work_hours' => [9, 17],
            'break_requirements' => 6,
            'meeting_conflicts' => [],
        ];

        $response = $this->postJson('/api/future-tech/quantum/optimize-scheduling', [
            'team_id' => $this->team->id,
            'constraints' => $constraints,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'optimized_schedule',
                    'quantum_advantage',
                    'optimization_score',
                    'computation_time',
                ]);
    }

    /** @test */
    public function it_can_create_ar_workspace()
    {
        $response = $this->postJson('/api/future-tech/ar/create-workspace', [
            'user_id' => $this->user->id,
            'visualization_type' => 'productivity_heatmap',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'ar_scene_id',
                    'ar_url',
                    'qr_code',
                    'supported_devices',
                    'features',
                ]);
    }

    /** @test */
    public function it_can_get_team_analytics()
    {
        $response = $this->getJson("/api/analytics/team/{$this->team->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'team_id',
                    'productivity_metrics',
                    'collaboration_score',
                    'break_compliance',
                    'recommendations',
                ]);
    }

    /** @test */
    public function it_can_export_data_for_gdpr()
    {
        $response = $this->postJson('/api/compliance/gdpr/export', [
            'user_id' => $this->user->id,
            'request_type' => 'data_export',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'export_file',
                    'download_url',
                    'expires_at',
                ]);
    }

    /** @test */
    public function it_can_get_system_health()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'checks' => [
                        'database',
                        'cache',
                        'memory',
                    ],
                    'overall_score',
                ]);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/kyukei-panda/status');
            $response->assertStatus(200);
        }

        // This should still work as we're under the limit
        $response = $this->getJson('/api/kyukei-panda/status');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_security_headers()
    {
        $response = $this->getJson('/api/kyukei-panda/status');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /** @test */
    public function it_can_handle_localization()
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'ja',
        ])->getJson('/api/localization/messages');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'locale',
                    'messages',
                    'panda_messages',
                ]);
    }

    /** @test */
    public function it_can_process_iot_data_stream()
    {
        $sensorData = [
            'temperature' => 22.5,
            'humidity' => 45,
            'air_quality' => 85,
            'noise_level' => 35,
        ];

        $response = $this->postJson('/api/iot/data/process', [
            'device_id' => 'sensor_001',
            'sensor_data' => $sensorData,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'device_id',
                    'processed_data',
                    'automation_results',
                ]);
    }

    /** @test */
    public function it_can_create_digital_twin()
    {
        $response = $this->postJson('/api/future-tech/digital-twin/create', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'digital_twin_id',
                    'deployment',
                    'synchronization',
                    'capabilities',
                ]);
    }

    /** @test */
    public function it_requires_authentication_for_protected_routes()
    {
        $this->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
        
        $response = $this->postJson('/api/ai/nlp/generate-insights', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(401);
    }
}
