<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Events\AppointmentUpdated;
use App\Models\CalendarEvent;

use App\Models\TimeSlotCapacity;
use App\Models\BlackoutDate;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentStatusMail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        \Log::info('[AppointmentController@index] Request received', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'user_role' => $request->user()->role,
            'isClient' => $request->user()->isClient()
        ]);
        
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

        \Log::info('[AppointmentController@index] Returning result', [
            'count' => count($result['data'] ?? []),
            'success' => $result['success'] ?? false
        ]);

        return response()->json($result);
    }

    // Helper method to fetch appointments
    private function fetchAppointments($request)
    {
        $query = Appointment::with([
            'user:id,email,first_name,last_name,phone', 
            'staff:id,email,first_name,last_name', 
            'service:id,name,price',
            'services:id,name,price',
            'cashier:id,first_name,last_name',
            'refunds'
        ])
            ->whereNull('archived_at')
            ->select([
                'id', 'user_id', 'staff_id', 'type', 'service_id', 'service_type',
                'appointment_date', 'appointment_time', 'purpose', 'status',
                'notes', 'staff_notes', 'completion_notes', 'completed_at', 'completed_by',
                'payment_status', 'payment_amount', 'discount_amount', 'original_price',
                'payment_type', 'payment_date', 'processed_by', 'payment_notes',
                'created_at', 'updated_at'
            ]); // Include payment information for refunds

        if ($request->user()->isClient()) {
            $query->where('user_id', $request->user()->id);
        }
        // Staff and admin users can see all appointments

        // Apply timeframe filter if provided
        if ($request->has('timeframe')) {
            $timeframe = $request->timeframe;
            $dateRange = $this->getDateRange($timeframe);
            $query->whereBetween('appointment_date', $dateRange);
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

        // Return with success wrapper for consistent API response
        return [
            'data' => $appointments->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ]
        ];
    }

    /**
     * Get date range based on timeframe
     */
    private function getDateRange($timeframe = 'monthly')
    {
        $now = now();
        
        switch ($timeframe) {
            case 'all':
                return [
                    $now->copy()->subYears(5)->startOfYear(),
                    $now->copy()->addYears(5)->endOfYear()
                ];

            case 'daily':
                return [
                    $now->copy()->subDays(6)->startOfDay(),
                    $now->copy()->endOfDay()
                ];
            
            case 'weekly':
                return [
                    $now->copy()->subWeeks(11)->startOfWeek(),
                    $now->copy()->addMonths(3)->endOfDay() // Allow some future in weekly
                ];
            
            case 'yearly':
                return [
                    $now->copy()->subYears(4)->startOfYear(),
                    $now->copy()->addYears(2)->endOfYear() // Allow future in yearly
                ];
            
            case 'monthly':
            default:
                return [
                    $now->copy()->subMonths(11)->startOfMonth(),
                    $now->copy()->addYear()->endOfDay() // Allow future in monthly
                ];
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|max:255', // Flexible type - can be from static types or service names
            'service_id' => 'nullable|exists:services,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'service_type' => 'nullable|string|max:255',
            'appointment_date' => 'required|date|after_or_equal:today', // Allow today
            'appointment_time' => 'required|date_format:H:i',
            'purpose' => 'nullable|string|max:500',
            'documents' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $appointmentDate = Carbon::createFromFormat('Y-m-d', $request->appointment_date);
        $appointmentTime = $request->appointment_time;

        // NEW VALIDATION: Check for weekend (Saturday=6, Sunday=0)
        $dayOfWeek = $appointmentDate->dayOfWeek;
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return response()->json([
                'message' => 'Appointments cannot be booked on weekends'
            ], 422);
        }

        // NEW VALIDATION: Check working hours (8 AM to 5 PM)
        $timeObj = Carbon::createFromFormat('H:i', $appointmentTime);
        $workingHourStart = Carbon::createFromFormat('H:i', '08:00');
        $workingHourEnd = Carbon::createFromFormat('H:i', '17:00');
        
        if ($timeObj < $workingHourStart || $timeObj >= $workingHourEnd) {
            return response()->json([
                'message' => 'Appointments can only be booked between 8:00 AM and 5:00 PM'
            ], 422);
        }

        // NEW VALIDATION: Check lunch break (12 PM to 1 PM)
        $lunchStart = Carbon::createFromFormat('H:i', '12:00');
        $lunchEnd = Carbon::createFromFormat('H:i', '13:00');
        
        if ($timeObj >= $lunchStart && $timeObj < $lunchEnd) {
            return response()->json([
                'message' => 'This time is during lunch break. Please select a different time'
            ], 422);
        }

        // VALIDATION: Check for unavailable dates/times via BlackoutDate (unified)
        $isUnavailable = BlackoutDate::where(function($query) use ($request) {
                // Check specific date (non-recurring)
                $query->where('date', $request->appointment_date)
                      ->where(function ($q) {
                          $q->whereNull('is_recurring')
                            ->orWhere('is_recurring', false);
                      });
            })
            ->where(function($query) use ($request) {
                // Check all-day unavailability (no time range = all day)
                $query->where(function($q) {
                    $q->whereNull('start_time')->whereNull('end_time');
                })
                // Or check time-specific unavailability that overlaps
                ->orWhere(function($q) use ($request) {
                    $q->whereNotNull('start_time')
                      ->whereNotNull('end_time')
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

        // Check for blackout dates (both specific and recurring)
        $blackoutDate = BlackoutDate::where(function($query) use ($request, $appointmentDate) {
            // Check specific date (skip those already checked above by only matching recurring here)
            $query->where(function ($q) use ($request) {
                      $q->where('date', $request->appointment_date)
                        ->where('is_recurring', false)
                        // Only match entries with time ranges not caught above
                        ->whereNotNull('start_time')
                        ->whereNotNull('end_time');
                  })
                  // Or check recurring blackout on this day of week
                  ->orWhere(function($q) use ($appointmentDate) {
                      $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                      $dayName = $dayNames[$appointmentDate->dayOfWeek];
                      
                      $q->where('is_recurring', true)
                        ->whereJsonContains('recurring_days', $dayName);
                  });
        })->first();

        if ($blackoutDate) {
            // Check if entire day is blocked or specific time range
            if (!$blackoutDate->start_time && !$blackoutDate->end_time) {
                return response()->json([
                    'message' => 'All-day blackout: ' . $blackoutDate->reason
                ], 422);
            }

            // Check if time falls within blackout time range
            $blackoutStart = Carbon::createFromFormat('H:i', $blackoutDate->start_time);
            $blackoutEnd = Carbon::createFromFormat('H:i', $blackoutDate->end_time);
            
            if ($timeObj >= $blackoutStart && $timeObj < $blackoutEnd) {
                return response()->json([
                    'message' => 'Time slot is blocked: ' . $blackoutDate->reason
                ], 422);
            }
        }

        // Check daily appointment limit per user (rolling 24-hour window)
        $hasReachedLimit = \App\Models\AppointmentSettings::userHasReachedDailyLimit($request->user()->id);
        if ($hasReachedLimit) {
            $settings = \App\Models\AppointmentSettings::getCurrent();
            $nextAvailable = \App\Models\AppointmentSettings::getNextAvailableTime($request->user()->id);
            $nextAvailableFormatted = $nextAvailable ? $nextAvailable->format('M d, Y \a\t g:i A') : null;

            return response()->json([
                'message' => "You have reached your booking limit of {$settings->daily_booking_limit_per_user} appointments per 24 hours."
                    . ($nextAvailableFormatted ? " You can book again on {$nextAvailableFormatted}." : ''),
                'limit' => $settings->daily_booking_limit_per_user,
                'next_available_time' => $nextAvailable?->toIso8601String(),
                'next_available_formatted' => $nextAvailableFormatted,
                'has_reached_limit' => true
            ], 422);
        }

        // VALIDATION: Check service availability (services may be temporarily unavailable)
        $serviceIdsToCheck = $request->service_ids ?: ($request->service_id ? [$request->service_id] : []);
        if (!empty($serviceIdsToCheck)) {
            $bookingDateTime = Carbon::parse($request->appointment_date . ' ' . $request->appointment_time);
            foreach ($serviceIdsToCheck as $sid) {
                $unavailability = \App\Models\ServiceUnavailability::isServiceUnavailableAt((int) $sid, $bookingDateTime);
                if ($unavailability) {
                    $serviceName = \App\Models\Service::find($sid)?->name ?? 'Selected service';
                    return response()->json([
                        'message' => "{$serviceName} is currently unavailable: {$unavailability->reason}",
                        'unavailable_service_id' => $sid,
                        'unavailability_reason' => $unavailability->reason,
                        'unavailability_category' => $unavailability->reason_category,
                    ], 422);
                }
            }
        }

        // ATOMIC BOOKING: Use transaction with pessimistic locking to prevent double-booking
        $slotCapacity = $this->getSlotCapacity($appointmentDate, $appointmentTime);

        // Get service_id - either from request or by looking up service by name/type
        $serviceId = $request->service_id;
        if (!$serviceId && $request->service_type) {
            $service = \App\Models\Service::where('name', $request->service_type)
                ->orWhere('name', 'LIKE', str_replace('_', ' ', $request->type ?? ''))
                ->first();
            $serviceId = $service?->id;
        }
        
        // If still no service ID, try to find by type (snake_case to Title Case)
        if (!$serviceId && $request->type) {
            $typeName = ucwords(str_replace('_', ' ', $request->type));
            $service = \App\Models\Service::where('name', $typeName)->first();
            $serviceId = $service?->id;
        }

        try {
            $appointment = DB::transaction(function () use ($request, $slotCapacity, $serviceId) {
                // Pessimistic lock: SELECT ... FOR UPDATE prevents concurrent reads
                $appointmentCount = Appointment::where('appointment_date', $request->appointment_date)
                    ->where('appointment_time', $request->appointment_time)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->count();

                if ($appointmentCount >= $slotCapacity) {
                    throw new \Exception('SLOT_FULL');
                }

                // Check if the SAME USER already has an appointment at this exact date/time
                $userAlreadyBooked = Appointment::where('appointment_date', $request->appointment_date)
                    ->where('appointment_time', $request->appointment_time)
                    ->where('user_id', $request->user()->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->exists();

                if ($userAlreadyBooked) {
                    throw new \Exception('USER_DUPLICATE');
                }

                // Handle multiple services
                $serviceIds = $request->service_ids ?: ($serviceId ? [$serviceId] : []);
                $services = \App\Models\Service::whereIn('id', $serviceIds)->get();
                $totalPrice = $services->sum('price');
                $serviceNames = $services->pluck('name')->join(', ');

                $appointmentData = [
                    'user_id' => $request->user()->id,
                    'type' => $request->type,
                    'service_id' => $serviceId ?: ($serviceIds[0] ?? null),
                    'service_type' => $serviceNames ?: $request->service_type,
                    'appointment_date' => $request->appointment_date,
                    'appointment_time' => $request->appointment_time,
                    'purpose' => $request->purpose ?? null,
                    'documents' => $request->documents,
                    'notes' => $request->notes,
                    'original_price' => $totalPrice,
                ];

                $appointment = Appointment::create($appointmentData);

                // Set protected fields explicitly (not mass-assignable)
                $appointment->payment_amount = $totalPrice;
                $appointment->status = 'pending';
                $appointment->save();

                // Attach services with current prices via sync
                if (!empty($serviceIds)) {
                    $syncData = [];
                    foreach ($services as $service) {
                        $syncData[$service->id] = ['price_at_booking' => $service->price];
                    }
                    \Log::info('Syncing services', ['syncData' => $syncData]);
                    $appointment->services()->sync($syncData);
                }

                return $appointment;
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_FULL') {
                return response()->json([
                    'message' => 'This time slot is at full capacity. Please select another time'
                ], 422);
            }
            if ($e->getMessage() === 'USER_DUPLICATE') {
                return response()->json([
                    'message' => 'You already have an appointment booked at this time'
                ], 422);
            }
            \Log::error('Appointment booking failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to book appointment. Please try again.',
                'success' => false
            ], 500);
        }

        // Log the booking action
        $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
        $appointmentDateFormatted = \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y');
        $appointmentTimeFormatted = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
        \App\Models\ActionLog::log(
            'book_appointment',
            "Booked appointment for {$serviceType} on {$appointmentDateFormatted} at {$appointmentTimeFormatted}",
            'Appointment',
            $appointment->id
        );

        // Broadcast appointment created for realtime UIs (OUTSIDE transaction)
        try {
            $appointment->load(['user', 'staff', 'service']);
            event(new \App\Events\AppointmentCreated($appointment));
        } catch (\Exception $e) {
            \Log::debug('Failed to broadcast appointment created event: ' . $e->getMessage());
        }

        // Send confirmation email immediately (sync mode)
        $emailSent = false;
        $emailError = null;
        try {
            Mail::to($request->user()->email)->send(new AppointmentConfirmationMail($appointment));
            $emailSent = true;
            \Log::info('Appointment confirmation email sent successfully', [
                'user_email' => $request->user()->email,
                'appointment_id' => $appointment->id
            ]);
        } catch (\Exception $e) {
            $emailSent = false;
            $emailError = $e->getMessage();
            \Log::error('Failed to send confirmation email: ' . $e->getMessage(), [
                'user_email' => $request->user()->email,
                'appointment_id' => $appointment->id,
                'exception' => $e
            ]);
        }

        // Record user activity for auto-archiving tracking
        $request->user()->forceFill(['last_activity_at' => now()])->save();

        $response = [
            'message' => 'Appointment booked successfully',
            'appointment' => $appointment->load('user'),
            'success' => true,
            'email_sent' => $emailSent
        ];

        // Add warning if email failed
        if (!$emailSent && strpos($emailError, 'Daily user sending limit') !== false) {
            $response['email_warning'] = 'Confirmation email could not be sent due to email server limits. Your appointment has been booked. Please contact support or check back later for your confirmation.';
            $response['email_error_type'] = 'RATE_LIMIT';
        } elseif (!$emailSent) {
            $response['email_warning'] = 'Confirmation email could not be sent at this time. Your appointment has been booked. You can view it in your appointments list.';
            $response['email_error_type'] = 'UNKNOWN';
        }

        return response()->json($response);
    }

    /**
     * Get the capacity limit for a specific time slot
     * 
     * @param Carbon $date
     * @param string $time (format: H:i)
     * @return int
     */
    private function getSlotCapacity(Carbon $date, $time)
    {
        $dayOfWeek = strtolower(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][$date->dayOfWeek]);
        $dateStr = $date->toDateString();

        // Priority 1: date-specific override
        $dateSpecific = TimeSlotCapacity::where('is_active', true)
            ->where('specific_date', $dateStr)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->first();

        if ($dateSpecific) {
            return $dateSpecific->max_appointments_per_slot;
        }

        // Priority 2: day-of-week specific, then global
        $capacity = TimeSlotCapacity::where('is_active', true)
            ->whereNull('specific_date')
            ->where(function($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                      ->orWhereNull('day_of_week');
            })
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->orderByRaw('CASE WHEN day_of_week IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        return $capacity ? $capacity->max_appointments_per_slot : 3;
    }

    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        // Log the view action for the appointment owner
        $user = auth()->user();
        if ($user && $appointment->user_id === $user->id) {
            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            $appointmentDateFormatted = \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y');
            \App\Models\ActionLog::log(
                'view_appointment',
                "Viewed appointment details for {$serviceType} on {$appointmentDateFormatted}",
                'Appointment',
                $appointment->id
            );
        }

        return response()->json([
            'data' => $appointment->load(['user', 'staff', 'service', 'services']),
            'success' => true
        ]);
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $request->validate([
            'status' => 'required|in:pending,approved,completed,cancelled,declined,no_show',
            'staff_notes' => 'nullable|string|max:1000',
        ]);

        // Status transition validation - enforce allowed state transitions
        $allowedTransitions = [
            'pending'   => ['approved', 'declined', 'cancelled'],
            'approved'  => ['completed', 'cancelled', 'declined', 'no_show'],
            'declined'  => ['pending'],  // allow re-review
            'completed' => [],           // terminal state
            'cancelled' => [],           // terminal state
            'no_show'   => ['pending'],  // allow re-review
        ];

        if (!in_array($request->status, $allowedTransitions[$appointment->status] ?? [])) {
            return response()->json([
                'message' => "Cannot transition from '{$appointment->status}' to '{$request->status}'.",
                'success' => false,
                'current_status' => $appointment->status,
                'allowed_transitions' => $allowedTransitions[$appointment->status] ?? [],
            ], 422);
        }

        $oldStatus = $appointment->status;
        
        // Wrap in transaction with lock to prevent concurrent status changes
        DB::transaction(function () use ($request, $appointment) {
            // Re-fetch with lock to prevent race condition
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

            $appointment->update([
                'staff_notes' => $request->staff_notes,
            ]);
            // Set protected field explicitly (not mass-assignable)
            $appointment->status = $request->status;
            $appointment->save();
        });

        // Refresh after transaction
        $appointment->refresh();

        // Log status update
        if ($oldStatus !== $request->status) {
            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            \App\Models\ActionLog::log(
                'update_appointment_status',
                "Updated appointment status from {$oldStatus} to {$request->status} for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType})",
                'Appointment',
                $appointment->id
            );
        }

        // Send status update email to client (outside transaction — non-blocking)
        if ($oldStatus !== $request->status) {
            try {
                if ($appointment->user) {
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                    \Log::info('Appointment status email sent to user', [
                        'user_email' => $appointment->user->email,
                        'appointment_id' => $appointment->id,
                        'old_status' => $oldStatus,
                        'new_status' => $request->status
                    ]);
                }
                
                // Also send to staff if assigned
                if ($appointment->staff_id && $appointment->staff && $appointment->staff->email) {
                    Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                    \Log::info('Appointment status email sent to staff', [
                        'staff_email' => $appointment->staff->email,
                        'appointment_id' => $appointment->id,
                        'old_status' => $oldStatus,
                        'new_status' => $request->status
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send status email: ' . $e->getMessage(), [
                    'user_email' => $appointment->user?->email ?? 'unknown',
                    'appointment_id' => $appointment->id,
                    'exception' => $e
                ]);
            }

            // If status changed to approved, notify cashiers
            if ($request->status === 'approved') {
                try {
                    $appointment->refresh();
                    $appointment->load(['user', 'staff', 'service']);
                    \App\Services\NotificationService::appointmentApproved($appointment);
                    \App\Services\NotificationService::notifyCashiersAppointmentApproved($appointment);
                } catch (\Exception $e) {
                    \Log::error('Failed to notify cashiers: ' . $e->getMessage());
                }
            }
        }

        // Invalidate stats cache when appointment status changes (especially important for completed status which affects revenue)
        $this->invalidateStatsCache();

        // Broadcast appointment update for realtime clients
        try {
            $appointment->refresh();
            $appointment->load(['user', 'staff', 'service']);
            event(new AppointmentUpdated($appointment));
        } catch (\Exception $e) {
            \Log::debug('Failed to broadcast appointment update event: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'data' => $appointment->load(['user', 'staff', 'service']),
            'success' => true
        ]);
    }

    public function approve(Appointment $appointment)
    {
        try {
            $this->authorize('update', $appointment);

            // Status transition guard: only pending appointments can be approved
            if ($appointment->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending appointments can be approved.',
                    'current_status' => $appointment->status,
                    'success' => false
                ], 422);
            }

            $oldStatus = $appointment->status;

            // Transaction with pessimistic lock to prevent race conditions
            DB::transaction(function () use ($appointment, $oldStatus) {
                // Re-fetch with lock to prevent concurrent status changes
                $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

                // Double-check status after acquiring lock
                if ($appointment->status !== 'pending') {
                    throw new \RuntimeException('Appointment status changed concurrently');
                }

                $appointment->status = 'approved';
                $appointment->save();

                // Refresh the model to get updated status
                $appointment->refresh();

                // Log the action
                $serviceType = $appointment->service_type ?? $appointment->type;
                \App\Models\ActionLog::log(
                    'approve',
                    "Approved appointment for {$appointment->user->first_name} {$appointment->user->last_name} - {$serviceType} on {$appointment->appointment_date} at {$appointment->appointment_time}",
                    'Appointment',
                    $appointment->id
                );

                // Send message within transaction (needs consistency with status change)
                if ($oldStatus !== 'approved') {
                    $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                    $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
                    $serviceType = $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type;
                    
                    $messageText = "✓ Your appointment has been APPROVED!\n\n" .
                        "📅 Date: " . $appointmentDate . "\n" .
                        "⏰ Time: " . $appointmentTime . "\n" .
                        "📋 Service: " . $serviceType . "\n\n" .
                        "Please arrive on time for your appointment. If you need to reschedule, please contact us.";
                    
                    Message::create([
                        'sender_id' => request()->user()->id,
                        'receiver_id' => $appointment->user_id,
                        'message' => $messageText,
                        'read' => false
                    ]);
                }
            });

            // Non-critical notifications OUTSIDE transaction — failures here won't roll back status
            if ($oldStatus !== 'approved') {
                try {
                    \App\Services\NotificationService::appointmentApproved($appointment);
                    \App\Services\NotificationService::notifyCashiersAppointmentApproved($appointment);
                } catch (\Exception $e) {
                    \Log::warning('Non-critical notification failed in approve: ' . $e->getMessage());
                }
            }

            // Invalidate stats cache (outside transaction — non-critical)
            $this->invalidateStatsCache();

            // Send approval emails immediately (outside the transaction)
            if ($oldStatus !== 'approved') {
                try {
                    $appointment->refresh();
                    $appointment->load(['user', 'staff', 'service']);
                    
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                    \Log::info('Appointment approval email sent to user', ['user_email' => $appointment->user->email, 'appointment_id' => $appointment->id]);
                    
                    if ($appointment->staff_id && $appointment->staff) {
                        Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                        \Log::info('Appointment approval email sent to staff', ['staff_email' => $appointment->staff->email, 'appointment_id' => $appointment->id]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to send approval email: ' . $e->getMessage(), ['appointment_id' => $appointment->id, 'exception' => $e]);
                }
            }

            // Broadcast approval
            $this->broadcastAppointmentUpdate($appointment);

            // Ensure model is fresh before response
            $appointment->refresh();

            return response()->json([
                'message' => 'Appointment approved successfully',
                'data' => $appointment->load(['user', 'staff', 'service']),
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Approve method error: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error approving appointment',
                'success' => false
            ], 500);
        }
    }

    private function broadcastAppointmentUpdate(Appointment $appointment)
    {
        try {
            $appointment->refresh();
            $appointment->load(['user', 'staff', 'service']);
            event(new \App\Events\AppointmentUpdated($appointment));
        } catch (\Exception $e) {
            \Log::debug('Failed to broadcast appointment update: ' . $e->getMessage());
        }
    }

    public function decline(Appointment $appointment)
    {
        try {
            $this->authorize('update', $appointment);

            // Status transition guard: only pending or approved appointments can be declined
            if (!in_array($appointment->status, ['pending', 'approved'])) {
                return response()->json([
                    'message' => 'Only pending or approved appointments can be declined.',
                    'current_status' => $appointment->status,
                    'success' => false
                ], 422);
            }

            $oldStatus = $appointment->status;
            $declineReason = request()->input('decline_reason', '');

            // Transaction with pessimistic lock to prevent race conditions
            DB::transaction(function () use ($appointment, $oldStatus, $declineReason) {
                // Re-fetch with lock to prevent concurrent status changes
                $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

                // Double-check status after acquiring lock
                if (!in_array($appointment->status, ['pending', 'approved'])) {
                    throw new \RuntimeException('Appointment status changed concurrently');
                }

                $appointment->status = 'declined';
                $appointment->decline_reason = $declineReason ?: null;
                $appointment->save();

                // Refresh the model to get updated status
                $appointment->refresh();

                // Log action
                $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
                $reasonText = $declineReason ? " - Reason: {$declineReason}" : '';
                \App\Models\ActionLog::log(
                    'decline_appointment',
                    "Declined appointment for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType}){$reasonText}",
                    'Appointment',
                    $appointment->id
                );

                // Send message within transaction (needs consistency with status change)
                if ($oldStatus !== 'declined') {
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
                }
            });

            // Non-critical notification OUTSIDE transaction — failure won't roll back status
            if ($oldStatus !== 'declined') {
                try {
                    \App\Services\NotificationService::appointmentDeclined($appointment, $declineReason);
                } catch (\Exception $e) {
                    \Log::warning('Non-critical notification failed in decline: ' . $e->getMessage());
                }
            }

            // Invalidate stats cache (outside transaction — non-critical)
            $this->invalidateStatsCache();

            // Send decline email immediately (outside the transaction)
            if ($oldStatus !== 'declined') {
                try {
                    $appointment->refresh();
                    $appointment->load(['user', 'staff', 'service']);
                    
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                    \Log::info('Appointment decline email sent to user', ['user_email' => $appointment->user->email, 'appointment_id' => $appointment->id]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send decline email: ' . $e->getMessage(), ['appointment_id' => $appointment->id, 'exception' => $e]);
                }
            }

            // Broadcast decline
            $this->broadcastAppointmentUpdate($appointment);

            // Ensure model is fresh before response
            $appointment->refresh();

            return response()->json([
                'message' => 'Appointment declined successfully',
                'data' => $appointment->load(['user', 'staff', 'service']),
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Decline method error: ' . $e->getMessage());
            \Log::error('Exception trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error declining appointment',
                'success' => false
            ], 500);
        }
    }

    public function complete(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        // Validate that only approved appointments can be completed
        if ($appointment->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved appointments can be marked as completed',
                'current_status' => $appointment->status
            ], 422);
        }

        $request->validate([
            'completion_notes' => 'nullable|string|max:1000'
        ]);

        $oldStatus = $appointment->status;
        $completionTime = now();
        $adminUser = $request->user();

        // Wrap status change + message + notification in a transaction
        DB::transaction(function () use ($appointment, $request, $completionTime, $adminUser, $oldStatus) {
            // Re-fetch with lock to prevent concurrent status changes
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

            $appointment->update([
                'completed_at' => $completionTime,
                'completion_notes' => $request->input('completion_notes'),
            ]);
            // Set protected fields explicitly (not mass-assignable)
            $appointment->status = 'completed';
            $appointment->completed_by = $adminUser->id;
            $appointment->save();

            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            \App\Models\ActionLog::log(
                'complete_appointment',
                "Marked appointment as completed for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType})",
                'Appointment',
                $appointment->id
            );

            // Create message notification within transaction
            if ($oldStatus !== 'completed') {
                $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
                $serviceType = $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type;
                
                $messageText = "✓ Your appointment has been COMPLETED!\n\n";
                $messageText .= "📅 Date: " . $appointmentDate . "\n";
                $messageText .= "⏰ Time: " . $appointmentTime . "\n";
                $messageText .= "📋 Service: " . $serviceType . "\n";
                
                if ($request->input('completion_notes')) {
                    $messageText .= "\n📝 Notes: " . $request->input('completion_notes') . "\n";
                }
                
                $messageText .= "\nThank you for using our services. If you have any questions or need further assistance, please feel free to contact us.";
                
                Message::create([
                    'sender_id' => $adminUser->id,
                    'receiver_id' => $appointment->user_id,
                    'message' => $messageText,
                    'read' => false,
                    'type' => 'appointment_completion'
                ]);
                
                // Create in-app notification
                \App\Services\NotificationService::appointmentCompleted($appointment);
            }
        });

        // Invalidate stats cache (outside transaction — non-critical)
        $this->invalidateStatsCache();

        // Send completion emails immediately (outside the transaction)
        if ($oldStatus !== 'completed') {
            try {
                $appointment->refresh();
                $appointment->load(['user', 'staff', 'service', 'completedBy']);
                
                Mail::to($appointment->user->email)->send(new \App\Mail\AppointmentCompletionMail($appointment, $adminUser));
                \Log::info('Appointment completion email sent to user', ['user_email' => $appointment->user->email, 'appointment_id' => $appointment->id]);
                
                if ($appointment->staff_id && $appointment->staff) {
                    Mail::to($appointment->staff->email)->send(new \App\Mail\AppointmentCompletionMail($appointment, $adminUser));
                    \Log::info('Appointment completion email sent to staff', ['staff_email' => $appointment->staff->email, 'appointment_id' => $appointment->id]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send completion email: ' . $e->getMessage(), ['appointment_id' => $appointment->id, 'exception' => $e]);
            }
        }

        // Broadcast completion
        $this->broadcastAppointmentUpdate($appointment);

        return response()->json([
            'message' => 'Appointment marked as completed successfully',
            'data' => $appointment->load(['user', 'staff', 'service', 'completedBy']),
            'success' => true
        ]);
    }

    /**
     * Mark an approved appointment as no-show.
     */
    public function markNoShow(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        // Phase 1 #6: Prevent marking paid appointments as no-show
        if ($appointment->payment_status === 'paid') {
            return response()->json([
                'message' => 'Cannot mark a paid appointment as no-show. Process a refund first if needed.',
                'success' => false
            ], 422);
        }

        if ($appointment->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved appointments can be marked as no-show',
                'current_status' => $appointment->status
            ], 422);
        }

        $adminUser = $request->user();

        DB::transaction(function () use ($appointment, $adminUser) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            $appointment->status = 'no_show';
            $appointment->save();

            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            \App\Models\ActionLog::log(
                'no_show_appointment',
                "Marked appointment as no-show for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType})",
                'Appointment',
                $appointment->id,
                'success',
                [
                    'appointment_id' => $appointment->id,
                    'client_name' => "{$appointment->user->first_name} {$appointment->user->last_name}",
                    'previous_status' => 'approved',
                    'previous_payment_status' => $appointment->payment_status,
                    'was_payment_attempted' => $appointment->payment_status === 'partially_paid',
                ]
            );

            $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
            $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');

            $messageText = "⚠️ Your appointment has been marked as NO SHOW.\n\n";
            $messageText .= "📅 Date: {$appointmentDate}\n";
            $messageText .= "⏰ Time: {$appointmentTime}\n";
            $messageText .= "📋 Service: {$serviceType}\n\n";
            $messageText .= "If you believe this is a mistake, please contact us to reschedule.";

            Message::create([
                'sender_id' => $adminUser->id,
                'receiver_id' => $appointment->user_id,
                'message' => $messageText,
                'read' => false,
                'type' => 'appointment_no_show'
            ]);
        });

        $this->invalidateStatsCache();
        $this->broadcastAppointmentUpdate($appointment);

        return response()->json([
            'message' => 'Appointment marked as no-show',
            'data' => $appointment->load(['user', 'staff', 'service']),
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
        $query = Appointment::query()
            ->whereNotNull('archived_at')
            ->with(['user', 'staff', 'service']);

        // Also include soft-deleted appointments in archive
        $query->withTrashed();

        // Only admins can view all archived appointments
        if (!$request->user()->isAdmin()) {
            if ($request->user()->isClient()) {
                $query->where('user_id', $request->user()->id);
            } elseif ($request->user()->isStaff()) {
                $query->where('staff_id', $request->user()->id);
            }
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('appointment_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('appointment_date', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('appointment_date', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $appointments = $query->orderBy('archived_at', 'desc')
                             ->paginate($request->get('per_page', 20));

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

        // Role-based filtering to prevent IDOR
        if ($request->user()->isClient()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->user()->isStaff()) {
            $query->where(function ($q) use ($request) {
                $q->where('staff_id', $request->user()->id)
                  ->orWhereNull('staff_id');
            });
        }
        // admin sees all - no filter needed

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
            SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = \'declined\' THEN 1 ELSE 0 END) as declined
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
        
        \Log::info('[userAppointments] Fetching appointments for user', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role
        ]);
        
        $perPage = $request->get('per_page', 50);
        $appointments = $user->appointments()
            ->with(['staff', 'service', 'services'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $appointments->items(),
            'success' => true,
            'count' => $appointments->total(),
            'current_page' => $appointments->currentPage(),
            'last_page' => $appointments->lastPage(),
        ]);
    }

    public function staffAppointments(Request $request)
    {
        $user = $request->user();
        
        $perPage = $request->get('per_page', 50);
        $appointments = $user->staffAppointments()
            ->with(['user', 'service', 'services'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $appointments->items(),
            'success' => true,
            'count' => $appointments->total(),
            'current_page' => $appointments->currentPage(),
            'last_page' => $appointments->lastPage(),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        
        // Admin and staff can cancel any appointment, clients can only cancel their own
        if ($user->isAdmin() || $user->isStaff()) {
            $appointment = Appointment::findOrFail($id);
        } else {
            $appointment = $user->appointments()->findOrFail($id);
        }

        if (!in_array($appointment->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Only pending or approved appointments can be cancelled',
                'success' => false
            ], 422);
        }

        $oldStatus = $appointment->status;

        // Wrap status change + message + notification in a transaction
        DB::transaction(function () use ($appointment, $user) {
            // Re-fetch with lock to prevent concurrent status changes
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

            // Set protected field explicitly (not mass-assignable)
            $appointment->status = 'cancelled';
            $appointment->save();

            // Log the action - differentiate between user and admin/staff cancellation
            $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
            $appointmentDateFormatted = \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y');
            $appointmentTimeFormatted = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
            $cancelledBy = $user->id === $appointment->user_id ? 'self' : 'admin/staff';
            \App\Models\ActionLog::log(
                'cancel_appointment',
                $cancelledBy === 'self'
                    ? "Cancelled own appointment for {$serviceType} on {$appointmentDateFormatted} at {$appointmentTimeFormatted}"
                    : "Cancelled appointment for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType}) on {$appointmentDateFormatted} at {$appointmentTimeFormatted}",
                'Appointment',
                $appointment->id
            );

            // Save message to database for user visibility
            $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
            $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A');
            $serviceType = $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type;

            $messageText = "✕ Your appointment has been CANCELLED.\n\n" .
                "📅 Date: " . $appointmentDate . "\n" .
                "⏰ Time: " . $appointmentTime . "\n" .
                "📋 Service: " . $serviceType . "\n\n" .
                "If you have any questions, please contact us.";

            Message::create([
                'sender_id' => $user->id,
                'receiver_id' => $appointment->user_id,
                'message' => $messageText,
                'read' => false
            ]);

            // Create in-app notification for the client
            \App\Services\NotificationService::appointmentCancelled($appointment);
        });

        // Invalidate stats (outside transaction — non-critical)
        $this->invalidateStatsCache();

        // Send cancellation emails immediately (outside the transaction)
        try {
            $appointment->refresh();
            $appointment->load(['user', 'staff', 'service']);
            
            Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
            \Log::info('Appointment cancellation email sent to user', ['user_email' => $appointment->user->email, 'appointment_id' => $appointment->id]);
            
            if ($appointment->staff_id && $appointment->staff) {
                Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                \Log::info('Appointment cancellation email sent to staff', ['staff_email' => $appointment->staff->email, 'appointment_id' => $appointment->id]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation email: ' . $e->getMessage(), ['appointment_id' => $appointment->id, 'exception' => $e]);
        }

        // Broadcast cancellation for realtime clients
        $this->broadcastAppointmentUpdate($appointment);

        return response()->json([
            'data' => $appointment->load(['user', 'staff', 'service']),
            'message' => 'Appointment cancelled successfully',
            'success' => true
        ]);
    }

    public function availableSlots(Request $request, $date)
    {
        $workingHours = [
            'start' => '08:00',
            'end' => '17:00',
        ];

        // Check BlackoutDate for unavailability (unified system)
        $unavailableRecords = BlackoutDate::where(function ($q) use ($date) {
            $q->where('date', $date)
              ->where(function ($q2) {
                  $q2->whereNull('is_recurring')
                     ->orWhere('is_recurring', false);
              });
        })->get();

        $bookedSlots = Appointment::where('appointment_date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('appointment_time')
            ->map(function ($time) {
                // Normalize "HH:MM:SS" from MySQL TIME columns to "HH:MM" for comparison
                return substr($time, 0, 5);
            })
            ->toArray();

        // Count bookings per time slot for capacity checking
        $bookingCounts = array_count_values($bookedSlots);

        // Parse date for capacity lookup
        $dateObj = Carbon::parse($date);

        $availableSlots = [];
        $currentTime = strtotime($workingHours['start']);
        $endTime = strtotime($workingHours['end']);

        while ($currentTime < $endTime) {
            $timeSlot = date('H:i', $currentTime);
            
            // Check if slot has remaining capacity (not just booked/unbooked)
            $currentBookings = $bookingCounts[$timeSlot] ?? 0;
            $slotCapacity = $this->getSlotCapacity($dateObj, $timeSlot);
            $isAvailable = $currentBookings < $slotCapacity;
            
            // Check unavailable records for unavailability
            if ($isAvailable && $unavailableRecords->isNotEmpty()) {
                foreach ($unavailableRecords as $record) {
                    $isUnavailable = false;
                    
                    if (!$record->start_time && !$record->end_time) {
                        // No time range = all-day block
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
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = \'declined\' THEN 1 ELSE 0 END) as declined
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

    /**
     * Suggest alternative dates and times when preferred slot is unavailable
     */
    public function suggestAlternative(Request $request)
    {
        $request->validate([
            'preferred_date' => 'required|date',
            'days_ahead' => 'nullable|integer|min:1|max:60',
        ]);

        $preferredDate = Carbon::parse($request->preferred_date);
        $daysAhead = $request->days_ahead ?? 14;

        $alternatives = [];
        $maxSlots = 3; // Show up to 3 alternatives

        // Check next 14 days (or specified days_ahead)
        for ($i = 0; $i < $daysAhead && count($alternatives) < $maxSlots; $i++) {
            $checkDate = $preferredDate->copy()->addDays($i);

            // Skip weekends
            if ($checkDate->isWeekend()) {
                continue;
            }

            // Skip blackout dates
            $isBlackedOut = BlackoutDate::where('date', $checkDate->toDateString())
                ->where(function ($q) {
                    $q->whereNull('is_recurring')
                      ->orWhere('is_recurring', false);
                })
                ->exists();

            if ($isBlackedOut) {
                continue;
            }

            // Get available time slots for this date
            $availableSlots = $this->getAvailableSlotsForDate($checkDate->toDateString());

            if (!empty($availableSlots)) {
                // Get first 2-3 available times
                $availableTimes = array_slice($availableSlots, 0, 2);
                $timeStrings = array_map(fn($slot) => substr($slot['time'], 0, 5), $availableTimes);

                $alternatives[] = [
                    'date' => $checkDate->toDateString(),
                    'day_name' => $checkDate->format('l'), // e.g., "Monday"
                    'available_times' => $timeStrings,
                    'available_slots' => count($availableSlots),
                    'first_available_time' => $availableTimes[0]['time'] ?? null,
                ];

                if (count($alternatives) >= $maxSlots) {
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'preferred_date' => $preferredDate->toDateString(),
            'alternatives' => $alternatives,
            'total_alternatives' => count($alternatives),
            'message' => count($alternatives) > 0
                ? "We found {$maxSlots} available alternatives for you!"
                : "Unfortunately, no slots available in the next {$daysAhead} days. Please contact support."
        ]);
    }

    /**
     * Smart alternative time slot suggestions
     * When a user selects an unavailable time, suggests the closest available slots
     * on the same day and nearby dates with AI-powered scoring
     */
    public function suggestAlternativeSlots(Request $request)
    {
        $request->validate([
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required|date_format:H:i',
            'days_ahead' => 'nullable|integer|min:1|max:30',
        ]);

        $preferredDate = Carbon::parse($request->preferred_date);
        $preferredTime = $request->preferred_time;
        $daysAhead = $request->days_ahead ?? 7;

        // -------------------------------------------
        // 1) Same-day alternative slots (closest first)
        // -------------------------------------------
        $sameDaySlots = $this->getAvailableSlotsForDate($preferredDate->toDateString());

        // Score and sort same-day slots by proximity to preferred time
        $preferredMinutes = $this->timeToMinutes($preferredTime);
        $sameDaySuggestions = [];

        foreach ($sameDaySlots as $slot) {
            $slotMinutes = $this->timeToMinutes($slot['time']);
            $distanceMinutes = abs($slotMinutes - $preferredMinutes);

            // Compute AI recommendation score (0-100)
            $score = $this->computeSlotScore($distanceMinutes, $slot['capacity_remaining'] ?? 1, $slot['time']);

            $sameDaySuggestions[] = [
                'time' => $slot['time'],
                'display' => $slot['display'],
                'distance_minutes' => $distanceMinutes,
                'capacity_remaining' => $slot['capacity_remaining'] ?? 1,
                'score' => $score,
                'label' => $this->getSlotLabel($distanceMinutes, $score),
                'is_closest' => false, // will be set below
            ];
        }

        // Sort by distance (closest to preferred time first)
        usort($sameDaySuggestions, fn($a, $b) => $a['distance_minutes'] <=> $b['distance_minutes']);

        // Mark the closest slot
        if (!empty($sameDaySuggestions)) {
            $sameDaySuggestions[0]['is_closest'] = true;
        }

        // Take top 6 closest same-day slots
        $sameDaySuggestions = array_slice($sameDaySuggestions, 0, 6);

        // -------------------------------------------
        // 2) Nearby date alternatives (next available dates)
        // -------------------------------------------
        $nearbyDates = [];
        $maxDateSuggestions = 3;

        for ($i = 1; $i <= $daysAhead && count($nearbyDates) < $maxDateSuggestions; $i++) {
            $checkDate = $preferredDate->copy()->addDays($i);

            // Skip weekends
            if ($checkDate->isWeekend()) {
                continue;
            }

            // Skip blackout dates
            $isBlackedOut = BlackoutDate::where(function($query) use ($checkDate) {
                $query->where('date', $checkDate->toDateString())
                      ->orWhere(function($q) use ($checkDate) {
                          $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                          $dayName = $dayNames[$checkDate->dayOfWeek];
                          $q->where('is_recurring', true)
                            ->whereJsonContains('recurring_days', $dayName);
                      });
            })->exists();

            if ($isBlackedOut) continue;

            $dateSlots = $this->getAvailableSlotsForDate($checkDate->toDateString());
            if (empty($dateSlots)) continue;

            // Find the slot closest to the preferred time on this date
            $bestSlot = null;
            $bestDistance = PHP_INT_MAX;
            foreach ($dateSlots as $slot) {
                $dist = abs($this->timeToMinutes($slot['time']) - $preferredMinutes);
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $bestSlot = $slot;
                }
            }

            $nearbyDates[] = [
                'date' => $checkDate->toDateString(),
                'day_name' => $checkDate->format('l'),
                'formatted_date' => $checkDate->format('M d, Y'),
                'total_available_slots' => count($dateSlots),
                'closest_time' => $bestSlot ? $bestSlot['time'] : null,
                'closest_time_display' => $bestSlot ? $bestSlot['display'] : null,
                'available_times' => array_map(fn($s) => [
                    'time' => $s['time'],
                    'display' => $s['display'],
                    'capacity_remaining' => $s['capacity_remaining'] ?? 1,
                ], array_slice($dateSlots, 0, 4)),
            ];
        }

        // -------------------------------------------
        // 3) Build AI recommendation summary
        // -------------------------------------------
        $bestRecommendation = null;
        if (!empty($sameDaySuggestions)) {
            // Recommend the highest-scored same-day slot
            $topScored = $sameDaySuggestions;
            usort($topScored, fn($a, $b) => $b['score'] <=> $a['score']);
            $best = $topScored[0];
            $bestRecommendation = [
                'type' => 'same_day',
                'date' => $preferredDate->toDateString(),
                'time' => $best['time'],
                'display' => $best['display'],
                'score' => $best['score'],
                'message' => $this->getRecommendationMessage($best, $preferredTime),
            ];
        } elseif (!empty($nearbyDates)) {
            $nd = $nearbyDates[0];
            $bestRecommendation = [
                'type' => 'nearby_date',
                'date' => $nd['date'],
                'time' => $nd['closest_time'],
                'display' => $nd['closest_time_display'],
                'score' => 80,
                'message' => "No same-day slots available. The nearest opening is on {$nd['day_name']}, {$nd['formatted_date']} at {$nd['closest_time_display']}.",
            ];
        }

        return response()->json([
            'success' => true,
            'preferred_date' => $preferredDate->toDateString(),
            'preferred_time' => $preferredTime,
            'same_day_slots' => $sameDaySuggestions,
            'nearby_dates' => $nearbyDates,
            'ai_recommendation' => $bestRecommendation,
            'has_same_day_options' => !empty($sameDaySuggestions),
            'total_suggestions' => count($sameDaySuggestions) + count($nearbyDates),
        ]);
    }

    /**
     * Convert HH:MM time string to total minutes from midnight
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Compute an AI recommendation score for a time slot (0-100)
     */
    private function computeSlotScore(int $distanceMinutes, int $capacityRemaining, string $time): int
    {
        $score = 100;

        // Penalize distance from preferred time (up to -50 points)
        $distancePenalty = min(50, (int)($distanceMinutes / 3));
        $score -= $distancePenalty;

        // Bonus for high capacity remaining (+10 max)
        $capacityBonus = min(10, $capacityRemaining * 3);
        $score += $capacityBonus;

        // Prefer mid-morning and early afternoon (+5)
        $hour = (int) explode(':', $time)[0];
        if ($hour >= 9 && $hour <= 11) $score += 5;  // Morning prime
        if ($hour >= 13 && $hour <= 15) $score += 3;  // Afternoon prime

        // Penalize early morning and late afternoon slightly
        if ($hour === 8) $score -= 3;
        if ($hour >= 16) $score -= 5;

        return max(0, min(100, $score));
    }

    /**
     * Get human-readable label for a slot suggestion
     */
    private function getSlotLabel(int $distanceMinutes, int $score): string
    {
        if ($distanceMinutes <= 30) {
            return 'Closest Available';
        }
        if ($distanceMinutes <= 60) {
            return 'Near Your Preference';
        }
        if ($score >= 80) {
            return 'Highly Recommended';
        }
        if ($score >= 60) {
            return 'Good Option';
        }
        return 'Available';
    }

    /**
     * Generate AI recommendation message
     */
    private function getRecommendationMessage(array $slot, string $preferredTime): string
    {
        $distance = $slot['distance_minutes'];

        if ($distance === 0) {
            return "Great news! Your preferred time at {$slot['display']} is available.";
        }
        if ($distance <= 30) {
            return "We recommend {$slot['display']} — it's just {$distance} minutes from your preferred time of {$preferredTime} and has good availability.";
        }
        if ($distance <= 60) {
            return "{$slot['display']} is the closest available slot, about {$distance} minutes from your preferred time.";
        }
        return "{$slot['display']} is available today with {$slot['capacity_remaining']} spots remaining.";
    }

    /**
     * Helper method to get available slots for a specific date
     */
    private function getAvailableSlotsForDate($date)
    {
        $date = Carbon::parse($date)->toDateString();
        $dayOfWeek = Carbon::parse($date)->dayName;
        $availableSlots = [];

        // Define business hours: 8 AM to 5 PM (30-minute slots, excluding lunch 12-1 PM)
        $startTime = strtotime('08:00');
        $endTime = strtotime('17:00');
        $lunchStart = strtotime('12:00');
        $lunchEnd = strtotime('13:00');

        $currentTime = $startTime;
        while ($currentTime < $endTime) {
            $timeSlot = date('H:i', $currentTime);
            
            // Skip lunch break
            if ($currentTime >= $lunchStart && $currentTime < $lunchEnd) {
                $currentTime = strtotime('+30 minutes', $currentTime);
                continue;
            }

            // Check if slot is at capacity
            $capacityRecord = TimeSlotCapacity::where(function ($q) use ($date, $dayOfWeek, $timeSlot) {
                $q->whereNull('day_of_week')
                  ->orWhere('day_of_week', strtolower($dayOfWeek));
            })
            ->where('start_time', '<=', $timeSlot)
            ->where('end_time', '>', $timeSlot)
            ->where('is_active', true)
            ->first();

            // Count existing appointments for this slot
            $bookedCount = Appointment::where('appointment_date', $date)
                ->where('appointment_time', $timeSlot)
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->count();

            $maxCapacity = $capacityRecord ? $capacityRecord->max_appointments_per_slot : 3;

            if ($bookedCount < $maxCapacity) {
                $availableSlots[] = [
                    'time' => $timeSlot,
                    'display' => date('g:i A', $currentTime),
                    'capacity_remaining' => $maxCapacity - $bookedCount
                ];
            }

            $currentTime = strtotime('+30 minutes', $currentTime);
        }

        return $availableSlots;
    }

    /**
     * Invalidate all stats-related cache keys
     * This ensures that revenue calculations are updated when appointments change status
     * Important: Call this whenever appointment status changes, especially to 'completed'
     */
    private function invalidateStatsCache()
    {
        // Clear batch dashboard caches that include revenue calculations
        Cache::forget('admin_batch_dashboard_daily');
        Cache::forget('admin_batch_dashboard_weekly');
        Cache::forget('admin_batch_dashboard_monthly');
        Cache::forget('admin_batch_dashboard_yearly');
        Cache::forget('admin_batch_dashboard_all');
        
        // Clear the full dashboard load cache
        Cache::forget('admin_batch_full_load_daily');
        Cache::forget('admin_batch_full_load_weekly');
        Cache::forget('admin_batch_full_load_monthly');
        Cache::forget('admin_batch_full_load_yearly');
        Cache::forget('admin_batch_full_load_all');

        // Clear admin appointments listing cache so status changes appear immediately
        // Use a tagged approach: store known cache keys and clear them all
        // Clear every possible combination of admin_appointments cache keys
        Cache::forget('admin_appointments_' . md5(json_encode([])));
        Cache::forget('admin_appointments_' . md5('[]'));
        // Clear with all possible param orders and timeframe combos
        foreach (['daily', 'weekly', 'monthly', 'yearly', 'all'] as $tf) {
            Cache::forget('admin_appointments_' . md5(json_encode(['limit' => '1000', 'timeframe' => $tf])));
            Cache::forget('admin_appointments_' . md5(json_encode(['timeframe' => $tf, 'limit' => '1000'])));
            Cache::forget('admin_appointments_' . md5(json_encode(['timeframe' => $tf])));
            // Also clear with integer limit (in case PHP casts differently)
            Cache::forget('admin_appointments_' . md5(json_encode(['limit' => 1000, 'timeframe' => $tf])));
        }
        // Note: Cache::flush() was removed here - it nukes the ENTIRE application cache
        // including sessions, rate-limit counters, and chatbot caches. The targeted
        // Cache::forget() calls above handle appointment-specific cache invalidation.

        // Clear analytics caches - when appointments change, analytics need to be recalculated
        Cache::forget('analytics_slot_utilization_30');
        Cache::forget('analytics_slot_utilization_7');
        Cache::forget('analytics_no_show_patterns_90');
        Cache::forget('analytics_demand_forecast_30');
        Cache::forget('analytics_quality_report_90');
        Cache::forget('analytics_auto_alerts');
        Cache::forget('analytics_dashboard_comprehensive');
    }

    /**
     * Get completed appointments for public testimonials
     * (Landing page doesn't require authentication)
     */
    public function getCompletedAppointmentsPublic(Request $request)
    {
        try {
            // Cap limit to prevent data dumping
            $limit = min((int)$request->input('limit', 3), 10);

            $appointments = Appointment::where('status', 'completed')
                ->with([
                    'user:id,first_name,last_name',
                    'service:id,name,price'
                ])
                ->orderBy('completed_at', 'desc')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($apt) {
                    return [
                        // Do not expose internal IDs or private notes publicly
                        'user' => [
                            'name' => ($apt->user ? substr($apt->user->first_name, 0, 1) . '. ' . $apt->user->last_name : 'Client')
                        ],
                        'type' => $apt->service?->name ?? $apt->service_type ?? 'Service',
                        'completed_at' => $apt->completed_at?->toDateTimeString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $appointments,
                'count' => count($appointments),
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching completed appointments for testimonials', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Unable to fetch testimonials',
                'timestamp' => now()->toDateTimeString()
            ], 500);
        }
    }
}