<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AppointmentSettings extends Model
{
    protected $fillable = [
        'daily_booking_limit_per_user',
        'is_active',
        'description',
        'last_updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_booking_limit_per_user' => 'integer',
    ];

    /**
     * Get the user who last updated these settings
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /**
     * Get the current active settings (singleton pattern)
     */
    public static function getCurrent()
    {
        return self::where('is_active', true)->first() ?? self::create([
            'daily_booking_limit_per_user' => 3,
            'is_active' => true,
            'description' => 'Default appointment settings',
        ]);
    }


    /**
     * Check if a user has reached their booking limit in the rolling 24-hour window.
     */
    public static function userHasReachedDailyLimit($userId, $date = null)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return false;
        }

        $count = self::getBookingsInLast24Hours($userId)->count();

        return $count >= $settings->daily_booking_limit_per_user;
    }

    /**
     * Get remaining bookings for user in the rolling 24-hour window.
     */
    public static function getRemainingBookingsForUser($userId, $date = null)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return null;
        }

        $count = self::getBookingsInLast24Hours($userId)->count();
        $remaining = $settings->daily_booking_limit_per_user - $count;

        return max(0, $remaining);
    }

    /**
     * Get all bookings created by a user in the rolling 24-hour window.
     */
    public static function getUserBookingsForDate($userId, $date = null)
    {
        return self::getBookingsInLast24Hours($userId);
    }

    /**
     * Clear the request-level cache (legacy - now unused as static cache is removed)
     */
    public static function clearRequestCache($userId)
    {
        // No-op: static cache removed to prevent staleness in persistent environments (Octane)
    }

    /**
     * Core query: get all pending/approved/completed bookings created in the last 24 hours.
     * Uses request-level caching to avoid repeated DB calls.
     */
    private static function getBookingsInLast24Hours($userId)
    {
        $since = \Carbon\Carbon::now()->subHours(24);

        $bookings = Appointment::where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->select('id', 'appointment_date', 'appointment_time', 'status', 'service_id', 'created_at')
            ->with('service')
            ->orderBy('created_at', 'asc')
            ->get();
        
        return $bookings;
    }

    /**
     * Calculate the exact datetime when the user can book again.
     */
    public static function getNextAvailableTime($userId)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return null;
        }

        $bookings = self::getBookingsInLast24Hours($userId);

        if ($bookings->count() < $settings->daily_booking_limit_per_user) {
            return null; // not at limit
        }

        // The oldest booking's created_at + 24h = when it falls out of the window
        $oldest = $bookings->first();
        if (!$oldest) {
            return null;
        }

        return Carbon::parse($oldest->created_at)->addHours(24);
    }
}
