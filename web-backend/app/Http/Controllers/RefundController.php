<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Refund;
use App\Models\RefundReason;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class RefundController extends Controller
{
    /**
     * Request a refund (for cashiers/users)
     */
    public function requestRefund(Request $request)
    {
        // Get valid reason keys from database
        $validKeys = RefundReason::getActiveRequestKeys();
        $validKeysStr = implode(',', $validKeys);

        $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => "required|string|in:{$validKeysStr}",
            'description' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::with(['user', 'service'])->findOrFail($request->appointment_id);

            // Ownership check: only the appointment owner, cashiers, or admins can request refunds
            $user = $request->user();
            if ($appointment->user_id !== $user->id && !in_array($user->role, ['cashier', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to request a refund for this appointment'
                ], 403);
            }

            // Phase 1 #16: Cashier cannot request refund on a payment they themselves processed
            if ($user->role === 'cashier' && $appointment->processed_by === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot request a refund for a payment you processed. Another cashier or admin must initiate the refund.'
                ], 403);
            }

            // Validate appointment is paid
            if ($appointment->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paid appointments can be refunded'
                ], 422);
            }

            // Validate payment amount exists
            if (!$appointment->payment_amount || $appointment->payment_amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot process refund: No payment amount found for this appointment. Please contact support.'
                ], 422);
            }

            // Validate refund amount doesn't exceed payment
            if ($request->refund_amount > $appointment->payment_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed payment amount'
                ], 422);
            }

            // Validate refund amount is valid
            if ($request->refund_amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount must be greater than zero'
                ], 422);
            }

            // Check cumulative refund total doesn't exceed original payment (prevents serial partial refund abuse)
            $cumulativeRefunded = $appointment->refunds()
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->lockForUpdate()
                ->sum('refund_amount');

            $remainingRefundable = $appointment->payment_amount - $cumulativeRefunded;

            if ($remainingRefundable <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This appointment has already been fully refunded or has pending/approved refunds covering the full amount.'
                ], 422);
            }

            if ($request->refund_amount > $remainingRefundable) {
                return response()->json([
                    'success' => false,
                    'message' => "Refund amount exceeds the remaining refundable balance. Maximum refundable: ₱" . number_format($remainingRefundable, 2)
                ], 422);
            }

            // Determine if partial refund (based on cumulative total, not just this request)
            $totalAfterThisRefund = $cumulativeRefunded + $request->refund_amount;
            $isPartial = $totalAfterThisRefund < $appointment->payment_amount;

            // Create refund record
            $refund = Refund::create([
                'appointment_id' => $appointment->id,
                'requested_by' => $request->user()->id,
                'refund_amount' => $request->refund_amount,
                'original_amount' => $appointment->payment_amount,
                'reason' => $request->reason,
                'description' => $request->description,
                'is_partial' => $isPartial,
            ]);
            // Set status explicitly (not via mass assignment)
            $refund->status = 'pending';
            $refund->save();

            // Log the action with enhanced metadata (#16)
            ActionLog::log(
                'request_refund',
                "Refund requested for {$appointment->user->first_name} {$appointment->user->last_name} - Amount: ₱{$request->refund_amount}",
                'Appointment',
                $appointment->id,
                'success',
                [
                    'refund_amount' => $request->refund_amount,
                    'reason' => $request->reason,
                    'original_payment_amount' => $appointment->payment_amount,
                    'original_cashier_id' => $appointment->processed_by,
                    'requesting_user_id' => $request->user()->id,
                ]
            );

            // Send email notification to user that refund request is being processed
            try {
                // Ensure all relationships are loaded
                $refund->load(['appointment.user', 'appointment.service', 'requestedBy']);
                
                $userEmail = $appointment->user->email;
                if (!$userEmail) {
                    \Log::warning('Cannot send refund request email: User email is missing for appointment #' . $appointment->id);
                } else {
                    \Log::info('Attempting to send refund request email to: ' . $userEmail);
                    Mail::to($userEmail)->queue(new \App\Mail\RefundRequestedMail($refund));
                    \Log::info('✅ Refund request email sent successfully to: ' . $userEmail);
                }
            } catch (\Exception $e) {
                \Log::error('❌ Failed to send refund request email: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                // Don't fail the request if email fails
            }

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
     * Get refunds for an appointment (cashier/admin - no ownership check)
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
     * Get refunds for an appointment (user-facing - verifies ownership)
     */
    public function getAppointmentRefundsForUser(Request $request, $appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // SECURITY: Verify the authenticated user owns this appointment
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view refunds for this appointment'
            ], 403);
        }

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

            $refund = Refund::with('appointment', 'appointment.user')->lockForUpdate()->findOrFail($refundId);

            // Verify status is pending
            if ($refund->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be approved'
                ], 422);
            }

            // Update refund to approved (completion is a separate step)
            $refund->status = 'approved';
            $refund->approved_by = $request->user()->id;
            $refund->approved_at = now();
            $refund->approval_notes = $request->approval_notes;
            $refund->refund_method = $request->refund_method;
            $refund->save();

            // Log the action (#15 enhanced)
            ActionLog::log(
                'approve_refund',
                "Approved refund for appointment #{$refund->appointment_id} - Amount: ₱{$refund->refund_amount}",
                'Refund',
                $refund->id,
                'success',
                [
                    'refund_amount' => $refund->refund_amount,
                    'approved_by' => $request->user()->id,
                    'appointment_id' => $refund->appointment_id,
                    'is_partial' => $refund->is_partial,
                    'refund_method' => $request->refund_method,
                ]
            );

            // Notify user with email and system message
            $this->sendRefundNotification($refund, 'approved');
            
            // Create in-app notification
            \App\Services\NotificationService::refundApproved($refund);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund approved successfully. It can now be completed by a cashier.',
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Refund approval error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
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

            $refund = Refund::with('appointment', 'appointment.user')->lockForUpdate()->findOrFail($refundId);

            if ($refund->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be rejected'
                ], 422);
            }

            $refund->status = 'rejected';
            $refund->approved_by = $request->user()->id;
            $refund->approved_at = now();
            $refund->rejection_reason = $request->rejection_reason;
            $refund->save();

            // Log the action (#15 enhanced)
            ActionLog::log(
                'reject_refund',
                "Rejected refund for appointment #{$refund->appointment_id}",
                'Refund',
                $refund->id,
                'success',
                [
                    'refund_amount' => $refund->refund_amount,
                    'rejected_by' => $request->user()->id,
                    'rejection_reason' => $request->rejection_reason,
                    'appointment_id' => $refund->appointment_id,
                ]
            );

            // Notify user
            $this->sendRefundNotification($refund, 'rejected');
            
            // Create in-app notification
            \App\Services\NotificationService::refundRejected($refund, $request->rejection_reason);

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

            $refund = Refund::with('appointment')->lockForUpdate()->findOrFail($refundId);

            if ($refund->status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved refunds can be completed'
                ], 422);
            }

            $transactionId = $request->transaction_id;
            if ($refund->refund_method === 'original_method' && $refund->appointment?->payment_type === 'online') {
                if (!$refund->appointment->paymongo_payment_id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This online payment does not have a PayMongo payment ID recorded and cannot be refunded automatically'
                    ], 422);
                }

                $paymongoRefund = $this->createPayMongoRefund($refund);
                $transactionId = $paymongoRefund['id'] ?? $transactionId;
            }

            $refund->status = 'completed';
            $refund->completed_at = now();
            $refund->transaction_id = $transactionId;
            $refund->payment_method_reversed = $refund->appointment->payment_type ?? $refund->payment_method_reversed;
            $refund->save();

            // Update appointment payment status now that refund is actually completed
            if (!$refund->is_partial) {
                $refund->appointment->payment_status = 'refunded';
                $refund->appointment->save();
            } else {
                $refund->appointment->payment_status = 'partially_refunded';
                $refund->appointment->save();
            }

            // Log the action (#15 enhanced)
            ActionLog::log(
                'complete_refund',
                "Completed refund for appointment #{$refund->appointment_id} - Amount: ₱{$refund->refund_amount}",
                'Refund',
                $refund->id,
                'success',
                [
                    'refund_id' => $refund->id,
                    'refund_amount' => $refund->refund_amount,
                    'approved_by' => $refund->approved_by,
                    'completed_by' => $request->user()->id,
                    'appointment_id' => $refund->appointment_id,
                    'is_partial' => $refund->is_partial,
                    'transaction_id' => $transactionId,
                ]
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
            'appointment' => function($q) {
                $q->select('id', 'user_id', 'payment_amount', 'service_id', 'payment_date', 'appointment_date', 'appointment_time');
            },
            'appointment.user:id,first_name,last_name,email,phone',
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
     * Get a single refund by ID (admin)
     */
    public function getRefund($refundId)
    {
        try {
            $refund = Refund::with([
                'appointment' => function($q) {
                    $q->select('id', 'user_id', 'payment_amount', 'service_id', 'payment_date', 'appointment_date', 'appointment_time');
                },
                'appointment.user:id,first_name,last_name,email,phone',
                'appointment.service:id,name',
                'requestedBy:id,first_name,last_name',
                'approvedBy:id,first_name,last_name'
            ])->findOrFail($refundId);

            return response()->json([
                'success' => true,
                'refund' => $refund
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund not found'
            ], 404);
        }
    }

    /**
     * Send refund notification to user
     */
    private function sendRefundNotification($refund, $status)
    {
        try {
            $appointment = $refund->appointment;
            $user = $appointment->user;

            // Prepare refund details for email
            $refundDetails = [
                'refund_id' => $refund->id,
                'amount' => $refund->refund_amount,
                'method' => $refund->refund_method,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'service' => $appointment->service?->name ?? 'N/A',
                'approval_notes' => $refund->approval_notes,
                'rejection_reason' => $refund->rejection_reason
            ];

            // Send email based on status - with error handling
            try {
                if (!$user->email) {
                    \Log::warning('Cannot send refund notification: User email is missing for refund #' . $refund->id);
                    return;
                }

                if ($status === 'approved') {
                    \Log::info('Attempting to send refund approved email to: ' . $user->email . ' for refund #' . $refund->id);
                    \Mail::to($user->email)->queue(new \App\Mail\RefundApprovedMail($refund));
                    \Log::info('✅ Successfully sent refund approved email to: ' . $user->email);
                } elseif ($status === 'completed') {
                    \Log::info('Attempting to send refund completed email to: ' . $user->email . ' for refund #' . $refund->id);
                    \Mail::to($user->email)->queue(new \App\Mail\RefundCompletedMail($refund));
                    \Log::info('✅ Successfully sent refund completed email to: ' . $user->email);
                } elseif ($status === 'rejected') {
                    \Log::info('Attempting to send refund rejected email to: ' . $user->email . ' for refund #' . $refund->id);
                    \Mail::to($user->email)->queue(new \App\Mail\RefundRejectedMail($refund));
                    \Log::info('✅ Successfully sent refund rejected email to: ' . $user->email);
                }
            } catch (\Exception $mailException) {
                \Log::error('❌ Mail sending error for refund #' . $refund->id . ': ' . $mailException->getMessage());
                \Log::error('Stack trace: ' . $mailException->getTraceAsString());
                // Don't fail the entire operation if email fails
            }

            $subject = match($status) {
                'approved' => 'Your Refund Request Has Been Approved',
                'completed' => 'Your Refund Has Been Processed Successfully',
                'rejected' => 'Your Refund Request Has Been Reviewed',
                default => 'Refund Status Update'
            };

            $body = match($status) {
                'approved' => "Your refund request for ₱" . number_format($refund->refund_amount, 2) . " has been approved and is ready for cashier processing. Refund Method: " . ($refund->refund_method ?: 'To be confirmed') . ". Approval Notes: " . ($refund->approval_notes ?: 'None provided'),
                'completed' => "Your refund of ₱" . number_format($refund->refund_amount, 2) . " has been successfully processed. Refund Method: {$refund->refund_method}. Approval Notes: {$refund->approval_notes}",
                'rejected' => "Your refund request for ₱" . number_format($refund->refund_amount, 2) . " has been reviewed and cannot be approved at this time. Reason: {$refund->rejection_reason}",
                default => "Your refund status has been updated."
            };

            // Create message notification in system
            // Use the admin who processed the refund, fallback to first admin
            $adminId = request()->user()?->id ?? \App\Models\User::where('role', 'admin')->first()?->id ?? 1;
            \App\Models\Message::create([
                'sender_id' => $adminId,
                'receiver_id' => $user->id,
                'subject' => $subject,
                'message' => $body,
                'type' => 'refund_notification',
                'read' => false
            ]);

            \Log::info('Refund notification processed for refund ' . $refund->id . ' with status ' . $status);

        } catch (\Exception $e) {
            \Log::error('Failed to send refund notification: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
        }
    }

    private function createPayMongoRefund(Refund $refund): array
    {
        $response = Http::withBasicAuth(config('paymongo.secret_key'), '')
            ->timeout(30)
            ->post(config('paymongo.api_base_url', 'https://api.paymongo.com/v1') . '/refunds', [
                'data' => [
                    'attributes' => [
                        'amount' => (int) round(((float) $refund->refund_amount) * 100),
                        'payment_id' => $refund->appointment->paymongo_payment_id,
                        'reason' => $this->mapRefundReasonToPayMongo($refund->reason),
                        'notes' => $this->buildPayMongoRefundNotes($refund),
                        'metadata' => [
                            'appointment_id' => (string) $refund->appointment_id,
                            'refund_id' => (string) $refund->id,
                            'reason' => (string) $refund->reason,
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $message = $response->json('errors.0.detail')
                ?? $response->json('errors.0.code')
                ?? 'Failed to create PayMongo refund';

            Log::error('PayMongo refund creation failed', [
                'refund_id' => $refund->id,
                'appointment_id' => $refund->appointment_id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new \RuntimeException($message);
        }

        return $response->json('data') ?? [];
    }

    private function mapRefundReasonToPayMongo(?string $reason): string
    {
        return match (strtolower((string) $reason)) {
            'duplicate', 'duplicate_payment' => 'duplicate',
            'fraud', 'fraudulent' => 'fraudulent',
            default => 'requested_by_customer',
        };
    }

    private function buildPayMongoRefundNotes(Refund $refund): string
    {
        $segments = [
            'Appointment #' . $refund->appointment_id,
            'Refund #' . $refund->id,
            'Reason: ' . ($refund->reason ?: 'unspecified'),
        ];

        if (!empty($refund->description)) {
            $segments[] = $refund->description;
        }

        return mb_substr(implode(' | ', $segments), 0, 255);
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
            ->whereIn('status', ['approved', 'completed', 'rejected'])
            ->count();

        if ($total === 0) return 0;

        // Count both 'approved' and 'completed' as approved (since approve sets status directly to completed)
        $approved = Refund::whereBetween('created_at', $dateRange)
            ->whereIn('status', ['approved', 'completed'])
            ->count();
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
