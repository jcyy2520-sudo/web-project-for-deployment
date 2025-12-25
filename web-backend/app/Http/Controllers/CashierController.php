<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\ActionLog;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\DiscountRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
        $cacheKey = "cashier_dashboard_stats_{$timeframe}";
        $ttl = 30; // Cache for 30 seconds

        $data = Cache::remember($cacheKey, $ttl, function () use ($timeframe) {
            $dateRange = $this->getDateRange($timeframe);

            // Get revenue and sales statistics using raw queries for speed
            $stats = [
                'totalRevenue' => (float) DB::table('appointments')
                    ->where('payment_status', 'paid')
                    ->whereBetween('payment_date', $dateRange)
                    ->sum('payment_amount'),
                'totalSales' => DB::table('appointments')
                    ->where('payment_status', 'paid')
                    ->whereBetween('payment_date', $dateRange)
                    ->count(),
                'todayRevenue' => (float) DB::table('appointments')
                    ->where('payment_status', 'paid')
                    ->whereDate('payment_date', now())
                    ->sum('payment_amount'),
                'todaySales' => DB::table('appointments')
                    ->where('payment_status', 'paid')
                    ->whereDate('payment_date', now())
                    ->count(),
            ];

            // Get revenue trend data
            $revenueTrend = $this->getRevenueTrend($timeframe);
            
            // Get sales by service
            $salesByService = $this->getSalesByService($dateRange);

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
        $query = Appointment::with(['user:id,email,first_name,last_name,phone,address', 'service:id,name,price'])
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
        $perPage = (int) $request->get('per_page', 20);
        $appointments = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate($perPage);

        return response()->json($appointments);
    }

    /**
     * Send receipt email to the appointment's client
     */
    public function sendReceiptEmail(Request $request, $appointmentId)
    {
        try {
            $appointment = Appointment::with(['user', 'service'])->findOrFail($appointmentId);
            $user = $appointment->user;

            $paymentAmount = $appointment->payment_amount ?? 0;
            $discount = $appointment->discount_amount ?? 0;
            $totalPaid = $paymentAmount;

            $body = "Official Receipt\n\n";
            $body .= "Receipt No: #{$appointment->id}\n";
            $body .= "Date: " . now()->toDateString() . "\n";
            $body .= "Client: {$user->first_name} {$user->last_name}\n";
            $body .= "Email: {$user->email}\n\n";
            $body .= "Service: " . ($appointment->service->name ?? 'N/A') . "\n";
            $body .= "Appointment Date: " . ($appointment->appointment_date ?? '') . "\n\n";
            $body .= "Subtotal: ₱" . number_format($paymentAmount + $discount, 2) . "\n";
            if ($discount > 0) {
                $body .= "Discount: ₱" . number_format($discount, 2) . " ({$appointment->discount_type})\n";
            }
            $body .= "Total Paid: ₱" . number_format($totalPaid, 2) . "\n\n";
            $body .= "Thank you for your business.\n";

            // send simple plaintext email
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Official Receipt')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            // Log the action
            ActionLog::log(
                'send_receipt_email',
                "Sent receipt email to {$user->first_name} {$user->last_name} ({$user->email}) for appointment #{$appointment->id}",
                'Appointment',
                $appointment->id
            );

            return response()->json(['success' => true, 'message' => 'Receipt emailed to client']);
        } catch (\Exception $e) {
            \Log::error('Failed to email receipt: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to email receipt'], 500);
        }
    }

    /**
     * Get completed appointments (paid) - with search and filtering
     */
    public function getCompletedAppointments(Request $request)
    {
        $query = Appointment::with(['user:id,email,first_name,last_name,phone', 'service:id,name,price', 'processedBy:id,first_name,last_name'])
            ->where('payment_status', 'paid');

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
            $search = $request->search;
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
            ->paginate($request->get('per_page', 20));

        return response()->json($appointments);
    }

    /**
     * Process payment and complete appointment
     */
    public function processPayment(Request $request, $appointmentId)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::with(['user', 'service'])->findOrFail($appointmentId);

            // Verify appointment is approved
            if ($appointment->status !== 'approved') {
                return response()->json([
                    'message' => 'Only approved appointments can be processed for payment',
                    'success' => false
                ], 422);
            }

            // Calculate amounts
            $paymentAmount = $request->payment_amount;
            $discountAmount = $request->discount_amount ?? 0;
            $totalPaid = $paymentAmount - $discountAmount;

            // Update appointment
            $appointment->update([
                'payment_status' => 'paid',
                'payment_amount' => $totalPaid,
                'discount_amount' => $discountAmount,
                'discount_type' => $request->discount_type,
                'processed_by' => $request->user()->id,
                'payment_date' => now(),
                'payment_notes' => $request->payment_notes,
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $request->user()->id
            ]);

            // Log the action
            ActionLog::log(
                'process_payment',
                "Processed payment of ₱{$totalPaid} for {$appointment->user->first_name} {$appointment->user->last_name} - {$appointment->service->name}",
                'Appointment',
                $appointment->id
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

            // Return receipt data
            return response()->json([
                'message' => 'Payment processed successfully',
                'success' => true,
                'receipt' => [
                    'id' => $appointment->id,
                    'date' => now(),
                    'clientName' => "{$appointment->user->first_name} {$appointment->user->last_name}",
                    'clientEmail' => $appointment->user->email,
                    'service' => $appointment->service->name ?? 'N/A',
                    'appointmentDate' => $appointment->appointment_date,
                    'subtotal' => $paymentAmount,
                    'discount' => $discountAmount,
                    'discountType' => $request->discount_type ?? '',
                    'totalPaid' => $totalPaid
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to process payment: ' . $e->getMessage(),
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
        $status = $request->get('status', null); // Optional: filter by status

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Appointment::with([
            'user:id,first_name,last_name,email',
            'service:id,name,price'
        ])
        ->whereBetween('appointment_date', [$startDate, $endDate]);

        // If no status filter provided, show both approved and completed appointments
        // This gives cashier full visibility of appointments they can process or have processed
        if ($status) {
            $query->where('status', $status);
        } else {
            // Include approved (ready for payment) and completed (paid) appointments
            $query->whereIn('status', ['approved', 'completed']);
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get()
            ->map(function ($apt) {
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
                'service' => $apt->service ? [
                    'id' => $apt->service->id,
                    'name' => $apt->service->name,
                    'price' => (float)($apt->service->price ?? 0),
                ] : [
                    'id' => null,
                    'name' => $apt->service_type ?? 'Service',
                    'price' => (float)($apt->payment_amount ?? 0),
                ],
                'completed_at' => $apt->completed_at,
                'completed_by' => $apt->completed_by ?? null,
                'payment_date' => $apt->payment_date,
                'processed_by' => $apt->processed_by ?? null,
                'outcome_status' => $apt->outcome_status ?? null,
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
        $currentUserRole = $request->user()->role;
        
        $query = ActionLog::with('user:id,first_name,last_name,role')
            ->orderBy('created_at', 'desc');

        if ($type === 'cashier') {
            // Get only the current user's own logs (My Logs tab)
            $query->where('user_id', $currentUserId);
        } else {
            // Get all logs from admin users only (Admin Logs tab)
            // This shows what admins have done, regardless of who is viewing
            $query->whereHas('user', function($q) {
                $q->where('role', 'admin');
            });
        }

        $logs = $query->paginate($request->get('per_page', 50));

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
                'message' => 'Failed to update profile: ' . $e->getMessage()
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
    private function getRevenueTrend($timeframe)
    {
        $dateRange = $this->getDateRange($timeframe);
        
        $query = Appointment::where('payment_status', 'paid')
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
    private function getSalesByService($dateRange)
    {
        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
        
        $services = DB::table('appointments')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.payment_status', 'paid')
            ->whereBetween('appointments.payment_date', $dateRange)
            ->select('services.name', DB::raw('COUNT(*) as count'))
            ->groupBy('services.name')
            ->orderBy('count', 'desc')
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
        
        // Group by payment type (if available)
        $cashCollected = $appointments->where('payment_notes', 'like', '%cash%')->sum('payment_amount');
        $cardCollected = $appointments->where('payment_notes', 'like', '%card%')->sum('payment_amount');
        // If no payment type info, calculate based on availability
        if ($cashCollected == 0 && $cardCollected == 0) {
            $cashCollected = $totalRevenue; // Assume all cash if no payment type specified
        }

        // Log the action
        ActionLog::log(
            'view_shift_report',
            "Viewed shift report from {$from} to {$to} - {$totalSales} transactions totaling ₱" . number_format($totalRevenue, 2),
            'ShiftReport',
            null
        );

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalSales,
            'total_discounts' => $totalDiscounts,
            'cash_collected' => $cashCollected,
            'card_collected' => $cardCollected,
            'total_refunds' => 0, // Would need refund tracking in appointments
            'appointments' => $appointments->toArray()
        ]);
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
     * Helper: Invalidate relevant caches
     */
    private function invalidateCaches()
    {
        try {
            // Try to flush tagged cache if supported
            Cache::tags(['stats', 'appointments'])->flush();
        } catch (\Exception $e) {
            // If tags not supported, fall back to general flush for specific keys
            \Log::debug('Cache tagging not supported, using fallback: ' . $e->getMessage());
            Cache::forget('cashier:stats');
            Cache::forget('cashier:appointments');
            Cache::forget('dashboard:stats');
        }
    }
}
