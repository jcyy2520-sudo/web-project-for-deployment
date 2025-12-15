<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertEvent extends Model
{
    protected $fillable = [
        'alert_rule_id',
        'severity',
        'message',
        'context',
        'channel',
        'sent',
        'sent_at',
        'external_id',
        'acknowledged',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgment_note',
    ];

    protected $casts = [
        'sent' => 'boolean',
        'acknowledged' => 'boolean',
        'context' => 'array',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Get the alert rule
     */
    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    /**
     * Get the user who acknowledged this
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Scope: Get unacknowledged alerts
     */
    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    /**
     * Scope: Get critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope: Get unsent alerts
     */
    public function scopeUnsent($query)
    {
        return $query->where('sent', false);
    }

    /**
     * Acknowledge this alert
     */
    public function acknowledge(int $userId, string $note = ''): void
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'acknowledgment_note' => $note,
        ]);
    }

    /**
     * Mark as sent
     */
    public function markSent(string $externalId = null): void
    {
        $this->update([
            'sent' => true,
            'sent_at' => now(),
            'external_id' => $externalId,
        ]);
    }
}
