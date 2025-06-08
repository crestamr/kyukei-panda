<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AutoConfigurationMiddleware
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Only run configuration check once per hour to avoid performance impact
        $cacheKey = 'auto_config_check_' . date('Y-m-d-H');
        
        if (!Cache::has($cacheKey)) {
            $this->performConfigurationCheck();
            Cache::put($cacheKey, true, 3600); // 1 hour
        }

        return $next($request);
    }

    private function performConfigurationCheck(): void
    {
        try {
            $result = $this->configService->validateAndFixConfiguration();
            
            if ($result['issues_found']) {
                Log::info('Configuration issues detected and fixed', [
                    'issues' => $result['issues'],
                    'fixes' => $result['fixes_applied'],
                ]);
                
                // Store configuration status for dashboard
                Cache::put('app_configuration_status', [
                    'status' => $result['configuration_status'],
                    'last_check' => now()->toISOString(),
                    'issues_fixed' => count($result['fixes_applied']),
                ], 86400);
            }
            
        } catch (\Exception $e) {
            Log::error('Configuration check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
