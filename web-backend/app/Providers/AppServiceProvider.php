<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register advanced chatbot services
        $this->app->singleton(\App\Services\WebSocketService::class, function ($app) {
            return new \App\Services\WebSocketService();
        });

        $this->app->singleton(\App\Services\WorkflowService::class, function ($app) {
            return new \App\Services\WorkflowService();
        });

        $this->app->singleton(\App\Services\ActionPermissionService::class, function ($app) {
            return new \App\Services\ActionPermissionService();
        });

        $this->app->singleton(\App\Services\ConversationThreadService::class, function ($app) {
            return new \App\Services\ConversationThreadService();
        });

        $this->app->singleton(\App\Services\ErrorHandlingService::class, function ($app) {
            return new \App\Services\ErrorHandlingService();
        });

        $this->app->singleton(\App\Services\ChatbotMetricsService::class, function ($app) {
            return new \App\Services\ChatbotMetricsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Chatbot Data Observers for 100% Real-time accuracy
        // This ensures that whenever data changes, the chatbot cache is cleared
        // and knowledge is kept up-to-date automatically
        $chatbotObserver = \App\Observers\ChatbotDataObserver::class;
        
        \App\Models\Appointment::observe($chatbotObserver);
        \App\Models\Payment::observe($chatbotObserver);
        \App\Models\Refund::observe($chatbotObserver);
        \App\Models\User::observe($chatbotObserver);
        \App\Models\Service::observe($chatbotObserver);
        
        // Also observe AppointmentSettings if it exists
        if (class_exists(\App\Models\AppointmentSettings::class)) {
            \App\Models\AppointmentSettings::observe($chatbotObserver);
        }
        
        // Observe Notification for real-time updates
        if (class_exists(\App\Models\Notification::class)) {
            \App\Models\Notification::observe($chatbotObserver);
        }

        // Configure granular rate limiting for different API endpoints
        RateLimiter::for('api', function (Request $request) {
            // Stricter limits for auth endpoints
            if ($request->is('api/auth/*') || $request->is('api/register') || $request->is('api/login')) {
                return Limit::perMinute(5)->by($request->ip());
            }

            // Moderate limits for batch operations (heavy database work)
            if ($request->is('api/*/batch/*') || $request->is('api/*/bulk/*')) {
                return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
            }

            // Standard limits for authenticated users
            if ($request->user()) {
                return Limit::perMinute(60)->by($request->user()->id);
            }

            // Strict limits for guests
            return Limit::perMinute(20)->by($request->ip());
        });

        // Rate limit for password reset attempts
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->email);
        });

        // Rate limit for verification codes
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // REMOVED: $this->syncDefaultServices(); - This was causing 500 errors!
        // Moved database logic to a Command or Job instead
    }
}