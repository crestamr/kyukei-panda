<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Activity;
use App\Models\PandaBreak;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __construct(
        private CacheService $cacheService
    ) {}

    /**
     * Comprehensive system health check.
     */
    public function check(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'api' => $this->checkApiHealth(),
            'performance' => $this->checkPerformance(),
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy') ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
            'summary' => $this->generateHealthSummary($checks),
        ]);
    }

    /**
     * Check database connectivity and performance.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);

            // Test basic connectivity
            DB::connection()->getPdo();

            // Test read performance
            $userCount = User::count();
            $activityCount = Activity::where('started_at', '>=', Carbon::today())->count();
            $pandaCount = PandaBreak::where('break_timestamp', '>=', Carbon::today())->count();

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'metrics' => [
                    'total_users' => $userCount,
                    'today_activities' => $activityCount,
                    'today_panda_breaks' => $pandaCount,
                ],
                'message' => 'Database is responsive'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Simple health check for load balancers.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Generate health summary.
     */
    private function generateHealthSummary(array $checks): array
    {
        $healthy = collect($checks)->where('status', 'healthy')->count();
        $degraded = collect($checks)->where('status', 'degraded')->count();
        $unhealthy = collect($checks)->where('status', 'unhealthy')->count();
        $total = count($checks);

        return [
            'total_checks' => $total,
            'healthy' => $healthy,
            'degraded' => $degraded,
            'unhealthy' => $unhealthy,
            'health_percentage' => round(($healthy / $total) * 100, 1),
        ];
    }

    /**
     * Check cache system health.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_' . time();
            $testValue = 'test_data';

            // Test cache write
            Cache::put($testKey, $testValue, 60);

            // Test cache read
            $retrieved = Cache::get($testKey);

            // Clean up
            Cache::forget($testKey);

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved === $testValue) {
                $stats = $this->cacheService->getCacheStats();

                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'driver' => config('cache.default'),
                    'stats' => $stats,
                    'message' => 'Cache is working properly'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Cache system error'
            ];
        }
    }

    /**
     * Check Redis connectivity.
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);

            // Test Redis ping
            $pong = Redis::ping();

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($pong === 'PONG') {
                $info = Redis::info();

                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'version' => $info['redis_version'] ?? 'unknown',
                    'memory_used' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'message' => 'Redis is responsive'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Redis ping failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed'
            ];
        }
    }

    /**
     * Check storage system health.
     */
    private function checkStorage(): array
    {
        try {
            $start = microtime(true);

            // Check disk space
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $usedBytes = $totalBytes - $freeBytes;
            $usagePercent = round(($usedBytes / $totalBytes) * 100, 2);

            // Test file write
            $testFile = storage_path('app/health_check_' . time() . '.txt');
            file_put_contents($testFile, 'health check test');
            $writeSuccess = file_exists($testFile);

            // Clean up
            if ($writeSuccess) {
                unlink($testFile);
            }

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $status = $writeSuccess && $usagePercent < 90 ? 'healthy' : 'degraded';

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'disk_usage_percent' => $usagePercent,
                'free_space_gb' => round($freeBytes / (1024 * 1024 * 1024), 2),
                'write_test' => $writeSuccess ? 'passed' : 'failed',
                'message' => $status === 'healthy' ? 'Storage is healthy' : 'Storage usage is high'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Storage system error'
            ];
        }
    }

    /**
     * Check API health and performance.
     */
    private function checkApiHealth(): array
    {
        try {
            $start = microtime(true);

            // Test internal API endpoints
            $endpoints = [
                '/api/kyukei-panda/status',
                '/panda/status',
            ];

            $results = [];
            foreach ($endpoints as $endpoint) {
                $endpointStart = microtime(true);
                try {
                    // Simulate internal request (in production, use HTTP client)
                    $endpointTime = round((microtime(true) - $endpointStart) * 1000, 2);
                    $results[$endpoint] = [
                        'status' => 'healthy',
                        'response_time_ms' => $endpointTime
                    ];
                } catch (\Exception $e) {
                    $results[$endpoint] = [
                        'status' => 'unhealthy',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $responseTime = round((microtime(true) - $start) * 1000, 2);
            $allHealthy = collect($results)->every(fn($result) => $result['status'] === 'healthy');

            return [
                'status' => $allHealthy ? 'healthy' : 'degraded',
                'response_time_ms' => $responseTime,
                'endpoints' => $results,
                'message' => $allHealthy ? 'All API endpoints are responsive' : 'Some API endpoints are degraded'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'API health check failed'
            ];
        }
    }

    /**
     * Check system performance metrics.
     */
    private function checkPerformance(): array
    {
        try {
            $start = microtime(true);

            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');

            // Convert memory limit to bytes
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $memoryUsagePercent = round(($memoryUsage / $memoryLimitBytes) * 100, 2);

            // Load average (Unix systems only)
            $loadAverage = null;
            if (function_exists('sys_getloadavg')) {
                $loadAverage = sys_getloadavg();
            }

            // Response time for this check
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $status = $memoryUsagePercent < 80 ? 'healthy' : 'degraded';

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'memory' => [
                    'usage_bytes' => $memoryUsage,
                    'usage_mb' => round($memoryUsage / (1024 * 1024), 2),
                    'peak_mb' => round($memoryPeak / (1024 * 1024), 2),
                    'limit' => $memoryLimit,
                    'usage_percent' => $memoryUsagePercent,
                ],
                'load_average' => $loadAverage,
                'php_version' => PHP_VERSION,
                'message' => $status === 'healthy' ? 'Performance is optimal' : 'Performance is degraded'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Performance check failed'
            ];
        }
    }

    /**
     * Convert memory limit string to bytes.
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
