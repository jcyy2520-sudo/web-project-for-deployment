<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TokenService;

class ValidateAccessToken
{
    public function handle(Request $request, Closure $next, $purpose = null)
    {
        $token = $request->query('token') || $request->header('X-Access-Token');
        $uuid = $request->query('uuid') || $request->route('uuid');

        if (!$token && !$uuid) {
            return response()->json(['error' => 'Missing token or UUID'], 401);
        }

        $result = null;

        if ($token && $uuid) {
            $result = TokenService::verifyTokenByUuid($uuid, $token);
        } elseif ($token) {
            $result = TokenService::verifyToken($token, $purpose);
        }

        if (!$result) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $request->merge([
            'token_user' => $result['user'],
            'token_data' => $result['token_data'],
            'token_purpose' => $result['purpose']
        ]);

        return $next($request);
    }
}
