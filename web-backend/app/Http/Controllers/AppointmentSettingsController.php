<?php

namespace App\Http\Controllers;

use App\Models\AppointmentSettings;
use App\Events\AppointmentSettingsChanged;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\ActionLog;

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
                    'message' => config('app.debug') ? 'Error retrieving appointment settings: ' . $e->getMessage() : 'Error retrieving appointment settings',
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

                // Broadcast change to all clients (non-blocking)
                try {
                    broadcast(new AppointmentSettingsChanged($settings, 'updated'));
                } catch (\Exception $e) {
                    \Log::debug('Failed to broadcast appointment settings change: ' . $e->getMessage());
                }

                ActionLog::log('update', "Updated appointment settings: daily limit {$oldLimit} → {$request->daily_booking_limit_per_user}", 'AppointmentSettings', $settings->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Appointment settings updated successfully',
                    'data' => $settings,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error updating appointment settings: ' . $e->getMessage() : 'Error updating appointment settings',
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

                $bookings = AppointmentSettings::getUserBookingsForDate($userId);
                $remaining = AppointmentSettings::getRemainingBookingsForUser($userId);
                $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($userId);

                // Calculate when the user can book again (exact datetime)
                $nextAvailableTime = null;
                $nextAvailableFormatted = null;
                if ($hasReachedLimit) {
                    $nextAvailable = AppointmentSettings::getNextAvailableTime($userId);
                    if ($nextAvailable) {
                        $nextAvailableTime = $nextAvailable->toIso8601String();
                        $nextAvailableFormatted = $nextAvailable->format('M d, Y \a\t g:i A');
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'limit' => $settings->daily_booking_limit_per_user,
                        'used' => $bookings->count(),
                        'remaining' => $remaining,
                        'has_reached_limit' => $hasReachedLimit,
                        'bookings_today' => $bookings->map(function ($appointment) {
                            return [
                                'id' => $appointment->id,
                                'time' => $appointment->appointment_time,
                                'status' => $appointment->status,
                                'service' => $appointment->service ? $appointment->service->name : 'N/A',
                                'booked_at' => $appointment->created_at?->toIso8601String(),
                            ];
                        }),
                        'next_available_time' => $nextAvailableTime,
                        'next_available_formatted' => $nextAvailableFormatted,
                        'message' => $hasReachedLimit 
                            ? "You have reached your booking limit of {$settings->daily_booking_limit_per_user} appointments per 24 hours." 
                              . ($nextAvailableFormatted ? " You can book again on {$nextAvailableFormatted}." : '')
                            : null,
                    ],
                ]);
            } catch (\Exception $e) {
                \Log::error('[getUserLimit] Exception', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error retrieving user limit: ' . $e->getMessage() : 'Error retrieving user limit',
                ], 500);
            }
        }, 'appointment_settings.user_limit');
    }

    /**
     * Check if user can book another appointment
     * GET /api/appointment-settings/can-book/{userId}
     */
    public function canUserBook($userId)
    {
        return $this->wrapExperimental(function () use ($userId) {
            try {
                $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($userId);
                $remaining = AppointmentSettings::getRemainingBookingsForUser($userId);
                $settings = AppointmentSettings::getCurrent();

                $nextAvailableFormatted = null;
                if ($hasReachedLimit) {
                    $nextAvailable = AppointmentSettings::getNextAvailableTime($userId);
                    if ($nextAvailable) {
                        $nextAvailableFormatted = $nextAvailable->format('M d, Y \a\t g:i A');
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'can_book' => !$hasReachedLimit,
                        'remaining' => $remaining,
                        'limit' => $settings ? $settings->daily_booking_limit_per_user : null,
                        'message' => $hasReachedLimit 
                            ? "You have reached your booking limit." . ($nextAvailableFormatted ? " You can book again on {$nextAvailableFormatted}." : '')
                            : ($remaining === 1 ? "You have 1 appointment slot remaining." : "You have $remaining appointment slots remaining."),
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error checking booking availability: ' . $e->getMessage() : 'Error checking booking availability',
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
                    'message' => config('app.debug') ? 'Error retrieving settings history: ' . $e->getMessage() : 'Error retrieving settings history',
                ], 500);
            }
        }, 'appointment_settings.history');
    }
}
