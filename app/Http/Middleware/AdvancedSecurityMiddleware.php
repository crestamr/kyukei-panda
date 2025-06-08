<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdvancedSecurityMiddleware
{
    private const MAX_REQUESTS_PER_MINUTE = 100;
    private const SUSPICIOUS_PATTERNS = [
        'sql_injection' => '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bDELETE\b|\bDROP\b)/i',
        'xss_attempt' => '/<script|javascript:|on\w+\s*=/i',
        'path_traversal' => '/\.\.\/|\.\.\\\\/',
        'command_injection' => '/(\||;|&|\$\(|\`)/i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting per IP
        $this->enforceRateLimit($request);

        // Security pattern detection
        $this->detectSecurityThreats($request);

        // Validate request headers
        $this->validateSecurityHeaders($request);

        // Log security events
        $this->logSecurityEvent($request);

        $response = $next($request);

        // Add security headers to response
        return $this->addSecurityHeaders($response);
    }

    private function enforceRateLimit(Request $request): void
    {
        $ip = $request->ip();
        $key = "rate_limit:{$ip}";
        $requests = Cache::get($key, 0);

        if ($requests >= self::MAX_REQUESTS_PER_MINUTE) {
            Log::warning('Rate limit exceeded', [
                'ip' => $ip,
                'requests' => $requests,
                'user_agent' => $request->userAgent(),
            ]);
            
            abort(429, 'Too Many Requests');
        }

        Cache::put($key, $requests + 1, 60);
    }

    private function detectSecurityThreats(Request $request): void
    {
        $allInput = json_encode($request->all());
        $url = $request->fullUrl();
        $userAgent = $request->userAgent();

        foreach (self::SUSPICIOUS_PATTERNS as $threatType => $pattern) {
            if (preg_match($pattern, $allInput) || preg_match($pattern, $url)) {
                Log::critical('Security threat detected', [
                    'threat_type' => $threatType,
                    'ip' => $request->ip(),
                    'url' => $url,
                    'user_agent' => $userAgent,
                    'input' => $allInput,
                ]);

                // Block suspicious requests
                abort(403, 'Forbidden: Security threat detected');
            }
        }
    }

    private function validateSecurityHeaders(Request $request): void
    {
        // Validate CSRF token for state-changing requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');
            if (!$token || !hash_equals(csrf_token(), $token)) {
                Log::warning('CSRF token validation failed', [
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                ]);
            }
        }

        // Validate API key for API requests
        if ($request->is('api/*')) {
            $apiKey = $request->header('X-API-Key');
            if (!$apiKey || !$this->validateApiKey($apiKey)) {
                Log::warning('Invalid API key', [
                    'ip' => $request->ip(),
                    'api_key' => substr($apiKey ?? '', 0, 8) . '...',
                ]);
            }
        }
    }

    private function logSecurityEvent(Request $request): void
    {
        // Log all requests for security monitoring
        Log::info('Request processed', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    private function validateApiKey(string $apiKey): bool
    {
        // Validate API key against database or cache
        return Cache::remember("api_key:{$apiKey}", 3600, function () use ($apiKey) {
            // In production, check against database
            return strlen($apiKey) >= 32 && preg_match('/^[a-zA-Z0-9]+$/', $apiKey);
        });
    }
}
