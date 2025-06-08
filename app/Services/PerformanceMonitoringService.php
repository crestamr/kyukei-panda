<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PerformanceMonitoringService
{
    /**
     * Monitor system performance metrics.
     */
    public function collectSystemMetrics(): array
    {
        $metrics = [
            'timestamp' => now()->toISOString(),
            'application' => $this->getApplicationMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'response_times' => $this->getResponseTimeMetrics(),
        ];

        // Store metrics for analysis
        $this->storeMetrics($metrics);

        return $metrics;
    }

    /**
     * Get application performance metrics.
     */
    private function getApplicationMetrics(): array
    {
        return [
            'uptime' => $this->getUptime(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'error_rate' => $this->getErrorRate(),
            'active_users' => $this->getActiveUsers(),
            'concurrent_sessions' => $this->getConcurrentSessions(),
        ];
    }

    /**
     * Get database performance metrics.
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $connectionTime = (microtime(true) - $start) * 1000;

            return [
                'connection_time' => round($connectionTime, 2),
                'active_connections' => $this->getDatabaseConnections(),
                'slow_queries' => $this->getSlowQueries(),
                'query_cache_hit_rate' => $this->getQueryCacheHitRate(),
                'database_size' => $this->getDatabaseSize(),
            ];
        } catch (\Exception $e) {
            Log::error('Database metrics collection failed', ['error' => $e->getMessage()]);
            return ['error' => 'Database metrics unavailable'];
        }
    }

    /**
     * Get cache performance metrics.
     */
    private function getCacheMetrics(): array
    {
        try {
            $start = microtime(true);
            Cache::get('performance_test_key', 'default');
            $cacheTime = (microtime(true) - $start) * 1000;

            return [
                'response_time' => round($cacheTime, 2),
                'hit_rate' => $this->getCacheHitRate(),
                'memory_usage' => $this->getCacheMemoryUsage(),
                'keys_count' => $this->getCacheKeysCount(),
                'evictions' => $this->getCacheEvictions(),
            ];
        } catch (\Exception $e) {
            Log::error('Cache metrics collection failed', ['error' => $e->getMessage()]);
            return ['error' => 'Cache metrics unavailable'];
        }
    }

    /**
     * Get queue performance metrics.
     */
    private function getQueueMetrics(): array
    {
        try {
            return [
                'pending_jobs' => $this->getPendingJobs(),
                'failed_jobs' => $this->getFailedJobs(),
                'processed_jobs_per_minute' => $this->getProcessedJobsPerMinute(),
                'average_job_time' => $this->getAverageJobTime(),
                'queue_workers' => $this->getActiveWorkers(),
            ];
        } catch (\Exception $e) {
            Log::error('Queue metrics collection failed', ['error' => $e->getMessage()]);
            return ['error' => 'Queue metrics unavailable'];
        }
    }

    /**
     * Get memory usage metrics.
     */
    private function getMemoryMetrics(): array
    {
        return [
            'current_usage' => round(memory_get_usage(true) / 1024 / 1024, 2), // MB
            'peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2), // MB
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => $this->getMemoryUsagePercentage(),
        ];
    }

    /**
     * Get response time metrics.
     */
    private function getResponseTimeMetrics(): array
    {
        $cacheKey = 'response_times_' . now()->format('Y-m-d-H-i');
        $responseTimes = Cache::get($cacheKey, []);

        if (empty($responseTimes)) {
            return [
                'average' => 0,
                'median' => 0,
                'p95' => 0,
                'p99' => 0,
                'samples' => 0,
            ];
        }

        sort($responseTimes);
        $count = count($responseTimes);

        return [
            'average' => round(array_sum($responseTimes) / $count, 2),
            'median' => $responseTimes[intval($count / 2)],
            'p95' => $responseTimes[intval($count * 0.95)],
            'p99' => $responseTimes[intval($count * 0.99)],
            'samples' => $count,
        ];
    }

    /**
     * Record response time for monitoring.
     */
    public function recordResponseTime(float $responseTime): void
    {
        $cacheKey = 'response_times_' . now()->format('Y-m-d-H-i');
        $responseTimes = Cache::get($cacheKey, []);
        $responseTimes[] = $responseTime;

        // Keep only last 1000 samples
        if (count($responseTimes) > 1000) {
            $responseTimes = array_slice($responseTimes, -1000);
        }

        Cache::put($cacheKey, $responseTimes, 3600);
    }

    /**
     * Check system health status.
     */
    public function getHealthStatus(): array
    {
        $metrics = $this->collectSystemMetrics();
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'overall_score' => 100,
        ];

        // Database health check
        if (isset($metrics['database']['error'])) {
            $health['checks']['database'] = ['status' => 'unhealthy', 'message' => 'Database connection failed'];
            $health['overall_score'] -= 30;
        } elseif ($metrics['database']['connection_time'] > 100) {
            $health['checks']['database'] = ['status' => 'degraded', 'message' => 'Slow database response'];
            $health['overall_score'] -= 10;
        } else {
            $health['checks']['database'] = ['status' => 'healthy', 'message' => 'Database responding normally'];
        }

        // Cache health check
        if (isset($metrics['cache']['error'])) {
            $health['checks']['cache'] = ['status' => 'unhealthy', 'message' => 'Cache connection failed'];
            $health['overall_score'] -= 20;
        } elseif ($metrics['cache']['response_time'] > 50) {
            $health['checks']['cache'] = ['status' => 'degraded', 'message' => 'Slow cache response'];
            $health['overall_score'] -= 5;
        } else {
            $health['checks']['cache'] = ['status' => 'healthy', 'message' => 'Cache responding normally'];
        }

        // Memory health check
        if ($metrics['memory']['usage_percentage'] > 90) {
            $health['checks']['memory'] = ['status' => 'critical', 'message' => 'High memory usage'];
            $health['overall_score'] -= 25;
        } elseif ($metrics['memory']['usage_percentage'] > 80) {
            $health['checks']['memory'] = ['status' => 'warning', 'message' => 'Elevated memory usage'];
            $health['overall_score'] -= 10;
        } else {
            $health['checks']['memory'] = ['status' => 'healthy', 'message' => 'Memory usage normal'];
        }

        // Determine overall status
        if ($health['overall_score'] < 70) {
            $health['status'] = 'unhealthy';
        } elseif ($health['overall_score'] < 90) {
            $health['status'] = 'degraded';
        }

        return $health;
    }

    // Private helper methods

    private function getUptime(): float
    {
        $uptimeFile = storage_path('app/uptime.txt');
        if (file_exists($uptimeFile)) {
            $startTime = (float) file_get_contents($uptimeFile);
            return round((time() - $startTime) / 3600, 2); // Hours
        }
        return 0;
    }

    private function getRequestsPerMinute(): int
    {
        return Cache::get('requests_per_minute', 0);
    }

    private function getErrorRate(): float
    {
        $totalRequests = Cache::get('total_requests', 1);
        $errorRequests = Cache::get('error_requests', 0);
        return round(($errorRequests / $totalRequests) * 100, 2);
    }

    private function getActiveUsers(): int
    {
        return Cache::get('active_users_count', 0);
    }

    private function getConcurrentSessions(): int
    {
        return Cache::get('concurrent_sessions', 0);
    }

    private function getDatabaseConnections(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getSlowQueries(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            return (int) $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getQueryCacheHitRate(): float
    {
        try {
            $hits = DB::select("SHOW STATUS LIKE 'Qcache_hits'")[0]->Value ?? 0;
            $inserts = DB::select("SHOW STATUS LIKE 'Qcache_inserts'")[0]->Value ?? 1;
            return round(($hits / ($hits + $inserts)) * 100, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDatabaseSize(): float
    {
        try {
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            return (float) $result[0]->size_mb ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheHitRate(): float
    {
        try {
            $info = Redis::info();
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 1;
            return round(($hits / ($hits + $misses)) * 100, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheMemoryUsage(): float
    {
        try {
            $info = Redis::info();
            return round(($info['used_memory'] ?? 0) / 1024 / 1024, 2); // MB
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheKeysCount(): int
    {
        try {
            return Redis::dbsize();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheEvictions(): int
    {
        try {
            $info = Redis::info();
            return (int) $info['evicted_keys'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getPendingJobs(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getFailedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProcessedJobsPerMinute(): int
    {
        return Cache::get('processed_jobs_per_minute', 0);
    }

    private function getAverageJobTime(): float
    {
        return Cache::get('average_job_time', 0);
    }

    private function getActiveWorkers(): int
    {
        return Cache::get('active_workers', 0);
    }

    private function getMemoryUsagePercentage(): float
    {
        $current = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        return $limit > 0 ? round(($current / $limit) * 100, 2) : 0;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }

        return $limit;
    }

    private function storeMetrics(array $metrics): void
    {
        // Store in cache for real-time access
        Cache::put('system_metrics_latest', $metrics, 300);

        // Store in time-series format for historical analysis
        $timeSeriesKey = 'metrics_' . now()->format('Y-m-d-H-i');
        Cache::put($timeSeriesKey, $metrics, 86400);

        // Log critical metrics
        if ($metrics['memory']['usage_percentage'] > 90 || 
            ($metrics['database']['connection_time'] ?? 0) > 1000) {
            Log::warning('Performance threshold exceeded', $metrics);
        }
    }
}
