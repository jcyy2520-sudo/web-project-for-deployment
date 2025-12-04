<?php

namespace App\Http\Controllers;

use App\Models\AppointmentSettings;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppointmentSettingsController extends Controller
{
    use SafeExperimentalFeature;
    /**
     * Get current appointment settings
     * GET /api/admin/appointment-settings
     */
    public function index()
    {
        return $this->wrapExperimental(function () {
            try {
                $settings = AppointmentSettings::getCurrent();

                return response()->json([
                    'success' => true,
                    'data' => $settings,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving appointment settings: ' . $e->getMessage(),
                ], 500);
            }
        }, 'appointment_settings.index');
    }

    /**
     * Update appointment settings
     * PUT /api/admin/appointment-settings
     */
    public function update(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'daily_booking_limit_per_user' => 'required|integer|min:1|max:50',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:500',
            ]);

            try {
                $settings = AppointmentSettings::getCurrent();

                $oldLimit = $settings->daily_booking_limit_per_user;
                
                $settings->update([
                    'daily_booking_limit_per_user' => $request->daily_booking_limit_per_user,
                    'is_active' => $request->boolean('is_active', true),
                    'description' => $request->description,
                    'last_updated_by' => auth()->id(),
                ]);

                // Clear cache to force refresh
                Cache::forget('appointment_settings');
                Cache::forget('analytics_dashboard_comprehensive');

                // Broadcast change to all clients via a notification or event
                // This will trigger an update on the user-facing booking system
                broadcast(new \App\Events\AppointmentSettingsUpdated(
                    $settings,
                    $oldLimit,
                    $request->daily_booking_limit_per_user
                ))->toOthers();

                return response()->json([
                    'success' => true,
                    'message' => 'Appointment settings updated successfully',
                    'data' => $settings,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating appointment settings: ' . $e->getMessage(),
                ], 500);
            }
        }, 'appointment_settings.update');
    }

    /**
     * Get current limit for a specific user
     * GET /api/appointment-settings/user-limit/{userId}/{date}
     */
    public function getUserLimit($userId, $date = null)
    {
        return $this->wrapExperimental(function () use ($userId, $date) {
            try {
                $date = $date ?? now()->format('Y-m-d');
                $settings = AppointmentSettings::getCurrent();

                if (!$settings || !$settings->is_active) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'limit' => null,
                            'used' => 0,
                            'remaining' => null,
                            'has_reached_limit' => false,
                            'bookings_today' => [],
                        ],
                    ]);
                }

                $bookingsToday = AppointmentSettings::getUserBookingsForDate($userId, $date);
                $remaining = AppointmentSettings::getRemainingBookingsForUser($userId, $date);
                $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($userId, $date);

                // Calculate when the user can book again
                $nextAvailableTime = null;
                if ($hasReachedLimit) {
                    $nextAvailableTime = $this->getNextAvailableBookingTime($userId, $date);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'limit' => $settings->daily_booking_limit_per_user,
                        'used' => $bookingsToday->count(),
                        'remaining' => $remaining,
                        'has_reached_limit' => $hasReachedLimit,
                        'bookings_today' => $bookingsToday->map(function ($appointment) {
                            return [
                                'id' => $appointment->id,
                                'time' => $appointment->appointment_time,
                                'status' => $appointment->status,
                                'service' => $appointment->service ? $appointment->service->name : 'N/A',
                            ];
                        }),
                        'date' => $date,
                        'next_available_time' => $nextAvailableTime,
                        'message' => $hasReachedLimit 
                            ? ($nextAvailableTime 
                                ? "You have reached your daily booking limit of {$settings->daily_booking_limit_per_user} appointments for today. You can book again $nextAvailableTime."
                                : "You have reached your daily booking limit of {$settings->daily_booking_limit_per_user} appointments for today. You can book again tomorrow.")
                            : null,
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving user limit: ' . $e->getMessage(),
                ], 500);
            }
        }, 'appointment_settings.user_limit');
    }

    /**
     * Get the next available time for a user to book
     * Returns time in 12-hour format (e.g., "tomorrow (Dec 04)" instead of military time)
     */
    private function getNextAvailableBookingTime($userId, $currentDate)
    {
        $currentDateObj = \Carbon\Carbon::createFromFormat('Y-m-d', $currentDate);
        $today = now()->format('Y-m-d');

        // If the current date is today, user can book tomorrow
        if ($currentDate === $today) {
            // Return "tomorrow" with the date in readable format
            $tomorrow = $currentDateObj->addDay()->format('M d');
            return "tomorrow ({$tomorrow})";
        }

        // If the current date is in the future, user can book the next day after that date
        if ($currentDateObj > \Carbon\Carbon::now()) {
            $nextAvailable = $currentDateObj->addDay()->format('M d');
            return "on {$nextAvailable}";
        }

        // Default case
        return "tomorrow";
    }

    /**
     * Check if user can book another appointment today
     * GET /api/appointment-settings/can-book/{userId}
     */
    public function canUserBook($userId)
    {
        return $this->wrapExperimental(function () use ($userId) {
            try {
                $today = now()->format('Y-m-d');
                $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($userId, $today);
                $remaining = AppointmentSettings::getRemainingBookingsForUser($userId, $today);
                $settings = AppointmentSettings::getCurrent();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'can_book' => !$hasReachedLimit,
                        'remaining' => $remaining,
                        'limit' => $settings ? $settings->daily_booking_limit_per_user : null,
                        'message' => $hasReachedLimit 
                            ? "You have reached your daily booking limit. You can book another appointment tomorrow."
                            : ($remaining === 1 ? "You have 1 appointment slot remaining today." : "You have $remaining appointment slots remaining today."),
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error checking booking availability: ' . $e->getMessage(),
                ], 500);
            }
        }, 'appointment_settings.can_book');
    }

    /**
     * Get history of settings changes
     * GET /api/admin/appointment-settings/history
     */
    public function getHistory()
    {
        return $this->wrapExperimental(function () {
            try {
                $history = AppointmentSettings::with('updatedBy')
                    ->orderBy('updated_at', 'desc')
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => $history,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving settings history: ' . $e->getMessage(),
                ], 500);
            }
        }, 'appointment_settings.history');
    }
}
