<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abuse Detection Middleware
 * 
 * Detects and blocks abusive behavior patterns including:
 * - Excessive error logging attempts (potential DoS)
 * - Suspicious request patterns
 * - Automated bot behavior
 */
class AbuseDetectionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        
        // Check if IP is blocked
        if ($this->isBlocked($ip)) {
            Log::channel('security')->warning('Blocked IP attempted access', [
                'ip' => $ip,
                'path' => $request->path(),
            ]);
            
            return response()->json([
                'error' => 'Access denied. Your IP has been temporarily blocked due to suspicious activity.',
            ], 403);
        }

        // Track request patterns
        $this->trackRequest($ip, $request);

        // Check for abuse patterns
        if ($this->detectAbuse($ip)) {
            $this->blockIp($ip, 3600); // Block for 1 hour
            
            Log::channel('security')->critical('IP blocked for abuse', [
                'ip' => $ip,
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'error' => 'Access denied due to suspicious activity pattern.',
            ], 429);
        }

        return $next($request);
    }

    /**
     * Check if an IP is blocked
     */
    private function isBlocked(string $ip): bool
    {
        return Cache::has("blocked_ip:{$ip}");
    }

    /**
     * Block an IP for a specified duration
     */
    private function blockIp(string $ip, int $seconds): void
    {
        Cache::put("blocked_ip:{$ip}", true, $seconds);
    }

    /**
     * Track request for pattern analysis
     */
    private function trackRequest(string $ip, Request $request): void
    {
        $key = "request_pattern:{$ip}";
        $window = 300; // 5 minute window
        
        $data = Cache::get($key, [
            'count' => 0,
            'paths' => [],
            'started_at' => now()->timestamp,
        ]);

        // Reset if window expired
        if (now()->timestamp - $data['started_at'] > $window) {
            $data = [
                'count' => 0,
                'paths' => [],
                'started_at' => now()->timestamp,
            ];
        }

        $data['count']++;
        $data['paths'][] = $request->path();
        
        // Keep only last 100 paths
        if (count($data['paths']) > 100) {
            $data['paths'] = array_slice($data['paths'], -100);
        }

        Cache::put($key, $data, $window);
    }

    /**
     * Detect abuse patterns
     */
    private function detectAbuse(string $ip): bool
    {
        $key = "request_pattern:{$ip}";
        $data = Cache::get($key);
        
        if (!$data) {
            return false;
        }

        // Pattern 1: Too many requests in window
        if ($data['count'] > 500) {
            return true;
        }

        // Pattern 2: Spamming specific endpoint (like frontend-errors)
        $pathCounts = array_count_values($data['paths']);
        foreach ($pathCounts as $path => $count) {
            // If more than 100 requests to same endpoint in 5 minutes
            if ($count > 100) {
                return true;
            }
        }

        // Pattern 3: Rapid-fire requests (more than 50 in 10 seconds)
        $recentKey = "recent_requests:{$ip}";
        $recentCount = Cache::get($recentKey, 0);
        Cache::put($recentKey, $recentCount + 1, 10);
        
        if ($recentCount > 50) {
            return true;
        }

        return false;
    }
}
