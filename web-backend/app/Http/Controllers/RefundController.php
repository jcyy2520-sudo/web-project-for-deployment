<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Refund;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RefundController extends Controller
{
    /**
     * Request a refund (for cashiers/users)
     */
    public function requestRefund(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|in:customer_request,service_not_provided,duplicate_payment,service_cancellation,poor_service,other',
            'description' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::with(['user', 'service'])->findOrFail($request->appointment_id);

            // Validate appointment is paid
            if ($appointment->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paid appointments can be refunded'
                ], 422);
            }

            // Validate refund amount doesn't exceed payment
            if ($request->refund_amount > $appointment->payment_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed payment amount'
                ], 422);
            }

            // Check if there's already an active refund request
            $existingRefund = $appointment->refunds()
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRefund) {
                return response()->json([
                    'success' => false,
                    'message' => 'This appointment already has an active refund request'
                ], 422);
            }

            // Determine if partial refund
            $isPartial = $request->refund_amount < $appointment->payment_amount;

            // Create refund record
            $refund = Refund::create([
                'appointment_id' => $appointment->id,
                'requested_by' => $request->user()->id,
                'refund_amount' => $request->refund_amount,
                'original_amount' => $appointment->payment_amount,
                'reason' => $request->reason,
                'description' => $request->description,
                'is_partial' => $isPartial,
                'status' => 'pending'
            ]);

            // Log the action
            ActionLog::log(
                'request_refund',
                "Refund requested for {$appointment->user->first_name} {$appointment->user->last_name} - Amount: ₱{$request->refund_amount}",
                'Appointment',
                $appointment->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund request submitted successfully',
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Refund request error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating refund request'
            ], 500);
        }
    }

    /**
     * Get refunds for an appointment
     */
    public function getAppointmentRefunds($appointmentId)
    {
        $refunds = Refund::where('appointment_id', $appointmentId)
            ->with(['requestedBy:id,first_name,last_name', 'approvedBy:id,first_name,last_name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'refunds' => $refunds
        ]);
    }

    /**
     * Get cashier's pending refund requests
     */
    public function getPendingRefunds(Request $request)
    {
        $query = Refund::where('status', 'pending')
            ->with([
                'appointment:id,user_id,payment_amount,service_id,payment_date',
                'appointment.user:id,first_name,last_name,email',
                'appointment.service:id,name',
                'requestedBy:id,first_name,last_name'
            ]);

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $refunds = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($refunds);
    }

    /**
     * Approve refund (admin only)
     */
    public function approveRefund(Request $request, $refundId)
    {
        $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
            'refund_method' => 'required|string|in:cash,check,card,bank_transfer,original_method'
        ]);

        try {
            DB::beginTransaction();

            $refund = Refund::with('appointment')->findOrFail($refundId);

            // Verify status is pending
            if ($refund->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be approved'
                ], 422);
            }

            // Update refund
            $refund->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'refund_method' => $request->refund_method
            ]);

            // Update appointment payment status if full refund
            if (!$refund->is_partial) {
                $refund->appointment->update([
                    'payment_status' => 'refunded'
                ]);
            } else {
                $refund->appointment->update([
                    'payment_status' => 'partially_refunded'
                ]);
            }

            // Log the action
            ActionLog::log(
                'approve_refund',
                "Approved refund for appointment #{$refund->appointment_id} - Amount: ₱{$refund->refund_amount}",
                'Refund',
                $refund->id
            );

            // Notify user
            $this->sendRefundNotification($refund, 'approved');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund approved successfully',
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Refund approval error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error approving refund'
            ], 500);
        }
    }

    /**
     * Reject refund (admin only)
     */
    public function rejectRefund(Request $request, $refundId)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $refund = Refund::findOrFail($refundId);

            if ($refund->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be rejected'
                ], 422);
            }

            $refund->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Log the action
            ActionLog::log(
                'reject_refund',
                "Rejected refund for appointment #{$refund->appointment_id}",
                'Refund',
                $refund->id
            );

            // Notify user
            $this->sendRefundNotification($refund, 'rejected');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund rejected successfully',
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Refund rejection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting refund'
            ], 500);
        }
    }

    /**
     * Mark refund as completed (admin only)
     */
    public function completeRefund(Request $request, $refundId)
    {
        $request->validate([
            'transaction_id' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            $refund = Refund::with('appointment')->findOrFail($refundId);

            if ($refund->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved refunds can be completed'
                ], 422);
            }

            $refund->update([
                'status' => 'completed',
                'completed_at' => now(),
                'transaction_id' => $request->transaction_id
            ]);

            // Log the action
            ActionLog::log(
                'complete_refund',
                "Completed refund for appointment #{$refund->appointment_id} - Amount: ₱{$refund->refund_amount}",
                'Refund',
                $refund->id
            );

            // Notify user
            $this->sendRefundNotification($refund, 'completed');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund completed successfully',
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Refund completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error completing refund'
            ], 500);
        }
    }

    /**
     * Get refund statistics (admin dashboard)
     */
    public function getRefundStats(Request $request)
    {
        $timeframe = $request->get('timeframe', 'monthly');
        $dateRange = $this->getDateRange($timeframe);

        $stats = [
            'totalRequests' => Refund::whereBetween('created_at', $dateRange)->count(),
            'pendingCount' => Refund::pending()->whereBetween('created_at', $dateRange)->count(),
            'approvedCount' => Refund::approved()->whereBetween('created_at', $dateRange)->count(),
            'completedCount' => Refund::completed()->whereBetween('created_at', $dateRange)->count(),
            'rejectedCount' => Refund::rejected()->whereBetween('created_at', $dateRange)->count(),
            'totalRefundAmount' => Refund::completed()->whereBetween('created_at', $dateRange)->sum('refund_amount'),
            'approvalRate' => $this->calculateApprovalRate($dateRange),
            'averageRefundAmount' => Refund::completed()->whereBetween('created_at', $dateRange)->avg('refund_amount'),
            'refundsByReason' => $this->getRefundsByReason($dateRange),
            'refundsByStatus' => $this->getRefundsByStatus($dateRange)
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get all refunds (admin view)
     */
    public function getAllRefunds(Request $request)
    {
        $query = Refund::with([
            'appointment:id,user_id,payment_amount,service_id,payment_date',
            'appointment.user:id,first_name,last_name,email',
            'appointment.service:id,name',
            'requestedBy:id,first_name,last_name',
            'approvedBy:id,first_name,last_name'
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by reason
        if ($request->has('reason') && $request->reason !== 'all') {
            $query->where('reason', $request->reason);
        }

        // Search by customer name/email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('appointment.user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $refunds = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($refunds);
    }

    /**
     * Send refund notification to user
     */
    private function sendRefundNotification($refund, $status)
    {
        try {
            $appointment = $refund->appointment;
            $user = $appointment->user;

            // Send email based on status
            if ($status === 'approved') {
                \Mail::to($user->email)->send(new \App\Mail\RefundApprovedMail($refund));
            } elseif ($status === 'rejected') {
                \Mail::to($user->email)->send(new \App\Mail\RefundRejectedMail($refund));
            } elseif ($status === 'completed') {
                \Mail::to($user->email)->send(new \App\Mail\RefundCompletedMail($refund));
            }

            $subject = match($status) {
                'approved' => 'Your Refund Request Has Been Approved',
                'rejected' => 'Your Refund Request Has Been Reviewed',
                'completed' => 'Your Refund Has Been Processed',
                default => 'Refund Status Update'
            };

            $body = match($status) {
                'approved' => "Your refund of ₱{$refund->refund_amount} has been approved and will be processed soon.",
                'rejected' => "Your refund request has been reviewed and cannot be approved at this time. Reason: {$refund->rejection_reason}",
                'completed' => "Your refund of ₱{$refund->refund_amount} has been successfully processed.",
                default => "Your refund status has been updated."
            };

            // Create message notification in system
            \App\Models\Message::create([
                'sender_id' => 1, // System message
                'recipient_id' => $user->id,
                'subject' => $subject,
                'body' => $body,
                'type' => 'refund_notification',
                'is_read' => false
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send refund notification: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Get date range based on timeframe
     */
    private function getDateRange($timeframe)
    {
        $now = now();
        return match($timeframe) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]
        };
    }

    /**
     * Helper: Calculate approval rate
     */
    private function calculateApprovalRate($dateRange)
    {
        $total = Refund::whereBetween('created_at', $dateRange)
            ->whereIn('status', ['approved', 'rejected'])
            ->count();

        if ($total === 0) return 0;

        $approved = Refund::approved()->whereBetween('created_at', $dateRange)->count();
        return round(($approved / $total) * 100, 2);
    }

    /**
     * Helper: Get refunds by reason
     */
    private function getRefundsByReason($dateRange)
    {
        return Refund::whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->selectRaw('reason, COUNT(*) as count, SUM(refund_amount) as total_amount')
            ->groupBy('reason')
            ->get();
    }

    /**
     * Helper: Get refunds by status
     */
    private function getRefundsByStatus($dateRange)
    {
        return Refund::whereBetween('created_at', $dateRange)
            ->selectRaw('status, COUNT(*) as count, SUM(refund_amount) as total_amount')
            ->groupBy('status')
            ->get();
    }

    /**
     * Get user's refund history
     */
    public function getUserRefunds(Request $request)
    {
        $status = $request->query('status');
        $perPage = $request->query('per_page', 5);
        $page = $request->query('page', 1);

        $query = Refund::with(['appointment' => function($q) {
            $q->with(['user', 'service']);
        }, 'requestedBy' => function($q) {
            $q->select('id', 'first_name', 'last_name', 'email');
        }])
            ->where('requested_by', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $refunds = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($refunds);
    }
}
