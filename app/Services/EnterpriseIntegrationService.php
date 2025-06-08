<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use App\Models\Activity;
use App\Models\PandaBreak;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnterpriseIntegrationService
{
    /**
     * Sync data with Microsoft Teams.
     */
    public function syncWithMicrosoftTeams(int $teamId, array $config): array
    {
        try {
            $team = Team::find($teamId);
            $accessToken = $config['access_token'];
            $teamsChannelId = $config['channel_id'];

            // Get recent panda breaks to sync
            $recentBreaks = PandaBreak::whereHas('user.teams', function ($query) use ($teamId) {
                $query->where('teams.id', $teamId);
            })
            ->where('break_timestamp', '>=', Carbon::now()->subHours(1))
            ->with('user')
            ->get();

            $syncedBreaks = 0;
            foreach ($recentBreaks as $break) {
                $message = $this->formatTeamsBreakMessage($break);
                
                $response = Http::withToken($accessToken)
                    ->post("https://graph.microsoft.com/v1.0/teams/{$teamsChannelId}/channels/general/messages", [
                        'body' => [
                            'contentType' => 'html',
                            'content' => $message,
                        ],
                    ]);

                if ($response->successful()) {
                    $syncedBreaks++;
                }
            }

            return [
                'success' => true,
                'synced_breaks' => $syncedBreaks,
                'total_breaks' => $recentBreaks->count(),
                'last_sync' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Microsoft Teams sync failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Integrate with Jira for project tracking.
     */
    public function syncWithJira(int $teamId, array $config): array
    {
        try {
            $jiraUrl = $config['jira_url'];
            $username = $config['username'];
            $apiToken = $config['api_token'];
            $projectKey = $config['project_key'];

            // Get team productivity data
            $team = Team::with('users')->find($teamId);
            $productivityData = [];

            foreach ($team->users as $user) {
                $activities = Activity::where('user_id', $user->id)
                    ->where('started_at', '>=', Carbon::now()->subDays(7))
                    ->get();

                $totalTime = $activities->sum('duration_seconds') / 3600; // Convert to hours
                $productivityScore = $activities->avg('productivity_score') * 100;

                $productivityData[] = [
                    'user' => $user->name,
                    'email' => $user->email,
                    'hours_worked' => round($totalTime, 1),
                    'productivity_score' => round($productivityScore, 1),
                ];
            }

            // Create Jira issue with productivity report
            $issueData = [
                'fields' => [
                    'project' => ['key' => $projectKey],
                    'summary' => 'Weekly Productivity Report - ' . now()->format('Y-m-d'),
                    'description' => $this->formatJiraProductivityReport($productivityData),
                    'issuetype' => ['name' => 'Task'],
                    'priority' => ['name' => 'Medium'],
                ],
            ];

            $response = Http::withBasicAuth($username, $apiToken)
                ->post("{$jiraUrl}/rest/api/2/issue", $issueData);

            if ($response->successful()) {
                $issueKey = $response->json()['key'];
                
                return [
                    'success' => true,
                    'jira_issue' => $issueKey,
                    'team_members' => count($productivityData),
                    'report_period' => '7 days',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create Jira issue',
                'response' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Jira sync failed', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export data to Google Sheets.
     */
    public function exportToGoogleSheets(int $userId, array $config): array
    {
        try {
            $spreadsheetId = $config['spreadsheet_id'];
            $accessToken = $config['access_token'];
            $sheetName = $config['sheet_name'] ?? 'Kyukei-Panda Data';

            // Get user's productivity data
            $activities = Activity::where('user_id', $userId)
                ->where('started_at', '>=', Carbon::now()->subDays(30))
                ->with('category')
                ->orderBy('started_at')
                ->get();

            $breaks = PandaBreak::where('user_id', $userId)
                ->where('break_timestamp', '>=', Carbon::now()->subDays(30))
                ->orderBy('break_timestamp')
                ->get();

            // Prepare data for sheets
            $sheetData = [
                ['Date', 'Application', 'Category', 'Duration (hours)', 'Productivity Score', 'Break Count', 'Break Duration (min)']
            ];

            $dailyData = $activities->groupBy(function ($activity) {
                return $activity->started_at->toDateString();
            });

            foreach ($dailyData as $date => $dayActivities) {
                $dayBreaks = $breaks->filter(function ($break) use ($date) {
                    return $break->break_timestamp->toDateString() === $date;
                });

                foreach ($dayActivities as $activity) {
                    $sheetData[] = [
                        $date,
                        $activity->application_name,
                        $activity->category?->name ?? 'Uncategorized',
                        round($activity->duration_seconds / 3600, 2),
                        round($activity->productivity_score, 2),
                        $dayBreaks->sum('panda_count'),
                        $dayBreaks->sum('break_duration'),
                    ];
                }
            }

            // Update Google Sheet
            $response = Http::withToken($accessToken)
                ->put("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$sheetName}!A1", [
                    'values' => $sheetData,
                    'majorDimension' => 'ROWS',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'rows_updated' => count($sheetData),
                    'spreadsheet_url' => "https://docs.google.com/spreadsheets/d/{$spreadsheetId}",
                    'last_export' => now()->toISOString(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update Google Sheet',
                'response' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Google Sheets export failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Integrate with Zapier webhooks.
     */
    public function triggerZapierWebhook(string $webhookUrl, array $data): array
    {
        try {
            $response = Http::post($webhookUrl, [
                'source' => 'kyukei-panda',
                'timestamp' => now()->toISOString(),
                'data' => $data,
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Zapier webhook failed', [
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync with time tracking tools (Toggl, RescueTime, etc.).
     */
    public function syncWithTimeTracker(int $userId, string $provider, array $config): array
    {
        switch ($provider) {
            case 'toggl':
                return $this->syncWithToggl($userId, $config);
            case 'rescuetime':
                return $this->syncWithRescueTime($userId, $config);
            default:
                return ['success' => false, 'error' => 'Unsupported provider'];
        }
    }

    /**
     * Generate webhook payload for external systems.
     */
    public function generateWebhookPayload(string $eventType, array $data): array
    {
        return [
            'event' => $eventType,
            'timestamp' => now()->toISOString(),
            'source' => 'kyukei-panda',
            'version' => '1.0',
            'data' => $data,
            'signature' => $this->generateWebhookSignature($data),
        ];
    }

    /**
     * Validate incoming webhook signatures.
     */
    public function validateWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    // Private helper methods

    private function formatTeamsBreakMessage(PandaBreak $break): string
    {
        $pandas = str_repeat('🐼', $break->panda_count);
        return "<p><strong>{$break->user->name}</strong> took a {$break->break_duration} minute break {$pandas}</p>";
    }

    private function formatJiraProductivityReport(array $productivityData): string
    {
        $report = "h2. Weekly Team Productivity Report\n\n";
        $report .= "Generated by Kyukei-Panda on " . now()->format('Y-m-d H:i:s') . "\n\n";
        $report .= "||Team Member||Hours Worked||Productivity Score||\n";

        foreach ($productivityData as $member) {
            $report .= "|{$member['user']}|{$member['hours_worked']}h|{$member['productivity_score']}%|\n";
        }

        $report .= "\n*Note: Productivity scores are calculated based on AI analysis of work patterns and break compliance.*";

        return $report;
    }

    private function syncWithToggl(int $userId, array $config): array
    {
        try {
            $apiToken = $config['api_token'];
            $workspaceId = $config['workspace_id'];

            // Get recent activities to sync
            $activities = Activity::where('user_id', $userId)
                ->where('started_at', '>=', Carbon::now()->subHours(24))
                ->get();

            $syncedEntries = 0;
            foreach ($activities as $activity) {
                $timeEntry = [
                    'description' => $activity->application_name,
                    'start' => $activity->started_at->toISOString(),
                    'duration' => $activity->duration_seconds,
                    'workspace_id' => $workspaceId,
                    'created_with' => 'kyukei-panda',
                ];

                $response = Http::withBasicAuth($apiToken, 'api_token')
                    ->post('https://api.track.toggl.com/api/v9/time_entries', $timeEntry);

                if ($response->successful()) {
                    $syncedEntries++;
                }
            }

            return [
                'success' => true,
                'synced_entries' => $syncedEntries,
                'total_activities' => $activities->count(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function syncWithRescueTime(int $userId, array $config): array
    {
        try {
            $apiKey = $config['api_key'];

            // Get productivity data to compare
            $activities = Activity::where('user_id', $userId)
                ->where('started_at', '>=', Carbon::now()->subDays(7))
                ->get();

            $response = Http::get('https://www.rescuetime.com/anapi/data', [
                'key' => $apiKey,
                'perspective' => 'interval',
                'resolution_time' => 'day',
                'restrict_begin' => Carbon::now()->subDays(7)->toDateString(),
                'restrict_end' => Carbon::now()->toDateString(),
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $rescueTimeData = $response->json();
                
                return [
                    'success' => true,
                    'kyukei_panda_hours' => round($activities->sum('duration_seconds') / 3600, 1),
                    'rescue_time_hours' => $this->calculateRescueTimeHours($rescueTimeData),
                    'data_comparison' => 'available',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch RescueTime data',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function calculateRescueTimeHours(array $data): float
    {
        $totalSeconds = 0;
        foreach ($data['rows'] ?? [] as $row) {
            $totalSeconds += $row[1] ?? 0; // Time spent in seconds
        }
        return round($totalSeconds / 3600, 1);
    }

    private function generateWebhookSignature(array $data): string
    {
        $payload = json_encode($data);
        $secret = config('app.webhook_secret', 'default-secret');
        return hash_hmac('sha256', $payload, $secret);
    }
}
