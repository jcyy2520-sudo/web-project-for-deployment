<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * ChatbotActionService - Execute actions through chatbot commands
 * 
 * This service handles action-based capabilities allowing users to perform
 * system operations through natural language commands via the chatbot.
 * 
 * Features:
 * - Role-based action permissions
 * - Transaction-safe operations
 * - Comprehensive error handling
 * - Action logging and audit trail
 * - Confirmation workflow for destructive actions
 * 
 * Security: All actions are validated against the user's role and permissions.
 */
class ChatbotActionService
{
    /**
     * Actions that require confirmation before execution
     */
    private const ACTIONS_REQUIRING_CONFIRMATION = [
        'cancel_appointment',
        'request_refund',
        'approve_refund',
        'reject_refund',
        'complete_refund',
        'delete_appointment',
    ];
    
    /**
     * Store pending actions awaiting confirmation
     */
    private static function storePendingAction(int $userId, string $action, array $params): string
    {
        $confirmationKey = 'chatbot_confirm_' . $userId . '_' . time();
        Cache::put($confirmationKey, [
            'action' => $action,
            'params' => $params,
            'created_at' => now(),
        ], 300); // 5 minutes expiry
        
        return $confirmationKey;
    }
    
    /**
     * Get and clear a pending action
     */
    public static function getPendingAction(int $userId, string $confirmationKey): ?array
    {
        $pending = Cache::get($confirmationKey);
        if ($pending) {
            Cache::forget($confirmationKey);
            return $pending;
        }
        return null;
    }
    /**
     * Execute an action based on the detected intent and user role
     * 
     * @param int $userId The authenticated user's ID
     * @param string $role The user's role (client, admin, cashier)
     * @param string $action The action to perform
     * @param array $params Parameters extracted from the user's message
     * @param bool $confirmed Whether this is a confirmed action (for destructive operations)
     * @return array Result of the action with success status and message
     */
    public static function executeAction(int $userId, string $role, string $action, array $params = [], bool $confirmed = false): array
    {
        // Validate role has permission for the action
        if (!self::canPerformAction($role, $action)) {
            return [
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'action' => $action,
                'role' => $role,
            ];
        }
        
        // Check if action requires confirmation
        if (!$confirmed && in_array($action, self::ACTIONS_REQUIRING_CONFIRMATION)) {
            $confirmationKey = self::storePendingAction($userId, $action, $params);
            return [
                'success' => true,
                'requires_confirmation' => true,
                'confirmation_key' => $confirmationKey,
                'message' => self::getConfirmationMessage($action, $params),
                'action' => $action,
            ];
        }
        
        // Log action attempt
        Log::info('ChatbotAction executing', [
            'user_id' => $userId,
            'role' => $role,
            'action' => $action,
            'params' => $params,
        ]);
        
        try {
            $result = match($action) {
                // ==================== CLIENT ACTIONS ====================
                'check_appointment' => self::checkUserAppointment($userId, $params),
                'cancel_appointment' => self::cancelUserAppointment($userId, $params),
                'check_payment_status' => self::checkUserPaymentStatus($userId, $params),
                'request_refund' => self::requestRefund($userId, $params),
                'check_refund_status' => self::checkRefundStatus($userId, $params),
                'view_profile' => self::viewUserProfile($userId),
                'edit_profile' => self::editUserProfile($userId, $params),
                'view_appointments' => self::viewUserAppointments($userId, $params),
                'book_appointment' => self::initiateBooking($userId, $params),
                'view_services' => self::viewServices($params),
                
                // ==================== ADMIN ACTIONS ====================
                'approve_appointment' => self::approveAppointment($userId, $params),
                'decline_appointment' => self::declineAppointment($userId, $params),
                'complete_appointment' => self::completeAppointment($userId, $params),
                'get_system_health' => self::getSystemHealth(),
                'get_analytics' => self::getAnalytics($params),
                'approve_refund' => self::approveRefund($userId, $params),
                'reject_refund' => self::rejectRefund($userId, $params),
                'view_all_appointments' => self::viewAllAppointments($params),
                'manage_users' => self::manageUsers($params),
                'view_user_details' => self::viewUserDetails($params),
                
                // ==================== CASHIER ACTIONS ====================
                'process_payment' => self::processPayment($userId, $params),
                'get_shift_report' => self::getShiftReport($params),
                'get_pending_payments' => self::getPendingPayments(),
                'get_pending_refunds' => self::getPendingRefundsAction(),
                'complete_refund' => self::completeRefund($userId, $params),
                'verify_receipt' => self::verifyReceipt($params),
                'view_daily_summary' => self::viewDailySummary($params),
                
                // ==================== GENERAL ACTIONS ====================
                'get_help' => self::getHelp($role),
                'search' => self::searchSystem($userId, $role, $params),
                
                default => [
                    'success' => false,
                    'message' => "I'm not sure how to handle that action. Type 'help' to see available commands.",
                ],
            };
            
            // Log successful action
            if ($result['success'] ?? false) {
                Log::info('ChatbotAction completed', [
                    'user_id' => $userId,
                    'action' => $action,
                    'result' => 'success',
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('ChatbotActionService error', [
                'action' => $action,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
    
    /**
     * Get confirmation message for destructive actions
     */
    private static function getConfirmationMessage(string $action, array $params): string
    {
        return match($action) {
            'cancel_appointment' => 'Are you sure you want to cancel this appointment? Reply "yes" or "confirm" to proceed.',
            'request_refund' => 'Are you sure you want to request a refund? Reply "yes" or "confirm" to proceed.',
            'approve_refund' => 'Are you sure you want to approve this refund? Reply "yes" or "confirm" to proceed.',
            'reject_refund' => 'Are you sure you want to reject this refund? Reply "yes" or "confirm" to proceed.',
            'complete_refund' => 'Are you sure you want to mark this refund as completed? Reply "yes" or "confirm" to proceed.',
            'delete_appointment' => 'Are you sure you want to delete this appointment? This cannot be undone. Reply "yes" or "confirm" to proceed.',
            default => 'Please confirm this action by replying "yes" or "confirm".',
        };
    }
    
    /**
     * Check if a role can perform a specific action
     */
    private static function canPerformAction(string $role, string $action): bool
    {
        $permissions = [
            'client' => [
                'check_appointment',
                'cancel_appointment',
                'check_payment_status',
                'request_refund',
                'check_refund_status',
                'view_profile',
                'edit_profile',
                'view_appointments',
                'book_appointment',
                'view_services',
                'get_help',
                'search',
            ],
            'cashier' => [
                'check_appointment',
                'check_payment_status',
                'process_payment',
                'get_shift_report',
                'get_pending_payments',
                'get_pending_refunds',
                'complete_refund',
                'verify_receipt',
                'view_daily_summary',
                'get_help',
                'search',
            ],
            'admin' => [
                // Admin can do everything
                'check_appointment',
                'cancel_appointment',
                'check_payment_status',
                'request_refund',
                'check_refund_status',
                'view_profile',
                'edit_profile',
                'view_appointments',
                'book_appointment',
                'view_services',
                'approve_appointment',
                'decline_appointment',
                'complete_appointment',
                'get_system_health',
                'get_analytics',
                'approve_refund',
                'reject_refund',
                'process_payment',
                'get_shift_report',
                'get_pending_payments',
                'get_pending_refunds',
                'complete_refund',
                'verify_receipt',
                'view_daily_summary',
                'view_all_appointments',
                'manage_users',
                'view_user_details',
                'get_help',
                'search',
            ],
            'staff' => [
                'check_appointment',
                'approve_appointment',
                'decline_appointment',
                'complete_appointment',
                'get_analytics',
                'view_all_appointments',
                'get_help',
                'search',
            ],
            'guest' => [
                'view_services',
                'get_help',
            ],
        ];
        
        $allowedActions = $permissions[$role] ?? $permissions['guest'];
        return in_array($action, $allowedActions);
    }
    
    // ==================== CLIENT ACTION IMPLEMENTATIONS ====================
    
    /**
     * Check user's next/specific appointment
     */
    private static function checkUserAppointment(int $userId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        
        if ($appointmentId) {
            $appointment = Appointment::with(['service:id,name,price'])
                ->where('user_id', $userId)
                ->find($appointmentId);
                
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found or you do not have access to it.',
                ];
            }
            
            return [
                'success' => true,
                'message' => "Appointment #{$appointment->id}: {$appointment->service->name} on " . 
                    $appointment->appointment_date->format('M d, Y') . " at {$appointment->appointment_time}. Status: {$appointment->status}.",
                'data' => [
                    'id' => $appointment->id,
                    'service' => $appointment->service->name,
                    'date' => $appointment->appointment_date->format('M d, Y'),
                    'time' => $appointment->appointment_time,
                    'status' => $appointment->status,
                ],
            ];
        }
        
        // Get next upcoming appointment
        $appointment = Appointment::with(['service:id,name,price'])
            ->where('user_id', $userId)
            ->where('appointment_date', '>=', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->first();
            
        if (!$appointment) {
            return [
                'success' => true,
                'message' => 'You have no upcoming appointments.',
                'data' => null,
            ];
        }
        
        return [
            'success' => true,
            'message' => "Your next appointment: {$appointment->service->name} on " . 
                $appointment->appointment_date->format('M d, Y') . " at {$appointment->appointment_time}. Status: {$appointment->status}.",
            'data' => [
                'id' => $appointment->id,
                'service' => $appointment->service->name,
                'date' => $appointment->appointment_date->format('M d, Y'),
                'time' => $appointment->appointment_time,
                'status' => $appointment->status,
            ],
        ];
    }
    
    /**
     * Cancel user's appointment
     */
    private static function cancelUserAppointment(int $userId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        
        if (!$appointmentId) {
            // Try to find the next upcoming appointment
            $appointment = Appointment::where('user_id', $userId)
                ->where('appointment_date', '>=', Carbon::today())
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->orderBy('appointment_date')
                ->first();
                
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'No upcoming appointment found to cancel. Please specify an appointment ID.',
                ];
            }
            
            return [
                'success' => false,
                'message' => "Found appointment #{$appointment->id} on " . $appointment->appointment_date->format('M d, Y') . 
                    ". Please confirm: say 'cancel appointment #{$appointment->id}' to proceed.",
                'requires_confirmation' => true,
                'data' => ['appointment_id' => $appointment->id],
            ];
        }
        
        $appointment = Appointment::where('user_id', $userId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->find($appointmentId);
            
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or cannot be cancelled.',
            ];
        }
        
        // Check cancellation window (e.g., 24 hours before)
        $appointmentDateTime = Carbon::parse($appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
        if ($appointmentDateTime->diffInHours(Carbon::now()) < 24) {
            return [
                'success' => false,
                'message' => 'Cannot cancel appointment less than 24 hours before the scheduled time. Please contact support.',
            ];
        }
        
        $appointment->status = 'cancelled';
        $appointment->save();
        
        return [
            'success' => true,
            'message' => "Appointment #{$appointmentId} has been cancelled successfully.",
            'data' => ['appointment_id' => $appointmentId],
        ];
    }
    
    /**
     * Check user's payment status
     */
    private static function checkUserPaymentStatus(int $userId, array $params): array
    {
        $payments = ChatbotServiceEnhancements::getUserPayments($userId);
        
        if (empty($payments)) {
            return [
                'success' => true,
                'message' => 'You have no payment records.',
                'data' => [],
            ];
        }
        
        $latest = $payments[0];
        $pendingCount = count(array_filter($payments, fn($p) => in_array($p['payment_status'], ['unpaid', 'partial'])));
        
        $message = "Latest payment: {$latest['service']} on {$latest['date']} - ₱{$latest['amount_paid']} ({$latest['payment_status']}).";
        if ($pendingCount > 0) {
            $message .= " You have {$pendingCount} pending payment(s).";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $payments,
        ];
    }
    
    /**
     * Request a refund for user
     */
    private static function requestRefund(int $userId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        $reason = $params['reason'] ?? 'Requested via chatbot';
        
        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID for the refund request.',
            ];
        }
        
        // Check if appointment exists and belongs to user
        $appointment = Appointment::with(['payments'])
            ->where('user_id', $userId)
            ->find($appointmentId);
            
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or you do not have access to it.',
            ];
        }
        
        // Check if payment exists
        $payment = $appointment->payments->first();
        if (!$payment || $payment->payment_status !== 'paid') {
            return [
                'success' => false,
                'message' => 'No paid record found for this appointment. Refunds can only be requested for paid appointments.',
            ];
        }
        
        // Check if refund already exists
        $existingRefund = Refund::where('appointment_id', $appointmentId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();
            
        if ($existingRefund) {
            return [
                'success' => false,
                'message' => "A refund request already exists for this appointment (Status: {$existingRefund->status}).",
            ];
        }
        
        // Create refund request
        $refund = Refund::create([
            'appointment_id' => $appointmentId,
            'requested_by' => $userId,
            'refund_amount' => $payment->amount_paid,
            'original_amount' => $payment->amount_paid,
            'reason' => $reason,
            'status' => 'pending',
        ]);
        
        return [
            'success' => true,
            'message' => "Refund request submitted for ₱" . number_format($payment->amount_paid, 2) . ". Your request is now pending review.",
            'data' => ['refund_id' => $refund->id],
        ];
    }
    
    /**
     * Check user's refund status
     */
    private static function checkRefundStatus(int $userId, array $params): array
    {
        $refunds = ChatbotServiceEnhancements::getUserRefunds($userId);
        
        if (empty($refunds)) {
            return [
                'success' => true,
                'message' => 'You have no refund requests.',
                'data' => [],
            ];
        }
        
        $latest = $refunds[0];
        $pendingCount = count(array_filter($refunds, fn($r) => $r['status'] === 'pending'));
        
        $message = "Latest refund: ₱{$latest['amount']} - Status: {$latest['status']} (Requested: {$latest['requested_at']}).";
        if ($pendingCount > 0) {
            $message .= " You have {$pendingCount} pending refund request(s).";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $refunds,
        ];
    }
    
    // ==================== ADMIN ACTION IMPLEMENTATIONS ====================
    
    /**
     * Approve an appointment
     */
    private static function approveAppointment(int $adminId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        
        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID to approve.',
            ];
        }
        
