<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appointment;
use App\Models\UnavailableDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminMessageMail;

class AdminController extends Controller
{
    public function getStats()
    {
        try {
            // PERFORMANCE OPTIMIZATION: Cache stats for 120 seconds to avoid repeated database hits
            // This dramatically reduces load when dashboard is viewed multiple times
            $cacheKey = 'admin_stats_' . auth()->id();
            
            $statsData = Cache::remember($cacheKey, 120, function () {
                // PERFORMANCE OPTIMIZATION: Use raw counts without loading full objects
                // This is extremely fast compared to loading relationship data
                
                $stats = DB::table('appointments')
                    ->whereNull('deleted_at')
                    ->selectRaw('
                        COUNT(*) as total_appointments,
                        SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending_appointments,
                        SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved_appointments,
                        SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed_appointments,
                        SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled_appointments
                    ')
                    ->first();

                // Use count() for users - it's optimized for counting
                $totalUsers = User::count();
                $activeUsers = User::where('is_active', true)->count();
                $totalStaff = User::where('role', 'staff')->count();
                
                $totalAppointments = $stats->total_appointments ?? 0;
                $pendingAppointments = $stats->pending_appointments ?? 0;
                $approvedAppointments = $stats->approved_appointments ?? 0;
                $completedAppointments = $stats->completed_appointments ?? 0;
                $cancelledAppointments = $stats->cancelled_appointments ?? 0;
                
                // Calculate revenue - prefer actual payment_amount, fallback to service catalog price
                $revenue = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->whereNull('appointments.deleted_at')
                    ->where(function($query) {
                        $query->where('appointments.payment_status', 'paid')
                              ->orWhere(function($q) {
                                  $q->where('appointments.status', 'completed')
                                    ->whereNull('appointments.payment_status');
                              });
                    })
                    ->sum(DB::raw('COALESCE(appointments.payment_amount, services.price, 0)'));

                return [
                    'totalUsers' => $totalUsers,
                    'activeUsers' => $activeUsers,
                    'totalStaff' => $totalStaff,
                    'totalAppointments' => $totalAppointments,
                    'pendingAppointments' => $pendingAppointments,
                    'approvedAppointments' => $approvedAppointments,
                    'completedAppointments' => $completedAppointments,
                    'cancelledAppointments' => $cancelledAppointments,
                    'revenue' => (float) ($revenue ?? 0),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $statsData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // NEW METHOD: Get all appointments for admin dashboard
    public function getAllAppointments(Request $request)
    {
        try {
            // Create a cache key based on query parameters
            $cacheKey = 'admin_appointments_' . md5(json_encode($request->all()));
            $cacheDuration = 30; // Cache for 30 seconds
            
            // Only cache if no specific filters are applied (for general listing)
            $useCache = !$request->has('status') && !$request->has('date') && !$request->has('user_id');
            
            $appointmentData = $useCache 
                ? Cache::remember($cacheKey, $cacheDuration, function () use ($request) {
                    return $this->fetchAppointments($request);
                })
                : $this->fetchAppointments($request);

            return response()->json($appointmentData);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch appointments',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // Helper method to fetch appointments (refactored for caching)
    private function fetchAppointments($request)
    {
        // Only get appointments with existing users (eager load with has to ensure user exists)
        $query = Appointment::has('user')->with(['user', 'staff', 'service', 'services']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->where('appointment_date', $request->date);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Pagination or limit
        $limit = $request->get('limit', null);
        $perPage = $request->get('per_page', 10);

        if ($limit) {
            $appointments = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'data' => $appointments,
                'success' => true
            ];
        }

        $appointments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Always return in consistent format with data array
        return [
            'data' => $appointments->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $appointments->currentPage(),
                'total' => $appointments->total(),
                'per_page' => $appointments->perPage(),
                'last_page' => $appointments->lastPage()
            ]
        ];
    }

    /**
     * Get monthly appointment summary for the admin calendar modal.
     * Returns appointment counts per date and blocked/unavailable dates for a given month.
     * Read-only — no database writes.
     */
    public function getMonthlyAppointmentSummary(Request $request)
    {
        try {
            $request->validate([
                'year' => 'required|integer|min:2020|max:2099',
                'month' => 'required|integer|min:1|max:12',
            ]);

            $year = (int) $request->year;
            $month = (int) $request->month;

            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();

            // Cache for 60 seconds keyed by year-month
            $cacheKey = "admin_monthly_summary_{$year}_{$month}";

            $data = Cache::remember($cacheKey, 60, function () use ($startDate, $endDate, $year, $month) {
                // 1. Appointment counts grouped by date
                $appointmentCounts = Appointment::whereHas('user')
                    ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->select('appointment_date', DB::raw('COUNT(*) as total'))
                    ->groupBy('appointment_date')
                    ->get()
                    ->keyBy(function ($item) {
                        return Carbon::parse($item->appointment_date)->format('Y-m-d');
                    })
                    ->map(function ($item) {
                        return (int) $item->total;
                    })
                    ->toArray();

                // 2. Appointment counts by status per date
                $statusCounts = Appointment::whereHas('user')
                    ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->select('appointment_date', 'status', DB::raw('COUNT(*) as total'))
                    ->groupBy('appointment_date', 'status')
                    ->get()
                    ->groupBy(function ($item) {
                        return Carbon::parse($item->appointment_date)->format('Y-m-d');
                    })
                    ->map(function ($group) {
                        $statuses = [];
                        foreach ($group as $item) {
                            $statuses[$item->status] = (int) $item->total;
                        }
                        return $statuses;
                    })
                    ->toArray();

                // 3. Unavailable/blocked dates with reasons
                $unavailableDates = UnavailableDate::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => Carbon::parse($item->date)->format('Y-m-d'),
                            'reason' => $item->reason ?: 'Unavailable',
                        ];
                    })
                    ->toArray();

                // Also get BlackoutDate entries if the model exists
                $blackoutDates = [];
                if (class_exists(\App\Models\BlackoutDate::class)) {
                    $blackoutDates = \App\Models\BlackoutDate::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                        ->get()
                        ->map(function ($item) {
                            return [
                                'date' => Carbon::parse($item->date)->format('Y-m-d'),
                                'reason' => $item->reason ?: 'Blocked',
                            ];
                        })
                        ->toArray();
                }

                // Merge unavailable + blackout into a single keyed array
                $blockedMap = [];
                foreach (array_merge($unavailableDates, $blackoutDates) as $entry) {
                    $blockedMap[$entry['date']] = $entry['reason'];
                }

                return [
                    'appointment_counts' => $appointmentCounts,
                    'status_counts' => $statusCounts,
                    'blocked_dates' => $blockedMap,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'year' => $year,
                'month' => $month,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly summary',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ], 500);
        }
    }

    public function generateReport(Request $request)
    {
        try {
            $request->validate([
                'reportType' => 'required|in:appointments,users,revenue,system',
                'startDate' => 'required|date',
                'endDate' => 'required|date|after:startDate',
                'format' => 'required|in:pdf,excel,csv'
            ]);

            $startDate = Carbon::parse($request->startDate);
            $endDate = Carbon::parse($request->endDate);

            $reportData = [];

            switch ($request->reportType) {
                case 'appointments':
                    $reportData = $this->generateAppointmentsReport($startDate, $endDate);
                    break;
                case 'users':
                    $reportData = $this->generateUsersReport($startDate, $endDate);
                    break;
                case 'revenue':
                    $reportData = $this->generateRevenueReport($startDate, $endDate);
                    break;
                case 'system':
                    $reportData = $this->generateSystemReport($startDate, $endDate);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => $reportData,
                'metadata' => [
                    'reportType' => $request->reportType,
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                    'format' => $request->format,
                    'generatedAt' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // NEW METHOD: Send message to users
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'userId' => 'required|exists:users,id',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|in:general,appointment,notification,urgent'
            ]);

            $user = User::findOrFail($request->userId);
            $admin = $request->user();

            // Save message to database (this creates the conversation)
            // IMPORTANT: The message must be created the same way as MessageController.store()
            // to ensure it appears in both the admin message view and the user's conversation view
            $messageModel = \App\Models\Message::create([
                'sender_id' => $admin->id,
                'receiver_id' => $user->id,
                'message' => $request->message,
                'subject' => $request->subject,
                'type' => $request->type,
                'read' => false  // Message is unread by receiver until they view it
            ]);

            // Send email ONLY for appointment-related messages
            if ($request->type === 'appointment') {
                try {
                    Mail::to($user->email)->queue(new AdminMessageMail(
                        $user,
                        $request->subject,
                        $request->message,
                        $request->type
                    ));
                } catch (\Exception $emailError) {
                    \Log::warning('Failed to send admin message email: ' . $emailError->getMessage());
                    // Don't fail the API request if email fails
                }
            }

            // Log the message
            try {
                \App\Models\ActionLog::log(
                    'message',
                    "Sent message to {$user->first_name} {$user->last_name}. Message content: {$request->message}",
                    'Message',
                    $messageModel->id
                );
            } catch (\Exception $logError) {
                \Log::warning('Failed to log message action: ' . $logError->getMessage());
                // Don't fail the API request if logging fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully to ' . $user->email,
                'data' => $messageModel->load(['sender', 'receiver'])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to send admin message', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'user_id' => $request->userId ?? null,
                'admin_id' => $request->user()->id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Failed to send message: ' . $e->getMessage() : 'Failed to send message'
            ], 500);
        }
    }

    // NEW METHOD: Get detailed stats for dashboard
    public function getDetailedStats()
    {
        try {
            // PERFORMANCE FIX: Replace 30+ individual COUNT queries with 2 aggregated queries

            // User statistics — single query with conditional aggregation
            $userAgg = DB::table('users')->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN role='client' THEN 1 ELSE 0 END) as clients,
                SUM(CASE WHEN role='staff' THEN 1 ELSE 0 END) as staff,
                SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_week,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_month
            ", [
                now()->startOfWeek(), now()->endOfWeek(),
                now()->startOfMonth(), now()->endOfMonth()
            ])->first();

            $userStats = [
                'total' => (int) $userAgg->total,
                'clients' => (int) $userAgg->clients,
                'staff' => (int) $userAgg->staff,
                'admins' => (int) $userAgg->admins,
                'active' => (int) $userAgg->active,
                'inactive' => (int) $userAgg->inactive,
                'new_today' => (int) $userAgg->new_today,
                'new_week' => (int) $userAgg->new_week,
                'new_month' => (int) $userAgg->new_month,
            ];

            // Appointment statistics — single query with conditional aggregation
            $aptAgg = DB::table('appointments')->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status='declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_count,
                SUM(CASE WHEN appointment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as week_count,
                SUM(CASE WHEN appointment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as month_count
            ", [
                now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString(),
                now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
            ])->first();

            $appointmentStats = [
                'total' => (int) $aptAgg->total,
                'pending' => (int) $aptAgg->pending,
                'approved' => (int) $aptAgg->approved,
                'completed' => (int) $aptAgg->completed,
                'cancelled' => (int) $aptAgg->cancelled,
                'declined' => (int) $aptAgg->declined,
                'today' => (int) $aptAgg->today_count,
                'week' => (int) $aptAgg->week_count,
                'month' => (int) $aptAgg->month_count,
            ];

            // Appointment type breakdown — single GROUP BY query
            $typeBreakdown = DB::table('appointments')
                ->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->mapWithKeys(function ($count, $key) {
                    $types = Appointment::getTypes();
                    $label = $types[$key] ?? $key;
                    return [$label => $count];
                })
                ->toArray();

            // Revenue calculation (based on paid appointments)
            $revenue = Appointment::where('payment_status', 'paid')->sum('payment_amount');

            // Monthly trends (last 6 months)
            $monthlyTrends = $this->getMonthlyTrends();

            $stats = [
                'userStats' => $userStats,
                'appointmentStats' => $appointmentStats,
                'typeBreakdown' => $typeBreakdown,
                'revenue' => $revenue,
                'monthlyTrends' => $monthlyTrends,
                'system' => [
                    'unavailable_dates' => UnavailableDate::count(),
                    'storage_usage' => '75%', // Placeholder
                    'system_uptime' => '99.9%', // Placeholder
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch detailed stats', ['error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred']);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch detailed statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // NEW METHOD: Get monthly trends for charts
    private function getMonthlyTrends()
    {
        // PERFORMANCE FIX: Replace 18 queries (6 months × 3 queries) with 3 GROUP BY queries
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $endDate = now()->endOfMonth();

        $usersByMonth = DB::table('users')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->whereBetween('created_at', [$sixMonthsAgo, $endDate])
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->pluck('cnt', 'ym');

        $aptsByMonth = DB::table('appointments')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->whereBetween('created_at', [$sixMonthsAgo, $endDate])
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->pluck('cnt', 'ym');

        $revenueByMonth = DB::table('appointments')
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(payment_amount) as total")
            ->where('payment_status', 'paid')
            ->whereBetween('payment_date', [$sixMonthsAgo, $endDate])
            ->groupByRaw("DATE_FORMAT(payment_date, '%Y-%m')")
            ->pluck('total', 'ym');

        $months = [];
        $userCounts = [];
        $appointmentCounts = [];
        $revenueData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $months[] = $date->format('M Y');
            $userCounts[] = (int) ($usersByMonth[$key] ?? 0);
            $appointmentCounts[] = (int) ($aptsByMonth[$key] ?? 0);
            $revenueData[] = (float) ($revenueByMonth[$key] ?? 0);
        }

        return [
            'months' => $months,
            'users' => $userCounts,
            'appointments' => $appointmentCounts,
            'revenue' => $revenueData
        ];
    }

    // NEW METHOD: Get appointment analytics
    public function getAppointmentAnalytics(Request $request)
    {
        try {
            $request->validate([
                'period' => 'sometimes|in:today,week,month,year,all',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date'
            ]);

            $period = $request->period ?? 'month';
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

            switch ($period) {
                case 'today':
                    $startDate = today();
                    $endDate = today()->endOfDay();
                    break;
                case 'week':
                    $startDate = now()->startOfWeek();
                    $endDate = now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
                    break;
                case 'year':
                    $startDate = now()->startOfYear();
                    $endDate = now()->endOfYear();
                    break;
                case 'all':
                    // Use custom start_date/end_date if provided, otherwise use full range
                    if (!$request->start_date || !$request->end_date) {
                        $startDate = Carbon::parse('2020-01-01');
                        $endDate = now()->endOfDay();
                    }
                    break;
            }

            // PERFORMANCE FIX: Use aggregated queries instead of loading all records into memory

            // Summary counts — single query with conditional aggregation
            $summary = DB::table('appointments')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                    COALESCE(SUM(CASE WHEN payment_status='paid' THEN payment_amount ELSE 0 END), 0) as revenue
                ")
                ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->first();

            // Status breakdown — GROUP BY
            $statusBreakdown = DB::table('appointments')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->groupBy('status')
                ->pluck('count', 'status');

            // Type breakdown — GROUP BY
            $typeBreakdown = DB::table('appointments')
                ->select('type', DB::raw('COUNT(*) as count'))
                ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->groupBy('type')
                ->pluck('count', 'type');

            // Daily appointment counts — single GROUP BY query instead of per-day loop
            $dailyRaw = DB::table('appointments')
                ->selectRaw("DATE(appointment_date) as day, COUNT(*) as cnt")
                ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->groupByRaw("DATE(appointment_date)")
                ->pluck('cnt', 'day');

            $dailyCounts = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $key = $currentDate->format('Y-m-d');
                $dailyCounts[$currentDate->format('M j')] = (int) ($dailyRaw[$key] ?? 0);
                $currentDate->addDay();
            }

            // Staff performance — single aggregated query
            $staffPerformance = DB::table('appointments')
                ->join('users', 'appointments.staff_id', '=', 'users.id')
                ->selectRaw("
                    appointments.staff_id,
                    CONCAT(users.first_name, ' ', users.last_name) as staff_name,
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN appointments.status='completed' THEN 1 ELSE 0 END) as completed
                ")
                ->whereBetween('appointments.appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereNotNull('appointments.staff_id')
                ->groupBy('appointments.staff_id', 'users.first_name', 'users.last_name')
                ->get()
                ->map(function ($row) {
                    return [
                        'staff_name' => $row->staff_name,
                        'total_appointments' => (int) $row->total_appointments,
                        'completed' => (int) $row->completed,
                        'completion_rate' => $row->total_appointments > 0
                            ? round(($row->completed / $row->total_appointments) * 100, 2)
                            : 0,
                    ];
                })
                ->values();

            $analytics = [
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'label' => $period
                ],
                'summary' => [
                    'total' => (int) $summary->total,
                    'completed' => (int) $summary->completed,
                    'pending' => (int) $summary->pending,
                    'revenue' => round((float) $summary->revenue, 2)
                ],
                'breakdown' => [
                    'status' => $statusBreakdown,
                    'type' => $typeBreakdown
                ],
                'daily_trends' => $dailyCounts,
                'staff_performance' => $staffPerformance,
                'top_services' => collect($typeBreakdown)->sortDesc()->take(5)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch appointment analytics', ['error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred']);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch appointment analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // NEW METHOD: Get system overview
    public function getSystemOverview()
    {
        try {
            // Database sizes (approximate)
            $userCount = User::count();
            $appointmentCount = Appointment::count();
            $unavailableDateCount = UnavailableDate::count();

            // Recent activity
            $recentUsers = User::latest()->take(5)->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);
            $recentAppointments = Appointment::with(['user', 'staff'])
                ->latest()
                ->take(5)
                ->get(['id', 'user_id', 'staff_id', 'type', 'appointment_date', 'status', 'created_at']);

            // System health indicators (dynamic checks)
            $dbHealthy = true;
            try {
                DB::select('SELECT 1');
            } catch (\Exception $e) {
                $dbHealthy = false;
            }

            $systemHealth = [
                'database' => $dbHealthy ? 'healthy' : 'unhealthy',
                'mail' => config('mail.mailers.' . config('mail.default') . '.host') ? 'operational' : 'not_configured',
                'storage' => is_writable(storage_path()) ? 'normal' : 'read_only',
                'performance' => $appointmentCount < 50000 ? 'optimal' : 'high_load'
            ];

            // Pending actions - single query with conditional aggregation
            $pendingCounts = DB::table('appointments')
                ->selectRaw("
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
                    SUM(CASE WHEN staff_id IS NULL AND status = 'approved' THEN 1 ELSE 0 END) as unassigned_appointments
                ")
                ->first();

            $pendingActions = [
                'pending_appointments' => (int) ($pendingCounts->pending_appointments ?? 0),
                'unassigned_appointments' => (int) ($pendingCounts->unassigned_appointments ?? 0),
            ];

            $overview = [
                'counts' => [
                    'users' => $userCount,
                    'appointments' => $appointmentCount,
                    'unavailable_dates' => $unavailableDateCount,
                ],
                'recent_activity' => [
                    'users' => $recentUsers,
                    'appointments' => $recentAppointments
                ],
                'system_health' => $systemHealth,
                'pending_actions' => $pendingActions,
                'last_updated' => now()->toDateTimeString()
            ];

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch system overview', ['error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred']);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system overview',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // Existing report generation methods
    private function generateAppointmentsReport($startDate, $endDate)
    {
        $appointments = Appointment::with(['user', 'staff'])
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->get();

        $statusCounts = $appointments->groupBy('status')->map->count();
        $typeCounts = $appointments->groupBy('type')->map->count();

        return [
            'totalAppointments' => $appointments->count(),
            'statusBreakdown' => $statusCounts,
            'typeBreakdown' => $typeCounts,
            'appointments' => $appointments->map(function($appointment) {
                return [
                    'id' => $appointment->id,
                    'user' => $appointment->user->full_name ?? 'N/A',
                    'staff' => $appointment->staff->full_name ?? 'Unassigned',
                    'type' => $appointment->type,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'status' => $appointment->status,
                    'purpose' => $appointment->purpose
                ];
            })
        ];
    }

    private function generateUsersReport($startDate, $endDate)
    {
        $users = User::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $roleCounts = $users->groupBy('role')->map->count();

        return [
            'totalUsers' => $users->count(),
            'roleBreakdown' => $roleCounts,
            'users' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at
                ];
            })
        ];
    }

    private function generateRevenueReport($startDate, $endDate)
    {
        $completedAppointments = Appointment::where('status', 'completed')
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->count();

        // Calculate actual revenue from payment records
        $totalRevenue = (float) DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where(function($query) {
                $query->where('appointments.payment_status', 'paid')
                      ->orWhere(function($q) {
                          $q->where('appointments.status', 'completed')
                            ->whereNull('appointments.payment_status');
                      });
            })
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
            ->sum(DB::raw('COALESCE(appointments.payment_amount, services.price, 0)'));

        $avgRevenue = $completedAppointments > 0
            ? round($totalRevenue / $completedAppointments, 2)
            : 0;

        // Revenue grouped by service type from actual data
        $revenueByType = DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where(function($query) {
                $query->where('appointments.payment_status', 'paid')
                      ->orWhere(function($q) {
                          $q->where('appointments.status', 'completed')
                            ->whereNull('appointments.payment_status');
                      });
            })
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
            ->select('services.name', DB::raw('COALESCE(SUM(COALESCE(appointments.payment_amount, services.price, 0)), 0) as total'))
            ->groupBy('services.name')
            ->orderByDesc('total')
            ->get()
            ->pluck('total', 'name')
            ->toArray();

        return [
            'totalRevenue' => $totalRevenue,
            'completedAppointments' => $completedAppointments,
            'averageRevenuePerAppointment' => $avgRevenue,
            'revenueByType' => $revenueByType
        ];
    }

    private function generateSystemReport($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalAppointments = Appointment::whereBetween('created_at', [$startDate, $endDate])->count();
        $unavailableDates = UnavailableDate::whereBetween('date', [$startDate, $endDate])->count();

        // Compute basic performance metrics from available data
        $failedJobs = 0;
        $totalJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $totalJobs = DB::table('jobs')->count() + $failedJobs;
        } catch (\Exception $e) {
            // Tables may not exist in all environments
        }
        $errorRate = $totalJobs > 0 ? round(($failedJobs / max($totalJobs, 1)) * 100, 2) . '%' : '0%';

        return [
            'systemUsage' => [
                'newUsers' => $totalUsers,
                'newAppointments' => $totalAppointments,
                'unavailableDates' => $unavailableDates
            ],
            'performance' => [
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'error_rate' => $errorRate,
                'failed_jobs' => $failedJobs
            ]
        ];
    }

    /**
     * Cancel multiple appointments due to unavailable date
     * Sends cancellation notifications individually or as a group
     */
    public function cancelBulkAppointments(Request $request)
    {
        try {
            $request->validate([
                'appointment_ids' => 'required|array|min:1|max:500',
                'appointment_ids.*' => 'integer|exists:appointments,id',
                'cancellation_reason' => 'required|string|max:500',
                'message_type' => 'required|in:individual,group',
                'include_reason_in_message' => 'boolean',
                'unavailable_date' => 'required|array',
                'unavailable_date.date' => 'required|date_format:Y-m-d'
            ]);

            $appointmentIds = $request->input('appointment_ids');
            $cancellationReason = $request->input('cancellation_reason');
            $messageType = $request->input('message_type'); // individual or group
            $includeReason = $request->boolean('include_reason_in_message', true);
            $unavailableDate = $request->input('unavailable_date');

            // Wrap cancellation + logging in a DB transaction for atomicity
            $result = DB::transaction(function () use ($appointmentIds, $cancellationReason, $unavailableDate) {
                // Only cancel appointments that are in cancellable states
                $appointments = Appointment::with(['user', 'staff', 'service'])
                    ->whereIn('id', $appointmentIds)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->get();

                if ($appointments->isEmpty()) {
                    return ['appointments' => collect(), 'cancelled_count' => 0, 'skipped' => count($appointmentIds)];
                }

                // Cancel only the fetched (cancellable) appointments
                $cancelledCount = Appointment::whereIn('id', $appointments->pluck('id'))
                    ->update(['status' => 'cancelled']);

                // Log the action
                \App\Models\ActionLog::log(
                    'bulk_cancel_appointments',
                    "Cancelled {$cancelledCount} appointments due to unavailable date ({$unavailableDate['date']}). Reason: {$cancellationReason}",
                    'Appointment',
                    null
                );

                return [
                    'appointments' => $appointments,
                    'cancelled_count' => $cancelledCount,
                    'skipped' => count($appointmentIds) - $appointments->count()
                ];
            });

            $appointments = $result['appointments'];
            $cancelledCount = $result['cancelled_count'];

            // Send notifications OUTSIDE the transaction (non-critical)
            if ($appointments->isNotEmpty()) {
                if ($messageType === 'individual') {
                    foreach ($appointments as $appointment) {
                        $this->sendAppointmentCancellationMessage(
                            $appointment,
                            $cancellationReason,
                            $includeReason
                        );
                    }
                } else {
                    $this->sendGroupCancellationMessage(
                        $appointments,
                        $cancellationReason,
                        $includeReason,
                        $unavailableDate['date']
                    );
                }
            }

            // Clear relevant caches
            try {
                Cache::tags(['admin', 'appointments'])->flush();
            } catch (\Exception $e) {
                \Log::debug('Cache tagging not supported: ' . $e->getMessage());
            }
            if (auth()->id()) {
                Cache::forget('admin_stats_' . auth()->id());
            }

            $response = [
                'success' => true,
                'message' => "Successfully cancelled {$cancelledCount} appointment(s) and sent notifications",
                'cancelled_count' => $cancelledCount,
                'message_type' => $messageType
            ];

            if ($result['skipped'] > 0) {
                $response['skipped_count'] = $result['skipped'];
                $response['skipped_note'] = 'Some appointments were already completed/cancelled and were skipped.';
            }

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error cancelling bulk appointments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel appointments',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Send individual cancellation message for a single appointment
     */
    private function sendAppointmentCancellationMessage($appointment, $reason, $includeReason)
    {
        try {
            // Create in-app message
            $messageContent = "Your appointment on " . 
                $appointment->appointment_date->format('F d, Y') . 
                " at " . $appointment->appointment_time;
            
            if ($includeReason) {
                $messageContent .= " has been cancelled. Reason: " . $reason;
            } else {
                $messageContent .= " has been cancelled.";
            }

            \App\Models\Message::create([
                'sender_id' => auth()->id(), // Admin sending the message
                'receiver_id' => $appointment->user_id,
                'subject' => 'Appointment Cancelled',
                'message' => $messageContent,
                'type' => 'cancellation',
                'read' => false
            ]);

            // Send email notification
            if ($appointment->user && $appointment->user->email) {
                try {
                    Mail::to($appointment->user->email)->queue(
                        new \App\Mail\AppointmentStatusMail($appointment)
                    );
                } catch (\Exception $e) {
                    \Log::error('Failed to send cancellation email for appointment ' . $appointment->id . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error sending appointment cancellation message: ' . $e->getMessage());
        }
    }

    /**
     * Send group cancellation message to all affected users
     */
    private function sendGroupCancellationMessage($appointments, $reason, $includeReason, $unavailableDate)
    {
        try {
            // Get unique users
            $userIds = $appointments->pluck('user_id')->unique();

            // Create group message for each user
            foreach ($userIds as $userId) {
                $userAppointments = $appointments->where('user_id', $userId);
                
                $messageContent = "Multiple appointments have been cancelled due to an unavailable date (" . 
                    (new Carbon($unavailableDate))->format('F d, Y') . "):\n\n";

                foreach ($userAppointments as $apt) {
                    $messageContent .= "• " . $apt->appointment_date->format('F d, Y') . " at " . $apt->appointment_time . " - " . 
                        ($apt->service_type ?? $apt->type) . "\n";
                }

                if ($includeReason) {
                    $messageContent .= "\nReason: " . $reason;
                }

                // Create message for this user
                \App\Models\Message::create([
                    'sender_id' => auth()->id(), // Admin sending the message
                    'receiver_id' => $userId,
                    'subject' => 'Multiple Appointments Cancelled',
                    'message' => $messageContent,
                    'type' => 'cancellation',
                    'read' => false
                ]);
            }

            // Also send individual emails to each user for better notification
            foreach ($appointments as $appointment) {
                if ($appointment->user && $appointment->user->email) {
                    try {
                        Mail::to($appointment->user->email)->queue(
                            new \App\Mail\AppointmentStatusMail($appointment)
                        );
                    } catch (\Exception $e) {
                        \Log::error('Failed to send group cancellation email: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error sending group cancellation message: ' . $e->getMessage());
        }
    }

    /**
     * Reserve a suggested time slot
     * POST /api/admin/reserve-suggested-slot
     * Called from AdminDecisionSupport component
     */
    public function reserveSuggestedSlot(Request $request)
    {
        $request->validate([
            'slot' => 'required|array',
            'slot.time' => 'required|date_format:H:i',
        ]);

        try {
            $slotData = $request->input('slot');
            
            // Get the suggested time slot and check availability
            $timeSlot = $slotData['time'];
            
            // This is informational - admin can use this to understand recommended slots
            // In practice, admin would manually create the appointment through the regular flow
            return response()->json([
                'success' => true,
                'message' => 'Slot reservation acknowledged. Suggested time: ' . $timeSlot,
                'data' => [
                    'recommended_time' => $timeSlot,
                    'action' => 'Consider booking at this time for optimal availability',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error reserving suggested slot: ' . $e->getMessage() : 'Error reserving suggested slot',
            ], 500);
        }
    }

    /**
     * Get completed appointments (sales data) with timeframe filtering
     * GET /api/admin/sales
     */
    public function getSales(Request $request)
    {
        try {
            $timeframe = $request->query('timeframe', 'monthly');
            
            // Build date range based on timeframe
            $dateRange = $this->getDateRange($timeframe);
            
            // Fetch completed appointments with user and service data
            $sales = Appointment::with(['user:id,email,first_name,last_name', 'service:id,name,price'])
                ->select([
                    'id', 'user_id', 'staff_id', 'type', 'service_id', 'service_type',
                    'appointment_date', 'appointment_time', 'purpose', 'status',
                    'notes', 'created_at', 'updated_at'
                ])
                ->where('status', 'completed')
                ->whereBetween('appointment_date', $dateRange)
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->limit(1000)
                ->get();

            return response()->json([
                'data' => $sales,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales data',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Get sales/financial data across all attorneys
     */
    public function getSalesData(Request $request)
    {
        try {
            $timeframe = $request->query('timeframe', 'monthly');
            
            // Build date range based on timeframe
            $dateRange = $this->getDateRange($timeframe);
            
            // Get completed appointments with payment info
            $sales = DB::table('appointments')
                ->join('users as clients', 'appointments.user_id', '=', 'clients.id')
                ->leftJoin('users as staff', 'appointments.staff_id', '=', 'staff.id')
                ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                ->where('appointments.status', 'completed')
                ->whereBetween('appointments.appointment_date', $dateRange)
                ->select(
                    'appointments.id',
                    'appointments.appointment_date',
                    'appointments.payment_status',
                    'appointments.payment_amount',
                    'appointments.discount_amount',
                    'appointments.discount_type',
                    'services.name as service_name',
                    'services.price as service_price',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'clients.email as client_email',
                    DB::raw('COALESCE(staff.first_name, "Unassigned") as staff_first_name'),
                    DB::raw('COALESCE(staff.last_name, "") as staff_last_name'),
                    'appointments.created_at'
                )
                ->orderBy('appointments.appointment_date', 'desc')
                ->get();

            // Get summary stats
            $stats = DB::table('appointments')
                ->where('appointments.status', 'completed')
                ->whereBetween('appointments.appointment_date', $dateRange)
                ->selectRaw('
                    COUNT(*) as total_transactions,
                    SUM(COALESCE(payment_amount, 0)) as total_received,
                    SUM(COALESCE(discount_amount, 0)) as total_discounts,
                    SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as fully_paid,
                    SUM(CASE WHEN payment_status = "partial" THEN 1 ELSE 0 END) as partially_paid,
                    SUM(CASE WHEN payment_status = "unpaid" THEN 1 ELSE 0 END) as unpaid_count
                ')
                ->first();

            $collectionRate = 0;
            if ($stats && $stats->total_transactions > 0) {
                $denominator = $stats->total_received + $stats->total_discounts;
                $collectionRate = $denominator > 0 ? round(($stats->total_received / $denominator) * 100, 2) : 0;
            }

            // Payment status breakdown
            $paymentMethods = DB::table('appointments')
                ->where('appointments.status', 'completed')
                ->whereBetween('appointments.appointment_date', $dateRange)
                ->selectRaw('payment_status as method, COUNT(*) as count, SUM(COALESCE(payment_amount, 0)) as total')
                ->groupBy('payment_status')
                ->get();

            // Staff performance breakdown
            $staffPerformance = DB::table('appointments')
                ->leftJoin('users', 'appointments.staff_id', '=', 'users.id')
                ->where('appointments.status', 'completed')
                ->whereBetween('appointments.appointment_date', $dateRange)
                ->selectRaw('
                    COALESCE(users.id, 0) as user_id,
                    CONCAT(COALESCE(users.first_name, "Unassigned"), " ", COALESCE(users.last_name, "")) as staff_name,
                    COUNT(*) as transaction_count,
                    SUM(COALESCE(payment_amount, 0)) as total_received
                ')
                ->groupBy('users.id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sales,
                'stats' => [
                    'totalTransactions' => $stats->total_transactions ?? 0,
                    'totalReceived' => (float) ($stats->total_received ?? 0),
                    'totalDiscounts' => (float) ($stats->total_discounts ?? 0),
                    'fullyPaid' => $stats->fully_paid ?? 0,
                    'partiallyPaid' => $stats->partially_paid ?? 0,
                    'unpaidCount' => $stats->unpaid_count ?? 0,
                    'collectionRate' => $collectionRate,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch sales data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales data',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get date range based on timeframe
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
     * Send bulk message to multiple users
     * POST /api/admin/send-bulk-message
     * Used for notifying affected users without cancelling their appointments
     */
    public function sendBulkMessage(Request $request)
    {
        try {
            $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'message' => 'required|string|max:2000',
                'subject' => 'required|string|max:255',
                'appointment_ids' => 'nullable|array',
                'date' => 'nullable|date'
            ]);

            $userIds = $request->input('user_ids');
            $messageContent = $request->input('message');
            $subject = $request->input('subject');
            $appointmentIds = $request->input('appointment_ids', []);
            $date = $request->input('date');

            $successCount = 0;
            $failedCount = 0;

            foreach ($userIds as $userId) {
                try {
                    // Create in-app message
                    \App\Models\Message::create([
                        'sender_id' => auth()->id(),
                        'receiver_id' => $userId,
                        'subject' => $subject,
                        'message' => $messageContent,
                        'type' => 'notification',
                        'read' => false
                    ]);

                    // Send email notification
                    $user = User::find($userId);
                    if ($user && $user->email) {
                        try {
                            Mail::to($user->email)->queue(
                                new AdminMessageMail($user, $subject, $messageContent, 'notification')
                            );
                        } catch (\Exception $e) {
                            \Log::error('Failed to send email to user ' . $userId . ': ' . $e->getMessage());
                        }
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send message to user ' . $userId . ': ' . $e->getMessage());
                    $failedCount++;
                }
            }

            // Log the action
            \App\Models\ActionLog::log(
                'send_bulk_message',
                "Sent message to {$successCount} users" . ($date ? " regarding date {$date}" : ''),
                'Message',
                null
            );

            return response()->json([
                'success' => true,
                'message' => "Message sent to {$successCount} user" . ($successCount !== 1 ? 's' : ''),
                'sent_count' => $successCount,
                'failed_count' => $failedCount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error sending bulk message: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    // Attorney methods removed
}
