<?php

namespace App\Http\Controllers;

use App\Models\UnavailableDate;
use App\Models\BlackoutDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\UnavailableDatesUpdated;
use App\Models\ActionLog;

class UnavailableDateController extends Controller
{
    public function index()
    {
        try {
            Log::info('Fetching unavailable dates (both legacy and blackout)');
            
            // Get legacy unavailable dates
            $legacyDates = UnavailableDate::orderBy('date', 'desc')->get();
            
            // Get new blackout dates
            $blackoutDates = BlackoutDate::orderBy('date', 'desc')->get();
            
            Log::info('Found ' . $legacyDates->count() . ' legacy unavailable dates and ' . $blackoutDates->count() . ' blackout dates');
            
            // Merge both collections
            $allUnavailableDates = $legacyDates->concat($blackoutDates);
            
            return response()->json([
                'data' => $allUnavailableDates,
                'legacy_count' => $legacyDates->count(),
                'blackout_count' => $blackoutDates->count(),
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch unavailable dates: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch unavailable dates',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Creating unavailable date', $request->all());

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string|max:255',
            'all_day' => 'boolean',
            'start_time' => 'required_if:all_day,false|nullable|date_format:H:i',
            'end_time' => 'required_if:all_day,false|nullable|date_format:H:i|after:start_time',
        ]);

        try {
            // Check if date already exists
            $existingDate = UnavailableDate::where('date', $request->date)->first();
            if ($existingDate) {
                Log::warning('Date already exists: ' . $request->date);
                return response()->json([
                    'message' => 'This date is already marked as unavailable',
                    'success' => false
                ], 409);
            }

            Log::info('Creating new unavailable date');
            $unavailableDate = UnavailableDate::create([
                'date' => $request->date,
                'reason' => $request->reason,
                'all_day' => $request->all_day ?? true,
                'start_time' => $request->all_day ? null : $request->start_time,
                'end_time' => $request->all_day ? null : $request->end_time,
                // REMOVED: 'created_by' => Auth::id(),
            ]);

            Log::info('Unavailable date created successfully with ID: ' . $unavailableDate->id);
            // Update last-update cache so clients can poll for changes
            try {
                Cache::put('unavailable_dates_last_update', now()->toDateTimeString());
                // Clear any cached lists
                Cache::forget('unavailable_dates');
                event(new UnavailableDatesUpdated());
            } catch (\Exception $e) {
                Log::error('Failed to set unavailable dates cache or broadcast: ' . $e->getMessage());
            }

            ActionLog::log('create', "Added unavailable date: {$unavailableDate->date} - {$unavailableDate->reason}", 'UnavailableDate', $unavailableDate->id);

            return response()->json([
                'data' => $unavailableDate,
                'message' => 'Unavailable date added successfully',
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create unavailable date: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to create unavailable date',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            Log::info('Deleting unavailable date with ID: ' . $id);
            $date = UnavailableDate::findOrFail($id);
            $date->delete();

            // Clear caches and broadcast change
            try {
                Cache::put('unavailable_dates_last_update', now()->toDateTimeString());
                Cache::forget('unavailable_dates');
                event(new UnavailableDatesUpdated());
            } catch (\Exception $e) {
                Log::error('Failed to set unavailable dates cache or broadcast on delete: ' . $e->getMessage());
            }

            Log::info('Unavailable date deleted successfully');

            ActionLog::log('delete', "Deleted unavailable date (ID: {$id})", 'UnavailableDate', $id);

            return response()->json([
                'message' => 'Unavailable date deleted successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete unavailable date: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete unavailable date',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Return last update timestamp for unavailable dates so clients can poll
     */
    public function lastUpdate()
    {
        try {
            $ts = Cache::get('unavailable_dates_last_update');
            return response()->json([
                'success' => true,
                'last_update' => $ts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Get appointments that would be affected by marking a date as unavailable
     * POST /api/admin/unavailable-dates/affected
     */
    public function getAffectedAppointments(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'all_day' => 'boolean',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
            ]);

            $date = $request->input('date');
            $allDay = $request->boolean('all_day', true);
            $startTime = $request->input('start_time');
            $endTime = $request->input('end_time');

            Log::info('Checking affected appointments for date: ' . $date, [
                'all_day' => $allDay,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]);

            // Build query to find appointments on this date
            $query = \App\Models\Appointment::with(['user', 'staff', 'service'])
                ->where('appointment_date', $date)
                ->whereNotIn('status', ['cancelled', 'completed', 'archived']);

            // If not all day, filter by time range
            if (!$allDay && $startTime && $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    // Check if appointment time falls within the unavailable range
                    $q->whereBetween('appointment_time', [$startTime, $endTime])
                      ->orWhere(function ($q2) use ($startTime, $endTime) {
                          // Also check start_time field if it exists
                          $q2->whereNotNull('start_time')
                             ->whereBetween('start_time', [$startTime, $endTime]);
                      });
                });
            }

            $affectedAppointments = $query->orderBy('appointment_time', 'asc')->get();

            Log::info('Found ' . $affectedAppointments->count() . ' affected appointments');

            return response()->json([
                'success' => true,
                'data' => $affectedAppointments,
                'count' => $affectedAppointments->count(),
                'date' => $date
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fetching affected appointments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch affected appointments',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }
}