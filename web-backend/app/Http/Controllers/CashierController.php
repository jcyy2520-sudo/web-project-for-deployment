<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\ActionLog;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\DiscountRate;
use App\Models\Service;
use App\Services\ReceiptService;
use App\Mail\ReceiptMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CashierController extends Controller
{
    /**
     * Get cashier dashboard statistics
     * Cached for 30 seconds for faster loading
     */
    public function getDashboardStats(Request $request)
    {
        $timeframe = $request->get('timeframe', 'monthly');
        if (!in_array($timeframe, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $timeframe = 'monthly';
        }
        $cashierId = $request->user()->id;
        $cacheKey = "cashier_dashboard_stats_{$cashierId}_{$timeframe}";
        $ttl = 30; // Cache for 30 seconds

        $data = Cache::remember($cacheKey, $ttl, function () use ($timeframe, $cashierId) {
            $dateRange = $this->getDateRange($timeframe);

            // Cashier dashboard metrics must reflect only payments processed by the current cashier.
            $periodStats = DB::table('appointments')
                ->where('payment_status', 'paid')
                ->where('processed_by', $cashierId)
                ->whereBetween('payment_date', $dateRange)
                ->selectRaw('COUNT(*) as total_sales, COALESCE(SUM(payment_amount), 0) as total_revenue')
                ->first();

            $todayStats = DB::table('appointments')
                ->where('payment_status', 'paid')
                ->where('processed_by', $cashierId)
                ->whereDate('payment_date', now())
                ->selectRaw('COUNT(*) as today_sales, COALESCE(SUM(payment_amount), 0) as today_revenue')
                ->first();

            $stats = [
                'totalRevenue' => (float) ($periodStats->total_revenue ?? 0),
                'totalSales' => (int) ($periodStats->total_sales ?? 0),
                'todayRevenue' => (float) ($todayStats->today_revenue ?? 0),
                'todaySales' => (int) ($todayStats->today_sales ?? 0),
            ];

            // Get revenue trend data
            $revenueTrend = $this->getRevenueTrend($timeframe, $cashierId);
            
            // Get sales by service
            $salesByService = $this->getSalesByService($dateRange, $cashierId);

            return [
                'stats' => $stats,
                'revenueTrend' => $revenueTrend,
                'salesByService' => $salesByService,
                'success' => true
            ];
        });

        return response()->json($data);
    }

    /**
     * Get approved appointments for cashier
     */
    public function getApprovedAppointments(Request $request)
    {
        // No caching — approved appointments are volatile and change frequently
        // when admin approves or cashier processes payments
        $query = Appointment::with(['user:id,email,first_name,last_name,phone,address', 'service:id,name,price', 'activeRefund'])
            ->where('status', 'approved')
            ->where('payment_status', '!=', 'paid');

        // Filter by date if provided
        if ($request->has('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        // Get today's appointments
        if ($request->has('today') && $request->today) {
            $query->whereDate('appointment_date', now());
        }

        // support server-side pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $appointments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($appointments);
    }

    /**
     * Send receipt email to the appointment's client
     */
    public function sendReceiptEmail(Request $request, $appointmentId)
    {
        try {
            $appointment = Appointment::with(['user', 'service', 'processedBy'])->findOrFail($appointmentId);
            $user = $appointment->user;

            // Phase 6 #14: Use ReceiptService + HTML Mailable
            $latestPayment = Payment::where('appointment_id', $appointment->id)->latest('id')->first();
            $receiptData = ReceiptService::generate($appointment, $latestPayment);

            Mail::to($user->email)->queue(new ReceiptMail($receiptData));

            // Log the action
            ActionLog::log(
                'send_receipt_email',
                "Sent receipt email to {$user->first_name} {$user->last_name} ({$user->email}) for appointment #{$appointment->id}",
                'Appointment',
                $appointment->id,
                'success',
                [
                    'receipt_id' => $receiptData['receipt_id'],
                    'client_email' => $user->email,
                ]
            );

            return response()->json(['success' => true, 'message' => 'Receipt emailed to client']);
        } catch (\Exception $e) {
            \Log::error('Failed to email receipt: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to email receipt'], 500);
        }
    }

    /**
     * Get completed appointments (paid, refunded, partially_refunded) - with search and filtering
     */
    public function getCompletedAppointments(Request $request)
    {
        $query = Appointment::with(['user:id,email,first_name,last_name,phone', 'service:id,name,price', 'processedBy:id,first_name,last_name', 'activeRefund'])
            ->whereIn('payment_status', ['paid', 'refunded', 'partially_refunded']);

        // Filter by date range
        if ($request->has('from') && $request->from) {
            $query->whereDate('payment_date', '>=', $request->from);
        }
        if ($request->has('to') && $request->to) {
            $query->whereDate('payment_date', '<=', $request->to);
        }

        // Filter by specific date
        if ($request->has('date')) {
            $query->whereDate('payment_date', $request->date);
        }

        // Search by client name or email
        if ($request->has('search') && $request->search) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by service
        if ($request->has('service') && $request->service) {
            $query->where('service_id', $request->service);
        }

        // Filter by cashier (processedBy)
        if ($request->has('cashier') && $request->cashier) {
            $query->where('processed_by', $request->cashier);
        }

        $appointments = $query->orderBy('payment_date', 'desc')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json($appointments);
    }

    /**
     * Process payment and complete appointment
     */
    public function processPayment(Request $request, $appointmentId)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|max:255',
            'discount_proof' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:1000',
            'payment_type' => 'nullable|string|in:cash,partial,in-kind,card,online',
            'goods_description' => 'nullable|string|max:1000',
            'in_kind_estimated_value' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::lockForUpdate()->with(['user', 'service'])->findOrFail($appointmentId);

            // Explicit no-show rejection with clear message
            if ($appointment->status === 'no_show') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Appointments marked as no-show cannot be processed for payment',
                    'success' => false
                ], 422);
            }

            // Verify appointment is approved
            if ($appointment->status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Only approved appointments can be processed for payment',
                    'success' => false
                ], 422);
            }

            // Prevent double payment
            if ($appointment->payment_status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'message' => 'This appointment has already been paid',
                    'success' => false
                ], 422);
            }

            // Calculate amounts
            $paymentAmount = $request->payment_amount;
            $servicePrice = $appointment->service->price ?? 0;
            $paymentType = $request->payment_type ?? 'cash';

            // --- Phase 2 #10: Server-side discount recalculation from DB rates ---
            // Backend is the source of truth — ignore frontend discount_amount, recalculate from DB rate
            $discountAmount = 0;
            $discountType = $request->discount_type;
            $discountRateFromDb = null;

            if ($discountType) {
                // Normalize discount type key for DB lookup
                $discountKey = str_replace([' ', '-'], '_', strtolower($discountType));
                // Map common frontend labels to DB keys
                $keyMap = [
                    'pwd' => 'pwd',
                    '20%_pwd_discount' => 'pwd',
                    'senior' => 'senior_citizen',
                    'senior_citizen' => 'senior_citizen',
                    '20%_senior_discount' => 'senior_citizen',
                    'student' => 'student',
                    '10%_student_discount' => 'student',
                ];
                $dbKey = $keyMap[$discountKey] ?? $discountKey;

                $discountRate = DiscountRate::getByType($dbKey);
                if ($discountRate) {
                    $discountRateFromDb = (float) $discountRate->discount_percentage;
                    $discountAmount = round(($servicePrice * $discountRateFromDb) / 100, 2);
                    $discountType = ucfirst(str_replace('_', ' ', $dbKey)) . " ({$discountRateFromDb}%)";
                } else {
                    // Unknown discount type — reject
                    DB::rollBack();
                    return response()->json([
                        'message' => "Unknown or inactive discount type: {$request->discount_type}",
                        'success' => false
                    ], 422);
                }
            }

            // --- Phase 1 #1: Backend amount validation against service price ---

            // Reject: discount exceeds service price
            if ($discountAmount > $servicePrice) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Discount amount cannot exceed the service price',
                    'success' => false
                ], 422);
            }

            // Reject: negative effective total
            if (($paymentAmount - $discountAmount) < 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment amount after discount cannot be negative',
                    'success' => false
                ], 422);
            }

            // For non-partial, non-in-kind payments: amount + discount must cover service price
            if ($paymentType !== 'partial' && $paymentType !== 'in-kind') {
                if (($paymentAmount + $discountAmount) < $servicePrice) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Payment amount (₱" . number_format($paymentAmount, 2) . ") plus discount (₱" . number_format($discountAmount, 2) . ") is less than service price (₱" . number_format($servicePrice, 2) . "). Use partial payment if intended.",
                        'success' => false,
                        'shortfall' => $servicePrice - $paymentAmount - $discountAmount
                    ], 422);
                }
            }

            // For partial payments: must be > 0 but explicitly less than service price
            if ($paymentType === 'partial' && $paymentAmount <= 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Partial payment amount must be greater than zero',
                    'success' => false
                ], 422);
            }

            // --- Phase 5 #5: In-kind payment guardrails ---
            if ($paymentType === 'in-kind') {
                if (empty($request->goods_description)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'A description of the goods/services received is required for in-kind payments',
                        'success' => false
                    ], 422);
                }
                if (!$request->in_kind_estimated_value || $request->in_kind_estimated_value <= 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'An estimated peso value greater than zero is required for in-kind payments',
                        'success' => false
                    ], 422);
                }
            }

            $totalPaid = $paymentAmount - $discountAmount;
            $shortfall = max(0, $servicePrice - $paymentAmount - $discountAmount);

            // --- Phase 3 #4 + #9: Partial vs full payment flow ---
            // Calculate total paid so far (including prior partial payments)
            $priorPaidTotal = (float) Payment::where('appointment_id', $appointment->id)->sum('amount_paid');
            $totalPaidSoFar = $priorPaidTotal + $totalPaid;
            $balanceRemaining = max(0, round($servicePrice - $totalPaidSoFar - $discountAmount, 2));

            if ($paymentType === 'partial') {
                // PARTIAL PAYMENT: keep appointment as approved, track balance
                $appointment->update([
                    'discount_amount' => $discountAmount,
                    'discount_type' => $discountType,
                    'payment_type' => 'partial',
                    'payment_date' => now(),
                    'payment_notes' => $request->payment_notes,
                ]);
                $appointment->payment_status = 'partially_paid';
                $appointment->payment_amount = round($totalPaidSoFar, 2);
                $appointment->balance_remaining = $balanceRemaining;
                $appointment->processed_by = $request->user()->id;
                // Status stays 'approved' — appointment not yet complete
                $appointment->save();
            } else {
                // FULL PAYMENT (or final payment on a partially-paid appointment)
                $appointment->update([
                    'discount_amount' => $discountAmount,
                    'discount_type' => $discountType,
                    'payment_type' => $paymentType ?? 'cash',
                    'payment_date' => now(),
                    'payment_notes' => $request->payment_notes,
                    'completed_at' => now(),
                ]);
                $appointment->payment_status = 'paid';
                $appointment->payment_amount = round($totalPaidSoFar, 2);
                $appointment->balance_remaining = 0;
                $appointment->processed_by = $request->user()->id;
                $appointment->status = 'completed';
                $appointment->completed_by = $request->user()->id;
                $appointment->save();
            }

            // Create a Payment record (always — supports multiple installments)
            $newPayment = null;
            try {
                $newPayment = Payment::create([
                    'appointment_id' => $appointment->id,
                    'recorded_by' => $request->user()->id,
                    'service_price' => $servicePrice,
                    'amount_paid' => $totalPaid,
                    'discount_amount' => $discountAmount,
                    'discount_proof' => $request->discount_proof,
                    'payment_method_id' => $this->resolvePaymentMethodId($paymentType),
                    'in_kind_estimated_value' => $paymentType === 'in-kind' ? $request->in_kind_estimated_value : null,
                    'goods_description' => $paymentType === 'in-kind' ? $request->goods_description : null,
                    'payment_date' => now(),
                    'notes' => $request->payment_notes,
                ]);
                $newPayment->shortfall = round($balanceRemaining, 2);
                $newPayment->payment_status = $paymentType === 'partial' ? 'partial' : 'paid';
                $newPayment->save();
            } catch (\Exception $e) {
                \Log::warning('Failed to create Payment record during cashier processPayment: ' . $e->getMessage());
            }

            // Log the action with enhanced metadata (#1 + #15)
            ActionLog::log(
                'process_payment',
                "Processed payment of ₱{$totalPaid} for " . ($appointment->user ? "{$appointment->user->first_name} {$appointment->user->last_name}" : "User #{$appointment->user_id}") . " - " . ($appointment->service->name ?? 'N/A'),
                'Appointment',
                $appointment->id,
                'success',
                [
                    'service_price' => $servicePrice,
                    'amount_entered' => $paymentAmount,
                    'discount_type' => $discountType,
                    'discount_rate_from_db' => $discountRateFromDb,
                    'discount_amount' => $discountAmount,
                    'discount_proof' => $request->discount_proof,
                    'payment_type' => $paymentType,
                    'total_paid' => $totalPaid,
                    'total_paid_so_far' => $totalPaidSoFar,
                    'balance_remaining' => $balanceRemaining,
                    'shortfall' => $shortfall,
                    'in_kind_description' => $paymentType === 'in-kind' ? $request->goods_description : null,
                    'in_kind_estimated_value' => $paymentType === 'in-kind' ? $request->in_kind_estimated_value : null,
                    'client_name' => $appointment->user ? "{$appointment->user->first_name} {$appointment->user->last_name}" : null,
                ]
            );

            // Create message notification for user
            $this->sendPaymentNotification($appointment, $request->user());

            // Invalidate caches
            $this->invalidateCaches();

            DB::commit();

            // Broadcast appointment update (payment completed) so dashboards update in real-time
            try {
                $appointment->refresh();
                $appointment->load(['user', 'service']);
                event(new \App\Events\AppointmentUpdated($appointment));
            } catch (\Exception $e) {
                \Log::debug('Failed to broadcast appointment payment event: ' . $e->getMessage());
            }

            // Return receipt data — Phase 6 #3: server-generated with integrity hash
            $receiptData = ReceiptService::generate($appointment, $newPayment);

            return response()->json([
                'message' => $paymentType === 'partial' ? 'Partial payment recorded successfully' : 'Payment processed successfully',
                'success' => true,
                'receipt' => [
                    'id' => $appointment->id,
                    'receiptId' => $receiptData['receipt_id'] ?? null,
                    'integrityHash' => $receiptData['integrity_hash'] ?? null,
                    'date' => $receiptData['date'] ?? now()->toIso8601String(),
                    'clientName' => $receiptData['client_name'] ?? 'N/A',
                    'clientEmail' => $receiptData['client_email'] ?? '',
                    'service' => $receiptData['service'] ?? 'N/A',
                    'appointmentDate' => $receiptData['appointment_date'] ?? null,
                    'subtotal' => $paymentAmount,
                    'discount' => $discountAmount,
                    'discountType' => $discountType ?? '',
                    'totalPaid' => $totalPaid,
                    'totalPaidSoFar' => $totalPaidSoFar,
                    'balanceRemaining' => $balanceRemaining,
                    'paymentType' => $paymentType,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing error: ' . $e->getMessage());
            
            return response()->json([
                'message' => config('app.debug') ? 'Failed to process payment: ' . $e->getMessage() : 'Failed to process payment',
                'success' => false
            ], 500);
        }
    }

    /**
     * Get appointments for calendar view
     * Returns flat array with all appointments (not filtered by status by default)
     * Includes all required fields for calendar display
     */
    public function getCalendarAppointments(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $requestedStatuses = $request->input('status');

        $statuses = collect(is_array($requestedStatuses) ? $requestedStatuses : explode(',', (string) $requestedStatuses))
            ->map(fn ($status) => trim((string) $status))
            ->filter(fn ($status) => in_array($status, ['pending', 'approved', 'completed', 'cancelled', 'declined', 'no_show'], true))
            ->values();

        if ($statuses->isEmpty()) {
            $statuses = collect(['pending', 'approved']);
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Appointment::with([
            'user:id,first_name,last_name,email',
            'service:id,name,price',
            'services:id,name,price',
            'activeRefund'
        ])
        ->whereBetween('appointment_date', [$startDate, $endDate])
        ->whereIn('status', $statuses->all());

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get()
            ->map(function ($apt) {
            $serviceItems = $apt->services
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => (float) ($service->pivot->price_at_booking ?? $service->price ?? 0),
                    ];
                })
                ->values();

            $primaryService = $serviceItems->first();
            $serviceName = $serviceItems->pluck('name')->filter()->join(', ');
            $servicePrice = $serviceItems->sum('price');

            if (!$primaryService && $apt->service) {
                $primaryService = [
                    'id' => $apt->service->id,
                    'name' => $apt->service->name,
                    'price' => (float) ($apt->service->price ?? 0),
                ];
                $serviceName = $apt->service->name;
                $servicePrice = (float) ($apt->service->price ?? 0);
            }

            if (!$primaryService) {
                $serviceName = $serviceName ?: ($apt->service_type ?? 'Service');
                $servicePrice = $servicePrice ?: (float) ($apt->payment_amount ?? 0);
                $primaryService = [
                    'id' => null,
                    'name' => $serviceName,
                    'price' => $servicePrice,
                ];
            }

            return [
                'id' => $apt->id,
                'user_id' => $apt->user_id,
                'staff_id' => $apt->staff_id,
                'appointment_date' => $apt->appointment_date ? $apt->appointment_date->format('Y-m-d') : null,
                'start_time' => $apt->start_time ?? $apt->appointment_time ?? '00:00',
                'end_time' => $apt->end_time ?? null,
                'status' => $apt->status,
                'payment_status' => $apt->payment_status ?? 'unpaid',
                'payment_amount' => (float)($apt->payment_amount ?? 0),
                'amount_paid' => (float)($apt->payment_amount ?? 0), // Amount already paid
                'discount_amount' => (float)($apt->discount_amount ?? 0),
                'discount_type' => $apt->discount_type ?? null,
                'purpose' => $apt->purpose ?? null,
                'type' => $apt->type ?? 'in-person',
                'service_type' => $apt->service_type ?? null,
                'identification_type' => $apt->identification_type ?? 'Not specified',
                'form_of_id' => $apt->identification_type ?? 'Not specified',
                'notes' => $apt->notes ?? null,
                'payment_notes' => $apt->payment_notes ?? null,
                'user' => $apt->user ? [
                    'id' => $apt->user->id,
                    'first_name' => $apt->user->first_name,
                    'last_name' => $apt->user->last_name,
                    'email' => $apt->user->email,
                ] : null,
                'service' => [
                    'id' => $primaryService['id'],
                    'name' => $serviceName ?: $primaryService['name'],
                    'price' => $servicePrice ?: (float) ($primaryService['price'] ?? 0),
                ],
                'services' => $serviceItems,
                'completed_at' => $apt->completed_at,
                'completed_by' => $apt->completed_by ?? null,
                'payment_date' => $apt->payment_date,
                'processed_by' => $apt->processed_by ?? null,
                'outcome_status' => $apt->outcome_status ?? null,
                'refund_status' => $apt->activeRefund ? $apt->activeRefund->status : null,
                'refund_amount' => $apt->activeRefund ? (float)$apt->activeRefund->refund_amount : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appointments,
            'appointments' => $appointments, // For backward compatibility
            'count' => $appointments->count(),
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Get action logs (admin and cashier)
     * type=admin: Show logs from users with admin role
     * type=cashier: Show logs from the current logged-in user only (cashier's own actions)
     */
    public function getActionLogs(Request $request)
    {
        $type = $request->get('type', 'cashier'); // default to 'cashier' for cashier's own logs
        $currentUserId = $request->user()->id;
        
        $query = ActionLog::with('user:id,first_name,last_name,role')
            ->orderBy('created_at', 'desc');

        if ($type === 'cashier') {
            // Get only the current user's own logs (My Logs tab)
            $query->where('user_id', $currentUserId);
        } else {
            // Admin logs may come from the legacy role column or the Spatie admin role.
            $query->whereHas('user', function ($q) {
                $q->where(function ($roleQuery) {
                    $roleQuery->where('role', 'admin')
                        ->orWhereHas('roles', function ($spatieRoleQuery) {
                            $spatieRoleQuery->where('name', 'admin');
                        });
                });
            });
        }

        $logs = $query->paginate(min((int) $request->get('per_page', 50), 100));

        return response()->json($logs);
    }

    /**
     * Get cashier profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'user' => $user,
            'success' => true
        ]);
    }

    /**
     * Update cashier profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $request->user()->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500'
        ]);

        try {
            $user = $request->user();

            // Update user fields
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $user->last_name = $request->last_name;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }

            $user->save();

            // Log the action
            ActionLog::log(
                'update_profile',
                "Updated profile information",
                'User',
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Failed to update profile: ' . $e->getMessage() : 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Helper: Get date range based on timeframe
     */
    private function getDateRange($timeframe = 'monthly')
    {
        $now = now();
        
        switch ($timeframe) {
            case 'daily':
                return [
                    $now->copy()->subDays(6)->startOfDay(),
                    $now->copy()->endOfDay()
                ];
            
            case 'weekly':
                return [
                    $now->copy()->subWeeks(11)->startOfWeek(),
                    $now->copy()->endOfDay()
                ];
            
            case 'yearly':
                return [
                    $now->copy()->subYears(4)->startOfYear(),
                    $now->copy()->endOfDay()
                ];
            
            case 'monthly':
            default:
                return [
                    $now->copy()->subMonths(11)->startOfMonth(),
                    $now->copy()->endOfDay()
                ];
        }
    }

    /**
     * Helper: Get revenue trend
     */
    private function getRevenueTrend($timeframe, int $cashierId)
    {
        $dateRange = $this->getDateRange($timeframe);
        
        $query = Appointment::where('payment_status', 'paid')
            ->where('processed_by', $cashierId)
            ->whereBetween('payment_date', $dateRange)
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(payment_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return $query->map(function($item) {
            return [
                'label' => Carbon::parse($item->date)->format('M d'),
                'value' => (float) $item->revenue
            ];
        });
    }

    /**
     * Helper: Get sales by service - optimized with raw query
     */
    private function getSalesByService($dateRange, int $cashierId)
    {
        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

        $pivotSales = DB::table('appointment_service')
            ->join('appointments', 'appointments.id', '=', 'appointment_service.appointment_id')
            ->where('appointments.payment_status', 'paid')
            ->where('appointments.processed_by', $cashierId)
            ->whereBetween('appointments.payment_date', $dateRange)
            ->select('appointment_service.service_id', DB::raw('COUNT(DISTINCT appointment_service.appointment_id) as appointment_count'))
            ->groupBy('appointment_service.service_id');

        $legacySales = DB::table('appointments')
            ->whereNotNull('service_id')
            ->where('payment_status', 'paid')
            ->where('processed_by', $cashierId)
            ->whereBetween('payment_date', $dateRange)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('appointment_service')
                    ->whereColumn('appointment_service.appointment_id', 'appointments.id');
            })
            ->select('service_id', DB::raw('COUNT(*) as appointment_count'))
            ->groupBy('service_id');

        $services = Service::query()
            ->where('services.is_active', true)
            ->leftJoinSub($pivotSales, 'pivot_sales', function ($join) {
                $join->on('services.id', '=', 'pivot_sales.service_id');
            })
            ->leftJoinSub($legacySales, 'legacy_sales', function ($join) {
                $join->on('services.id', '=', 'legacy_sales.service_id');
            })
            ->select(
                'services.name',
                DB::raw('COALESCE(pivot_sales.appointment_count, 0) + COALESCE(legacy_sales.appointment_count, 0) as count')
            )
            ->orderByDesc('count')
            ->orderBy('services.name')
            ->get();

        return $services->map(function($item, $index) use ($colors) {
            return [
                'label' => $item->name ?? 'Unknown',
                'value' => (int) $item->count,
                'color' => $colors[$index % count($colors)]
            ];
        })->values();
    }

    /**
     * Helper: Get random color for charts
     */
    private function getRandomColor()
    {
        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
        return $colors[array_rand($colors)];
    }

    private function resolvePaymentMethodId(string $paymentType): int
    {
        $definition = match ($paymentType) {
            'card' => ['slug' => 'card', 'name' => 'Card', 'description' => 'Credit/Debit card payment'],
            'in-kind' => ['slug' => 'goods_barter', 'name' => 'Goods/Barter', 'description' => 'Payment in goods or services'],
            'online' => ['slug' => 'online_gateway', 'name' => 'Online Gateway', 'description' => 'Hosted online checkout payment'],
            default => ['slug' => 'cash', 'name' => 'Cash', 'description' => 'Cash payment'],
        };

        return PaymentMethod::firstOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'description' => $definition['description'],
            ]
        )->id;
    }

    /**
     * Helper: Send payment notification
     */
    private function sendPaymentNotification($appointment, $cashier)
    {
        try {
            $appointmentDate = Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
            $serviceType = $appointment->service->name ?? 'N/A';
            
            $messageText = "✓ Your payment has been processed!\n\n";
            $messageText .= "📅 Date: " . $appointmentDate . "\n";
            $messageText .= "📋 Service: " . $serviceType . "\n";
            $messageText .= "💰 Amount Paid: ₱" . number_format($appointment->payment_amount, 2) . "\n";
            
            if ($appointment->discount_amount > 0) {
                $messageText .= "🎫 Discount: ₱" . number_format($appointment->discount_amount, 2) . " ({$appointment->discount_type})\n";
            }
            
            $messageText .= "\nThank you for your payment. Your receipt has been generated.";
            
            Message::create([
                'sender_id' => $cashier->id,
                'receiver_id' => $appointment->user_id,
                'message' => $messageText,
                'read' => false,
                'type' => 'payment_processed'
            ]);
            
            // Create in-app notification
            \App\Services\NotificationService::paymentProcessed($appointment, $appointment->payment_amount);
            
        } catch (\Exception $e) {
            \Log::error('Failed to send payment notification: ' . $e->getMessage());
        }
    }

    /**
     * Get shift report for date range
     */
    public function getShiftReport(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        
        // Default to today if not specified
        if (!$from || !$to) {
            $from = now()->toDateString();
            $to = now()->toDateString();
        }

        // Get all completed appointments in the date range for current cashier
        $appointments = Appointment::where('payment_status', 'paid')
            ->where('processed_by', $request->user()->id)
            ->whereBetween('payment_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        // Calculate totals
        $totalRevenue = $appointments->sum('payment_amount');
        $totalSales = $appointments->count();
        $totalDiscounts = $appointments->sum('discount_amount');
        
        // Group by payment type using the dedicated payment_type column
        $cashCollected = $appointments->where('payment_type', 'cash')->sum('payment_amount');
        $cardCollected = $appointments->where('payment_type', 'card')->sum('payment_amount');
        $partialCollected = $appointments->where('payment_type', 'partial')->sum('payment_amount');
        $inKindCount = $appointments->where('payment_type', 'in-kind')->count();
        // Fallback: if no payment_type data is present, assume all cash
        if ($cashCollected == 0 && $cardCollected == 0 && $partialCollected == 0 && $inKindCount == 0) {
            $cashCollected = $totalRevenue;
        }

        // Calculate actual refund amounts for this cashier's appointments in the period
        $appointmentIds = $appointments->pluck('id')->toArray();
        $totalRefunds = 0;
        $refundCount = 0;
        if (!empty($appointmentIds)) {
            $refundData = \App\Models\Refund::whereIn('appointment_id', $appointmentIds)
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(refund_amount), 0) as total')
                ->first();
            $totalRefunds = (float) ($refundData->total ?? 0);
            $refundCount = (int) ($refundData->count ?? 0);
        }

        // Log the action
        ActionLog::log(
            'view_shift_report',
            "Viewed shift report from {$from} to {$to} - {$totalSales} transactions totaling ₱" . number_format($totalRevenue, 2),
            'ShiftReport',
            null
        );

        // --- Phase 7 #11: Enhanced breakdowns ---

        // Revenue by service type
        $appointmentsWithService = Appointment::where('payment_status', 'paid')
            ->where('processed_by', $request->user()->id)
            ->whereBetween('payment_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('service:id,name')
            ->get();

        $revenueByService = $appointmentsWithService->groupBy(fn($a) => $a->service->name ?? 'Unknown')
            ->map(fn($group, $name) => [
                'service' => $name,
                'count' => $group->count(),
                'revenue' => round($group->sum('payment_amount'), 2),
            ])->values()->toArray();

        // Discount usage summary
        $discountUsage = $appointments->filter(fn($a) => $a->discount_amount > 0)
            ->groupBy('discount_type')
            ->map(fn($group, $type) => [
                'type' => $type ?: 'Unknown',
                'count' => $group->count(),
                'total' => round($group->sum('discount_amount'), 2),
            ])->values()->toArray();

        // In-kind summary from Payment records
        $inKindPayments = [];
        if (!empty($appointmentIds)) {
            $inKindPayments = Payment::whereIn('appointment_id', $appointmentIds)
                ->whereNotNull('goods_description')
                ->get();
        }
        $inKindSummary = [
            'count' => $inKindCount,
            'total_estimated_value' => round(collect($inKindPayments)->sum('in_kind_estimated_value'), 2),
        ];

        // Hourly distribution of payments
        $hourlyDistribution = [];
        foreach ($appointments as $apt) {
            if ($apt->payment_date) {
                $hour = Carbon::parse($apt->payment_date)->format('H');
                if (!isset($hourlyDistribution[$hour])) {
                    $hourlyDistribution[$hour] = ['hour' => (int) $hour, 'count' => 0, 'revenue' => 0];
                }
                $hourlyDistribution[$hour]['count']++;
                $hourlyDistribution[$hour]['revenue'] = round($hourlyDistribution[$hour]['revenue'] + (float) $apt->payment_amount, 2);
            }
        }
        // Sort by hour and fill gaps
        ksort($hourlyDistribution);
        $hourlyDistribution = array_values($hourlyDistribution);

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalSales,
            'total_discounts' => $totalDiscounts,
            'cash_collected' => $cashCollected,
            'card_collected' => $cardCollected,
            'partial_collected' => $partialCollected,
            'in_kind_count' => $inKindCount,
            'total_refunds' => $totalRefunds,
            'refund_count' => $refundCount,
            'net_revenue' => $totalRevenue - $totalRefunds,
            'revenue_by_service' => $revenueByService,
            'discount_usage' => $discountUsage,
            'in_kind_summary' => $inKindSummary,
            'hourly_distribution' => $hourlyDistribution,
            'appointments' => $appointments->toArray()
        ]);
    }

    /**
     * Change cashier password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        try {
            $user->password = \Hash::make($request->new_password);
            $user->save();

            ActionLog::log(
                'change_password',
                "Changed account password",
                'User',
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Password change error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Export shift report to accounting system
     */
    public function exportShiftReport(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');

        try {
            // Get the shift report data
            $appointments = Appointment::where('payment_status', 'paid')
                ->where('processed_by', $request->user()->id)
                ->whereBetween('payment_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->with(['user', 'service'])
                ->get();

            // Log the export action
            ActionLog::log(
                'export_shift_report',
                "Exported shift report from {$from} to {$to} - {$appointments->count()} transactions",
                'ShiftReport',
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Shift report export initiated',
                'count' => $appointments->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Shift report export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export shift report'
            ], 500);
        }
    }

    /**
     * Get payment history for an appointment (Phase 3 — partial payments)
     */
    public function getPaymentHistory($appointmentId)
    {
        $appointment = Appointment::with('service:id,name,price')->findOrFail($appointmentId);
        $payments = Payment::where('appointment_id', $appointmentId)
            ->with('recordedBy:id,first_name,last_name')
            ->orderBy('created_at', 'asc')
            ->get();

        $servicePrice = $appointment->service->price ?? 0;
        $totalPaid = $payments->sum('amount_paid');

        return response()->json([
            'success' => true,
            'service_price' => (float) $servicePrice,
            'total_paid' => (float) $totalPaid,
            'balance_remaining' => (float) max(0, $servicePrice - $totalPaid),
            'payment_status' => $appointment->payment_status,
            'payments' => $payments,
        ]);
    }

    /**
     * Phase 6 #3: Re-fetch receipt for reprints — recomputes integrity hash.
     */
    public function getReceipt($appointmentId)
    {
        $appointment = Appointment::with(['user', 'service', 'processedBy'])->findOrFail($appointmentId);
        $latestPayment = Payment::where('appointment_id', $appointment->id)->latest('id')->first();
        $receiptData = ReceiptService::generate($appointment, $latestPayment);

        ActionLog::log(
            'reprint_receipt',
            "Reprinted receipt for appointment #{$appointment->id}",
            'Appointment',
            $appointment->id,
            'success',
            ['receipt_id' => $receiptData['receipt_id'], 'appointment_id' => $appointment->id]
        );

        return response()->json(['success' => true, 'receipt' => $receiptData]);
    }

    /**
     * Helper: Invalidate relevant caches
     */
    private function invalidateCaches()
    {
        try {
            // Try to flush tagged cache if supported
            Cache::tags(['stats', 'appointments'])->flush();
        } catch (\Exception $e) {
            // If tags not supported, clear known cashier cache keys by pattern
            \Log::debug('Cache tagging not supported, using key-based fallback: ' . $e->getMessage());
            $cashierId = auth()->id();
            foreach (['daily', 'weekly', 'monthly', 'yearly'] as $timeframe) {
                Cache::forget("cashier_dashboard_stats_{$timeframe}");
                if ($cashierId) {
                    Cache::forget("cashier_dashboard_stats_{$cashierId}_{$timeframe}");
                }
            }
            // Clear approved appointments cache (pattern-based keys)
            // Since keys are hashed, we clear the most common patterns
            Cache::forget('cashier:stats');
            Cache::forget('cashier:appointments');
            Cache::forget('dashboard:stats');
            Cache::forget('public_init_data');
        }
    }

    /**
     * Get active discount rates from database
     * Phase 2 #10: Frontend fetches rates instead of hardcoding
     */
    public function getDiscountRates()
    {
        $rates = DiscountRate::activeDiscounts();

        return response()->json([
            'success' => true,
            'rates' => $rates->map(function ($rate) {
                return [
                    'type' => $rate->discount_type,
                    'percentage' => (float) $rate->discount_percentage,
                    'description' => $rate->description,
                ];
            })
        ]);
    }
}
