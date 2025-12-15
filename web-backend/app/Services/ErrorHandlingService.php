<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ErrorHandlingService - Advanced error handling and recovery
 * 
 * Provides:
 * - Graceful degradation
 * - Intelligent retry logic
 * - Smart error messages
 * - Fallback strategies
 * - Error tracking and analytics
 */
class ErrorHandlingService
{
    private const MAX_RETRIES = 3;
    private const RETRY_BACKOFF_MULTIPLIER = 2; // Exponential backoff
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_TIMEOUT = 300; // 5 minutes

    /**
     * Execute with retry logic
     */
    public function executeWithRetry(
        callable $callback,
        int $maxRetries = self::MAX_RETRIES,
        array $retryableExceptions = []
    ): array {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $result = call_user_func($callback);
                return [
                    'success' => true,
                    'result' => $result,
                    'attempts' => $attempt + 1,
                ];
            } catch (\Exception $e) {
                $attempt++;
                $lastException = $e;

                // Check if exception is retryable
                $isRetryable = $this->isRetryableException($e, $retryableExceptions);

                if (!$isRetryable || $attempt >= $maxRetries) {
                    break;
                }

                // Calculate backoff
                $delay = pow(self::RETRY_BACKOFF_MULTIPLIER, $attempt - 1) * 100;
                usleep($delay * 1000); // Convert ms to microseconds

                Log::warning("Retry attempt {$attempt} after exception: " . $e->getMessage());
            }
        }

        return [
            'success' => false,
            'error' => $lastException->getMessage(),
            'attempts' => $attempt,
            'exception_class' => get_class($lastException),
        ];
    }

    /**
     * Check circuit breaker status
     */
    public function isCircuitBreakerOpen(string $service): bool
    {
        try {
            $cacheKey = "circuit_breaker:{$service}";
            $state = Cache::get($cacheKey);

            if (!$state) {
                return false;
            }

            if ($state['open_at'] && now()->diffInSeconds($state['open_at']) > self::CIRCUIT_BREAKER_TIMEOUT) {
                // Timeout expired, try half-open
                $this->setCircuitBreakerState($service, 'half-open');
                return false;
            }

            return $state['status'] === 'open';
        } catch (\Exception $e) {
            Log::error('Circuit breaker check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record service error for circuit breaker
     */
    public function recordServiceError(string $service): void
    {
        try {
            $cacheKey = "circuit_breaker:{$service}";
            $state = Cache::get($cacheKey, [
                'status' => 'closed',
                'error_count' => 0,
                'open_at' => null,
            ]);

            $state['error_count']++;

            if ($state['error_count'] >= self::CIRCUIT_BREAKER_THRESHOLD) {
                $state['status'] = 'open';
                $state['open_at'] = now();
                Log::warning("Circuit breaker opened for service: {$service}");
            }

            Cache::put($cacheKey, $state, 3600);
        } catch (\Exception $e) {
            Log::error('Failed to record service error: ' . $e->getMessage());
        }
    }

    /**
     * Record service success for circuit breaker
     */
    public function recordServiceSuccess(string $service): void
    {
        try {
            $cacheKey = "circuit_breaker:{$service}";
            $state = [
                'status' => 'closed',
                'error_count' => 0,
                'open_at' => null,
            ];

            Cache::put($cacheKey, $state, 3600);
            Log::debug("Circuit breaker closed for service: {$service}");
        } catch (\Exception $e) {
            Log::error('Failed to record service success: ' . $e->getMessage());
        }
    }

    /**
     * Get intelligent error message for user
     */
    public function getIntelligentErrorMessage(\Exception $exception, $context = []): string
    {
        $exceptionClass = get_class($exception);
        $message = $exception->getMessage();

        // Map exceptions to user-friendly messages
        $errorMessages = [
            'Illuminate\Database\QueryException' => 'We encountered a database issue. Please try again in a moment.',
            'Symfony\Component\HttpKernel\Exception\ValidationException' => 'Please check your input and try again.',
            'Illuminate\Auth\AuthenticationException' => 'Please log in to continue.',
            'Illuminate\Auth\Access\AuthorizationException' => 'You do not have permission to perform this action.',
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => 'The requested resource was not found.',
            'RuntimeException' => 'An unexpected error occurred. Our team has been notified.',
        ];

        if (isset($errorMessages[$exceptionClass])) {
            return $errorMessages[$exceptionClass];
        }

        // For API-specific errors
        if (str_contains($message, 'API')) {
            return 'We had trouble communicating with an external service. Please try again.';
        }

        if (str_contains($message, 'timeout')) {
            return 'The request took too long. Please try again.';
        }

        // Default message
        return 'An unexpected error occurred. Please try again or contact support.';
    }

    /**
     * Handle degraded service
     */
    public function handleDegradedService(string $service, array $context = []): array
    {
        try {
            Log::warning("Service degraded: {$service}", $context);

            // Determine fallback strategy
            $fallbackData = $this->getFallbackData($service, $context);

            if ($fallbackData) {
                return [
                    'success' => true,
                    'degraded' => true,
                    'message' => "Running in reduced mode. Some features may be limited.",
                    'data' => $fallbackData,
                ];
            }

            return [
                'success' => false,
                'degraded' => true,
                'message' => "Service temporarily unavailable. Please try again later.",
            ];
        } catch (\Exception $e) {
            Log::error('Degraded service handling failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Service error. Please contact support.',
            ];
        }
    }

    /**
     * Get fallback data when service is unavailable
     */
    private function getFallbackData(string $service, array $context): ?array
    {
        $cacheKey = "fallback_data:{$service}";

        try {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                return $cachedData;
            }

            // Try to fetch fresh data from database/cache
            switch ($service) {
                case 'appointments':
                    return $this->getCachedAppointments($context);
                case 'payments':
                    return $this->getCachedPayments($context);
                case 'services':
                    return $this->getCachedServices();
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::debug("Failed to get fallback data: {$service}");
            return null;
        }
    }

    /**
     * Log error with context
     */
    public function logErrorWithContext(\Exception $exception, array $context = []): void
    {
        try {
            $errorId = Str::uuid()->toString();
            
            Log::error("Error ID: {$errorId}", [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'context' => $context,
                'user_id' => auth('sanctum')->id() ?? auth()->id(),
                'ip_address' => request()->ip(),
                'url' => request()->url(),
            ]);

            // Cache error for analysis
            Cache::push("errors:recent", [
                'id' => $errorId,
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'timestamp' => now()->toDateTimeString(),
                'context' => $context,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log error with context: ' . $e->getMessage());
        }
    }

    /**
     * Get error summary for admin
     */
    public function getErrorSummary(int $hours = 24): array
    {
        try {
            $recentErrors = Cache::get("errors:recent", []);
            
            $errorsByType = [];
            $errorCount = 0;

            foreach ($recentErrors as $error) {
                $type = $error['class'];
                if (!isset($errorsByType[$type])) {
                    $errorsByType[$type] = 0;
                }
                $errorsByType[$type]++;
                $errorCount++;
            }

            return [
                'total_errors' => $errorCount,
                'errors_by_type' => $errorsByType,
                'recent_errors' => array_slice($recentErrors, -10),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get error summary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate error recovery
     */
    public function validateRecovery(string $errorId): array
    {
        try {
            $cacheKey = "error_recovery:{$errorId}";
            $recovery = Cache::get($cacheKey);

            if (!$recovery) {
                return [
                    'recovered' => false,
                    'message' => 'Recovery not found',
                ];
            }

            return [
                'recovered' => true,
                'recovery' => $recovery,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate recovery: ' . $e->getMessage());
            return ['recovered' => false];
        }
    }

    /**
     * Private helper methods
     */

    private function isRetryableException(\Exception $exception, array $retryableExceptions): bool
    {
        if (empty($retryableExceptions)) {
            // Default retryable exceptions
            $retryableExceptions = [
                'Illuminate\Database\QueryException',
                'RuntimeException',
                'TimeoutException',
            ];
        }

        foreach ($retryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    private function setCircuitBreakerState(string $service, string $status): void
    {
        $cacheKey = "circuit_breaker:{$service}";
        $state = Cache::get($cacheKey, []);
        $state['status'] = $status;

        if ($status === 'half-open') {
            $state['open_at'] = null;
        }

        Cache::put($cacheKey, $state, 3600);
    }

    private function getCachedAppointments(array $context): ?array
    {
        try {
            $userId = $context['user_id'] ?? auth()->id();
            if (!$userId) return null;

            return Cache::remember("appointments:user:{$userId}", 3600, function () use ($userId) {
                return \App\Models\Appointment::where('user_id', $userId)
                    ->limit(10)
                    ->get()
                    ->toArray();
            });
        } catch (\Exception $e) {
            Log::debug('Failed to get cached appointments: ' . $e->getMessage());
            return null;
        }
    }

    private function getCachedPayments(array $context): ?array
    {
        try {
            $userId = $context['user_id'] ?? auth()->id();
            if (!$userId) return null;

            return Cache::remember("payments:user:{$userId}", 3600, function () use ($userId) {
                return \App\Models\Payment::where('user_id', $userId)
                    ->limit(10)
                    ->get()
                    ->toArray();
            });
        } catch (\Exception $e) {
            Log::debug('Failed to get cached payments: ' . $e->getMessage());
            return null;
        }
    }

    private function getCachedServices(): ?array
    {
        try {
            return Cache::remember("services:all", 3600, function () {
                return \App\Models\Service::where('is_active', true)
                    ->limit(20)
                    ->get()
                    ->toArray();
            });
        } catch (\Exception $e) {
            Log::debug('Failed to get cached services: ' . $e->getMessage());
            return null;
        }
    }
}
