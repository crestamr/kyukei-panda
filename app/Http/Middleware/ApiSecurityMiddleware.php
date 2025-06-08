<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting based on IP and user
        $key = $this->getRateLimitKey($request);
        $maxAttempts = $this->getMaxAttempts($request);
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Try again in ' . $seconds . ' seconds.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        // Security headers
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // API-specific headers
        $response->headers->set('X-API-Version', '1.0');
        $response->headers->set('X-Rate-Limit-Limit', (string) $maxAttempts);
        $response->headers->set('X-Rate-Limit-Remaining', (string) max(0, $maxAttempts - RateLimiter::attempts($key)));

        return $response;
    }

    /**
     * Get the rate limit key for the request.
     */
    private function getRateLimitKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            return 'api:user:' . $user->id;
        }

        return 'api:ip:' . $request->ip();
    }

    /**
     * Get the maximum attempts allowed for the request.
     */
    private function getMaxAttempts(Request $request): int
    {
        // Higher limits for authenticated users
        if ($request->user()) {
            return match($request->route()?->getName()) {
                'api.kyukei-panda.activities.record' => 120, // 2 per second
                'api.kyukei-panda.status' => 60, // 1 per second
                'api.kyukei-panda.suggestions' => 30, // 0.5 per second
                default => 100,
            };
        }

        // Lower limits for unauthenticated requests
        return 20;
    }
}
