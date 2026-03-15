<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Block deactivated/blocked users even if their token is still valid
        if (!$user->is_active || in_array($user->account_status ?? 'active', ['blocked', 'deactivated', 'deleted'])) {
            return response()->json(['message' => 'Account is deactivated or blocked'], 403);
        }

        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}