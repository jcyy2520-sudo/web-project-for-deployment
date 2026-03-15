<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only middleware.
 * 
 * This resolves the Phase 3 routes that use middleware('admin') without parameters.
 * Unlike RoleMiddleware which requires role parameters (e.g., role:admin,staff),
 * this middleware hardcodes the admin check.
 * 
 * Rollback: Remove this file and the 'admin' alias from bootstrap/app.php.
 * Phase 3 routes would then need to change from middleware('admin') to middleware('role:admin').
 */
class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Block deactivated/blocked users even if their token is still valid
        if (!$user->is_active || in_array($user->account_status ?? 'active', ['blocked', 'deactivated', 'deleted'])) {
            return response()->json(['message' => 'Account is deactivated or blocked'], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized — Admin access required'], 403);
        }

        return $next($request);
    }
}
