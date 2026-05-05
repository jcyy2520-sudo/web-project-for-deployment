<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public static function create(int $userId, string $type, string $title, string $message, array $options = []): ?Notification
    {
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'icon' => $options['icon'] ?? null,
                'color' => $options['color'] ?? null,
                'related_id' => $options['related_id'] ?? null,
                'related_type' => $options['related_type'] ?? null,
                'data' => $options['data'] ?? null,
                'is_read' => false,
                'is_sent' => true
            ]);

            if ($notification) {
                WebPushService::sendNotification($notification);
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Notify user about appointment approval
     */
    public static function appointmentApproved($appointment): void
    {
        self::create(
            $appointment->user_id,
            'appointment_approved',
            'Appointment Approved',
            "Your appointment for {$appointment->appointment_date->format('M d, Y')} at {$appointment->appointment_time} has been approved.",
            [
                'icon' => 'check-circle',
                'color' => 'green',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => 'approved',
                    'url' => '/appointments',
                ],
            ]
        );
    }

    /**
     * Notify user about appointment decline
     */
    public static function appointmentDeclined($appointment, string $reason = ''): void
    {
        $message = "Your appointment for {$appointment->appointment_date->format('M d, Y')} at {$appointment->appointment_time} has been declined.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        self::create(
            $appointment->user_id,
            'appointment_declined',
            'Appointment Declined',
            $message,
            [
                'icon' => 'x-circle',
                'color' => 'red',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => 'declined',
                    'url' => '/appointments',
                ],
            ]
        );
    }

    /**
     * Notify user about appointment cancellation
     */
    public static function appointmentCancelled($appointment): void
    {
        self::create(
            $appointment->user_id,
            'appointment_cancelled',
            'Appointment Cancelled',
            "Your appointment for {$appointment->appointment_date->format('M d, Y')} at {$appointment->appointment_time} has been cancelled.",
            [
                'icon' => 'x-circle',
                'color' => 'red',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => 'cancelled',
                    'url' => '/appointments',
                ],
            ]
        );
    }

    /**
     * Notify user about appointment completion
     */
    public static function appointmentCompleted($appointment): void
    {
        self::create(
            $appointment->user_id,
            'appointment_completed',
            'Appointment Completed',
            "Your appointment on {$appointment->appointment_date->format('M d, Y')} has been marked as completed.",
            [
                'icon' => 'check',
                'color' => 'blue',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => 'completed',
                    'url' => '/appointments',
                ],
            ]
        );
    }

    public static function appointmentStatusUpdated($appointment, string $oldStatus, string $newStatus): void
    {
        switch ($newStatus) {
            case 'approved':
                self::appointmentApproved($appointment);
                return;
            case 'declined':
                self::appointmentDeclined($appointment, (string) ($appointment->decline_reason ?? ''));
                return;
            case 'cancelled':
                self::appointmentCancelled($appointment);
                return;
            case 'completed':
                self::appointmentCompleted($appointment);
                return;
            default:
                self::create(
                    $appointment->user_id,
                    'appointment_status_updated',
                    'Appointment Updated',
                    "Your appointment status changed from {$oldStatus} to {$newStatus}.",
                    [
                        'icon' => 'bell',
                        'color' => 'blue',
                        'related_id' => $appointment->id,
                        'related_type' => 'Appointment',
                        'data' => [
                            'appointment_id' => $appointment->id,
                            'old_status' => $oldStatus,
                            'status' => $newStatus,
                            'url' => '/appointments',
                        ],
                    ]
                );
                return;
        }
    }

    /**
     * Notify user about payment processed
     */
    public static function paymentProcessed($appointment, float $amount): void
    {
        self::create(
            $appointment->user_id,
            'payment_processed',
            'Payment Received',
            "Your payment of ₱" . number_format($amount, 2) . " has been processed successfully.",
            [
                'icon' => 'currency-dollar',
                'color' => 'green',
                'related_id' => $appointment->id,
                'related_type' => 'Appointment',
                'data' => ['amount' => $amount]
            ]
        );
    }

    /**
     * Notify user about refund approval
     */
    public static function refundApproved($refund): void
    {
        self::create(
            $refund->appointment->user_id,
            'refund_approved',
            'Refund Approved',
            "Your refund request of ₱" . number_format($refund->refund_amount, 2) . " has been approved and processed.",
            [
                'icon' => 'arrow-path',
                'color' => 'green',
                'related_id' => $refund->id,
                'related_type' => 'Refund'
            ]
        );
    }

    /**
     * Notify user about refund rejection
     */
    public static function refundRejected($refund, string $reason = ''): void
    {
        $message = "Your refund request of ₱" . number_format($refund->refund_amount, 2) . " has been rejected.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        self::create(
            $refund->appointment->user_id,
            'refund_rejected',
            'Refund Request Rejected',
            $message,
            [
                'icon' => 'x-circle',
                'color' => 'red',
                'related_id' => $refund->id,
                'related_type' => 'Refund'
            ]
        );
    }

    /**
     * Notify user about new message
     */
    public static function newMessage($message): void
    {
        self::create(
            $message->receiver_id,
            'new_message',
            'New Message',
            "You have a new message from " . ($message->sender->first_name ?? 'Admin'),
            [
                'icon' => 'chat-bubble-left',
                'color' => 'blue',
                'related_id' => $message->id,
                'related_type' => 'Message'
            ]
        );
    }

    /**
     * Send notification to all admins
     */
    public static function notifyAdmins(string $type, string $title, string $message, array $options = []): void
    {
        $admins = User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            self::create($admin->id, $type, $title, $message, $options);
        }
    }

    /**
     * Send notification to all cashiers about a new approved appointment ready for payment
     */
    public static function notifyCashiersAppointmentApproved($appointment): void
    {
        // Get all active cashiers using role column (not Spatie)
        $cashiers = User::where('role', 'cashier')->where('is_active', true)->get();
        
        $serviceName = $appointment->service->name ?? $appointment->service_type ?? 'Service';
        $appointmentDate = $appointment->appointment_date->format('M d, Y');
        $appointmentTime = $appointment->appointment_time;
        $clientName = $appointment->user->first_name . ' ' . $appointment->user->last_name;
        $price = $appointment->service->price ?? 0;

        foreach ($cashiers as $cashier) {
            self::create(
                $cashier->id,
                'appointment_ready_for_payment',
                'New Appointment Ready for Payment',
                "Appointment #{$appointment->id} for {$clientName} ({$serviceName}) on {$appointmentDate} at {$appointmentTime} is ready for payment. Amount: ₱" . number_format($price, 2),
                [
                    'icon' => 'currency-peso',
                    'color' => 'blue',
                    'related_id' => $appointment->id,
                    'related_type' => 'Appointment',
                    'data' => [
                        'appointment_id' => $appointment->id,
                        'client_name' => $clientName,
                        'service' => $serviceName,
                        'amount' => $price,
                        'date' => $appointmentDate,
                        'time' => $appointmentTime,
                        'url' => '/cashier',
                    ]
                ]
            );
        }
    }

    /**
     * Send notification to all cashiers about a new refund request
     */
    public static function notifyCashiersRefundRequested($refund): void
    {
        // Get all active cashiers using role column (not Spatie)
        $cashiers = User::where('role', 'cashier')->where('is_active', true)->get();
        
        $clientName = $refund->appointment->user->first_name . ' ' . $refund->appointment->user->last_name;
        $amount = $refund->refund_amount;

        foreach ($cashiers as $cashier) {
            self::create(
                $cashier->id,
                'refund_request',
                'New Refund Request',
                "Refund request #{$refund->id} from {$clientName} for ₱" . number_format($amount, 2) . " needs processing.",
                [
                    'icon' => 'arrow-path',
                    'color' => 'orange',
                    'related_id' => $refund->id,
                    'related_type' => 'Refund',
                    'data' => [
                        'refund_id' => $refund->id,
                        'client_name' => $clientName,
                        'amount' => $amount,
                    ]
                ]
            );
        }
    }
}
