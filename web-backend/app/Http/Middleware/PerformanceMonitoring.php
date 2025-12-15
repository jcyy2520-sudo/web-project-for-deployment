<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\RequestMetric;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Log the error and continue
            $this->logMetric($request, null, $startTime, $startMemory, $e);
            throw $e;
        }

        $this->logMetric($request, $response, $startTime, $startMemory);

        return $response;
    }

    /**
     * Log the request metric
     */
    private function logMetric(Request $request, $response = null, $startTime = null, $startMemory = null, $exception = null): void
    {
        try {
            // Don't log health checks and metrics endpoints (avoid recursion)
            if ($this->shouldSkipLogging($request->path())) {
                return;
            }

            // Skip logging in testing mode
            if (app()->environment('testing')) {
                return;
            }

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // ms
            $memoryUsed = memory_get_usage(true) - $startMemory;

            $isError = false;
            $statusCode = 200;
            $errorType = null;

            if ($exception) {
                $isError = true;
                $errorType = get_class($exception);
                $statusCode = 500;
            } elseif ($response) {
                $statusCode = $response->getStatusCode();
                $isError = $statusCode >= 400;
            }

            RequestMetric::create([
                'method' => $request->method(),
                'path' => $request->path(),
                'endpoint' => $request->route()?->getName(),
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'memory_usage' => $memoryUsed,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'is_error' => $isError,
                'error_type' => $errorType,
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
        }
    }

    /**
     * Determine if we should skip logging for this path
     */
    private function shouldSkipLogging(string $path): bool
    {
        $skipPaths = [
            'api/health',
            'api/admin/error-logs',
            'api/admin/metrics',
            'api/admin/performance',
            'health-check',
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_contains($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }
}
