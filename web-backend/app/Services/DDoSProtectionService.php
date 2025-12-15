<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DDoSProtectionService
{
    private const DEFAULT_RATE_LIMIT = 100; // requests
    private const DEFAULT_TIME_WINDOW = 60; // seconds
    private const SUSPICIOUS_THRESHOLD = 150; // requests per minute
    private const RISK_SCORE_THRESHOLD = 70;
    private const BLOCK_DURATION = 3600; // 1 hour in seconds

    public function __construct(private RateLimiter $limiter)
    {
    }

    /**
     * Check if request should be allowed
     */
    public function shouldAllowRequest(string $ip, string $endpoint = null, string $method = null): bool
    {
        // Check if IP is blocked
        if ($this->isIpBlocked($ip)) {
            return false;
        }

        // Get current request count for IP
        $requestKey = "ddos:requests:{$ip}";
        $requestCount = (int) Cache::get($requestKey, 0);
        $requestCount++;

        // Check rate limit
        if ($requestCount > self::DEFAULT_RATE_LIMIT) {
            $this->handleSuspiciousActivity($ip, $endpoint, $method, $requestCount);
            return false;
        }

        // Update counter
        Cache::put($requestKey, $requestCount, self::DEFAULT_TIME_WINDOW);

        // Check for spike patterns
        if ($this->detectAnomalousPattern($ip, $endpoint)) {
            $this->handleSuspiciousActivity($ip, $endpoint, $method, $requestCount);
            return false;
        }

        return true;
    }

    /**
     * Detect anomalous request patterns
     */
    private function detectAnomalousPattern(string $ip, ?string $endpoint = null): bool
    {
        // Track per-endpoint request counts
        if ($endpoint) {
            $endpointKey = "ddos:endpoint:{$ip}:{$endpoint}";
            $endpointCount = (int) Cache::get($endpointKey, 0);
            $endpointCount++;
            Cache::put($endpointKey, $endpointCount, self::DEFAULT_TIME_WINDOW);

            // Alert if hitting same endpoint excessively
            if ($endpointCount > self::SUSPICIOUS_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle suspicious activity
     */
    private function handleSuspiciousActivity(string $ip, ?string $endpoint = null, ?string $method = null, int $requestCount = 0): void
    {
        $riskScore = $this->calculateRiskScore($ip, $requestCount);

        $event = SecurityEvent::create([
            'event_type' => 'rate_limit_exceeded',
            'ip_address' => $ip,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_count_per_minute' => $requestCount,
            'is_suspicious' => true,
            'risk_score' => $riskScore,
            'details' => [
                'detection_time' => now()->toIso8601String(),
                'pattern' => 'excessive_requests',
                'method' => $method,
            ],
        ]);

        Log::warning('Suspicious activity detected', [
            'ip' => $ip,
            'risk_score' => $riskScore,
            'request_count' => $requestCount,
            'endpoint' => $endpoint,
        ]);

        // Block IP if risk score is high enough
        if ($riskScore >= self::RISK_SCORE_THRESHOLD) {
            $this->blockIp($ip, $riskScore);
        }
    }

    /**
     * Calculate risk score
     */
    private function calculateRiskScore(string $ip, int $requestCount): float
    {
        $score = 0;

        // Base score on request count
        if ($requestCount > self::SUSPICIOUS_THRESHOLD) {
            $score += min(40, (($requestCount - self::SUSPICIOUS_THRESHOLD) / 10));
        }

        // Check for repeat offenses
        $recentEvents = SecurityEvent::byIp($ip)->where('is_suspicious', true)
            ->where('created_at', '>', now()->subHours(1))
            ->count();

        if ($recentEvents > 0) {
            $score += min(30, $recentEvents * 10);
        }

        // Check if already blocked before
        $previousBlocks = SecurityEvent::byIp($ip)
            ->where('action_taken', 'blocked')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        if ($previousBlocks > 0) {
            $score += min(30, $previousBlocks * 15);
        }

        return min(100, $score);
    }

    /**
     * Block an IP address
     */
    public function blockIp(string $ip, float $riskScore = 100): void
    {
        $blockedUntil = now()->addSeconds(self::BLOCK_DURATION);

        Cache::put("ddos:blocked:{$ip}", true, self::BLOCK_DURATION);

        SecurityEvent::where('ip_address', $ip)->latest()->first()?->update([
            'action_taken' => 'blocked',
            'blocked_until' => $blockedUntil,
        ]);

        SecurityEvent::create([
            'event_type' => 'ip_blocked',
            'ip_address' => $ip,
            'is_suspicious' => true,
            'risk_score' => $riskScore,
            'action_taken' => 'blocked',
            'blocked_until' => $blockedUntil,
            'details' => [
                'blocked_until' => $blockedUntil->toIso8601String(),
                'duration_seconds' => self::BLOCK_DURATION,
            ],
        ]);

        Log::warning("IP blocked due to suspicious activity: {$ip}", [
            'risk_score' => $riskScore,
            'blocked_until' => $blockedUntil,
        ]);
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(string $ip): void
    {
        Cache::forget("ddos:blocked:{$ip}");
        
        SecurityEvent::where('ip_address', $ip)
            ->where('action_taken', 'blocked')
            ->update(['blocked_until' => null]);

        Log::info("IP unblocked: {$ip}");
    }

    /**
     * Check if IP is currently blocked
     */
    public function isIpBlocked(string $ip): bool
    {
        // Check cache first
        if (Cache::has("ddos:blocked:{$ip}")) {
            return true;
        }

        // Check database for active blocks
        $block = SecurityEvent::where('ip_address', $ip)
            ->where('blocked_until', '>', now())
            ->where('action_taken', 'blocked')
            ->latest()
            ->first();

        return $block !== null;
    }

    /**
     * Get blocked IPs list
     */
    public function getBlockedIps(): array
    {
        return SecurityEvent::blocked()->get()->map(function ($event) {
            return [
                'ip' => $event->ip_address,
                'blocked_since' => $event->created_at,
                'blocked_until' => $event->blocked_until,
                'risk_score' => $event->risk_score,
                'reason' => $event->details['pattern'] ?? 'unknown',
            ];
        })->toArray();
    }

    /**
     * Get security events summary
     */
    public function getSecuritySummary(int $minutes = 60): array
    {
        $events = SecurityEvent::recent($minutes)->get();

        return [
            'total_events' => $events->count(),
            'suspicious_events' => $events->where('is_suspicious', true)->count(),
            'blocked_ips' => $events->where('action_taken', 'blocked')->pluck('ip_address')->unique()->count(),
            'high_risk_events' => $events->where('risk_score', '>=', 70)->count(),
            'by_type' => $events->groupBy('event_type')->map->count(),
        ];
    }

    /**
     * Clear rate limit for an IP (admin use only)
     */
    public function clearRateLimit(string $ip): void
    {
        Cache::forget("ddos:requests:{$ip}");
        Log::info("Rate limit cleared for IP: {$ip}");
    }

    /**
     * Update rate limit configuration
     */
    public function updateRateLimit(string $ip, int $limit, int $windowSeconds): void
    {
        $key = "ddos:rate_limit:{$ip}";
        Cache::put($key, [
            'limit' => $limit,
            'window' => $windowSeconds,
        ], 86400); // Store for 24 hours
    }

    /**
     * Get rate limit configuration for IP
     */
    public function getRateLimit(string $ip): array
    {
        $custom = Cache::get("ddos:rate_limit:{$ip}");
        
        return $custom ?? [
            'limit' => self::DEFAULT_RATE_LIMIT,
            'window' => self::DEFAULT_TIME_WINDOW,
        ];
    }
}
