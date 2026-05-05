<?php

namespace App\Providers;

use Illuminate\Console\Command;
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
        $this->app->afterResolving(Command::class, function (Command $command, $app): void {
            $command->setLaravel($app);
        });

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

        // MANDATORY: ChatbotSecurityService — always-on security layer (not feature-flagged)
        $this->app->singleton(\App\Services\ChatbotSecurityService::class, function ($app) {
            return new \App\Services\ChatbotSecurityService();
        });


        $this->app->singleton(\App\Services\ChatbotLearningService::class, function ($app) {
            return new \App\Services\ChatbotLearningService();
        });

        // MANDATORY: Explicitly bind UnifiedChatbotService so that AgentReasoningService
        // and AgentToolRegistry are properly injected (not silently null from auto-resolution)
        $this->app->singleton(\App\Services\UnifiedChatbotService::class, function ($app) {
            return new \App\Services\UnifiedChatbotService(
                $app->make(\App\Services\LLMService::class),
                $app->make(\App\Services\VectorEmbeddingService::class),
                $app->make(\App\Services\ChatbotRealTimeDataService::class),
                $app->make(\App\Services\ChatbotFeedbackService::class),
                $app->make(\App\Services\DynamicSystemPromptService::class),
                $app->make(\App\Services\DynamicKnowledgeFeedService::class),
                $app->make(\App\Services\ChatbotSecurityService::class),
                $this->resolveOptional(\App\Services\StreamingLLMService::class),
                $this->resolveOptional(\App\Services\ChatbotMemoryService::class),
                $this->resolveOptional(\App\Services\ChatbotGuardService::class),
                $this->resolveOptional(\App\Services\ChatbotAnalyticsService::class),
                $app->make(\App\Services\AgentReasoningService::class),     // NOT optional
                $app->make(\App\Services\AgentToolRegistry::class),         // NOT optional
                $this->resolveOptional(\App\Services\IntelligentFallbackService::class),
            );
        });
    }

    /**
     * Safely resolve an optional service, returning null if it doesn't exist or fails.
     */
    private function resolveOptional(string $class): mixed
    {
        try {
            return class_exists($class) ? $this->app->make($class) : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to resolve optional service {$class}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS across the entire application in production (and all environments except explicitly local testing)
        // This ensures email links, pagination, and redirections maintain secure protocols behind load balancers.
        if (env('APP_ENV') !== 'local' || env('FORCE_HTTPS', true)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

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

        // Register Analytics Observers for real-time analytics updates
        // This ensures analytics cache is invalidated whenever appointments, refunds, or services change
        $analyticsObserver = \App\Observers\AnalyticsObserver::class;
        
        \App\Models\Appointment::observe($analyticsObserver);
        \App\Models\Refund::observe($analyticsObserver);
        \App\Models\Service::observe($analyticsObserver);

        // Register ML Outcome Observer for automatic feedback loop
        \App\Models\Appointment::observe(\App\Observers\MlOutcomeObserver::class);

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

        $forgotPasswordLimiterKey = static function (Request $request): string {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = $email !== '' ? $email : 'guest';

            return $identifier . '|' . ($request->ip() ?? 'unknown');
        };

        RateLimiter::for('forgot-password-send-code', function (Request $request) use ($forgotPasswordLimiterKey) {
            return Limit::perMinutes(5, 5)->by($forgotPasswordLimiterKey($request));
        });

        RateLimiter::for('forgot-password-verify-code', function (Request $request) use ($forgotPasswordLimiterKey) {
            return Limit::perMinutes(5, 5)->by($forgotPasswordLimiterKey($request));
        });

        RateLimiter::for('forgot-password-reset', function (Request $request) use ($forgotPasswordLimiterKey) {
            return Limit::perMinutes(5, 5)->by($forgotPasswordLimiterKey($request));
        });

        RateLimiter::for('forgot-password-resend-code', function (Request $request) use ($forgotPasswordLimiterKey) {
            return Limit::perMinutes(10, 3)->by($forgotPasswordLimiterKey($request));
        });
    }
}