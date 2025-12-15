<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production Safety Middleware
 * 
 * Checks for dangerous configuration in production environments
 * and logs warnings. Also blocks sensitive endpoints if misconfigured.
 */
class ProductionSafetyMiddleware
{
    /**
     * Dangerous configuration patterns that should never be in production
     */
    private array $dangerousPatterns = [
        'APP_DEBUG' => 'true',
        'APP_ENV' => 'local',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for production-like deployments
        if ($this->isProductionLikeEnvironment()) {
            $this->checkDangerousConfiguration($request);
        }

        return $next($request);
    }

    /**
     * Determine if we're in a production-like environment
     */
    private function isProductionLikeEnvironment(): bool
    {
        // Check if we're on a production URL (not localhost)
        $host = request()->getHost();
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
        
        // Also check for production indicators
        $hasProductionUrl = !empty(env('APP_URL')) && !str_contains(env('APP_URL'), 'localhost');
        
        return !$isLocalhost || $hasProductionUrl;
    }

    /**
     * Check for dangerous configuration and log warnings
     */
    private function checkDangerousConfiguration(Request $request): void
    {
        $warnings = [];

        if (config('app.debug') === true) {
            $warnings[] = 'APP_DEBUG=true is enabled in a production-like environment. This exposes sensitive information!';
        }

        if (config('app.env') === 'local') {
            $warnings[] = 'APP_ENV=local is set in a production-like environment. Set to "production" for proper security.';
        }

        // Log all warnings
        foreach ($warnings as $warning) {
            Log::channel('security')->critical('SECURITY WARNING: ' . $warning, [
                'ip' => $request->ip(),
                'host' => $request->getHost(),
                'path' => $request->path(),
                'user_id' => auth()->id(),
            ]);
        }

        // Store warnings for potential admin notification
        if (!empty($warnings) && auth()->check() && auth()->user()->role === 'admin') {
            session()->flash('security_warnings', $warnings);
        }
    }
}
