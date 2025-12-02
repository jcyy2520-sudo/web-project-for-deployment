<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Service;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Batch Controller - Combine multiple API calls into one request
 * Reduces number of HTTP requests and improves performance
 * All endpoints are cached appropriately
 */
class BatchController extends Controller
{
    use SafeExperimentalFeature;
    /**
     * Get all dashboard data in a single request
     * Combines: stats, appointments summary, users summary, services
     * 
     * POST /api/admin/batch/dashboard
     * Body: { timeframe: 'monthly' }
     */
    public function dashboardData(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $timeframe = $request->query('timeframe', 'monthly') ?? 'monthly';
            $realtime = filter_var($request->query('realtime', false), FILTER_VALIDATE_BOOLEAN);
            
            $cacheKey = "admin_batch_dashboard_{$timeframe}";
            $ttl = 15; // seconds

            if ($realtime) {
                $data = $this->computeDashboardData($timeframe);
            } else {
                $data = Cache::remember($cacheKey, $ttl, function () use ($timeframe) {
                    return $this->computeDashboardData($timeframe);
                });
            }

            return response()->json(['data' => $data]);
        }, 'batch_dashboard');
    }

    /**
     * Compute dashboard data without cache
     */
    private function computeDashboardData($timeframe)
    {
        // Build date range based on timeframe
        $dateRange = $this->getDateRange($timeframe);
        
        // Get stats with timeframe filtering
        $totalUsers = DB::table('users')
            ->where('role', 'client')
            ->whereBetween('created_at', $dateRange)
            ->count();

        $totalStaff = DB::table('users')
            ->where('role', 'staff')
            ->whereBetween('created_at', $dateRange)
            ->count();

        $totalAppointments = DB::table('appointments')
            ->whereBetween('appointment_date', $dateRange)
            ->count();

        $appointmentStats = DB::table('appointments')
            ->whereBetween('appointment_date', $dateRange)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $revenue = DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.appointment_date', $dateRange)
            ->select(DB::raw('COALESCE(SUM(services.price),0) as total'))
            ->value('total');

        $stats = [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalAppointments' => $totalAppointments,
            'pendingAppointments' => $appointmentStats['pending'] ?? 0,
            'approvedAppointments' => $appointmentStats['approved'] ?? 0,
            'completedAppointments' => $appointmentStats['completed'] ?? 0,
            'cancelledAppointments' => $appointmentStats['cancelled'] ?? 0,
            'revenue' => (float)$revenue,
            'appointmentsByStatus' => [
                ['label' => 'Pending', 'value' => $appointmentStats['pending'] ?? 0, 'color' => '#f59e0b'],
                ['label' => 'Approved', 'value' => $appointmentStats['approved'] ?? 0, 'color' => '#3b82f6'],
                ['label' => 'Completed', 'value' => $appointmentStats['completed'] ?? 0, 'color' => '#10b981'],
                ['label' => 'Cancelled', 'value' => $appointmentStats['cancelled'] ?? 0, 'color' => '#ef4444'],
            ],
        ];

        // Get recent appointments count by status (same timeframe)
        $recentAppointmentStats = DB::table('appointments')
            ->whereBetween('appointment_date', $dateRange)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Get user counts by role (same timeframe)
        $userStats = DB::table('users')
            ->whereBetween('created_at', $dateRange)
            ->select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();

        // Get services count (not filtered by timeframe)
        $servicesCount = DB::table('services')->count();

        return [
            // Stats section
            'stats' => $stats,
            
            // Quick stats
            'appointmentStats' => $recentAppointmentStats,
            'userStats' => $userStats,
            'servicesCount' => $servicesCount,
            
            // Summary counters
            'summary' => [
                'totalAppointments' => $totalAppointments,
                'pendingAppointments' => $appointmentStats['pending'] ?? 0,
                'totalUsers' => $totalUsers,
                'totalStaff' => $totalStaff,
                'totalRevenue' => $revenue,
            ]
        ];
    }

    /**
     * Get all critical data for admin dashboard load
     * Combines: stats, users list, appointments list, services, unavailable dates
     * 
     * GET /api/admin/batch/full-load
     */
    public function fullDashboardLoad(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $timeframe = $request->query('timeframe', 'monthly') ?? 'monthly';

            // Use batch cache key to avoid duplicate requests while loading
            $cacheKey = "admin_full_load_{$timeframe}_" . auth()->id();
            $ttl = 10; // seconds - short TTL during initial load

            $data = Cache::remember($cacheKey, $ttl, function () use ($timeframe) {
                return $this->computeFullLoad($timeframe);
            });

            return response()->json(['data' => $data]);
        }, 'batch_full_load');
    }

    /**
     * Compute full dashboard load
     */
    private function computeFullLoad($timeframe)
    {
        $startTime = microtime(true);

        // Parallel-friendly - all queries execute independently
        $stats = $this->getStatsForLoad($timeframe);
        $users = $this->getUsersForLoad();
        $appointments = $this->getAppointmentsForLoad();
        $services = $this->getServicesForLoad();
        $unavailableDates = $this->getUnavailableDatesForLoad();

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'stats' => $stats,
            'users' => $users,
            'appointments' => $appointments,
            'services' => $services,
            'unavailableDates' => $unavailableDates,
            'meta' => [
                'loadTime' => $duration,
                'timeframe' => $timeframe,
                'cached' => false, // Could be true if returned from cache
            ]
        ];
    }

    private function getStatsForLoad($timeframe)
    {
        // Inline stats computation to avoid reflection issues
        $totalUsers = DB::table('users')
            ->where('role', 'client')
            ->count();

        $totalStaff = DB::table('users')
            ->where('role', 'staff')
            ->count();

        $totalAppointments = DB::table('appointments')->count();

        $appointmentStats = DB::table('appointments')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $revenue = DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.status', 'completed')
            ->select(DB::raw('COALESCE(SUM(services.price),0) as total'))
            ->value('total');

        return [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalAppointments' => $totalAppointments,
            'pendingAppointments' => $appointmentStats['pending'] ?? 0,
            'approvedAppointments' => $appointmentStats['approved'] ?? 0,
            'completedAppointments' => $appointmentStats['completed'] ?? 0,
            'cancelledAppointments' => $appointmentStats['cancelled'] ?? 0,
            'revenue' => (float)$revenue,
            'appointmentsByStatus' => [
                ['label' => 'Pending', 'value' => $appointmentStats['pending'] ?? 0, 'color' => '#f59e0b'],
                ['label' => 'Approved', 'value' => $appointmentStats['approved'] ?? 0, 'color' => '#3b82f6'],
                ['label' => 'Completed', 'value' => $appointmentStats['completed'] ?? 0, 'color' => '#10b981'],
                ['label' => 'Cancelled', 'value' => $appointmentStats['cancelled'] ?? 0, 'color' => '#ef4444'],
            ],
        ];
    }

    private function getUsersForLoad()
    {
        return DB::table('users')
            ->select('id', 'name', 'email', 'role', 'is_active', 'created_at')
            ->where('role', '!=', 'super_admin')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    private function getAppointmentsForLoad()
    {
        return DB::table('appointments')
            ->select('id', 'service_id', 'user_id', 'status', 'appointment_date', 'appointment_time', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    private function getServicesForLoad()
    {
        return DB::table('services')
            ->select('id', 'name', 'description', 'price')
            ->get()
            ->toArray();
    }

    private function getUnavailableDatesForLoad()
    {
        return DB::table('unavailable_dates')
            ->select('id', 'date', 'reason')
            ->where('date', '>=', now())
            ->get()
            ->toArray();
    }

    /**
     * Get date range based on timeframe for filtering stats
     * Returns [$startDate, $endDate] for whereBetween queries
     */
    private function getDateRange($timeframe = 'monthly')
    {
        $now = now();
        
        switch ($timeframe) {
            case 'daily':
                // Last 7 days
                return [
                    $now->copy()->subDays(6)->startOfDay(),
                    $now->copy()->endOfDay()
                ];
            
            case 'weekly':
                // Last 12 weeks
                return [
                    $now->copy()->subWeeks(11)->startOfWeek(),
                    $now->copy()->endOfDay()
                ];
            
            case 'yearly':
                // Last 5 years
                return [
                    $now->copy()->subYears(4)->startOfYear(),
                    $now->copy()->endOfDay()
                ];
            
            case 'monthly':
            default:
                // Last 12 months
                return [
                    $now->copy()->subMonths(11)->startOfMonth(),
                    $now->copy()->endOfDay()
                ];
        }
    }
}
