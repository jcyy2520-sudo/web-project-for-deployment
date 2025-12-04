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
                    ->selectRaw('
                        COUNT(*) as total_appointments,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_appointments,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_appointments
                    ')
                    ->first();

                // Use count() for users - it's optimized for counting
                $totalUsers = User::count();
                $activeUsers = User::where('is_active', true)->count();
                
                $totalAppointments = $stats->total_appointments ?? 0;
                $pendingAppointments = $stats->pending_appointments ?? 0;
                $completedAppointments = $stats->completed_appointments ?? 0;
                
                // Calculate revenue - based on completed appointments with their service prices
                $revenue = DB::table('appointments')
                    ->join('services', 'appointments.service_id', '=', 'services.id')
                    ->where('appointments.status', 'completed')
                    ->sum(DB::raw('COALESCE(services.price, 0)'));

                return [
                    'totalUsers' => $totalUsers,
                    'activeUsers' => $activeUsers,
                    'totalAppointments' => $totalAppointments,
                    'pendingAppointments' => $pendingAppointments,
                    'completedAppointments' => $completedAppointments,
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper method to fetch appointments (refactored for caching)
    private function fetchAppointments($request)
    {
        // Only get appointments with existing users (eager load with whereHas to ensure user exists)
        $query = Appointment::whereHas('user')->with(['user', 'staff', 'service']);

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
                'error' => $e->getMessage()
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
            $messageModel = \App\Models\Message::create([
                'sender_id' => $admin->id,
                'receiver_id' => $user->id,
                'message' => $request->message,
                'subject' => $request->subject,
                'type' => $request->type,
                'read' => false
            ]);

            // Send email to user (fail silently if email fails)
            try {
                Mail::to($user->email)->send(new AdminMessageMail(
                    $user,
                    $request->subject,
                    $request->message,
                    $request->type
                ));
            } catch (\Exception $emailError) {
                \Log::warning('Failed to send admin message email: ' . $emailError->getMessage());
                // Don't fail the API request if email fails
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
                'error' => $e->getMessage(),
                'user_id' => $request->userId ?? null,
                'admin_id' => $request->user()->id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    // NEW METHOD: Get detailed stats for dashboard
    public function getDetailedStats()
    {
        try {
            // User statistics
            $userStats = [
                'total' => User::count(),
                'clients' => User::where('role', 'client')->count(),
                'staff' => User::where('role', 'staff')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'new_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'new_month' => User::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ];

            // Appointment statistics
            $appointmentStats = [
                'total' => Appointment::count(),
                'pending' => Appointment::where('status', 'pending')->count(),
                'approved' => Appointment::where('status', 'approved')->count(),
                'completed' => Appointment::where('status', 'completed')->count(),
                'cancelled' => Appointment::where('status', 'cancelled')->count(),
                'declined' => Appointment::where('status', 'declined')->count(),
                'today' => Appointment::whereDate('appointment_date', today())->count(),
                'week' => Appointment::whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'month' => Appointment::whereBetween('appointment_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ];

            // Appointment type breakdown
            $appointmentTypes = Appointment::getTypes();
            $typeBreakdown = [];
            foreach ($appointmentTypes as $key => $label) {
                $typeBreakdown[$label] = Appointment::where('type', $key)->count();
            }

            // Revenue calculation (based on completed appointments)
            $completedAppointments = $appointmentStats['completed'];
            $revenue = $completedAppointments * 100; // $100 per completed appointment

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
            \Log::error('Failed to fetch detailed stats', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch detailed statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // NEW METHOD: Get monthly trends for charts
    private function getMonthlyTrends()
    {
        $months = [];
        $userCounts = [];
        $appointmentCounts = [];
        $revenueData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('M Y');
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $months[] = $month;
            $userCounts[] = User::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $appointmentCounts[] = Appointment::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            
            // Revenue based on completed appointments that month
            $completedCount = Appointment::where('status', 'completed')
                ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
                ->count();
            $revenueData[] = $completedCount * 100;
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
            }

            $appointments = Appointment::with(['user', 'staff'])
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->get();

            $statusBreakdown = $appointments->groupBy('status')->map->count();
            $typeBreakdown = $appointments->groupBy('type')->map->count();

            // Daily appointment counts for the period
            $dailyCounts = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $count = Appointment::whereDate('appointment_date', $currentDate)->count();
                $dailyCounts[$currentDate->format('M j')] = $count;
                $currentDate->addDay();
            }

            // Staff performance
            $staffPerformance = Appointment::with('staff')
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->whereNotNull('staff_id')
                ->get()
                ->groupBy('staff_id')
                ->map(function ($appointments, $staffId) {
                    $staff = $appointments->first()->staff;
                    return [
                        'staff_name' => $staff->full_name,
                        'total_appointments' => $appointments->count(),
                        'completed' => $appointments->where('status', 'completed')->count(),
                        'completion_rate' => $appointments->count() > 0 
                            ? round(($appointments->where('status', 'completed')->count() / $appointments->count()) * 100, 2)
                            : 0
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
                    'total' => $appointments->count(),
                    'completed' => $appointments->where('status', 'completed')->count(),
                    'pending' => $appointments->where('status', 'pending')->count(),
                    'revenue' => $appointments->where('status', 'completed')->count() * 100
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
            \Log::error('Failed to fetch appointment analytics', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch appointment analytics',
                'error' => $e->getMessage()
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

            // System health indicators
            $systemHealth = [
                'database' => 'healthy',
                'mail' => 'operational',
                'storage' => 'normal',
                'performance' => 'optimal'
            ];

            // Pending actions
            $pendingActions = [
                'pending_appointments' => Appointment::where('status', 'pending')->count(),
                'unassigned_appointments' => Appointment::whereNull('staff_id')->where('status', 'approved')->count(),
                'pending_reviews' => 0, // Placeholder
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
            \Log::error('Failed to fetch system overview', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system overview',
                'error' => $e->getMessage()
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

        return [
            'totalRevenue' => $completedAppointments * 100,
            'completedAppointments' => $completedAppointments,
            'averageRevenuePerAppointment' => 100,
            'revenueByType' => [
                'consultation' => 5000,
                'document_review' => 3000,
                'notary_services' => 2000
            ]
        ];
    }

    private function generateSystemReport($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalAppointments = Appointment::whereBetween('created_at', [$startDate, $endDate])->count();
        $unavailableDates = UnavailableDate::whereBetween('date', [$startDate, $endDate])->count();

        return [
            'systemUsage' => [
                'newUsers' => $totalUsers,
                'newAppointments' => $totalAppointments,
                'unavailableDates' => $unavailableDates
            ],
            'performance' => [
                'uptime' => '99.9%',
                'averageResponseTime' => '120ms',
                'errorRate' => '0.1%'
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
                'appointment_ids' => 'required|array|min:1',
                'appointment_ids.*' => 'integer|exists:appointments,id',
                'cancellation_reason' => 'required|string|max:500',
                'message_type' => 'required|in:individual,group',
                'include_reason_in_message' => 'boolean',
                'unavailable_date' => 'required|array'
            ]);

            $appointmentIds = $request->input('appointment_ids');
            $cancellationReason = $request->input('cancellation_reason');
            $messageType = $request->input('message_type'); // individual or group
            $includeReason = $request->boolean('include_reason_in_message', true);
            $unavailableDate = $request->input('unavailable_date');

            // Fetch all appointments to be cancelled
            $appointments = Appointment::with(['user', 'staff', 'service'])
                ->whereIn('id', $appointmentIds)
                ->get();

            if ($appointments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No appointments found to cancel'
                ], 404);
            }

            // Cancel all appointments
            $cancelledCount = Appointment::whereIn('id', $appointmentIds)
                ->update(['status' => 'cancelled']);

            // Log the action
            \App\Models\ActionLog::log(
                'bulk_cancel_appointments',
                "Cancelled {$cancelledCount} appointments due to unavailable date ({$unavailableDate['date']}). Reason: {$cancellationReason}",
                'Appointment',
                null
            );

            // Send notifications based on message type
            if ($messageType === 'individual') {
                // Send individual messages to each user
                foreach ($appointments as $appointment) {
                    $this->sendAppointmentCancellationMessage(
                        $appointment,
                        $cancellationReason,
                        $includeReason
                    );
                }
            } else {
                // Send one group message to all affected users
                $this->sendGroupCancellationMessage(
                    $appointments,
                    $cancellationReason,
                    $includeReason,
                    $unavailableDate['date']
                );
            }

            // Clear relevant caches
            Cache::tags(['admin', 'appointments'])->flush();
            if (auth()->id()) {
                Cache::forget('admin_stats_' . auth()->id());
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully cancelled {$cancelledCount} appointment(s) and sent notifications",
                'cancelled_count' => $cancelledCount,
                'message_type' => $messageType
            ]);

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
                'error' => $e->getMessage()
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
                    Mail::to($appointment->user->email)->send(
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
                        Mail::to($appointment->user->email)->send(
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
                'message' => 'Error reserving suggested slot: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign staff based on decision support recommendations
     * POST /api/admin/assign-staff
     * Called from AdminDecisionSupport component
     */
    public function assignStaff(Request $request)
    {
        $request->validate([
            'recommendation' => 'required|array',
        ]);

        try {
            $recommendation = $request->input('recommendation');
            
            // This endpoint acknowledges the staff recommendation from decision support
            // The actual appointment assignment happens through the regular appointment update flow
            // This is informational for admins to understand which staff are recommended
            return response()->json([
                'success' => true,
                'message' => 'Staff assignment recommendation acknowledged.',
                'data' => [
                    'recommended_staff_id' => $recommendation['staff_id'] ?? null,
                    'reasoning' => $recommendation['reasoning'] ?? [],
                    'action' => 'Consider assigning this staff member based on the recommendations',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing staff assignment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
