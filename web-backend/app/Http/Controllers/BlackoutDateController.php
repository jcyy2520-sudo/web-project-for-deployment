<?php

namespace App\Http\Controllers;

use App\Models\BlackoutDate;

use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ActionLog;

class BlackoutDateController extends Controller
{
    use SafeExperimentalFeature;
    /**
     * Get all blackout dates
     */
    public function index(Request $request)
    {
        // Get BlackoutDate entries
        $query = BlackoutDate::query();

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                $request->start_date,
                $request->end_date
            ])->orWhere('is_recurring', true);
        }

        if ($request->has('reason')) {
            $query->where('reason', 'like', "%{$request->reason}%");
        }

        $blackoutDates = $query->orderBy('date')->get();

        return response()->json([
            'success' => true,
            'data' => $blackoutDates,
            'total' => $blackoutDates->count(),
        ]);
    }

    /**
     * Create a new blackout date
     */
    public function store(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'date' => 'required_if:is_recurring,false|date',
                'reason' => 'required|string|max:255',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'is_recurring' => 'boolean',
                'recurring_days' => 'nullable|array|required_if:is_recurring,true',
                'recurring_days.*' => 'in:monday,tuesday,wednesday,thursday,friday',
            ]);

            try {
                $blackoutDate = BlackoutDate::create($request->all());

                // Clear caches and broadcast change so user-side updates
                $this->clearUnavailableDateCaches();

                ActionLog::log('create', "Created blackout date: {$blackoutDate->date} - {$blackoutDate->reason}", 'BlackoutDate', $blackoutDate->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Blackout date created successfully',
                    'data' => $blackoutDate
                ], 201);
            } catch (\Exception $e) {
                Log::error('Error creating blackout date: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error creating blackout date: ' . $e->getMessage() : 'Error creating blackout date'
                ], 500);
            }
        }, 'blackout_date.store');
    }

    /**
     * Update a blackout date
     */
    public function update(Request $request, BlackoutDate $blackoutDate)
    {
        return $this->wrapExperimental(function () use ($request, $blackoutDate) {
            $request->validate([
                'date' => 'required|date',
                'reason' => 'required|string|max:255',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'is_recurring' => 'boolean',
                'recurring_days' => 'nullable|array',
                'recurring_days.*' => 'in:monday,tuesday,wednesday,thursday,friday',
            ]);

            try {
                $blackoutDate->update($request->all());

                // Clear caches and broadcast change so user-side updates
                $this->clearUnavailableDateCaches();

                ActionLog::log('update', "Updated blackout date: {$blackoutDate->date} - {$blackoutDate->reason}", 'BlackoutDate', $blackoutDate->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Blackout date updated successfully',
                    'data' => $blackoutDate
                ]);
            } catch (\Exception $e) {
                Log::error('Error updating blackout date: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error updating blackout date: ' . $e->getMessage() : 'Error updating blackout date'
                ], 500);
            }
        }, 'blackout_date.update');
    }

    /**
     * Delete a blackout date
     */
    public function destroy(BlackoutDate $blackoutDate)
    {
        return $this->wrapExperimental(function () use ($blackoutDate) {
            try {
                $dateValue = $blackoutDate->date;
                $blackoutId = $blackoutDate->id;
                $blackoutDate->delete();

                // Clear caches and broadcast change so user-side updates
                $this->clearUnavailableDateCaches();

                ActionLog::log('delete', "Deleted blackout date: {$dateValue} (ID: {$blackoutId})", 'BlackoutDate', $blackoutId);

                return response()->json([
                    'success' => true,
                    'message' => 'Blackout date deleted successfully'
                ]);
            } catch (\Exception $e) {
                Log::error('Error deleting blackout date: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error deleting blackout date: ' . $e->getMessage() : 'Error deleting blackout date'
                ], 500);
            }
        }, 'blackout_date.destroy');
    }

    /**
     * Get unavailable dates for a date range (for clients to view)
     */
    public function getUnavailableDatesForClients(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $unavailableDates = [];

        // Add weekends
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayOfWeek = $currentDate->dayOfWeek;
            
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                $unavailableDates[] = [
                    'date' => $currentDate->toDateString(),
                    'reason' => $currentDate->dayName . ' - Closed',
                    'type' => 'weekend',
                    'day_name' => $currentDate->dayName,
                ];
            }
            
            $currentDate->addDay();
        }

        // Add blackout dates
        $blackoutDates = BlackoutDate::where(function ($query) use ($request) {
            $query->whereBetween('date', [
                $request->start_date,
                $request->end_date
            ])->orWhere('is_recurring', true);
        })->get();

        foreach ($blackoutDates as $blackout) {
            if ($blackout->is_recurring && $blackout->recurring_days) {
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    $dayName = strtolower($currentDate->englishDayOfWeek);
                    if (in_array($dayName, $blackout->recurring_days)) {
                        $key = $currentDate->toDateString();
                        
                        // Don't duplicate weekends
                        if (!in_array($key, array_column($unavailableDates, 'date'))) {
                            $unavailableDates[] = [
                                'date' => $key,
                                'reason' => $blackout->reason,
                                'type' => 'blackout',
                                'recurring' => true,
                                'time_range' => $blackout->start_time && $blackout->end_time 
                                    ? "{$blackout->start_time} - {$blackout->end_time}"
                                    : null,
                            ];
                        }
                    }
                    $currentDate->addDay();
                }
            } else if ($blackout->date) {
                $unavailableDates[] = [
                    'date' => $blackout->date->toDateString(),
                    'reason' => $blackout->reason,
                    'type' => 'blackout',
                    'time_range' => $blackout->start_time && $blackout->end_time 
                        ? "{$blackout->start_time} - {$blackout->end_time}"
                        : null,
                ];
            }
        }

        // Remove duplicates
        $unavailableDates = array_values(array_unique($unavailableDates, SORT_REGULAR));

        return response()->json([
            'success' => true,
            'unavailable_dates' => $unavailableDates,
            'total_unavailable' => count($unavailableDates),
            'total_days_in_range' => $startDate->diffInDays($endDate) + 1,
        ]);
    }

    /**
     * Clear unavailable date caches and broadcast change event
     * so user-side systems pick up the changes
     */
    private function clearUnavailableDateCaches()
    {
        try {
            Cache::put('unavailable_dates_last_update', now()->toDateTimeString());
            Cache::forget('unavailable_dates');
            Cache::forget('blackout_dates');

            // Broadcast event so user-side calendars update
            if (class_exists(\App\Events\UnavailableDatesUpdated::class)) {
                event(new \App\Events\UnavailableDatesUpdated());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear unavailable date caches: ' . $e->getMessage());
        }
    }
}
