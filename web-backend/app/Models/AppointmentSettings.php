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
        return self::where('is_active', true)->latest('updated_at')->first()
            ?? self::latest('updated_at')->first()
            ?? self::create([
                'daily_booking_limit_per_user' => 3,
                'is_active' => true,
                'description' => 'Default appointment settings',
            ]);
    }


    /**
     * Check if a user has reached their booking limit for the resolved appointment date.
     */
    public static function userHasReachedDailyLimit($userId, $date = null)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return false;
        }

        $count = self::getBookingsForLimitDate($userId, $date)->count();

        return $count >= $settings->daily_booking_limit_per_user;
    }

    /**
    * Get remaining bookings for user on the resolved appointment date.
     */
    public static function getRemainingBookingsForUser($userId, $date = null)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return null;
        }

        $count = self::getBookingsForLimitDate($userId, $date)->count();
        $remaining = $settings->daily_booking_limit_per_user - $count;

        return max(0, $remaining);
    }

    /**
     * Get all bookings for the resolved appointment date.
     */
    public static function getUserBookingsForDate($userId, $date = null)
    {
        return self::getBookingsForLimitDate($userId, $date);
    }

    /**
     * Clear the request-level cache (legacy - now unused as static cache is removed)
     */
    public static function clearRequestCache($userId)
    {
        // No-op: static cache removed to prevent staleness in persistent environments (Octane)
    }

    /**
     * Core query: get all bookings for the resolved appointment date.
     * Cancellations and other later status changes do not restore the user's limit for that date.
     */
    private static function getBookingsForLimitDate($userId, $date = null)
    {
        $resolvedDate = self::resolveLimitDate($userId, $date);

        return Appointment::where('user_id', $userId)
            ->whereDate('appointment_date', $resolvedDate->toDateString())
            ->select('id', 'appointment_date', 'appointment_time', 'status', 'service_id', 'created_at')
            ->with('service')
            ->orderBy('appointment_time', 'asc')
            ->get();
    }

    /**
     * Calculate the next business-day start for the resolved appointment date.
     */
    public static function getNextAvailableTime($userId, $date = null)
    {
        $settings = self::getCurrent();

        if (!$settings || !$settings->is_active) {
            return null;
        }

        $bookings = self::getBookingsForLimitDate($userId, $date);

        if ($bookings->count() < $settings->daily_booking_limit_per_user) {
            return null;
        }

        $nextDate = self::resolveLimitDate($userId, $date)->copy()->addDay();

        while ($nextDate->isWeekend()) {
            $nextDate->addDay();
        }

        return $nextDate->setTime(8, 0);
    }

    private static function resolveLimitDate($userId, $date = null)
    {
        if ($date) {
            return Carbon::parse($date)->startOfDay();
        }

        $latestAppointmentDate = Appointment::where('user_id', $userId)
            ->max('appointment_date');

        if ($latestAppointmentDate) {
            return Carbon::parse($latestAppointmentDate)->startOfDay();
        }

        return Carbon::today();
    }
}