        $appointment = Appointment::where('status', 'pending')->find($appointmentId);
        
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or is not in pending status.',
            ];
        }
        
        $appointment->status = 'approved';
        $appointment->save();
        
        // Reload with relationships for notifications
        $appointment->refresh();
        $appointment->load(['user', 'service']);

        // Notify the client
        try {
            \App\Services\NotificationService::appointmentApproved($appointment);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify client: ' . $e->getMessage());
        }

        // Notify cashiers about the approved appointment ready for payment
        try {
            \App\Services\NotificationService::notifyCashiersAppointmentApproved($appointment);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify cashiers: ' . $e->getMessage());
        }

        // Broadcast appointment update for realtime clients
        try {
            event(new \App\Events\AppointmentUpdated($appointment));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::debug('Failed to broadcast appointment update: ' . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => "Appointment #{$appointmentId} has been approved successfully. The client and cashiers have been notified.",
            'data' => ['appointment_id' => $appointmentId],
        ];
    }
    
    /**
     * Decline an appointment
     */
    private static function declineAppointment(int $adminId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        $reason = $params['reason'] ?? 'Declined by admin';
        
        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID to decline.',
            ];
        }
        
        $appointment = Appointment::where('status', 'pending')->find($appointmentId);
        
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or is not in pending status.',
            ];
        }
        
        $appointment->status = 'cancelled';
        $appointment->cancellation_reason = $reason;
        $appointment->save();
        
        return [
            'success' => true,
            'message' => "Appointment #{$appointmentId} has been declined.",
            'data' => ['appointment_id' => $appointmentId],
        ];
    }
    
    /**
     * Get system health for admin
     */
    private static function getSystemHealth(): array
    {
        $health = ChatbotServiceEnhancements::getSystemHealth();
        
        $message = "System Status: {$health['status']}. Database: {$health['database']}.";
        if (!empty($health['issues'])) {
            $message .= " Issues: " . implode('; ', $health['issues']) . ".";
        }
        if (!empty($health['warnings'])) {
            $message .= " Warnings: " . implode('; ', $health['warnings']) . ".";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $health,
        ];
    }
    
    /**
     * Approve a refund request
     */
    private static function approveRefund(int $adminId, array $params): array
    {
        $refundId = $params['refund_id'] ?? null;
        
        if (!$refundId) {
            return [
                'success' => false,
                'message' => 'Please specify the refund ID to approve.',
            ];
        }
        
        $refund = Refund::where('status', 'pending')->find($refundId);
        
        if (!$refund) {
            return [
                'success' => false,
                'message' => 'Refund not found or is not in pending status.',
            ];
        }
        
        $refund->status = 'approved';
        $refund->approved_by = $adminId;
        $refund->approved_at = Carbon::now();
        $refund->save();
        
        return [
            'success' => true,
            'message' => "Refund #{$refundId} (₱" . number_format($refund->refund_amount, 2) . ") has been approved. Awaiting cashier processing.",
            'data' => ['refund_id' => $refundId],
        ];
    }
    
    /**
     * Reject a refund request
     */
    private static function rejectRefund(int $adminId, array $params): array
    {
        $refundId = $params['refund_id'] ?? null;
        $reason = $params['reason'] ?? 'Rejected by admin';
        
        if (!$refundId) {
            return [
                'success' => false,
                'message' => 'Please specify the refund ID to reject.',
            ];
        }
        
        $refund = Refund::where('status', 'pending')->find($refundId);
        
        if (!$refund) {
            return [
                'success' => false,
                'message' => 'Refund not found or is not in pending status.',
            ];
        }
        
        $refund->status = 'rejected';
        $refund->rejection_reason = $reason;
        $refund->save();
        
        return [
            'success' => true,
            'message' => "Refund #{$refundId} has been rejected.",
            'data' => ['refund_id' => $refundId],
        ];
    }
    
    // ==================== CASHIER ACTION IMPLEMENTATIONS ====================
    
    /**
     * Process a payment
     */
    private static function processPayment(int $cashierId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        $amount = $params['amount'] ?? null;
        
        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID to process payment.',
            ];
        }
        
        $appointment = Appointment::with(['service', 'payments'])
            ->where('status', 'approved')
            ->find($appointmentId);
            
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or is not in approved status.',
            ];
        }
        
        // Check if already paid
        $existingPayment = $appointment->payments->first();
        if ($existingPayment && $existingPayment->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'This appointment has already been paid.',
            ];
        }
        
        $servicePrice = $appointment->service->price ?? 0;
        $amountPaid = $amount ?? $servicePrice;
        
        if ($existingPayment) {
            $existingPayment->amount_paid = $amountPaid;
            $existingPayment->payment_status = 'paid';
            $existingPayment->payment_date = Carbon::now();
            $existingPayment->recorded_by = $cashierId;
            $existingPayment->save();
        } else {
            Payment::create([
                'appointment_id' => $appointmentId,
                'recorded_by' => $cashierId,
                'service_price' => $servicePrice,
                'amount_paid' => $amountPaid,
                'payment_status' => 'paid',
                'payment_date' => Carbon::now(),
            ]);
        }
        
        // Mark appointment as completed
        $appointment->status = 'completed';
        $appointment->save();
        
        return [
            'success' => true,
            'message' => "Payment of ₱" . number_format($amountPaid, 2) . " processed for appointment #{$appointmentId}.",
            'data' => ['appointment_id' => $appointmentId, 'amount' => $amountPaid],
        ];
    }
    
    /**
     * Get shift report for cashier
     */
    private static function getShiftReport(array $params): array
    {
        $startDate = isset($params['start_date']) ? Carbon::parse($params['start_date']) : null;
        $endDate = isset($params['end_date']) ? Carbon::parse($params['end_date']) : null;
        
        $report = ChatbotServiceEnhancements::getShiftReport($startDate, $endDate);
        
        if (isset($report['error'])) {
            return [
                'success' => false,
                'message' => 'Unable to generate shift report.',
            ];
        }
        
        $message = "Shift Report ({$report['period']['start']} - {$report['period']['end']}): ";
        $message .= "Collected: ₱{$report['payments']['total_collected']} ({$report['payments']['count']} payments), ";
        $message .= "Refunded: ₱{$report['refunds']['total_refunded']} ({$report['refunds']['count']} refunds), ";
        $message .= "Net: ₱{$report['net_revenue']}.";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $report,
        ];
    }
    
    /**
     * Get pending payments
     */
    private static function getPendingPayments(): array
    {
        $appointments = ChatbotServiceEnhancements::getAppointmentsForPayment(10);
        $count = count($appointments);
        
        if ($count === 0) {
            return [
                'success' => true,
                'message' => 'No appointments awaiting payment.',
                'data' => [],
            ];
        }
        
        $first = $appointments[0];
        $message = "{$count} appointment(s) ready for payment. Next: {$first['client_name']} - {$first['service']} (₱{$first['service_price']}).";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $appointments,
        ];
    }
    
    /**
     * Get pending refunds for cashier
     */
    private static function getPendingRefundsAction(): array
    {
        $refunds = ChatbotServiceEnhancements::getPendingRefunds(10);
        $count = count($refunds);
        
        if ($count === 0) {
            return [
                'success' => true,
                'message' => 'No pending refund requests.',
                'data' => [],
            ];
        }
        
        $first = $refunds[0];
        $message = "{$count} pending refund(s). Next: {$first['client_name']} - ₱{$first['amount']} ({$first['reason']}).";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $refunds,
        ];
    }
    
    /**
     * Complete a refund (mark as processed)
     */
    private static function completeRefund(int $cashierId, array $params): array
    {
        $refundId = $params['refund_id'] ?? null;
        
        if (!$refundId) {
            return [
                'success' => false,
                'message' => 'Please specify the refund ID to complete.',
            ];
        }
        
        $refund = Refund::where('status', 'approved')->find($refundId);
        
        if (!$refund) {
            return [
                'success' => false,
                'message' => 'Refund not found or is not in approved status.',
            ];
        }
        
        $refund->status = 'completed';
        $refund->completed_at = Carbon::now();
        $refund->save();
        
        return [
            'success' => true,
            'message' => "Refund #{$refundId} (₱" . number_format($refund->refund_amount, 2) . ") has been marked as completed.",
            'data' => ['refund_id' => $refundId],
        ];
    }
    
    // ==================== NEW CLIENT ACTION IMPLEMENTATIONS ====================
    
    /**
     * View user's profile
     */
    private static function viewUserProfile(int $userId): array
    {
        $user = User::select('id', 'name', 'email', 'phone', 'created_at')
            ->find($userId);
            
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }
        
        $memberSince = Carbon::parse($user->created_at)->format('F Y');
        
        return [
            'success' => true,
            'message' => "Your Profile:\n• Name: {$user->name}\n• Email: {$user->email}\n• Phone: {$user->phone}\n• Member since: {$memberSince}",
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'member_since' => $memberSince,
            ],
        ];
    }
    
    /**
     * Edit user's profile
     */
    private static function editUserProfile(int $userId, array $params): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }
        
        $updated = [];
        
        if (isset($params['name']) && !empty($params['name'])) {
            $user->name = $params['name'];
            $updated[] = 'name';
        }
        
        if (isset($params['phone']) && !empty($params['phone'])) {
            $user->phone = $params['phone'];
            $updated[] = 'phone';
        }
        
        if (empty($updated)) {
            return [
                'success' => false,
                'message' => 'Please specify what you want to update. You can update your name or phone number.',
            ];
        }
        
        $user->save();
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully! Updated: ' . implode(', ', $updated),
            'data' => ['updated_fields' => $updated],
        ];
    }
    
    /**
     * View user's appointments with filters
     */
    private static function viewUserAppointments(int $userId, array $params): array
    {
        $status = $params['status'] ?? null;
        $limit = $params['limit'] ?? 5;
        
        $query = Appointment::with(['service:id,name,price'])
            ->where('user_id', $userId)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc');
            
        if ($status) {
            $query->where('status', $status);
        }
        
        $appointments = $query->limit($limit)->get();
        
        if ($appointments->isEmpty()) {
            $statusMsg = $status ? " with status '{$status}'" : '';
            return [
                'success' => true,
                'message' => "You have no appointments{$statusMsg}.",
                'data' => [],
            ];
        }
        
        $list = $appointments->map(function ($apt) {
            return [
                'id' => $apt->id,
                'service' => $apt->service->name ?? 'Unknown',
                'date' => $apt->appointment_date->format('M d, Y'),
                'time' => $apt->appointment_time,
                'status' => $apt->status,
            ];
        })->toArray();
        
        $message = "Your appointments:\n";
        foreach ($list as $apt) {
            $message .= "• #{$apt['id']}: {$apt['service']} on {$apt['date']} at {$apt['time']} - {$apt['status']}\n";
        }
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $list,
        ];
    }
    
    /**
     * Initiate booking (provides available services and slots)
     */
    private static function initiateBooking(int $userId, array $params): array
    {
        $serviceId = $params['service_id'] ?? null;
        $date = isset($params['date']) ? Carbon::parse($params['date']) : null;
        
        if (!$serviceId) {
            // List available services
            $services = Service::where('is_available', true)
                ->select('id', 'name', 'price', 'duration')
                ->get();
                
            if ($services->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No services are currently available.',
                ];
            }
            
            $message = "Available services for booking:\n";
            foreach ($services as $service) {
                $message .= "• {$service->name} - ₱" . number_format($service->price, 2) . " ({$service->duration} mins)\n";
            }
            $message .= "\nTo book, please specify the service and your preferred date.";
            
            return [
                'success' => true,
                'message' => trim($message),
                'data' => $services->toArray(),
                'next_action' => 'select_service',
            ];
        }
        
        // Service selected, provide booking info
        $service = Service::find($serviceId);
        if (!$service || !$service->is_available) {
            return [
                'success' => false,
                'message' => 'Service not found or not available.',
            ];
        }
        
        return [
            'success' => true,
            'message' => "You've selected {$service->name} (₱" . number_format($service->price, 2) . "). Please use the booking page to complete your appointment with your preferred date and time.",
            'data' => [
                'service' => $service->toArray(),
                'booking_url' => '/book',
            ],
            'redirect_to' => '/book?service=' . $serviceId,
        ];
    }
    
    /**
     * View available services
     */
    private static function viewServices(array $params): array
    {
        $search = $params['search'] ?? null;
        
        $query = Service::where('is_available', true)
            ->select('id', 'name', 'description', 'price', 'duration');
            
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $services = $query->get();
        
        if ($services->isEmpty()) {
            return [
                'success' => true,
                'message' => $search 
                    ? "No services found matching '{$search}'." 
                    : 'No services are currently available.',
                'data' => [],
            ];
        }
        
        $message = "Available Services:\n";
        foreach ($services as $service) {
            $message .= "• {$service->name} - ₱" . number_format($service->price, 2) . "\n";
            if ($service->description) {
                $message .= "  {$service->description}\n";
            }
        }
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $services->toArray(),
        ];
    }
    
    // ==================== NEW ADMIN ACTION IMPLEMENTATIONS ====================
    
    /**
     * Complete an appointment (admin/staff)
     */
    private static function completeAppointment(int $adminId, array $params): array
    {
        $appointmentId = $params['appointment_id'] ?? null;
        
        if (!$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please specify the appointment ID to mark as completed.',
            ];
        }
        
        $appointment = Appointment::with(['user:id,name', 'service:id,name'])
            ->whereIn('status', ['approved', 'in_progress'])
            ->find($appointmentId);
            
        if (!$appointment) {
            return [
                'success' => false,
                'message' => 'Appointment not found or cannot be completed (must be approved or in progress).',
            ];
        }
        
        DB::beginTransaction();
        try {
            $appointment->status = 'completed';
            $appointment->completed_at = Carbon::now();
            $appointment->completed_by = $adminId;
            $appointment->save();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => "Appointment #{$appointmentId} for {$appointment->user->name} ({$appointment->service->name}) has been marked as completed.",
                'data' => [
                    'appointment_id' => $appointmentId,
                    'client' => $appointment->user->name,
                    'service' => $appointment->service->name,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * View all appointments (admin)
     */
    private static function viewAllAppointments(array $params): array
    {
        $status = $params['status'] ?? null;
        $date = isset($params['date']) ? Carbon::parse($params['date']) : null;
        $limit = $params['limit'] ?? 10;
        
        $query = Appointment::with(['user:id,name', 'service:id,name,price'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc');
            
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($date) {
            $query->whereDate('appointment_date', $date);
        }
        
        $appointments = $query->limit($limit)->get();
        
        if ($appointments->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No appointments found matching your criteria.',
                'data' => [],
            ];
        }
        
        $list = $appointments->map(function ($apt) {
            return [
                'id' => $apt->id,
                'client' => $apt->user->name ?? 'Unknown',
                'service' => $apt->service->name ?? 'Unknown',
                'date' => $apt->appointment_date->format('M d, Y'),
                'time' => $apt->appointment_time,
                'status' => $apt->status,
            ];
        })->toArray();
        
        $message = "Appointments:\n";
        foreach ($list as $apt) {
            $message .= "• #{$apt['id']}: {$apt['client']} - {$apt['service']} ({$apt['date']} {$apt['time']}) - {$apt['status']}\n";
        }
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $list,
            'total' => count($list),
        ];
    }
    
    /**
     * Manage users (admin)
     */
    private static function manageUsers(array $params): array
    {
        $action = $params['action'] ?? 'list';
        $search = $params['search'] ?? null;
        $role = $params['role'] ?? null;
        $limit = $params['limit'] ?? 10;
        
        $query = User::select('id', 'name', 'email', 'phone', 'created_at')
            ->orderBy('created_at', 'desc');
            
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($role) {
            $query->role($role);
        }
        
        $users = $query->limit($limit)->get();
        
        if ($users->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No users found.',
                'data' => [],
            ];
        }
        
        $message = "Users:\n";
        foreach ($users as $user) {
            $message .= "• #{$user->id}: {$user->name} ({$user->email})\n";
        }
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $users->toArray(),
            'total' => count($users),
        ];
    }
    
    /**
     * View user details (admin)
     */
    private static function viewUserDetails(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        $email = $params['email'] ?? null;
        
        if (!$userId && !$email) {
            return [
                'success' => false,
                'message' => 'Please specify a user ID or email to view.',
            ];
        }
        
        $query = User::with(['appointments' => function($q) {
            $q->latest('appointment_date')->limit(5);
        }]);
        
        if ($userId) {
            $user = $query->find($userId);
        } else {
            $user = $query->where('email', $email)->first();
        }
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }
        
        $totalAppointments = $user->appointments()->count();
        $completedAppointments = $user->appointments()->where('status', 'completed')->count();
        
        $message = "User Details:\n";
        $message .= "• Name: {$user->name}\n";
        $message .= "• Email: {$user->email}\n";
        $message .= "• Phone: {$user->phone}\n";
        $message .= "• Member since: " . $user->created_at->format('M d, Y') . "\n";
        $message .= "• Total Appointments: {$totalAppointments}\n";
        $message .= "• Completed: {$completedAppointments}";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone', 'created_at']),
                'stats' => [
                    'total_appointments' => $totalAppointments,
                    'completed_appointments' => $completedAppointments,
                ],
            ],
        ];
    }
    
    /**
     * Get analytics with parameters (admin)
     */
    private static function getAnalytics(array $params = []): array
    {
        $period = $params['period'] ?? 'week';
        $type = $params['type'] ?? 'overview';
        
        $startDate = match($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfWeek(),
        };
        
        $endDate = Carbon::now();
        
        // Get appointment stats
        $appointmentStats = Appointment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        // Get payment stats
        $paymentStats = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('SUM(amount_paid) as total, COUNT(*) as count')
            ->first();
            
        // Get refund stats
        $refundStats = Refund::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count, SUM(refund_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();
            
        $totalRevenue = $paymentStats->total ?? 0;
        $totalRefunded = collect($refundStats)->sum('total');
        $netRevenue = $totalRevenue - $totalRefunded;
        
        $message = "Analytics ({$period}):\n";
        $message .= "• Appointments: " . array_sum($appointmentStats) . " total\n";
        foreach ($appointmentStats as $status => $count) {
            $message .= "  - {$status}: {$count}\n";
        }
        $message .= "• Revenue: ₱" . number_format($totalRevenue, 2) . "\n";
        $message .= "• Refunds: ₱" . number_format($totalRefunded, 2) . "\n";
        $message .= "• Net: ₱" . number_format($netRevenue, 2);
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'period' => $period,
                'appointments' => $appointmentStats,
                'revenue' => [
                    'total' => $totalRevenue,
                    'refunded' => $totalRefunded,
                    'net' => $netRevenue,
                ],
                'payments_count' => $paymentStats->count ?? 0,
            ],
        ];
    }
    
    // ==================== NEW CASHIER ACTION IMPLEMENTATIONS ====================
    
    /**
     * Verify a receipt
     */
    private static function verifyReceipt(array $params): array
    {
        $receiptNumber = $params['receipt_number'] ?? null;
        $appointmentId = $params['appointment_id'] ?? null;
        
        if (!$receiptNumber && !$appointmentId) {
            return [
                'success' => false,
                'message' => 'Please provide a receipt number or appointment ID to verify.',
            ];
        }
        
        $query = Payment::with(['appointment.user:id,name', 'appointment.service:id,name,price']);
        
        if ($receiptNumber) {
            $payment = $query->where('receipt_number', $receiptNumber)->first();
        } else {
            $payment = $query->where('appointment_id', $appointmentId)->first();
        }
        
        if (!$payment) {
            return [
                'success' => false,
                'message' => 'No payment record found.',
            ];
        }
        
        $appointment = $payment->appointment;
        
        $message = "Receipt Verified ✓\n";
        $message .= "• Receipt #: {$payment->receipt_number}\n";
        $message .= "• Client: {$appointment->user->name}\n";
        $message .= "• Service: {$appointment->service->name}\n";
        $message .= "• Amount: ₱" . number_format($payment->amount_paid, 2) . "\n";
        $message .= "• Status: {$payment->payment_status}\n";
        $message .= "• Date: " . Carbon::parse($payment->payment_date)->format('M d, Y h:i A');
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'receipt_number' => $payment->receipt_number,
                'amount' => $payment->amount_paid,
                'status' => $payment->payment_status,
                'client' => $appointment->user->name,
                'service' => $appointment->service->name,
                'date' => $payment->payment_date,
            ],
        ];
    }
    
    /**
     * View daily summary (cashier)
     */
    private static function viewDailySummary(array $params): array
    {
        $date = isset($params['date']) ? Carbon::parse($params['date']) : Carbon::today();
        
        // Get today's payments
        $payments = Payment::whereDate('payment_date', $date)
            ->selectRaw('COUNT(*) as count, SUM(amount_paid) as total')
            ->first();
            
        // Get today's appointments
        $appointments = Appointment::whereDate('appointment_date', $date)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        // Get pending items
        $pendingPayments = Appointment::where('status', 'approved')
            ->whereDoesntHave('payments', function($q) {
                $q->where('payment_status', 'paid');
            })
            ->count();
            
        $pendingRefunds = Refund::where('status', 'approved')->count();
        
        $message = "Daily Summary for " . $date->format('M d, Y') . ":\n";
        $message .= "• Payments Collected: ₱" . number_format($payments->total ?? 0, 2) . " ({$payments->count} transactions)\n";
        $message .= "• Appointments: " . array_sum($appointments) . " total\n";
        foreach ($appointments as $status => $count) {
            $message .= "  - {$status}: {$count}\n";
        }
        $message .= "• Pending Payments: {$pendingPayments}\n";
        $message .= "• Pending Refunds: {$pendingRefunds}";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'date' => $date->format('Y-m-d'),
                'payments' => [
                    'count' => $payments->count ?? 0,
                    'total' => $payments->total ?? 0,
                ],
                'appointments' => $appointments,
                'pending' => [
                    'payments' => $pendingPayments,
                    'refunds' => $pendingRefunds,
                ],
            ],
        ];
    }
    
    // ==================== GENERAL ACTION IMPLEMENTATIONS ====================
    
    /**
     * Get help based on role
     */
    private static function getHelp(string $role): array
    {
        $commands = [
            'client' => [
                'check appointment' => 'Check your appointment status',
                'cancel appointment' => 'Cancel an upcoming appointment',
                'my appointments' => 'View all your appointments',
                'payment status' => 'Check payment status',
                'request refund' => 'Request a refund',
                'refund status' => 'Check refund status',
                'view profile' => 'View your profile',
                'services' => 'View available services',
                'book' => 'Start booking an appointment',
            ],
            'admin' => [
                'pending appointments' => 'View pending appointments',
                'approve [ID]' => 'Approve an appointment',
                'decline [ID]' => 'Decline an appointment',
                'complete [ID]' => 'Mark appointment as completed',
                'analytics' => 'View system analytics',
                'system health' => 'Check system health',
                'pending refunds' => 'View pending refunds',
                'approve refund [ID]' => 'Approve a refund',
                'reject refund [ID]' => 'Reject a refund',
                'users' => 'Manage users',
            ],
            'cashier' => [
                'pending payments' => 'View appointments awaiting payment',
                'process payment [ID]' => 'Process a payment',
                'shift report' => 'View shift report',
                'daily summary' => 'View daily summary',
                'pending refunds' => 'View approved refunds to process',
                'complete refund [ID]' => 'Mark refund as completed',
                'verify receipt' => 'Verify a receipt',
            ],
            'staff' => [
                'pending appointments' => 'View pending appointments',
                'approve [ID]' => 'Approve an appointment',
                'decline [ID]' => 'Decline an appointment',
                'complete [ID]' => 'Mark appointment as completed',
                'analytics' => 'View analytics',
            ],
        ];
        
        $roleCommands = $commands[$role] ?? $commands['client'];
        
        $message = "Available Commands:\n";
        foreach ($roleCommands as $command => $description) {
            $message .= "• \"{$command}\" - {$description}\n";
        }
        $message .= "\nYou can also type naturally, and I'll try to understand what you need!";
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $roleCommands,
        ];
    }
    
    /**
     * Search system (contextual based on role)
     */
    private static function searchSystem(int $userId, string $role, array $params): array
    {
        $query = $params['query'] ?? null;
        
        if (!$query) {
            return [
                'success' => false,
                'message' => 'Please specify what you want to search for.',
            ];
        }
        
        $results = [
            'appointments' => [],
            'services' => [],
            'users' => [],
        ];
        
        // Search appointments
        $appointmentQuery = Appointment::with(['user:id,name', 'service:id,name'])
            ->where(function($q) use ($query) {
                $q->where('id', $query)
                  ->orWhereHas('service', function($sq) use ($query) {
                      $sq->where('name', 'like', "%{$query}%");
                  });
            });
            
        if ($role === 'client') {
            $appointmentQuery->where('user_id', $userId);
        }
        
        $results['appointments'] = $appointmentQuery->limit(5)->get()->map(function($apt) {
            return [
                'id' => $apt->id,
                'client' => $apt->user->name ?? 'Unknown',
                'service' => $apt->service->name ?? 'Unknown',
                'date' => $apt->appointment_date->format('M d, Y'),
                'status' => $apt->status,
            ];
        })->toArray();
        
        // Search services
        $results['services'] = Service::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'price'])
            ->toArray();
            
        // Admin/Staff can search users
        if (in_array($role, ['admin', 'staff'])) {
            $results['users'] = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name', 'email'])
                ->toArray();
        }
        
        $totalResults = count($results['appointments']) + count($results['services']) + count($results['users']);
        
        if ($totalResults === 0) {
            return [
                'success' => true,
                'message' => "No results found for '{$query}'.",
                'data' => $results,
            ];
        }
        
        $message = "Search results for '{$query}':\n";
        
        if (!empty($results['appointments'])) {
            $message .= "\nAppointments:\n";
            foreach ($results['appointments'] as $apt) {
                $message .= "• #{$apt['id']}: {$apt['service']} - {$apt['status']}\n";
            }
        }
        
        if (!empty($results['services'])) {
            $message .= "\nServices:\n";
            foreach ($results['services'] as $svc) {
                $message .= "• {$svc['name']} - ₱" . number_format($svc['price'], 2) . "\n";
            }
        }
        
        if (!empty($results['users'])) {
            $message .= "\nUsers:\n";
            foreach ($results['users'] as $user) {
                $message .= "• {$user['name']} ({$user['email']})\n";
            }
        }
        
        return [
            'success' => true,
            'message' => trim($message),
            'data' => $results,
            'total' => $totalResults,
        ];
    }
    
    // ==================== UTILITY METHODS ====================
    
    /**
     * Get available actions for a role
     */
    public static function getAvailableActions(string $role): array
    {
        return match($role) {
            'client' => [
                'check_appointment', 'cancel_appointment', 'check_payment_status',
                'request_refund', 'check_refund_status', 'view_profile', 'edit_profile',
                'view_appointments', 'book_appointment', 'view_services', 'get_help', 'search',
            ],
            'cashier' => [
                'check_appointment', 'check_payment_status', 'process_payment',
                'get_shift_report', 'get_pending_payments', 'get_pending_refunds',
                'complete_refund', 'verify_receipt', 'view_daily_summary', 'get_help', 'search',
            ],
            'admin' => [
                'check_appointment', 'cancel_appointment', 'check_payment_status',
                'request_refund', 'check_refund_status', 'view_profile', 'edit_profile',
                'view_appointments', 'book_appointment', 'view_services',
                'approve_appointment', 'decline_appointment', 'complete_appointment',
                'get_system_health', 'get_analytics', 'approve_refund', 'reject_refund',
                'process_payment', 'get_shift_report', 'get_pending_payments',
                'get_pending_refunds', 'complete_refund', 'verify_receipt',
                'view_daily_summary', 'view_all_appointments', 'manage_users',
                'view_user_details', 'get_help', 'search',
            ],
            'staff' => [
                'check_appointment', 'approve_appointment', 'decline_appointment',
                'complete_appointment', 'get_analytics', 'view_all_appointments',
                'get_help', 'search',
            ],
            default => ['view_services', 'get_help'],
        };
    }
    
    /**
     * Map intent to action
     */
    public static function intentToAction(string $intent): ?string
    {
        return match($intent) {
            'check_appointment', 'appointment_status' => 'check_appointment',
            'cancel_appointment' => 'cancel_appointment',
            'view_appointments', 'my_appointments' => 'view_appointments',
            'check_payment', 'payment_status' => 'check_payment_status',
            'request_refund' => 'request_refund',
            'check_refund', 'refund_status' => 'check_refund_status',
            'view_profile' => 'view_profile',
            'edit_profile' => 'edit_profile',
            'book_appointment' => 'book_appointment',
            'view_services', 'available_services' => 'view_services',
            'approve_appointment' => 'approve_appointment',
            'decline_appointment', 'reject_appointment' => 'decline_appointment',
            'complete_appointment' => 'complete_appointment',
            'view_analytics', 'analytics' => 'get_analytics',
            'system_health' => 'get_system_health',
            'approve_refund' => 'approve_refund',
            'reject_refund' => 'reject_refund',
            'process_payment' => 'process_payment',
            'shift_report' => 'get_shift_report',
            'pending_payments' => 'get_pending_payments',
            'pending_refunds' => 'get_pending_refunds',
            'complete_refund', 'process_refund' => 'complete_refund',
            'verify_receipt' => 'verify_receipt',
            'daily_summary' => 'view_daily_summary',
            'view_all_appointments' => 'view_all_appointments',
            'manage_users' => 'manage_users',
            'view_user' => 'view_user_details',
            'help' => 'get_help',
            'search' => 'search',
            default => null,
        };
    }
}
