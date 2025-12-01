<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Get critical stats only (fast endpoint)
     * Cached for 2 minutes
     */
    public function summary()
    {
        $cacheKey = 'admin_stats_summary';
        $ttl = 15; // seconds - shorter TTL for near-real-time

        $stats = Cache::remember($cacheKey, $ttl, function () {
            return [
                'totalUsers' => User::where('role', 'client')->count(),
                'totalAppointments' => Appointment::count(),
                'pendingAppointments' => Appointment::where('status', 'pending')->count(),
                'completedAppointments' => Appointment::where('status', 'completed')->count(),
            ];
        });

        return response()->json(['data' => $stats]);
    }

    /**
     * Get detailed statistics
     * This is the main stats endpoint called by admin dashboard
     */
    public function index()
    {
        $timeframe = request()->query('timeframe', 'monthly');
        $realtime = filter_var(request()->query('realtime', false), FILTER_VALIDATE_BOOLEAN);
        $cacheKey = "admin_stats_detailed_{$timeframe}";
        $ttl = 15; // seconds - short TTL for near-real-time

        if ($realtime) {
            // Bypass cache for ad-hoc realtime requests
            $stats = $this->computeStats($timeframe);
        } else {
            $stats = Cache::remember($cacheKey, $ttl, function () use ($timeframe) {
                return $this->computeStats($timeframe);
            });
        }

        return response()->json(['data' => $stats]);
    }

    /**
     * Compute stats without cache (used by index and realtime bypass)
     */
    private function computeStats($timeframe)
    {
        // Build date range based on timeframe
        $dateRange = $this->getDateRange($timeframe);
        
        // Use raw queries where possible for performance
        $totalUsers = DB::table('users')
            ->where('role', 'client')
            ->whereBetween('created_at', $dateRange)
            ->count();

        $totalStaff = DB::table('users')
            ->where('role', 'staff')
            ->whereBetween('created_at', $dateRange)
            ->count();

        // Appointments within timeframe
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

        // Revenue: join appointments -> services.price (only count completed appointments within timeframe)
        $revenue = DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.appointment_date', $dateRange)
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
            // timeframe series (daily/weekly/monthly/yearly)
            'appointmentsByPeriod' => $this->getAppointmentsByPeriod($timeframe),
            'revenueByPeriod' => $this->getRevenueByPeriod($timeframe),
        ];
    }

    /**
     * Get appointments data grouped by month
     */
    private function getAppointmentsByMonth()
    {
        $appointments = DB::table('appointments')
            ->select(
                DB::raw('DATE_FORMAT(appointment_date, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get()
            ->reverse();

        return $appointments->map(function ($item) {
            return [
                'label' => date('M Y', strtotime($item->month . '-01')),
                'value' => $item->count,
            ];
        })->values()->toArray();
    }

    /**
     * Get user growth data
     */
    private function getUserGrowth()
    {
        $users = DB::table('users')
            ->where('role', 'client')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get()
            ->reverse();

        return $users->map(function ($item) {
            return [
                'label' => date('M Y', strtotime($item->month . '-01')),
                'value' => $item->count,
            ];
        })->values()->toArray();
    }

    /**
     * Get appointments aggregated by the requested period.
     * Supported: daily (last 7 days), weekly (last 12 weeks), monthly (last 12 months), yearly (last 5 years)
     */
    private function getAppointmentsByPeriod($period = 'monthly')
    {
        switch ($period) {
            case 'daily':
                $rows = DB::table('appointments')
                    ->select(DB::raw('DATE(appointment_date) as period'), DB::raw('count(*) as count'))
                    ->where('appointment_date', '>=', now()->subDays(6)->startOfDay())
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatSeries($rows, 'Y-m-d', 7);

            case 'weekly':
                // last 12 weeks grouped by ISO week (year-week)
                $rows = DB::table('appointments')
                    ->select(DB::raw('YEARWEEK(appointment_date, 3) as period'), DB::raw('count(*) as count'))
                    ->where('appointment_date', '>=', now()->subWeeks(11)->startOfWeek())
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatWeekSeries($rows, 12);

            case 'yearly':
                $rows = DB::table('appointments')
                    ->select(DB::raw('YEAR(appointment_date) as period'), DB::raw('count(*) as count'))
                    ->where('appointment_date', '>=', now()->subYears(4)->startOfYear())
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatSeries($rows, 'Y', 5);

            case 'monthly':
            default:
                $rows = DB::table('appointments')
                    ->select(DB::raw('DATE_FORMAT(appointment_date, "%Y-%m") as period'), DB::raw('count(*) as count'))
                    ->where('appointment_date', '>=', now()->subMonths(11)->startOfMonth())
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatSeries($rows, 'M Y', 12, true);
        }
    }

    private function getRevenueByPeriod($period = 'monthly')
    {
        // Assuming appointments may have a `price` column. If not, returns zeros.
        switch ($period) {
            case 'daily':
                $rows = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->select(DB::raw('DATE(appointment_date) as period'), DB::raw('COALESCE(SUM(services.price),0) as total'))
                    ->where('appointment_date', '>=', now()->subDays(6)->startOfDay())
                    ->where('appointments.status', 'completed')
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatRevenueSeries($rows, 'Y-m-d', 7);

            case 'weekly':
                $rows = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->select(DB::raw('YEARWEEK(appointment_date, 3) as period'), DB::raw('COALESCE(SUM(services.price),0) as total'))
                    ->where('appointment_date', '>=', now()->subWeeks(11)->startOfWeek())
                    ->where('appointments.status', 'completed')
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatWeekRevenueSeries($rows, 12);

            case 'yearly':
                $rows = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->select(DB::raw('YEAR(appointment_date) as period'), DB::raw('COALESCE(SUM(services.price),0) as total'))
                    ->where('appointment_date', '>=', now()->subYears(4)->startOfYear())
                    ->where('appointments.status', 'completed')
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatRevenueSeries($rows, 'Y', 5);

            case 'monthly':
            default:
                $rows = DB::table('appointments')
                    ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                    ->select(DB::raw('DATE_FORMAT(appointment_date, "%Y-%m") as period'), DB::raw('COALESCE(SUM(services.price),0) as total'))
                    ->where('appointment_date', '>=', now()->subMonths(11)->startOfMonth())
                    ->where('appointments.status', 'completed')
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                return $this->formatRevenueSeries($rows, 'M Y', 12, true);
        }
    }

    // Helpers to normalize results into label/value arrays
    private function formatSeries($rows, $labelFormat = 'Y-m-d', $expected = 7, $isMonth = false)
    {
        $labels = [];
        $now = now();
        if ($labelFormat === 'Y') {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subYears($i)->format($labelFormat);
            }
        } elseif ($isMonth) {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subMonths($i)->format($labelFormat);
            }
        } else {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subDays($i)->format($labelFormat);
            }
        }

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->period] = $r->count ?? $r->total ?? 0;
        }

        $series = [];
        foreach ($labels as $label) {
            // If DB returned YYYY-MM for months, or YEARWEEK for weeks, try to match appropriately
            $value = 0;
            if (isset($map[$label])) $value = $map[$label];
            else {
                // Try matching Y-m (2024-08) for M Y label formats
                foreach ($map as $k => $v) {
                    if (strpos($label, ' ') !== false && date('M Y', strtotime($k . '-01')) === $label) {
                        $value = $v; break;
                    }
                }
            }
            $series[] = ['label' => $label, 'value' => (int)$value];
        }

        return $series;
    }

    private function formatRevenueSeries($rows, $labelFormat = 'Y-m-d', $expected = 7, $isMonth = false)
    {
        $labels = [];
        $now = now();
        if ($labelFormat === 'Y') {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subYears($i)->format($labelFormat);
            }
        } elseif ($isMonth) {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subMonths($i)->format($labelFormat);
            }
        } else {
            for ($i = $expected - 1; $i >= 0; $i--) {
                $labels[] = $now->copy()->subDays($i)->format($labelFormat);
            }
        }

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->period] = $r->total ?? 0;
        }

        $series = [];
        foreach ($labels as $label) {
            $value = 0;
            if (isset($map[$label])) $value = $map[$label];
            else {
                foreach ($map as $k => $v) {
                    if (strpos($label, ' ') !== false && date('M Y', strtotime($k . '-01')) === $label) {
                        $value = $v; break;
                    }
                }
            }
            $series[] = ['label' => $label, 'value' => (float)$value];
        }

        return $series;
    }

    private function formatWeekSeries($rows, $weeks = 12)
    {
        // rows use YEARWEEK numeric key (e.g., 202352)
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->period] = $r->count;
        }

        $series = [];
        $now = now()->startOfWeek();
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i);
            $yw = intval($weekStart->format('o')) * 100 + intval($weekStart->format('W'));
            $label = $weekStart->format('M j');
            $series[] = ['label' => $label, 'value' => (int)($map[(string)$yw] ?? 0)];
        }

        return $series;
    }

    private function formatWeekRevenueSeries($rows, $weeks = 12)
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->period] = $r->total;
        }

        $series = [];
        $now = now()->startOfWeek();
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i);
            $yw = intval($weekStart->format('o')) * 100 + intval($weekStart->format('W'));
            $label = $weekStart->format('M j');
            $series[] = ['label' => $label, 'value' => (float)($map[(string)$yw] ?? 0)];
        }

        return $series;
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
