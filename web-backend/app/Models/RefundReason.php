<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundReason extends Model
{
    protected $fillable = [
        'type',
        'key',
        'label',
        'is_active',
        'is_default',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: Get request-type reasons (used when submitting a refund)
     */
    public function scopeRequestReasons($query)
    {
        return $query->where('type', 'request');
    }

    /**
     * Scope: Get decline-type reasons (used when rejecting a refund)
     */
    public function scopeDeclineReasons($query)
    {
        return $query->where('type', 'decline');
    }

    /**
     * Scope: Get only active reasons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relationship: The user who created this reason
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get active request reason keys for validation
     */
    public static function getActiveRequestKeys()
    {
        return static::requestReasons()->active()->orderBy('sort_order')->pluck('key')->toArray();
    }

    /**
     * Get active decline reason keys for validation
     */
    public static function getActiveDeclineKeys()
    {
        return static::declineReasons()->active()->orderBy('sort_order')->pluck('key')->toArray();
    }
}
