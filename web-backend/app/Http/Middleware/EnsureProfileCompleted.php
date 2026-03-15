<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->profile_completed) {
            return response()->json([
                'error' => 'Profile Incomplete',
                'message' => 'Please complete your profile before accessing this feature.',
                'profile_completed' => false,
            ], 422);
        }

        return $next($request);
    }
}
