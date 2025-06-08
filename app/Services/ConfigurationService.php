<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ConfigurationService
{
    /**
     * Validate and fix application configuration.
     */
    public function validateAndFixConfiguration(): array
    {
        $issues = [];
        $fixes = [];

        // Check Pusher configuration
        $pusherIssues = $this->validatePusherConfiguration();
        if (!empty($pusherIssues)) {
            $issues['pusher'] = $pusherIssues;
            $fixes['pusher'] = $this->fixPusherConfiguration();
        }

        // Check database configuration
        $dbIssues = $this->validateDatabaseConfiguration();
        if (!empty($dbIssues)) {
            $issues['database'] = $dbIssues;
            $fixes['database'] = $this->fixDatabaseConfiguration();
        }

        // Check cache configuration
        $cacheIssues = $this->validateCacheConfiguration();
        if (!empty($cacheIssues)) {
            $issues['cache'] = $cacheIssues;
            $fixes['cache'] = $this->fixCacheConfiguration();
        }

        // Check queue configuration
        $queueIssues = $this->validateQueueConfiguration();
        if (!empty($queueIssues)) {
            $issues['queue'] = $queueIssues;
            $fixes['queue'] = $this->fixQueueConfiguration();
        }

        return [
            'issues_found' => !empty($issues),
            'issues' => $issues,
            'fixes_applied' => $fixes,
            'configuration_status' => empty($issues) ? 'healthy' : 'fixed',
        ];
    }

    /**
     * Validate Pusher configuration.
     */
    private function validatePusherConfiguration(): array
    {
        $issues = [];

        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherSecret = config('broadcasting.connections.pusher.secret');
        $pusherAppId = config('broadcasting.connections.pusher.app_id');

        if (empty($pusherKey) || $pusherKey === 'your-pusher-key') {
            $issues[] = 'Pusher app key is missing or using default value';
        }

        if (empty($pusherSecret) || $pusherSecret === 'your-pusher-secret') {
            $issues[] = 'Pusher app secret is missing or using default value';
        }

        if (empty($pusherAppId) || $pusherAppId === 'your-pusher-app-id') {
            $issues[] = 'Pusher app ID is missing or using default value';
        }

        return $issues;
    }

    /**
     * Fix Pusher configuration by setting safe defaults.
     */
    private function fixPusherConfiguration(): array
    {
        $fixes = [];

        // Set safe default values for development
        if (app()->environment('local', 'testing')) {
            Config::set('broadcasting.connections.pusher.key', 'kyukei-panda-key');
            Config::set('broadcasting.connections.pusher.secret', 'kyukei-panda-secret');
            Config::set('broadcasting.connections.pusher.app_id', 'kyukei-panda-app');
            
            $fixes[] = 'Set development Pusher credentials';
        }

        // Disable broadcasting if no valid Pusher config
        if (config('broadcasting.default') === 'pusher' && $this->hasPusherIssues()) {
            Config::set('broadcasting.default', 'null');
            $fixes[] = 'Disabled broadcasting due to invalid Pusher configuration';
        }

        // Enable alternative WebSocket service
        Config::set('broadcasting.connections.kyukei-websocket.key', 'kyukei-panda-local');
        Config::set('broadcasting.connections.kyukei-websocket.secret', 'kyukei-panda-secret');
        $fixes[] = 'Enabled alternative WebSocket service';

        return $fixes;
    }

    /**
     * Validate database configuration.
     */
    private function validateDatabaseConfiguration(): array
    {
        $issues = [];

        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $issues[] = 'Database connection failed: ' . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Fix database configuration.
     */
    private function fixDatabaseConfiguration(): array
    {
        $fixes = [];

        // If PostgreSQL fails, try SQLite for development
        if (app()->environment('local', 'testing')) {
            try {
                \DB::connection()->getPdo();
            } catch (\Exception $e) {
                Config::set('database.default', 'sqlite');
                Config::set('database.connections.sqlite.database', database_path('kyukei_panda.sqlite'));
                $fixes[] = 'Switched to SQLite for development';
            }
        }

        return $fixes;
    }

    /**
     * Validate cache configuration.
     */
    private function validateCacheConfiguration(): array
    {
        $issues = [];

        try {
            \Cache::put('config_test', 'test', 1);
            if (\Cache::get('config_test') !== 'test') {
                $issues[] = 'Cache write/read test failed';
            }
        } catch (\Exception $e) {
            $issues[] = 'Cache connection failed: ' . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Fix cache configuration.
     */
    private function fixCacheConfiguration(): array
    {
        $fixes = [];

        // Fallback to file cache if Redis fails
        if (config('cache.default') === 'redis') {
            try {
                \Cache::put('config_test', 'test', 1);
            } catch (\Exception $e) {
                Config::set('cache.default', 'file');
                $fixes[] = 'Switched to file cache due to Redis connection issues';
            }
        }

        return $fixes;
    }

    /**
     * Validate queue configuration.
     */
    private function validateQueueConfiguration(): array
    {
        $issues = [];

        if (config('queue.default') === 'redis') {
            try {
                \Queue::size();
            } catch (\Exception $e) {
                $issues[] = 'Queue connection failed: ' . $e->getMessage();
            }
        }

        return $issues;
    }

    /**
     * Fix queue configuration.
     */
    private function fixQueueConfiguration(): array
    {
        $fixes = [];

        // Fallback to database queue if Redis fails
        if (config('queue.default') === 'redis') {
            try {
                \Queue::size();
            } catch (\Exception $e) {
                Config::set('queue.default', 'database');
                $fixes[] = 'Switched to database queue due to Redis connection issues';
            }
        }

        return $fixes;
    }

    /**
     * Check if Pusher has configuration issues.
     */
    private function hasPusherIssues(): bool
    {
        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherSecret = config('broadcasting.connections.pusher.secret');
        $pusherAppId = config('broadcasting.connections.pusher.app_id');

        return empty($pusherKey) || 
               empty($pusherSecret) || 
               empty($pusherAppId) ||
               $pusherKey === 'kyukei-panda-key' ||
               $pusherSecret === 'kyukei-panda-secret' ||
               $pusherAppId === 'kyukei-panda-app';
    }

    /**
     * Get application configuration status.
     */
    public function getConfigurationStatus(): array
    {
        return [
            'app_env' => app()->environment(),
            'app_debug' => config('app.debug'),
            'database' => [
                'connection' => config('database.default'),
                'status' => $this->getDatabaseStatus(),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $this->getCacheStatus(),
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'status' => $this->getQueueStatus(),
            ],
            'broadcasting' => [
                'driver' => config('broadcasting.default'),
                'status' => $this->getBroadcastingStatus(),
            ],
            'services' => [
                'pusher' => $this->getPusherStatus(),
                'redis' => $this->getRedisStatus(),
                'websocket' => $this->getWebSocketStatus(),
            ],
        ];
    }

    private function getDatabaseStatus(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    private function getCacheStatus(): string
    {
        try {
            \Cache::put('status_test', 'test', 1);
            return \Cache::get('status_test') === 'test' ? 'working' : 'failed';
        } catch (\Exception $e) {
            return 'failed';
        }
    }

    private function getQueueStatus(): string
    {
        try {
            \Queue::size();
            return 'working';
        } catch (\Exception $e) {
            return 'failed';
        }
    }

    private function getBroadcastingStatus(): string
    {
        $driver = config('broadcasting.default');
        
        if ($driver === 'null') {
            return 'disabled';
        }
        
        if ($driver === 'pusher' && $this->hasPusherIssues()) {
            return 'misconfigured';
        }
        
        return 'configured';
    }

    private function getPusherStatus(): string
    {
        if ($this->hasPusherIssues()) {
            return 'not_configured';
        }
        
        return 'configured';
    }

    private function getRedisStatus(): string
    {
        try {
            \Redis::ping();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    private function getWebSocketStatus(): string
    {
        // Check if our WebSocket service is available
        try {
            $webSocketService = app(WebSocketService::class);
            $health = $webSocketService->healthCheck();
            return $health['websocket_service'] === 'operational' ? 'operational' : 'failed';
        } catch (\Exception $e) {
            return 'unavailable';
        }
    }

    /**
     * Generate configuration recommendations.
     */
    public function getConfigurationRecommendations(): array
    {
        $recommendations = [];

        // Environment-specific recommendations
        if (app()->environment('production')) {
            if (config('app.debug')) {
                $recommendations[] = [
                    'type' => 'security',
                    'message' => 'Disable debug mode in production',
                    'action' => 'Set APP_DEBUG=false',
                ];
            }

            if (config('cache.default') === 'file') {
                $recommendations[] = [
                    'type' => 'performance',
                    'message' => 'Use Redis for better cache performance in production',
                    'action' => 'Set CACHE_DRIVER=redis',
                ];
            }
        }

        // Real-time features recommendation
        if (config('broadcasting.default') === 'null') {
            $recommendations[] = [
                'type' => 'feature',
                'message' => 'Enable real-time features for better user experience',
                'action' => 'Configure Pusher or use Kyukei WebSocket service',
            ];
        }

        return $recommendations;
    }
}
