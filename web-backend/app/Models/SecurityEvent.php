<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $fillable = [
        'event_type',
        'ip_address',
        'user_id',
        'endpoint',
        'method',
        'status_code',
        'request_count_per_minute',
        'is_suspicious',
        'risk_score',
        'details',
        'action_taken',
        'blocked_until',
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'risk_score' => 'float',
        'details' => 'array',
        'blocked_until' => 'datetime',
    ];

    /**
     * Get recent events
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get suspicious events
     */
    public function scopeSuspicious($query)
    {
        return $query->where('is_suspicious', true)
            ->orWhere('risk_score', '>=', 70);
    }

    /**
     * Get events by IP
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get currently blocked IPs
     */
    public function scopeBlocked($query)
    {
        return $query->whereNotNull('blocked_until')
            ->where('blocked_until', '>', now());
    }
}
