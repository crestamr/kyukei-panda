<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NativeAppService;
use Illuminate\Http\JsonResponse;

class NativeAppController extends Controller
{
    public function __construct(
        private NativeAppService $nativeAppService
    ) {}

    /**
     * Get native app status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => $this->nativeAppService->getStatus(),
            'platform' => $this->nativeAppService->getPlatformInfo(),
            'configuration' => $this->nativeAppService->getConfiguration(),
            'is_running_in_native' => $this->nativeAppService->isRunningInNative(),
        ]);
    }

    /**
     * Send test notification.
     */
    public function testNotification(): JsonResponse
    {
        $success = $this->nativeAppService->sendNotification(
            'Kyukei-Panda Test',
            'Native app is working! 🐼'
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification sent successfully' : 'Failed to send notification',
        ]);
    }

    /**
     * Send panda break notification.
     */
    public function pandaBreakNotification(): JsonResponse
    {
        $success = $this->nativeAppService->showPandaBreakNotification(3);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Panda break notification sent!' : 'Failed to send notification',
        ]);
    }
}
