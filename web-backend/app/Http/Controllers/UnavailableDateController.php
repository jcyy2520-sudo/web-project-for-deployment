<?php

namespace App\Http\Controllers;

use App\Models\UnavailableDate;
use App\Models\BlackoutDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\UnavailableDatesUpdated;

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
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Creating unavailable date', $request->all());

        $request->validate([
            'date' => 'required|date|after:today',
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
                'error' => $e->getMessage(),
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
            return response()->json([
                'message' => 'Unavailable date deleted successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete unavailable date: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete unavailable date',
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage()
            ], 500);
        }
    }
}