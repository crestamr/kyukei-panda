<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\PandaBreakRecorded;
use App\Models\User;
use App\Models\PandaBreak;
use App\Models\DailyPandaLimit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    private string $botToken;
    private string $signingSecret;

    public function __construct()
    {
        $this->botToken = config('services.slack.bot_token', '');
        $this->signingSecret = config('services.slack.signing_secret', '');
    }

    /**
     * Handle panda emoji messages from Slack.
     */
    public function handlePandaMessage(array $event): void
    {
        if (!isset($event['text']) || !str_contains($event['text'], '🐼')) {
            return;
        }

        $pandaCount = substr_count($event['text'], '🐼');
        $user = User::where('slack_user_id', $event['user'])->first();

        if (!$user) {
            $this->sendSlackMessage($event['channel'],
                "Please link your Slack account to Kyukei-Panda first! 🔗");
            return;
        }

        $result = $this->processPandaBreak([
            'user_id' => $user->id,
            'slack_user_id' => $event['user'],
            'slack_channel_id' => $event['channel'],
            'slack_message_ts' => $event['ts'],
            'panda_count' => $pandaCount,
            'break_timestamp' => Carbon::createFromTimestamp((float) $event['ts']),
            'message_text' => $event['text'] ?? '',
        ]);

        if ($result['success']) {
            $this->sendSlackMessage($event['channel'], [
                'text' => "🐼 Break time recorded!",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Break Recorded!* 🐼\n" .
                                     "Duration: {$result['duration']} minutes\n" .
                                     "Daily usage: {$result['daily_usage']}/6 pandas\n" .
                                     "Remaining: {$result['remaining_minutes']} minutes"
                        ]
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => '💡 *Tip:* Regular breaks improve productivity!'
                            ]
                        ]
                    ]
                ]
            ]);
        } else {
            $this->sendSlackMessage($event['channel'], "🚫 {$result['message']}");
        }
    }

    /**
     * Process a panda break and update daily limits.
     */
    public function processPandaBreak(array $data): array
    {
        $today = Carbon::today();
        $dailyLimit = DailyPandaLimit::firstOrCreate([
            'user_id' => $data['user_id'],
            'date' => $today
        ]);

        $newTotal = $dailyLimit->pandas_used + $data['panda_count'];

        if ($newTotal > 6) {
            return [
                'success' => false,
                'message' => 'Daily panda limit exceeded! You can only use 6 pandas per day.'
            ];
        }

        // Record the panda break
        $pandaBreak = PandaBreak::create([
            'user_id' => $data['user_id'],
            'slack_user_id' => $data['slack_user_id'],
            'slack_channel_id' => $data['slack_channel_id'],
            'slack_message_ts' => $data['slack_message_ts'],
            'panda_count' => $data['panda_count'],
            'break_duration' => $data['panda_count'] * 10,
            'break_timestamp' => $data['break_timestamp'],
            'message_text' => $data['message_text'] ?? '',
        ]);

        // Update daily limit
        $dailyLimit->update([
            'pandas_used' => $newTotal,
            'total_break_minutes' => $dailyLimit->total_break_minutes + ($data['panda_count'] * 10),
            'first_break_at' => $dailyLimit->first_break_at ?? $data['break_timestamp'],
            'last_break_at' => $data['break_timestamp'],
        ]);

        // Get user and broadcast real-time event
        $user = User::find($data['user_id']);
        $dailyStatus = [
            'pandas_used' => $newTotal,
            'total_break_minutes' => $dailyLimit->total_break_minutes + ($data['panda_count'] * 10),
            'remaining_pandas' => 6 - $newTotal,
            'remaining_minutes' => (6 - $newTotal) * 10,
        ];

        // Broadcast the event for real-time updates
        broadcast(new PandaBreakRecorded($pandaBreak, $user, $dailyStatus))->toOthers();

        return [
            'success' => true,
            'duration' => $data['panda_count'] * 10,
            'daily_usage' => $newTotal,
            'remaining_minutes' => (6 - $newTotal) * 10
        ];
    }

    /**
     * Send a message to Slack.
     */
    private function sendSlackMessage(string $channel, $message): void
    {
        if (empty($this->botToken)) {
            Log::warning('Slack bot token not configured');
            return;
        }

        $payload = is_array($message) ? $message : ['text' => $message];
        $payload['channel'] = $channel;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->botToken}",
                'Content-Type' => 'application/json'
            ])->post('https://slack.com/api/chat.postMessage', $payload);

            if (!$response->successful()) {
                Log::error('Failed to send Slack message', [
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception sending Slack message', [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);
        }
    }

    /**
     * Verify Slack request signature.
     */
    public function verifySlackSignature(string $timestamp, string $signature, string $body): bool
    {
        if (empty($this->signingSecret)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 60 * 5) {
            return false; // Request too old
        }

        $expectedSignature = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$body}", $this->signingSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get daily panda status for a user.
     */
    public function getDailyPandaStatus(string $slackUserId): array
    {
        $user = User::where('slack_user_id', $slackUserId)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Please link your Slack account to Kyukei-Panda first!'
            ];
        }

        $dailyUsage = DailyPandaLimit::where('user_id', $user->id)
            ->where('date', Carbon::today())
            ->first();

        $pandasUsed = $dailyUsage?->pandas_used ?? 0;
        $pandaEmojis = str_repeat('🐼', $pandasUsed) . str_repeat('⚪', 6 - $pandasUsed);

        return [
            'success' => true,
            'user' => $user,
            'pandas_used' => $pandasUsed,
            'total_minutes' => $dailyUsage?->total_break_minutes ?? 0,
            'panda_visualization' => $pandaEmojis,
            'remaining_pandas' => 6 - $pandasUsed,
            'remaining_minutes' => (6 - $pandasUsed) * 10,
        ];
    }
}
