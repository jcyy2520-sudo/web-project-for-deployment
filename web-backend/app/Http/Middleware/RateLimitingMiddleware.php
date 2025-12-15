<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitingMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(private RateLimiter $limiter)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->getKey($request);
        $limit = $this->getLimit($request);

        if ($this->limiter->tooManyAttempts($key, $limit)) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $this->limiter->availableIn($key),
            ], 429)
                ->header('Retry-After', $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key);

        $response = $next($request);

        return $response->header('X-RateLimit-Limit', $limit)
            ->header('X-RateLimit-Remaining', $this->limiter->attempts($key));
    }

    /**
     * Get the rate limit key for the request
     */
    private function getKey(Request $request): string
    {
        $baseKey = 'api:' . $request->ip();

        // Use API key if provided
        if ($request->hasHeader('X-API-Key')) {
            $baseKey = 'api-key:' . $request->header('X-API-Key');
        } elseif (auth()->check()) {
            $baseKey = 'user:' . auth()->id();
        }

        return $baseKey;
    }

    /**
     * Get the rate limit for the request
     */
    private function getLimit(Request $request): int
    {
        $config = config('security.rate_limiting', []);

        // Check if this is an auth attempt
        if ($request->path() === 'api/login' || $request->path() === 'api/register') {
            $limit = $config['auth_attempts'] ?? '5,15';
            return (int) explode(',', $limit)[0];
        }

        // Check if using API key
        if ($request->hasHeader('X-API-Key')) {
            $limit = $config['api_key'] ?? '1000,1';
            return (int) explode(',', $limit)[0];
        }

        // Default limit
        $limit = $config['default'] ?? '60,1';
        return (int) explode(',', $limit)[0];
    }
}
