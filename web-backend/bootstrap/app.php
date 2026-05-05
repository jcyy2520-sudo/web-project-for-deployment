<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        // Send appointment reminders every minute
        $schedule->command('appointments:send-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
        
        // Clean up expired verification codes daily at 2 AM
        $schedule->command('cleanup:verification-codes')
            ->dailyAt('02:00');
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Apply SecurityHeaders globally to all responses
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->statefulApi();

        $middleware->alias([
            'action.log' => \App\Http\Middleware\AutomaticActionLogMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            // 'admin' alias resolves Phase 3 routes that use middleware('admin')
            // It maps to RoleMiddleware which will receive 'admin' as the role parameter
            // from route definitions like: middleware(['auth:sanctum', 'admin'])
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'chatbot.rate-limit' => \App\Http\Middleware\ChatbotRateLimitMiddleware::class,
            'abuse.detect' => \App\Http\Middleware\AbuseDetectionMiddleware::class,
            'production.safety' => \App\Http\Middleware\ProductionSafetyMiddleware::class,
            'custom.rate-limit' => \App\Http\Middleware\RateLimitingMiddleware::class,
            'performance.monitor' => \App\Http\Middleware\PerformanceMonitoring::class,
            'profile.completed' => \App\Http\Middleware\EnsureProfileCompleted::class,
        ]);
        
        // Configure unauthenticated redirect for API requests
        // This prevents the "Route [login] not defined" error
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Return null to throw AuthenticationException instead of redirecting
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling
        $exceptions->render(function (Throwable $e, $request) {
            // Log all exceptions with detailed context
            // Wrap auth()->id() in try-catch to prevent cascading failures
            // (e.g. when the encryption key itself is the problem)
            $userId = null;
            try {
                $userId = auth()->id();
            } catch (\Throwable $authException) {
                // Silently ignore — auth is unavailable during this error
            }

            \Illuminate\Support\Facades\Log::error('API Exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'method' => $request->method(),
                'path' => $request->path(),
                'user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            // Return JSON for API requests
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = 500;
                $message = 'An error occurred';

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = 'Validation failed';
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'errors' => $e->errors(),
                    ], $status);
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $status = 401;
                    $message = 'Unauthenticated';
                } elseif ($e instanceof \Illuminate\Authorization\AuthorizationException) {
                    $status = 403;
                    $message = 'Unauthorized';
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $status = 404;
                    $message = 'Resource not found';
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'HTTP Exception';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();