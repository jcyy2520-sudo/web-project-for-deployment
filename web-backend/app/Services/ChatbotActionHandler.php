<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use App\Models\Notification;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * ChatbotActionHandler - Execute system actions through the chatbot
 * 
 * This handler processes action-based requests from the chatbot, enforcing role-based
 * security and ensuring all actions follow business rules.
 * 
 * ✅ Action Execution - Actually perform operations (approve, cancel, process, etc.)
 * ✅ Role-Based Security - Verify permissions before executing actions
 * ✅ Audit Trail - Log all chatbot-initiated actions
 * ✅ Business Rule Enforcement - Follow all system validation rules
 * ✅ Real-time Data - Always use current database state
 */
class ChatbotActionHandler
{
    // Action types
    const ACTION_VIEW = 'view';
    const ACTION_APPROVE = 'approve';
    const ACTION_DECLINE = 'decline';
    const ACTION_CANCEL = 'cancel';
    const ACTION_COMPLETE = 'complete';
    const ACTION_RESCHEDULE = 'reschedule';
    const ACTION_PROCESS_PAYMENT = 'process_payment';
    const ACTION_PROCESS_REFUND = 'process_refund';
    const ACTION_SEND_NOTIFICATION = 'send_notification';

    // Role permissions mapping
    private static array $rolePermissions = [
        'client' => [
            'appointment' => ['view', 'cancel', 'reschedule'],
            'payment' => ['view'],
            'refund' => ['view', 'request'],
            'notification' => ['view'],
        ],
        'cashier' => [
            'appointment' => ['view'],
            'payment' => ['view', 'process', 'verify'],
            'refund' => ['view', 'process', 'approve'],
            'notification' => ['view', 'send'],
        ],
        'admin' => [
            'appointment' => ['view', 'approve', 'decline', 'cancel', 'complete', 'reschedule'],
            'payment' => ['view', 'process', 'verify', 'refund'],
            'refund' => ['view', 'approve', 'decline', 'process'],
            'notification' => ['view', 'send', 'broadcast'],
            'user' => ['view', 'manage', 'disable'],
            'service' => ['view', 'manage'],
            'system' => ['view', 'configure'],
        ],
        'staff' => [
            'appointment' => ['view', 'approve', 'decline', 'complete'],
            'payment' => ['view'],
            'refund' => ['view'],
            'notification' => ['view', 'send'],
        ],
    ];

    /**
     * Check if a role has permission for a specific action on a resource
     */
    public static function hasPermission(string $role, string $resource, string $action): bool
    {
        $role = strtolower($role);
        $permissions = self::$rolePermissions[$role] ?? [];
        $resourcePermissions = $permissions[$resource] ?? [];
        
        return in_array($action, $resourcePermissions);
    }

