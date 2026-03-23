<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ServiceUnavailability extends Model
{
    protected $table = 'service_unavailabilities';

    protected $fillable = [
        'service_id',
        'reason',
        'reason_category',
        'is_global',
        'unavailable_from',
        'unavailable_until',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_active' => 'boolean',
        'unavailable_from' => 'datetime',
        'unavailable_until' => 'datetime',
    ];

    const REASON_CATEGORIES = [
        'maintenance' => 'Maintenance',
        'staff_unavailable' => 'Staff Unavailable',
        'system_upgrade' => 'System Upgrade',
        'holiday' => 'Holiday',
        'policy_change' => 'Policy Change',
        'custom' => 'Custom',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this unavailability is currently in effect.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Global (indefinite) unavailability — always active while is_active=true
        if ($this->is_global) {
            return true;
        }

        // Scheduled — check if current time falls within the range
        $now = Carbon::now();

        if ($this->unavailable_from && $now->lt($this->unavailable_from)) {
            return false;
        }

        if ($this->unavailable_until && $now->gt($this->unavailable_until)) {
            return false;
        }

        return true;
    }

    /**
     * Scope to only active unavailabilities currently in effect.
     */
    public function scopeCurrentlyActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                // Global (indefinite)
                $q->where('is_global', true)
                  // Or scheduled and currently in range
                  ->orWhere(function ($sq) {
                      $sq->where('is_global', false)
                         ->where(function ($inner) {
                             $inner->whereNull('unavailable_from')
                                   ->orWhere('unavailable_from', '<=', Carbon::now());
                         })
                         ->where(function ($inner) {
                             $inner->whereNull('unavailable_until')
                                   ->orWhere('unavailable_until', '>=', Carbon::now());
                         });
                  });
            });
    }

    /**
     * Check if a service is unavailable at a specific date/time.
     */
    public static function isServiceUnavailableAt(int $serviceId, ?Carbon $dateTime = null): ?self
    {
        $dateTime = $dateTime ?? Carbon::now();

        return static::where('service_id', $serviceId)
            ->where('is_active', true)
            ->where(function ($q) use ($dateTime) {
                $q->where('is_global', true)
                  ->orWhere(function ($sq) use ($dateTime) {
                      $sq->where('is_global', false)
                         ->where(function ($inner) use ($dateTime) {
                             $inner->whereNull('unavailable_from')
                                   ->orWhere('unavailable_from', '<=', $dateTime);
                         })
                         ->where(function ($inner) use ($dateTime) {
                             $inner->whereNull('unavailable_until')
                                   ->orWhere('unavailable_until', '>=', $dateTime);
                         });
                  });
            })
            ->first();
    }
}
