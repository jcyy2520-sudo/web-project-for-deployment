<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontendErrorLog extends Model
{
    protected $fillable = [
        'message',
        'error_type',
        'stack_trace',
        'file',
        'line',
        'column',
        'url',
        'user_agent',
        'ip_address',
        'user_id',
        'context',
        'breadcrumbs',
        'device_info',
        'severity',
        'is_reported',
        'occurrence_count',
    ];

    protected $casts = [
        'context' => 'array',
        'breadcrumbs' => 'array',
    ];

    /**
     * Get the user associated with this error
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get recent errors
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Get critical errors
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope: Get unreported errors
     */
    public function scopeUnreported($query)
    {
        return $query->where('is_reported', false);
    }

    /**
     * Scope: Get errors by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('error_type', $type);
    }

    /**
     * Mark as reported
     */
    public function markReported(): void
    {
        $this->update(['is_reported' => true]);
    }

    /**
     * Increment occurrence count
     */
    public function incrementOccurrence(): void
    {
        $this->increment('occurrence_count');
    }
}
