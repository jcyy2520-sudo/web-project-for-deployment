<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'condition',
        'threshold',
        'channel',
        'slack_webhook',
        'email_recipients',
        'enabled',
        'cooldown_minutes',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Get alert events for this rule
     */
    public function alertEvents(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    /**
     * Get recent alert events
     */
    public function recentEvents($hours = 24)
    {
        return $this->alertEvents()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Check if rule can trigger (cooldown check)
     */
    public function canTrigger(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!$this->last_triggered_at) {
            return true;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isPast();
    }

    /**
     * Mark rule as triggered
     */
    public function markTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }
}
