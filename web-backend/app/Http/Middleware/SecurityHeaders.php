<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 * Adds security headers to all responses
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->header('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // XSS Protection: set to 0 (deprecated in modern browsers, can cause leaks in mode=block)
        $response->header('X-XSS-Protection', '0');

        // Referrer Policy - control how much referrer information is shared
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - prevent inline scripts and restrict resource loading
        $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors 'none';");

        // Permissions Policy - control which browser features can be used
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS - enforce HTTPS in production (with preload for browser HSTS preload lists)
        if (config('app.env') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cross-Origin-Opener-Policy - isolate browsing context from cross-origin popups
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin-Resource-Policy - allow cross-origin for API consumption by frontend
        $response->header('Cross-Origin-Resource-Policy', 'cross-origin');

        // Prevent caching of sensitive responses (expanded to cover all auth-protected data)
        if ($request->is(
            'api/user', 'api/login', 'api/logout', 'api/profile*', 'api/admin/*',
            'api/forgot-password/*', 'api/cashier/*', 'api/refunds/*',
            'api/appointments/*', 'api/messages/*', 'api/notifications/*',
            'api/documents/*', 'api/action-logs/*', 'api/audit-logs/*'
        )) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }

        return $response;
    }
}
