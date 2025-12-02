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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        // Sync default appointment types to services on app boot
        $this->syncDefaultServices();
    }

    /**
     * Sync default appointment types from Appointment model to Services table
     */
    private function syncDefaultServices(): void
    {
        try {
            // Only run in CLI or if not already synced
            if (php_sapi_name() === 'cli') {
                return; // Skip in CLI (migrations, commands, etc)
            }

            $appointmentTypes = \App\Models\Appointment::getTypes();
            
            foreach ($appointmentTypes as $key => $label) {
                // Check if service already exists (by exact label name)
                if (!\App\Models\Service::where('name', $label)->exists()) {
                    \App\Models\Service::create([
                        'name' => $label,
                        'description' => 'Predefined appointment type',
                        'is_active' => true
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail to not break the app
            \Illuminate\Support\Facades\Log::debug('Service sync failed: ' . $e->getMessage());
        }
    }
}