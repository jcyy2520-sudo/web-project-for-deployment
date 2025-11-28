<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CalendarEvent;
use App\Models\UnavailableDate;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentStatusMail;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        // Create cache key based on user and query parameters
        $cacheKey = 'appointments_' . $request->user()->id . '_' . md5(json_encode($request->all()));
        $cacheDuration = 30; // Cache for 30 seconds
        
        // Only cache non-filtered requests
        $useCache = !$request->has('status') && !$request->has('date');
        
        $result = $useCache 
            ? Cache::remember($cacheKey, $cacheDuration, function () use ($request) {
                return $this->fetchAppointments($request);
            })
            : $this->fetchAppointments($request);

        return response()->json($result);
    }

    // Helper method to fetch appointments
    private function fetchAppointments($request)
    {
        $query = Appointment::with(['user:id,email,first_name,last_name', 'staff:id,email,first_name,last_name'])
            ->select([
                'id', 'user_id', 'staff_id', 'type', 'service_id', 'service_type',
                'appointment_date', 'appointment_time', 'purpose', 'status',
                'notes', 'created_at', 'updated_at'
            ]); // OPTIMIZATION: Only select needed columns

        if ($request->user()->isClient()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->user()->isStaff()) {
            $query->where('staff_id', $request->user()->id)
                  ->orWhereNull('staff_id');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->where('appointment_date', $request->date);
        }

        // Check for include parameter for user data
        if ($request->has('include') && $request->include === 'user') {
            $query->with('user');
        }

        // Check for limit parameter
        if ($request->has('limit')) {
            $appointments = $query->orderBy('appointment_date', 'desc')
                                 ->orderBy('appointment_time', 'desc')
                                 ->limit($request->limit)
                                 ->get();
            
            return [
                'data' => $appointments,
                'success' => true
            ];
        }

        $appointments = $query->orderBy('appointment_date', 'desc')
                             ->orderBy('appointment_time', 'desc')
                             ->paginate($request->get('per_page', 10));

        return $appointments;
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(Appointment::getTypes())),
            'service_id' => 'nullable|exists:services,id',
            'service_type' => 'nullable|string|max:255',
            'appointment_date' => 'required|date|after:today',
            'appointment_time' => 'required|date_format:H:i',
            'purpose' => 'nullable|string|max:500',
            'documents' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        // FIXED: Check for unavailable dates/times first - Use UnavailableDate model
        $isUnavailable = UnavailableDate::where('date', $request->appointment_date)
            ->where(function($query) use ($request) {
                // Check all-day unavailability
                $query->where('all_day', true)
                // Or check time-specific unavailability that overlaps with requested time
                ->orWhere(function($q) use ($request) {
                    $q->where('all_day', false)
                      ->where('start_time', '<=', $request->appointment_time)
                      ->where('end_time', '>=', $request->appointment_time);
                });
            })
            ->exists();

        if ($isUnavailable) {
            return response()->json([
                'message' => 'Selected date and time is not available for booking'
            ], 422);
        }

        // Check for existing appointment at same time
        $existingAppointment = Appointment::where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingAppointment) {
            return response()->json([
                'message' => 'Time slot already booked'
            ], 422);
        }

        $appointment = Appointment::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'service_id' => $request->service_id,
            'service_type' => $request->service_type,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'purpose' => $request->purpose ?? null,
            'documents' => $request->documents,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // Send confirmation email
        try {
            Mail::to($request->user()->email)->send(new AppointmentConfirmationMail($appointment));
        } catch (\Exception $e) {
            \Log::error('Failed to send confirmation email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Appointment booked successfully',
            'appointment' => $appointment->load('user'),
            'success' => true
        ]);
    }

    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);
        return response()->json([
            'data' => $appointment->load(['user', 'staff']),
            'success' => true
        ]);
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $request->validate([
            'status' => 'required|in:approved,completed,cancelled,declined',
            'staff_notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $appointment->status;
        
        // If approving, assign current user as staff if not already assigned
        if ($request->status === 'approved' && !$appointment->staff_id) {
            $appointment->staff_id = $request->user()->id;
        }

        $appointment->update([
            'status' => $request->status,
            'staff_notes' => $request->staff_notes,
            'staff_id' => $appointment->staff_id
        ]);

        // Send status update email to client
        if ($oldStatus !== $request->status) {
            try {
                Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                
                // Also send to staff if assigned
                if ($appointment->staff_id && $appointment->staff->email) {
                    Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send status email: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'data' => $appointment->load(['user', 'staff']),
            'success' => true
        ]);
    }

    public function approve(Appointment $appointment)
    {
        try {
            $this->authorize('update', $appointment);

            $oldStatus = $appointment->status;
            
            // Assign current user as staff if not already assigned
            if (!$appointment->staff_id) {
                $appointment->staff_id = request()->user()->id;
            }

            $appointment->update([
                'status' => 'approved',
                'staff_id' => $appointment->staff_id
            ]);

            // PERFORMANCE OPTIMIZATION: Clear cached data when appointment status changes
            Cache::forget('admin_stats_*');
            Cache::forget('admin_appointments_*');
            Cache::forget('appointments_*');

            // Log the action
            $serviceType = $appointment->service_type ?? $appointment->type;
            \App\Models\ActionLog::log(
                'approve',
                "Approved appointment for {$appointment->user->first_name} {$appointment->user->last_name} - {$serviceType} on {$appointment->appointment_date} at {$appointment->appointment_time}",
                'Appointment',
                $appointment->id
            );

            // Send status update email to client
            if ($oldStatus !== 'approved') {
                try {
                    // Reload appointment to get fresh relationships
                    $appointment->refresh();
                    $appointment->load(['user', 'staff']);
                    
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                    
                    // Also send to staff if assigned and staff exists
                    if ($appointment->staff_id && $appointment->staff) {
                        Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                    }
                    
                    // Save message to database for user visibility
                    $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                    $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
                    $serviceType = $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type;
                    
                    $messageText = "✓ Your appointment has been APPROVED!\n\n" .
                        "📅 Date: " . $appointmentDate . "\n" .
                        "⏰ Time: " . $appointmentTime . "\n" .
                        "📋 Service: " . $serviceType . "\n\n" .
                        "Please arrive on time for your appointment. If you need to reschedule, please contact us.";
                    
                    Message::create([
                        'sender_id' => $appointment->staff_id,
                        'receiver_id' => $appointment->user_id,
                        'message' => $messageText,
                        'read' => false
                    ]);
                    
                } catch (\Exception $e) {
                    \Log::error('Failed to send approval email or create message: ' . $e->getMessage());
                    \Log::error('Exception trace: ' . $e->getTraceAsString());
                    // Still return success even if email fails
                }
            }

            return response()->json([
                'message' => 'Appointment approved successfully',
                'data' => $appointment->load(['user', 'staff']),
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Approve method error: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error approving appointment: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function decline(Appointment $appointment)
    {
        try {
            $this->authorize('update', $appointment);

            $oldStatus = $appointment->status;
            $declineReason = request()->input('decline_reason', '');

            $appointment->update([
                'status' => 'declined',
                'decline_reason' => $declineReason ?: null
            ]);

            // PERFORMANCE OPTIMIZATION: Clear cached data when appointment status changes
            Cache::forget('admin_stats_*');
            Cache::forget('admin_appointments_*');
            Cache::forget('appointments_*');

            // Log action
            try {
                $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
                $reasonText = $declineReason ? " - Reason: {$declineReason}" : '';
                \App\Models\ActionLog::log(
                    'decline_appointment',
                    "Declined appointment for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType}){$reasonText}",
                    'Appointment',
                    $appointment->id
                );
            } catch (\Exception $e) {
                \Log::error('Failed to log appointment decline: ' . $e->getMessage());
            }

            // Send status update email to client
            if ($oldStatus !== 'declined') {
                try {
                    // Reload appointment to get fresh relationships
                    $appointment->refresh();
                    $appointment->load(['user', 'staff']);
                    
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                    
                    // Save message to database for user visibility
                    $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                    $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
                    $serviceType = $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type;
                    
                    $messageText = "✕ Your appointment has been DECLINED.\n\n";
                    $messageText .= "📅 Date: " . $appointmentDate . "\n";
                    $messageText .= "⏰ Time: " . $appointmentTime . "\n";
                    $messageText .= "📋 Service: " . $serviceType . "\n";
                    
                    if ($declineReason) {
                        $messageText .= "\n❌ Reason: " . $declineReason . "\n";
                    }
                    
                    $messageText .= "\nPlease contact our support team if you have any questions or would like to discuss alternative options.";
                    
                    $declineAdmin = request()->user();
                    Message::create([
                        'sender_id' => $declineAdmin->id,
                        'receiver_id' => $appointment->user_id,
                        'message' => $messageText,
                        'read' => false
                    ]);
                    
                } catch (\Exception $e) {
                    \Log::error('Failed to send decline email or create message: ' . $e->getMessage());
                    \Log::error('Exception trace: ' . $e->getTraceAsString());
                    // Still return success even if email fails
                }
            }

            return response()->json([
                'message' => 'Appointment declined successfully',
                'data' => $appointment->load(['user', 'staff']),
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Decline method error: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error declining appointment: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function complete(Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $oldStatus = $appointment->status;

        $appointment->update([
            'status' => 'completed'
        ]);

        // Log action
        try {
            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            \App\Models\ActionLog::log(
                'complete_appointment',
                "Marked appointment as completed for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType})",
                'Appointment',
                $appointment->id
            );
        } catch (\Exception $e) {
            \Log::error('Failed to log appointment completion: ' . $e->getMessage());
        }

        // Send status update email to client
        if ($oldStatus !== 'completed') {
            try {
                // Reload appointment to get fresh relationships
                $appointment->refresh();
                $appointment->load(['user', 'staff']);
                
                Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                
                // Also send to staff if assigned and staff exists
                if ($appointment->staff_id && $appointment->staff) {
                    Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send completion email: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Appointment marked as completed',
            'data' => $appointment->load(['user', 'staff']),
            'success' => true
        ]);
    }

    public function assignStaff(Request $request, Appointment $appointment)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id'
        ]);

        $staff = User::findOrFail($request->staff_id);
        if (!$staff->isStaff()) {
            return response()->json([
                'message' => 'Selected user is not a staff member'
            ], 422);
        }

        $appointment->update(['staff_id' => $request->staff_id]);

        return response()->json([
            'message' => 'Staff assigned successfully',
            'data' => $appointment->load(['user', 'staff']),
            'success' => true
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);
        
        // Soft delete (archive) instead of permanent delete
        $appointment->delete();

        return response()->json([
            'message' => 'Appointment archived successfully',
            'success' => true
        ]);
    }

    public function getArchived(Request $request)
    {
        $query = Appointment::onlyTrashed()->with(['user', 'staff']);

        // Only admins can view all archived appointments
        if (!$request->user()->isAdmin()) {
            if ($request->user()->isClient()) {
                $query->where('user_id', $request->user()->id);
            } elseif ($request->user()->isStaff()) {
                $query->where('staff_id', $request->user()->id);
            }
        }

        $appointments = $query->orderBy('deleted_at', 'desc')
                             ->paginate($request->get('per_page', 10));

        return response()->json($appointments);
    }

    public function restore(Request $request, $id)
    {
        $appointment = Appointment::withTrashed()->findOrFail($id);
        
        $this->authorize('update', $appointment);
        
        $appointment->restore();

        return response()->json([
            'message' => 'Appointment restored successfully',
            'data' => $appointment->load(['user', 'staff']),
            'success' => true
        ]);
    }

    public function permanentDelete(Request $request, $id)
    {
        $appointment = Appointment::withTrashed()->findOrFail($id);
        
        $this->authorize('delete', $appointment);
        
        // Only admins can permanently delete
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only admins can permanently delete appointments',
                'success' => false
            ], 403);
        }
        
        $appointment->forceDelete();

        return response()->json([
            'message' => 'Appointment permanently deleted',
            'success' => true
        ]);
    }

    public function getTodayAppointments(Request $request)
    {
        $query = Appointment::with(['user', 'staff'])
            ->where('appointment_date', today());

        if ($request->user()->isStaff()) {
            $query->where('staff_id', $request->user()->id)
                  ->orWhereNull('staff_id');
        }

        $appointments = $query->orderBy('appointment_time')->get();

        return response()->json([
            'data' => $appointments,
            'success' => true
        ]);
    }

    public function getStats(Request $request)
    {
        // PERFORMANCE OPTIMIZATION: Use single query with conditional aggregation
        // Replaces 6 separate database queries with 1 efficient query
        
        $query = DB::table('appointments');

        if ($request->user()->isClient()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->user()->isStaff()) {
            $query->where('staff_id', $request->user()->id);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = "declined" THEN 1 ELSE 0 END) as declined
        ')
        ->first();

        return response()->json([
            'total' => (int)($stats->total ?? 0),
            'pending' => (int)($stats->pending ?? 0),
            'approved' => (int)($stats->approved ?? 0),
            'completed' => (int)($stats->completed ?? 0),
            'cancelled' => (int)($stats->cancelled ?? 0),
            'declined' => (int)($stats->declined ?? 0),
        ]);
    }

    // NEW METHODS FOR USER DASHBOARD

    public function userAppointments(Request $request)
    {
        $user = $request->user();
        
        $appointments = $user->appointments()
            ->with('staff')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        return response()->json([
            'data' => $appointments,
            'success' => true
        ]);
    }

    public function staffAppointments(Request $request)
    {
        $user = $request->user();
        
        $appointments = $user->staffAppointments()
            ->with('user')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        return response()->json([
            'data' => $appointments,
            'success' => true
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $appointment = $user->appointments()->findOrFail($id);

        if (!in_array($appointment->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Only pending or approved appointments can be cancelled',
                'success' => false
            ], 422);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'cancelled']);

        // Clear relevant caches to ensure admin dashboard updates
        // Clear admin stats cache
        if (auth()->id()) {
            Cache::forget('admin_stats_' . auth()->id());
        }
        
        // Clear all admin appointment caches by flushing with a prefix
        // Use a pattern that matches common cache keys
        $cacheTagsToFlush = ['admin', 'appointments', 'admin_appointments', 'admin_stats'];
        foreach ($cacheTagsToFlush as $tag) {
            try {
                Cache::tags($tag)->flush();
            } catch (\Exception $e) {
                // If tags not supported, try direct key deletion
                \Log::debug('Cache tags not supported for: ' . $tag);
            }
        }

        // Send cancellation email
        try {
            // Reload appointment to get fresh relationships
            $appointment->refresh();
            $appointment->load(['user', 'staff']);
            
            Mail::to($user->email)->send(new AppointmentStatusMail($appointment));
            
            // Also send to staff if assigned
            if ($appointment->staff_id && $appointment->staff) {
                Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation email: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $appointment,
            'message' => 'Appointment cancelled successfully',
            'success' => true
        ]);
    }

    public function availableSlots(Request $request, $date)
    {
        $workingHours = [
            'start' => '09:00',
            'end' => '17:00',
        ];

        // Check UnavailableDate for unavailability
        $unavailableRecords = UnavailableDate::where('date', $date)->get();

        $bookedSlots = Appointment::where('appointment_date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('appointment_time')
            ->toArray();

        $availableSlots = [];
        $currentTime = strtotime($workingHours['start']);
        $endTime = strtotime($workingHours['end']);

        while ($currentTime <= $endTime) {
            $timeSlot = date('H:i', $currentTime);
            
            // Check if slot is not booked
            $isAvailable = !in_array($timeSlot, $bookedSlots);
            
            // Check unavailable records for unavailability
            if ($isAvailable && $unavailableRecords->isNotEmpty()) {
                foreach ($unavailableRecords as $record) {
                    $isUnavailable = false;
                    
                    if ($record->all_day) {
                        $isUnavailable = true;
                    } else if ($record->start_time && $record->end_time) {
                        $slotTime = strtotime($timeSlot);
                        $startTime = strtotime($record->start_time);
                        $endUnavailable = strtotime($record->end_time);
                        
                        if ($slotTime >= $startTime && $slotTime <= $endUnavailable) {
                            $isUnavailable = true;
                        }
                    }
                    
                    if ($isUnavailable) {
                        $isAvailable = false;
                        break;
                    }
                }
            }

            if ($isAvailable) {
                $availableSlots[] = [
                    'time' => $timeSlot,
                    'display' => date('g:i A', $currentTime)
                ];
            }

            $currentTime = strtotime('+30 minutes', $currentTime);
        }

        return response()->json([
            'data' => $availableSlots,
            'success' => true
        ]);
    }

    public function getTypes()
    {
        return response()->json([
            'data' => Appointment::getTypes(),
            'success' => true
        ]);
    }

    public function stats()
    {
        // PERFORMANCE OPTIMIZATION: Use selectRaw with GROUP BY to get all counts in single query
        // Instead of 6 separate database queries, we now use 1 efficient query
        $stats = DB::table('appointments')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = "declined" THEN 1 ELSE 0 END) as declined
            ')
            ->first();

        return response()->json([
            'total' => (int)$stats->total,
            'pending' => (int)$stats->pending,
            'approved' => (int)$stats->approved,
            'completed' => (int)$stats->completed,
            'cancelled' => (int)$stats->cancelled,
            'declined' => (int)$stats->declined,
        ]);
    }
}