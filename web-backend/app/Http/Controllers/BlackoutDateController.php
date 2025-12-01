<?php

namespace App\Http\Controllers;

use App\Models\BlackoutDate;
use App\Models\UnavailableDate;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BlackoutDateController extends Controller
{
    /**
     * Get all blackout dates (including legacy UnavailableDate entries)
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

        // Also get legacy UnavailableDate entries and convert to BlackoutDate format
        $unavailableDates = UnavailableDate::query();
        
        if ($request->has('reason')) {
            $unavailableDates->where('reason', 'like', "%{$request->reason}%");
        }

        $unavailableDates = $unavailableDates->orderBy('date')->get()->map(function ($unavailable) {
            return [
                'id' => $unavailable->id,
                'date' => $unavailable->date,
                'reason' => $unavailable->reason,
                'start_time' => $unavailable->start_time,
                'end_time' => $unavailable->end_time,
                'is_recurring' => false,
                'recurring_days' => null,
                'is_legacy' => true,
                'created_at' => $unavailable->created_at,
                'updated_at' => $unavailable->updated_at,
            ];
        });

        // Merge both collections
        $allDates = $blackoutDates->concat($unavailableDates)->sortBy('date')->values();

        return response()->json([
            'success' => true,
            'data' => $allDates,
            'total' => count($allDates),
            'info' => 'Includes both new blackout dates and legacy unavailable dates'
        ]);
    }

    /**
     * Create a new blackout date
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required_if:is_recurring,false|date',
            'reason' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'recurring_days' => 'nullable|array|required_if:is_recurring,true',
            'recurring_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        try {
            $blackoutDate = BlackoutDate::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Blackout date created successfully',
                'data' => $blackoutDate
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating blackout date: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a blackout date
     */
    public function update(Request $request, BlackoutDate $blackoutDate)
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'recurring_days' => 'nullable|array',
            'recurring_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        try {
            $blackoutDate->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Blackout date updated successfully',
                'data' => $blackoutDate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating blackout date: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a blackout date
     */
    public function destroy(BlackoutDate $blackoutDate)
    {
        try {
            $blackoutDate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blackout date deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting blackout date: ' . $e->getMessage()
            ], 500);
        }
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
}
