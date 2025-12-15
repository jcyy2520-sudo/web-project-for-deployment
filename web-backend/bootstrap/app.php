<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
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
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'chatbot.rate-limit' => \App\Http\Middleware\ChatbotRateLimitMiddleware::class,
            'abuse.detect' => \App\Http\Middleware\AbuseDetectionMiddleware::class,
            'production.safety' => \App\Http\Middleware\ProductionSafetyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling
        $exceptions->render(function (Throwable $e, $request) {
            // Log all exceptions with detailed context
            \Illuminate\Support\Facades\Log::error('API Exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'method' => $request->method(),
                'path' => $request->path(),
                'user_id' => auth()->id(),
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
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();