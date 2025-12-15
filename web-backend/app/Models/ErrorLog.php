<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'level',
        'message',
        'exception',
        'stack_trace',
        'file',
        'line',
        'context',
        'user_agent',
        'ip_address',
        'url',
        'method',
        'request_data',
        'user_id',
    ];

    protected $casts = [
        'context' => 'array',
        'request_data' => 'array',
    ];

    /**
     * Scope: Get errors from the last N hours
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Get errors of a specific level
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: Critical errors only
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('level', ['error', 'critical']);
    }

    /**
     * Get related user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
