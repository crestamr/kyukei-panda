<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SlackController extends Controller
{
    public function __construct(private SlackService $slackService)
    {
    }

    /**
     * Handle Slack events (including panda emoji messages).
     */
    public function events(Request $request): Response
    {
        // Verify Slack signature
        if (!$this->verifySlackSignature($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();

        // Handle URL verification challenge
        if ($payload['type'] === 'url_verification') {
            return response($payload['challenge']);
        }

        // Handle message events
        if ($payload['type'] === 'event_callback' && $payload['event']['type'] === 'message') {
            // Ignore bot messages to prevent loops
            if (isset($payload['event']['bot_id'])) {
                return response('OK');
            }

            $this->slackService->handlePandaMessage($payload['event']);
        }

        return response('OK');
    }

    /**
     * Handle Slack slash commands.
     */
    public function slashCommand(Request $request): Response
    {
        if (!$this->verifySlackSignature($request)) {
            return response('Unauthorized', 401);
        }

        $command = $request->input('command');
        $userId = $request->input('user_id');

        if ($command === '/panda-status') {
            return $this->handlePandaStatusCommand($userId);
        }

        return response()->json([
            'text' => 'Unknown command. Available commands: /panda-status'
        ]);
    }

    /**
     * Handle the /panda-status slash command.
     */
    private function handlePandaStatusCommand(string $slackUserId): Response
    {
        $status = $this->slackService->getDailyPandaStatus($slackUserId);

        if (!$status['success']) {
            return response()->json([
                'text' => $status['message']
            ]);
        }

        return response()->json([
            'text' => 'Your panda break status',
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🐼 Your Panda Break Status'
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Today's Usage:*\n{$status['panda_visualization']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Break Time:*\n{$status['total_minutes']}/60 minutes"
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Remaining:* {$status['remaining_pandas']} pandas ({$status['remaining_minutes']} minutes)"
                    ]
                ]
            ]
        ]);
    }

    /**
     * Verify Slack request signature.
     */
    private function verifySlackSignature(Request $request): bool
    {
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (!$timestamp || !$signature) {
            Log::warning('Missing Slack signature headers');
            return false;
        }

        $body = $request->getContent();

        return $this->slackService->verifySlackSignature($timestamp, $signature, $body);
    }
}
