<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Log the request metric (sampled to reduce DB load)
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

            // PERFORMANCE FIX: Sample only a percentage of requests to reduce DB write load
            // Errors are always logged; normal requests are sampled at configured rate
            $rawRate = config('monitoring.sample_rate', 5);
            $sampleRate = max(0, min(100, (int) $rawRate)); // Clamp to [0,100], default 5%
            if ($sampleRate === 0) {
                $sampleRate = 5; // Fallback: never silently disable all logging
                Log::warning('PerformanceMonitoring: sample_rate was 0 or invalid, defaulting to 5%', [
                    'raw_value' => $rawRate,
                ]);
            }
            $isError = false;

            if ($exception) {
                $isError = true;
            } elseif ($response && $response->getStatusCode() >= 400) {
                $isError = true;
            }

            // Always log errors, sample normal requests
            if (!$isError && random_int(1, 100) > $sampleRate) {
                return;
            }

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // ms
            $memoryUsed = memory_get_usage(true) - $startMemory;
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