    /**
     * Execute a chatbot-requested action with full security and validation
     */
    public static function executeAction(array $params): array
    {
        $action = $params['action'] ?? null;
        $resource = $params['resource'] ?? null;
        $resourceId = $params['resource_id'] ?? null;
        $userId = $params['user_id'] ?? null;
        $role = strtolower($params['role'] ?? 'guest');
        $data = $params['data'] ?? [];

        // Validate required parameters
        if (!$action || !$resource) {
            return [
                'success' => false,
                'message' => 'Action and resource are required.',
                'code' => 'INVALID_PARAMS'
            ];
        }

        // Check permissions
        if (!self::hasPermission($role, $resource, $action)) {
            return [
                'success' => false,
                'message' => "You don't have permission to {$action} {$resource}. This action requires " . self::getRequiredRole($resource, $action) . " access.",
                'code' => 'PERMISSION_DENIED'
            ];
        }

        // Route to appropriate handler
        try {
            $result = match($resource) {
                'appointment' => self::handleAppointmentAction($action, $resourceId, $userId, $role, $data),
                'payment' => self::handlePaymentAction($action, $resourceId, $userId, $role, $data),
                'refund' => self::handleRefundAction($action, $resourceId, $userId, $role, $data),
                'notification' => self::handleNotificationAction($action, $resourceId, $userId, $role, $data),
                'user' => self::handleUserAction($action, $resourceId, $userId, $role, $data),
                'service' => self::handleServiceAction($action, $resourceId, $userId, $role, $data),
                'system' => self::handleSystemAction($action, $userId, $role, $data),
                default => ['success' => false, 'message' => 'Unknown resource type.', 'code' => 'UNKNOWN_RESOURCE']
            };

            // Log the action
            if ($result['success'] ?? false) {
                self::logAction($action, $resource, $resourceId, $userId, $role, $result);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('ChatbotActionHandler error: ' . $e->getMessage(), [
                'action' => $action,
                'resource' => $resource,
                'resource_id' => $resourceId,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
                'code' => 'EXECUTION_ERROR'
            ];
        }
    }

    /**
     * Handle appointment-related actions
     */
    private static function handleAppointmentAction(string $action, ?int $appointmentId, ?int $userId, string $role, array $data): array
    {
        // For view actions without ID, return list
        if ($action === 'view' && !$appointmentId) {
            return self::viewAppointments($userId, $role, $data);
        }

        // Validate appointment exists
        if (!$appointmentId) {
            return ['success' => false, 'message' => 'Appointment ID is required.', 'code' => 'MISSING_ID'];
        }

        $appointment = Appointment::with(['user', 'service', 'payments'])->find($appointmentId);
        
        if (!$appointment) {
            return ['success' => false, 'message' => "Appointment #{$appointmentId} not found.", 'code' => 'NOT_FOUND'];
        }

        // For clients, verify ownership
        if ($role === 'client' && $appointment->user_id !== $userId) {
            return ['success' => false, 'message' => 'You can only manage your own appointments.', 'code' => 'OWNERSHIP_DENIED'];
        }

        return match($action) {
            'view' => self::viewAppointmentDetails($appointment),
            'approve' => self::approveAppointment($appointment, $userId),
            'decline' => self::declineAppointment($appointment, $userId, $data['reason'] ?? null),
            'cancel' => self::cancelAppointment($appointment, $userId, $role, $data['reason'] ?? null),
            'complete' => self::completeAppointment($appointment, $userId),
            'reschedule' => self::rescheduleAppointment($appointment, $userId, $role, $data),
            default => ['success' => false, 'message' => 'Unknown appointment action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View appointments list based on role
     */
    private static function viewAppointments(?int $userId, string $role, array $filters = []): array
    {
        $query = Appointment::with(['user:id,first_name,last_name,email', 'service:id,name,price'])
            ->orderBy('appointment_date', 'desc');

        // Apply role-based filtering
        if ($role === 'client') {
            $query->where('user_id', $userId);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date filter
        if (!empty($filters['date'])) {
            $query->whereDate('appointment_date', $filters['date']);
        }

        // Default to today's and future appointments for admin/cashier
        if (in_array($role, ['admin', 'cashier', 'staff']) && empty($filters['date']) && empty($filters['status'])) {
            $query->whereDate('appointment_date', '>=', Carbon::today());
        }

        $appointments = $query->limit($filters['limit'] ?? 10)->get();

        return [
            'success' => true,
            'message' => "Found {$appointments->count()} appointment(s).",
            'data' => [
                'appointments' => $appointments->map(fn($apt) => [
                    'id' => $apt->id,
                    'client' => trim(($apt->user->first_name ?? '') . ' ' . ($apt->user->last_name ?? '')),
                    'service' => $apt->service->name ?? 'N/A',
                    'date' => $apt->appointment_date->format('M d, Y'),
                    'time' => $apt->appointment_time,
                    'status' => $apt->status,
                ])->toArray(),
                'count' => $appointments->count(),
            ]
        ];
    }

    /**
     * View single appointment details
     */
    private static function viewAppointmentDetails(Appointment $appointment): array
    {
        $payment = $appointment->payments->first();
        
        return [
            'success' => true,
            'message' => "Appointment #{$appointment->id} details retrieved.",
            'data' => [
                'appointment' => [
                    'id' => $appointment->id,
                    'client' => [
                        'name' => trim(($appointment->user->first_name ?? '') . ' ' . ($appointment->user->last_name ?? '')),
                        'email' => $appointment->user->email ?? '',
                        'phone' => $appointment->user->phone ?? '',
                    ],
                    'service' => [
                        'name' => $appointment->service->name ?? 'N/A',
                        'price' => $appointment->service->price ?? 0,
                        'duration' => $appointment->service->duration ?? null,
                    ],
                    'date' => $appointment->appointment_date->format('M d, Y'),
                    'time' => $appointment->appointment_time,
                    'status' => $appointment->status,
                    'notes' => $appointment->notes ?? null,
                    'payment_status' => $payment?->payment_status ?? 'unpaid',
                    'created_at' => $appointment->created_at->format('M d, Y H:i'),
                ]
            ]
        ];
    }

    /**
     * Approve an appointment
     */
    private static function approveAppointment(Appointment $appointment, ?int $approvedBy): array
    {
        if ($appointment->status !== 'pending') {
            return [
                'success' => false,
                'message' => "Cannot approve: appointment is currently '{$appointment->status}'. Only pending appointments can be approved.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $appointment->status = 'approved';
        $appointment->approved_by = $approvedBy;
        $appointment->approved_at = now();
        $appointment->save();

        // Reload with relationships for notifications
        $appointment->refresh();
        $appointment->load(['user', 'service']);

        // Create notification for client
        self::createNotification(
            $appointment->user_id,
            'Appointment Approved',
            "Your appointment on {$appointment->appointment_date->format('M d, Y')} at {$appointment->appointment_time} has been approved.",
            'appointment',
            $appointment->id
        );

        // Notify cashiers about the approved appointment ready for payment
        try {
            \App\Services\NotificationService::notifyCashiersAppointmentApproved($appointment);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify cashiers via chatbot action: ' . $e->getMessage());
        }

        // Broadcast appointment update for realtime clients
        try {
            event(new \App\Events\AppointmentUpdated($appointment));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::debug('Failed to broadcast appointment update: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => "Appointment #{$appointment->id} has been approved. The client and cashiers have been notified.",
            'data' => ['appointment_id' => $appointment->id, 'new_status' => 'approved']
        ];
    }

    /**
     * Decline an appointment
     */
    private static function declineAppointment(Appointment $appointment, ?int $declinedBy, ?string $reason): array
    {
        if (!in_array($appointment->status, ['pending', 'approved'])) {
            return [
                'success' => false,
                'message' => "Cannot decline: appointment is currently '{$appointment->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $appointment->status = 'declined';
        $appointment->declined_by = $declinedBy;
        $appointment->declined_at = now();
        $appointment->decline_reason = $reason;
        $appointment->save();

        // Create notification for client
        self::createNotification(
            $appointment->user_id,
            'Appointment Declined',
            "Your appointment on {$appointment->appointment_date->format('M d, Y')} has been declined." . ($reason ? " Reason: {$reason}" : ''),
            'appointment',
            $appointment->id
        );

        return [
            'success' => true,
            'message' => "Appointment #{$appointment->id} has been declined. The client has been notified.",
            'data' => ['appointment_id' => $appointment->id, 'new_status' => 'declined', 'reason' => $reason]
        ];
    }

    /**
     * Cancel an appointment
     */
    private static function cancelAppointment(Appointment $appointment, ?int $cancelledBy, string $role, ?string $reason): array
    {
        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return [
                'success' => false,
                'message' => "Cannot cancel: appointment is already '{$appointment->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        // Check cancellation rules for clients
        if ($role === 'client') {
            $settings = \App\Models\AppointmentSettings::latest()->first();
            $cancellationDays = $settings->cancellation_window_days ?? 1;
            $appointmentDate = Carbon::parse($appointment->appointment_date);
            
            if ($appointmentDate->diffInDays(now()) < $cancellationDays) {
                return [
                    'success' => false,
                    'message' => "Appointments must be cancelled at least {$cancellationDays} day(s) in advance.",
                    'code' => 'CANCELLATION_WINDOW'
                ];
            }
        }

        $appointment->status = 'cancelled';
        $appointment->cancelled_by = $cancelledBy;
        $appointment->cancelled_at = now();
        $appointment->cancellation_reason = $reason;
        $appointment->save();

        // Notify relevant parties
        if ($role === 'client') {
            // Notify admin about client cancellation
            self::notifyAdmins(
                'Appointment Cancelled',
                "Client {$appointment->user->first_name} cancelled their appointment on {$appointment->appointment_date->format('M d, Y')}."
            );
        } else {
            // Notify client about admin/staff cancellation
            self::createNotification(
                $appointment->user_id,
                'Appointment Cancelled',
                "Your appointment on {$appointment->appointment_date->format('M d, Y')} has been cancelled." . ($reason ? " Reason: {$reason}" : ''),
                'appointment',
                $appointment->id
            );
        }

        return [
            'success' => true,
            'message' => "Appointment #{$appointment->id} has been cancelled.",
            'data' => ['appointment_id' => $appointment->id, 'new_status' => 'cancelled']
        ];
    }

    /**
     * Complete an appointment
     */
    private static function completeAppointment(Appointment $appointment, ?int $completedBy): array
    {
        if ($appointment->status !== 'approved') {
            return [
                'success' => false,
                'message' => "Cannot complete: only approved appointments can be marked as complete. Current status: '{$appointment->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $appointment->status = 'completed';
        $appointment->completed_by = $completedBy;
        $appointment->completed_at = now();
        $appointment->save();

        // Notify client
        self::createNotification(
            $appointment->user_id,
            'Appointment Completed',
            "Your appointment on {$appointment->appointment_date->format('M d, Y')} has been marked as complete. Thank you!",
            'appointment',
            $appointment->id
        );

        return [
            'success' => true,
            'message' => "Appointment #{$appointment->id} marked as complete.",
            'data' => ['appointment_id' => $appointment->id, 'new_status' => 'completed']
        ];
    }

    /**
     * Reschedule an appointment
     */
    private static function rescheduleAppointment(Appointment $appointment, ?int $userId, string $role, array $data): array
    {
        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return [
                'success' => false,
                'message' => "Cannot reschedule: appointment is '{$appointment->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $newDate = $data['new_date'] ?? null;
        $newTime = $data['new_time'] ?? null;

        if (!$newDate || !$newTime) {
            return [
                'success' => false,
                'message' => "Please provide a new date and time for the reschedule. Example: 'Reschedule appointment #123 to January 15, 2025 at 2:00 PM'",
                'code' => 'MISSING_DATA',
                'action_required' => 'provide_datetime'
            ];
        }

        try {
            $parsedDate = Carbon::parse($newDate);
            
            // Validate date is in the future
            if ($parsedDate->isPast()) {
                return [
                    'success' => false,
                    'message' => 'The new date must be in the future.',
                    'code' => 'PAST_DATE'
                ];
            }

            // Check availability (simplified - in production would check actual slot availability)
            $existingCount = Appointment::whereDate('appointment_date', $parsedDate)
                ->where('appointment_time', $newTime)
                ->where('status', '!=', 'cancelled')
                ->where('id', '!=', $appointment->id)
                ->count();

            if ($existingCount >= 5) { // Assuming max 5 appointments per slot
                return [
                    'success' => false,
                    'message' => 'The requested time slot is not available. Please choose a different time.',
                    'code' => 'SLOT_UNAVAILABLE'
                ];
            }

            $oldDate = $appointment->appointment_date->format('M d, Y');
            $oldTime = $appointment->appointment_time;

            $appointment->appointment_date = $parsedDate;
            $appointment->appointment_time = $newTime;
            $appointment->rescheduled_by = $userId;
            $appointment->rescheduled_at = now();
            $appointment->save();

            // Notify about reschedule
            if ($role === 'client') {
                self::notifyAdmins(
                    'Appointment Rescheduled',
                    "Client {$appointment->user->first_name} rescheduled from {$oldDate} to {$parsedDate->format('M d, Y')} at {$newTime}."
                );
            } else {
                self::createNotification(
                    $appointment->user_id,
                    'Appointment Rescheduled',
                    "Your appointment has been rescheduled from {$oldDate} at {$oldTime} to {$parsedDate->format('M d, Y')} at {$newTime}.",
                    'appointment',
                    $appointment->id
                );
            }

            return [
                'success' => true,
                'message' => "Appointment #{$appointment->id} rescheduled to {$parsedDate->format('M d, Y')} at {$newTime}.",
                'data' => [
                    'appointment_id' => $appointment->id,
                    'old_date' => $oldDate,
                    'old_time' => $oldTime,
                    'new_date' => $parsedDate->format('M d, Y'),
                    'new_time' => $newTime,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not parse the date. Please use a format like "January 15, 2025" or "2025-01-15".',
                'code' => 'INVALID_DATE'
            ];
        }
    }

    /**
     * Handle payment-related actions
     */
    private static function handlePaymentAction(string $action, ?int $paymentId, ?int $userId, string $role, array $data): array
    {
        if ($action === 'view' && !$paymentId) {
            return self::viewPayments($userId, $role, $data);
        }

        if (!$paymentId && $action === 'process') {
            // Try to process by appointment ID
            $appointmentId = $data['appointment_id'] ?? null;
            if ($appointmentId) {
                return self::processPaymentByAppointment($appointmentId, $userId, $data);
            }
            return ['success' => false, 'message' => 'Payment or appointment ID is required.', 'code' => 'MISSING_ID'];
        }

        $payment = Payment::with(['appointment.user', 'appointment.service'])->find($paymentId);
        
        if (!$payment) {
            return ['success' => false, 'message' => "Payment #{$paymentId} not found.", 'code' => 'NOT_FOUND'];
        }

        return match($action) {
            'view' => self::viewPaymentDetails($payment),
            'process' => self::processPayment($payment, $userId, $data),
            'verify' => self::verifyPayment($payment, $userId),
            default => ['success' => false, 'message' => 'Unknown payment action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View payments list
     */
    private static function viewPayments(?int $userId, string $role, array $filters = []): array
    {
        $query = Payment::with(['appointment.user:id,first_name,last_name', 'appointment.service:id,name'])
            ->orderBy('created_at', 'desc');

        if ($role === 'client') {
            $query->whereHas('appointment', fn($q) => $q->where('user_id', $userId));
        }

        if (!empty($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }

        $payments = $query->limit($filters['limit'] ?? 10)->get();

        return [
            'success' => true,
            'message' => "Found {$payments->count()} payment(s).",
            'data' => [
                'payments' => $payments->map(fn($p) => [
                    'id' => $p->id,
                    'client' => trim(($p->appointment->user->first_name ?? '') . ' ' . ($p->appointment->user->last_name ?? '')),
                    'service' => $p->appointment->service->name ?? 'N/A',
                    'amount' => number_format($p->amount_paid ?? $p->total_amount ?? 0, 2),
                    'status' => $p->payment_status,
                    'date' => $p->payment_date?->format('M d, Y') ?? 'Pending',
                ])->toArray()
            ]
        ];
    }

    /**
     * View payment details
     */
    private static function viewPaymentDetails(Payment $payment): array
    {
        return [
            'success' => true,
            'message' => "Payment #{$payment->id} details retrieved.",
            'data' => [
                'payment' => [
                    'id' => $payment->id,
                    'appointment_id' => $payment->appointment_id,
                    'client' => trim(($payment->appointment->user->first_name ?? '') . ' ' . ($payment->appointment->user->last_name ?? '')),
                    'service' => $payment->appointment->service->name ?? 'N/A',
                    'total_amount' => number_format($payment->total_amount ?? 0, 2),
                    'amount_paid' => number_format($payment->amount_paid ?? 0, 2),
                    'discount' => number_format($payment->total_discount_applied ?? 0, 2),
                    'status' => $payment->payment_status,
                    'payment_method' => $payment->payment_method ?? 'N/A',
                    'payment_date' => $payment->payment_date?->format('M d, Y H:i') ?? 'Not paid',
                ]
            ]
        ];
    }

    /**
     * Process a payment
     */
    private static function processPayment(Payment $payment, ?int $processedBy, array $data): array
    {
        if ($payment->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => "Payment #{$payment->id} has already been processed.",
                'code' => 'ALREADY_PROCESSED'
            ];
        }

        $payment->payment_status = 'paid';
        $payment->payment_date = now();
        $payment->processed_by = $processedBy;
        $payment->payment_method = $data['method'] ?? 'cash';
        $payment->amount_paid = $data['amount'] ?? $payment->total_amount;
        $payment->save();

        // Notify client
        self::createNotification(
            $payment->appointment->user_id,
            'Payment Received',
            "Your payment of PHP " . number_format($payment->amount_paid, 2) . " has been received. Thank you!",
            'payment',
            $payment->id
        );

        return [
            'success' => true,
            'message' => "Payment #{$payment->id} processed successfully. Amount: PHP " . number_format($payment->amount_paid, 2),
            'data' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount_paid,
                'status' => 'paid'
            ]
        ];
    }

    /**
     * Process payment by appointment
     */
    private static function processPaymentByAppointment(int $appointmentId, ?int $processedBy, array $data): array
    {
        $appointment = Appointment::with(['service', 'payments'])->find($appointmentId);
        
        if (!$appointment) {
            return ['success' => false, 'message' => "Appointment #{$appointmentId} not found.", 'code' => 'NOT_FOUND'];
        }

        // Check if payment already exists
        $payment = $appointment->payments->first();
        
        if ($payment && $payment->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => "Payment for appointment #{$appointmentId} has already been processed.",
                'code' => 'ALREADY_PAID'
            ];
        }

        if (!$payment) {
            // Create new payment
            $payment = Payment::create([
                'appointment_id' => $appointmentId,
                'total_amount' => $appointment->service->price ?? 0,
                'amount_paid' => $data['amount'] ?? $appointment->service->price ?? 0,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $data['method'] ?? 'cash',
                'processed_by' => $processedBy,
            ]);
        } else {
            return self::processPayment($payment, $processedBy, $data);
        }

        // Notify client
        self::createNotification(
            $appointment->user_id,
            'Payment Received',
            "Your payment of PHP " . number_format($payment->amount_paid, 2) . " for your appointment has been received.",
            'payment',
            $payment->id
        );

        return [
            'success' => true,
            'message' => "Payment for appointment #{$appointmentId} processed successfully. Amount: PHP " . number_format($payment->amount_paid, 2),
            'data' => ['payment_id' => $payment->id, 'amount' => $payment->amount_paid]
        ];
    }

    /**
     * Verify a payment
     */
    private static function verifyPayment(Payment $payment, ?int $verifiedBy): array
    {
        return [
            'success' => true,
            'message' => "Payment #{$payment->id} verification complete.",
            'data' => [
                'payment_id' => $payment->id,
                'is_paid' => $payment->payment_status === 'paid',
                'amount_expected' => $payment->total_amount,
                'amount_received' => $payment->amount_paid,
                'verified_at' => now()->toIso8601String()
            ]
        ];
    }

    /**
     * Handle refund-related actions
     */
    private static function handleRefundAction(string $action, ?int $refundId, ?int $userId, string $role, array $data): array
    {
        if ($action === 'view' && !$refundId) {
            return self::viewRefunds($userId, $role, $data);
        }

        if ($action === 'request') {
            return self::requestRefund($userId, $data);
        }

        if (!$refundId) {
            return ['success' => false, 'message' => 'Refund ID is required.', 'code' => 'MISSING_ID'];
        }

        $refund = Refund::with(['appointment.user', 'appointment.service'])->find($refundId);
        
        if (!$refund) {
            return ['success' => false, 'message' => "Refund #{$refundId} not found.", 'code' => 'NOT_FOUND'];
        }

        return match($action) {
            'view' => self::viewRefundDetails($refund),
            'approve' => self::approveRefund($refund, $userId),
            'decline' => self::declineRefund($refund, $userId, $data['reason'] ?? null),
            'process' => self::processRefund($refund, $userId),
            default => ['success' => false, 'message' => 'Unknown refund action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View refunds list
     */
    private static function viewRefunds(?int $userId, string $role, array $filters = []): array
    {
        $query = Refund::with(['appointment.user:id,first_name,last_name', 'appointment.service:id,name'])
            ->orderBy('created_at', 'desc');

        if ($role === 'client') {
            $query->where('requested_by', $userId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $refunds = $query->limit($filters['limit'] ?? 10)->get();

        return [
            'success' => true,
            'message' => "Found {$refunds->count()} refund request(s).",
            'data' => [
                'refunds' => $refunds->map(fn($r) => [
                    'id' => $r->id,
                    'client' => trim(($r->appointment->user->first_name ?? '') . ' ' . ($r->appointment->user->last_name ?? '')),
                    'amount' => number_format($r->refund_amount, 2),
                    'reason' => $r->reason ?? 'N/A',
                    'status' => $r->status,
                    'requested_at' => $r->created_at->format('M d, Y'),
                ])->toArray()
            ]
        ];
    }

    /**
     * View refund details
     */
    private static function viewRefundDetails(Refund $refund): array
    {
        return [
            'success' => true,
            'message' => "Refund #{$refund->id} details retrieved.",
            'data' => [
                'refund' => [
                    'id' => $refund->id,
                    'appointment_id' => $refund->appointment_id,
                    'client' => trim(($refund->appointment->user->first_name ?? '') . ' ' . ($refund->appointment->user->last_name ?? '')),
                    'refund_amount' => number_format($refund->refund_amount, 2),
                    'original_amount' => number_format($refund->original_amount ?? 0, 2),
                    'reason' => $refund->reason ?? 'Not specified',
                    'status' => $refund->status,
                    'requested_at' => $refund->created_at->format('M d, Y H:i'),
                    'completed_at' => $refund->completed_at?->format('M d, Y H:i'),
                ]
            ]
        ];
    }

    /**
     * Request a new refund (client action)
     */
    private static function requestRefund(?int $userId, array $data): array
    {
        $appointmentId = $data['appointment_id'] ?? null;
        $reason = $data['reason'] ?? null;

        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID for the refund request.',
                'code' => 'MISSING_APPOINTMENT',
                'action_required' => 'provide_appointment_id'
            ];
        }

        $appointment = Appointment::with(['service', 'payments'])->find($appointmentId);
        
        if (!$appointment) {
            return ['success' => false, 'message' => "Appointment #{$appointmentId} not found.", 'code' => 'NOT_FOUND'];
        }

        // Verify ownership
        if ($appointment->user_id !== $userId) {
            return ['success' => false, 'message' => 'You can only request refunds for your own appointments.', 'code' => 'OWNERSHIP_DENIED'];
        }

        // Check if already refunded
        $existingRefund = Refund::where('appointment_id', $appointmentId)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->first();
        
        if ($existingRefund) {
            return [
                'success' => false,
                'message' => "A refund request already exists for this appointment (Status: {$existingRefund->status}).",
                'code' => 'DUPLICATE_REQUEST'
            ];
        }

        // Get payment amount
        $payment = $appointment->payments->first();
        $refundAmount = $payment?->amount_paid ?? $appointment->service->price ?? 0;

        // Create refund request
        $refund = Refund::create([
            'appointment_id' => $appointmentId,
            'requested_by' => $userId,
            'refund_amount' => $refundAmount,
            'original_amount' => $payment?->amount_paid ?? $refundAmount,
            'reason' => $reason ?? 'Requested via chatbot',
            'status' => 'pending',
        ]);

        // Notify admins/cashiers
        self::notifyAdmins(
            'New Refund Request',
            "A refund request for PHP " . number_format($refundAmount, 2) . " has been submitted for appointment #{$appointmentId}."
        );

        return [
            'success' => true,
            'message' => "Refund request submitted successfully. Amount: PHP " . number_format($refundAmount, 2) . ". Your request will be reviewed shortly.",
            'data' => ['refund_id' => $refund->id, 'amount' => $refundAmount, 'status' => 'pending']
        ];
    }

    /**
     * Approve a refund request
     */
    private static function approveRefund(Refund $refund, ?int $approvedBy): array
    {
        if ($refund->status !== 'pending') {
            return [
                'success' => false,
                'message' => "Cannot approve: refund is currently '{$refund->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $refund->status = 'approved';
        $refund->approved_by = $approvedBy;
        $refund->approved_at = now();
        $refund->save();

        // Notify client
        self::createNotification(
            $refund->requested_by,
            'Refund Approved',
            "Your refund request of PHP " . number_format($refund->refund_amount, 2) . " has been approved and will be processed shortly.",
            'refund',
            $refund->id
        );

        return [
            'success' => true,
            'message' => "Refund #{$refund->id} approved. Amount: PHP " . number_format($refund->refund_amount, 2),
            'data' => ['refund_id' => $refund->id, 'status' => 'approved']
        ];
    }

    /**
     * Decline a refund request
     */
    private static function declineRefund(Refund $refund, ?int $declinedBy, ?string $reason): array
    {
        if ($refund->status !== 'pending') {
            return [
                'success' => false,
                'message' => "Cannot decline: refund is currently '{$refund->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $refund->status = 'declined';
        $refund->declined_by = $declinedBy;
        $refund->declined_at = now();
        $refund->decline_reason = $reason;
        $refund->save();

        // Notify client
        self::createNotification(
            $refund->requested_by,
            'Refund Declined',
            "Your refund request has been declined." . ($reason ? " Reason: {$reason}" : ''),
            'refund',
            $refund->id
        );

        return [
            'success' => true,
            'message' => "Refund #{$refund->id} has been declined.",
            'data' => ['refund_id' => $refund->id, 'status' => 'declined']
        ];
    }

    /**
     * Process/complete a refund
     */
    private static function processRefund(Refund $refund, ?int $processedBy): array
    {
        if (!in_array($refund->status, ['pending', 'approved'])) {
            return [
                'success' => false,
                'message' => "Cannot process: refund is currently '{$refund->status}'.",
                'code' => 'INVALID_STATUS'
            ];
        }

        $refund->status = 'completed';
        $refund->completed_by = $processedBy;
        $refund->completed_at = now();
        $refund->save();

        // Notify client
        self::createNotification(
            $refund->requested_by,
            'Refund Completed',
            "Your refund of PHP " . number_format($refund->refund_amount, 2) . " has been processed.",
            'refund',
            $refund->id
        );

        return [
            'success' => true,
            'message' => "Refund #{$refund->id} completed. Amount: PHP " . number_format($refund->refund_amount, 2),
            'data' => ['refund_id' => $refund->id, 'amount' => $refund->refund_amount, 'status' => 'completed']
        ];
    }

    /**
     * Handle notification actions
     */
    private static function handleNotificationAction(string $action, ?int $notificationId, ?int $userId, string $role, array $data): array
    {
        if ($action === 'view') {
            return self::viewNotifications($userId, $role, $data);
        }

        if ($action === 'send') {
            return self::sendNotification($userId, $role, $data);
        }

        return ['success' => false, 'message' => 'Unknown notification action.', 'code' => 'UNKNOWN_ACTION'];
    }

    /**
     * View notifications
     */
    private static function viewNotifications(?int $userId, string $role, array $filters = []): array
    {
        $query = Notification::orderBy('created_at', 'desc');
        
        if ($role === 'client') {
            $query->where('user_id', $userId);
        }

        if (isset($filters['unread']) && $filters['unread']) {
            $query->where('is_read', false);
        }

        $notifications = $query->limit($filters['limit'] ?? 10)->get();
        $unreadCount = Notification::where('user_id', $userId)->where('is_read', false)->count();

        return [
            'success' => true,
            'message' => "You have {$unreadCount} unread notification(s).",
            'data' => [
                'notifications' => $notifications->map(fn($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'is_read' => $n->is_read,
                    'created_at' => $n->created_at->format('M d, Y H:i'),
                ])->toArray(),
                'unread_count' => $unreadCount
            ]
        ];
    }

    /**
     * Send a notification
     */
    private static function sendNotification(?int $senderId, string $role, array $data): array
    {
        $recipientId = $data['recipient_id'] ?? null;
        $title = $data['title'] ?? 'Notification';
        $message = $data['message'] ?? null;

        if (!$message) {
            return ['success' => false, 'message' => 'Message content is required.', 'code' => 'MISSING_MESSAGE'];
        }

        if ($recipientId) {
            self::createNotification($recipientId, $title, $message, 'chatbot', null);
            return ['success' => true, 'message' => 'Notification sent successfully.'];
        }

        if ($role === 'admin' && ($data['broadcast'] ?? false)) {
            // Broadcast to all clients
            $clients = User::role('client')->pluck('id');
            foreach ($clients as $clientId) {
                self::createNotification($clientId, $title, $message, 'broadcast', null);
            }
            return ['success' => true, 'message' => "Broadcast sent to {$clients->count()} clients."];
        }

        return ['success' => false, 'message' => 'Recipient ID is required.', 'code' => 'MISSING_RECIPIENT'];
    }

    /**
     * Handle user management actions (admin only)
     */
    private static function handleUserAction(string $action, ?int $targetUserId, ?int $adminId, string $role, array $data): array
    {
        if ($action === 'view' && !$targetUserId) {
            return self::viewUsers($data);
        }

        if (!$targetUserId) {
            return ['success' => false, 'message' => 'User ID is required.', 'code' => 'MISSING_ID'];
        }

        $user = User::find($targetUserId);
        
        if (!$user) {
            return ['success' => false, 'message' => "User #{$targetUserId} not found.", 'code' => 'NOT_FOUND'];
        }

        return match($action) {
            'view' => self::viewUserDetails($user),
            default => ['success' => false, 'message' => 'Unknown user action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View users list
     */
    private static function viewUsers(array $filters = []): array
    {
        $query = User::query()->orderBy('created_at', 'desc');

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        $users = $query->limit($filters['limit'] ?? 10)->get();

        return [
            'success' => true,
            'message' => "Found {$users->count()} user(s).",
            'data' => [
                'users' => $users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => trim($u->first_name . ' ' . $u->last_name),
                    'email' => $u->email,
                    'role' => $u->getRoleNames()->first() ?? 'user',
                    'created_at' => $u->created_at->format('M d, Y'),
                ])->toArray()
            ]
        ];
    }

    /**
     * View user details
     */
    private static function viewUserDetails(User $user): array
    {
        $appointmentsCount = Appointment::where('user_id', $user->id)->count();
        
        return [
            'success' => true,
            'message' => "User details for {$user->first_name} {$user->last_name}.",
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->getRoleNames()->first() ?? 'user',
                    'total_appointments' => $appointmentsCount,
                    'email_verified' => !empty($user->email_verified_at),
                    'created_at' => $user->created_at->format('M d, Y'),
                ]
            ]
        ];
    }

    /**
     * Handle service actions
     */
    private static function handleServiceAction(string $action, ?int $serviceId, ?int $userId, string $role, array $data): array
    {
        if ($action === 'view' && !$serviceId) {
            return self::viewServices($data);
        }

        if (!$serviceId) {
            return ['success' => false, 'message' => 'Service ID is required.', 'code' => 'MISSING_ID'];
        }

        $service = Service::find($serviceId);
        
        if (!$service) {
            return ['success' => false, 'message' => "Service #{$serviceId} not found.", 'code' => 'NOT_FOUND'];
        }

        return match($action) {
            'view' => self::viewServiceDetails($service),
            default => ['success' => false, 'message' => 'Unknown service action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View services list
     */
    private static function viewServices(array $filters = []): array
    {
        $query = Service::query()->orderBy('name');

        if (!isset($filters['include_inactive']) || !$filters['include_inactive']) {
            $query->where('is_active', true);
        }

        $services = $query->get();

        return [
            'success' => true,
            'message' => "Found {$services->count()} service(s).",
            'data' => [
                'services' => $services->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => number_format($s->price ?? 0, 2),
                    'duration' => ($s->duration ?? 0) . ' minutes',
                    'is_active' => $s->is_active,
                ])->toArray()
            ]
        ];
    }

    /**
     * View service details
     */
    private static function viewServiceDetails(Service $service): array
    {
        $appointmentsCount = Appointment::where('service_id', $service->id)->count();
        
        return [
            'success' => true,
            'message' => "Service: {$service->name}",
            'data' => [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => number_format($service->price ?? 0, 2),
                    'duration' => ($service->duration ?? 0) . ' minutes',
                    'is_active' => $service->is_active,
                    'total_appointments' => $appointmentsCount,
                ]
            ]
        ];
    }

    /**
     * Handle system actions (admin only)
     */
    private static function handleSystemAction(string $action, ?int $userId, string $role, array $data): array
    {
        return match($action) {
            'view' => self::viewSystemStatus(),
            default => ['success' => false, 'message' => 'Unknown system action.', 'code' => 'UNKNOWN_ACTION']
        };
    }

    /**
     * View system status
     */
    private static function viewSystemStatus(): array
    {
        $health = ChatbotServiceEnhancements::getSystemHealth();
        $analytics = ChatbotServiceEnhancements::getAdminAnalytics();

        return [
            'success' => true,
            'message' => "System status: {$health['status']}",
            'data' => [
                'health' => $health,
                'analytics' => $analytics
            ]
        ];
    }

    // ================== HELPER METHODS ==================

    /**
     * Create a notification
     */
    private static function createNotification(int $userId, string $title, string $message, string $type = 'general', ?int $referenceId = null): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'reference_id' => $referenceId,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify all admins
     */
    private static function notifyAdmins(string $title, string $message): void
    {
        try {
            $admins = User::role('admin')->pluck('id');
            foreach ($admins as $adminId) {
                self::createNotification($adminId, $title, $message, 'system');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to notify admins: ' . $e->getMessage());
        }
    }

    /**
     * Log chatbot action for audit trail
     */
    private static function logAction(string $action, string $resource, ?int $resourceId, ?int $userId, string $role, array $result): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => "chatbot_{$action}_{$resource}",
                'model_type' => ucfirst($resource),
                'model_id' => $resourceId,
                'changes' => json_encode([
                    'action' => $action,
                    'resource' => $resource,
                    'role' => $role,
                    'result' => $result['success'] ?? false,
                    'message' => $result['message'] ?? null,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Don't fail on audit log errors
            Log::warning('Failed to create audit log: ' . $e->getMessage());
        }
    }

    /**
     * Get required role for an action
     */
    private static function getRequiredRole(string $resource, string $action): string
    {
        foreach (self::$rolePermissions as $role => $permissions) {
            if (isset($permissions[$resource]) && in_array($action, $permissions[$resource])) {
                return $role;
            }
        }
        return 'admin';
    }
}
