<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ChatbotActionService - Execute actions through chatbot commands
 * 
 * This service handles action-based capabilities allowing users to perform
 * system operations through natural language commands via the chatbot.
 * 
 * Security: All actions are validated against the user's role and permissions.
 */
class ChatbotActionService
{
    /**
     * Execute an action based on the detected intent and user role
     * 
     * @param int $userId The authenticated user's ID
     * @param string $role The user's role (client, admin, cashier)
     * @param string $action The action to perform
     * @param array $params Parameters extracted from the user's message
     * @return array Result of the action with success status and message
     */
    public static function executeAction(int $userId, string $role, string $action, array $params = []): array
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
        
        try {
            switch ($action) {
                // ==================== CLIENT ACTIONS ====================
                case 'check_appointment':
                    return self::checkUserAppointment($userId, $params);
                    
                case 'cancel_appointment':
                    return self::cancelUserAppointment($userId, $params);
                    
                case 'check_payment_status':
                    return self::checkUserPaymentStatus($userId, $params);
                    
                case 'request_refund':
                    return self::requestRefund($userId, $params);
                    
                case 'check_refund_status':
                    return self::checkRefundStatus($userId, $params);
                    
                // ==================== ADMIN ACTIONS ====================
                case 'approve_appointment':
                    return self::approveAppointment($userId, $params);
                    
                case 'decline_appointment':
                    return self::declineAppointment($userId, $params);
                    
                case 'get_system_health':
                    return self::getSystemHealth();
                    
                case 'get_analytics':
                    return self::getAnalytics();
                    
                case 'approve_refund':
                    return self::approveRefund($userId, $params);
                    
                case 'reject_refund':
                    return self::rejectRefund($userId, $params);
                    
                // ==================== CASHIER ACTIONS ====================
                case 'process_payment':
                    return self::processPayment($userId, $params);
                    
                case 'get_shift_report':
                    return self::getShiftReport($params);
                    
                case 'get_pending_payments':
                    return self::getPendingPayments();
                    
                case 'get_pending_refunds':
                    return self::getPendingRefundsAction();
                    
                case 'complete_refund':
                    return self::completeRefund($userId, $params);
                    
                default:
                    return [
                        'success' => false,
                        'message' => 'Unknown action: ' . $action,
                    ];
            }
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
            ],
            'cashier' => [
                'check_appointment',
                'check_payment_status',
                'process_payment',
                'get_shift_report',
                'get_pending_payments',
                'get_pending_refunds',
                'complete_refund',
            ],
            'admin' => [
                'check_appointment',
                'cancel_appointment',
                'check_payment_status',
                'request_refund',
                'check_refund_status',
                'approve_appointment',
                'decline_appointment',
                'get_system_health',
                'get_analytics',
                'approve_refund',
                'reject_refund',
                'process_payment',
                'get_shift_report',
                'get_pending_payments',
                'get_pending_refunds',
                'complete_refund',
            ],
            'staff' => [
                'check_appointment',
                'approve_appointment',
                'decline_appointment',
                'get_analytics',
            ],
        ];
        
        $allowedActions = $permissions[$role] ?? [];
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
     * Get analytics for admin
     */
    private static function getAnalytics(): array
    {
        $analytics = ChatbotServiceEnhancements::getAdminAnalytics();
        
        if (isset($analytics['error'])) {
            return [
                'success' => false,
                'message' => 'Unable to fetch analytics.',
            ];
        }
        
        $message = "Analytics: Weekly Revenue: " . ($analytics['revenue']['weekly'] ?? 'N/A') . 
            ", Monthly Revenue: " . ($analytics['revenue']['monthly'] ?? 'N/A') . 
            ". New users this week: " . ($analytics['user_growth']['weekly'] ?? 0) . ".";
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $analytics,
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
}
