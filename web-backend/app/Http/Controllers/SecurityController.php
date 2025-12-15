<?php

namespace App\Http\Controllers;

use App\Services\DDoSProtectionService;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(private DDoSProtectionService $ddosService)
    {
    }

    /**
     * Get security events
     */
    public function getSecurityEvents(Request $request): JsonResponse
    {
        $minutes = (int) $request->get('minutes', 60);
        $events = SecurityEvent::recent($minutes)->get();

        return response()->json([
            'count' => $events->count(),
            'suspicious' => $events->where('is_suspicious', true)->count(),
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'ip_address' => $event->ip_address,
                    'risk_score' => $event->risk_score,
                    'is_suspicious' => $event->is_suspicious,
                    'action_taken' => $event->action_taken,
                    'created_at' => $event->created_at,
                ];
            })->toArray(),
        ]);
    }

    /**
     * Get blocked IPs
     */
    public function getBlockedIps(): JsonResponse
    {
        $blockedIps = $this->ddosService->getBlockedIps();

        return response()->json([
            'count' => count($blockedIps),
            'blocked_ips' => $blockedIps,
        ]);
    }

    /**
     * Block an IP address
     */
    public function blockIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'nullable|string',
        ]);

        $ip = $request->input('ip_address');
        $this->ddosService->blockIp($ip, 100);

        return response()->json([
            'success' => true,
            'message' => "IP {$ip} has been blocked",
            'blocked_until' => now()->addHours(1),
        ]);
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
        ]);

        $ip = $request->input('ip_address');
        $this->ddosService->unblockIp($ip);

        return response()->json([
            'success' => true,
            'message' => "IP {$ip} has been unblocked",
        ]);
    }

    /**
     * Get security summary
     */
    public function securitySummary(Request $request): JsonResponse
    {
        $minutes = (int) $request->get('minutes', 60);
        $summary = $this->ddosService->getSecuritySummary($minutes);

        return response()->json($summary);
    }

    /**
     * Get rate limit for IP
     */
    public function getRateLimit(string $ip): JsonResponse
    {
        $rateLimit = $this->ddosService->getRateLimit($ip);

        return response()->json([
            'ip' => $ip,
            'limit' => $rateLimit['limit'],
            'window_seconds' => $rateLimit['window'],
        ]);
    }

    /**
     * Update rate limit
     */
    public function updateRateLimit(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'limit' => 'required|integer|min:10',
            'window_seconds' => 'required|integer|min:10',
        ]);

        $this->ddosService->updateRateLimit(
            $request->input('ip_address'),
            $request->input('limit'),
            $request->input('window_seconds')
        );

        return response()->json([
            'success' => true,
            'message' => 'Rate limit updated',
        ]);
    }
}
