<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestMetric extends Model
{
    protected $fillable = [
        'method',
        'path',
        'endpoint',
        'status_code',
        'response_time_ms',
        'memory_usage',
        'user_id',
        'ip_address',
        'is_error',
        'error_type',
    ];

    /**
     * Scope: Get metrics from last N hours
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Get slow requests (above threshold)
     */
    public function scopeSlow($query, $threshold = 1000)
    {
        return $query->where('response_time_ms', '>', $threshold);
    }

    /**
     * Scope: Get errors only
     */
    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    /**
     * Get related user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get average response time for a path
     */
    public static function avgResponseTime($path, $hours = 24)
    {
        return self::where('path', $path)
            ->recent($hours)
            ->avg('response_time_ms');
    }

    /**
     * Get top slow endpoints
     */
    public static function topSlowEndpoints($limit = 10, $hours = 24)
    {
        return self::recent($hours)
            ->selectRaw('path, AVG(response_time_ms) as avg_time, COUNT(*) as count')
            ->groupBy('path')
            ->orderByDesc('avg_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Get error rate by endpoint
     */
    public static function errorRateByEndpoint($hours = 24)
    {
        return self::recent($hours)
            ->selectRaw('path, 
                SUM(CASE WHEN is_error = 1 THEN 1 ELSE 0 END) as error_count,
                COUNT(*) as total_requests')
            ->groupBy('path')
            ->get()
            ->map(function ($item) {
                $item->error_rate = $item->total_requests > 0 
                    ? round(($item->error_count / $item->total_requests) * 100, 2)
                    : 0;
                return $item;
            });
    }
}
