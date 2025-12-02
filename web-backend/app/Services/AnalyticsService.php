<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeSlotCapacity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get smart slot utilization analysis
     * Shows underbooked days, overbooked days, and time slot demand
     */
    public function getSlotUtilization($dateRange = 30)
    {
        $endDate = now();
        $startDate = now()->subDays($dateRange);

        // Get all time slots available
        $timeSlots = TimeSlotCapacity::where('is_active', true)->get();
        
        // Get appointments in range
        // GROUP BY formatted date string (Y-m-d) to match our dateStr format
        $appointments = Appointment::whereBetween('appointment_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy(function($appointment) {
                return $appointment->appointment_date->format('Y-m-d');
            });

        // Calculate utilization per day
        $daysAnalysis = [];
        $totalCapacity = 0;
        $totalBooked = 0;

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeekIso; // 1-7 (Monday-Sunday)
            
            // Get time slots for this day of week
            $dayMap = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
            $dayName = $dayMap[$dayOfWeek] ?? '';
            $dayNameLower = strtolower($dayName);
            
            $daySlots = $timeSlots->filter(function ($slot) use ($dayName, $dayNameLower) {
                // Handle both uppercase and lowercase day names
                if (empty($slot->day_of_week)) return false;
                
                $slotDayLower = strtolower($slot->day_of_week);
                return strpos($slotDayLower, $dayNameLower) !== false || 
                       strpos($slot->day_of_week, $dayName) !== false;
            });

            // Fix: Calculate dayCapacity correctly - multiply max by count only if we have slots
            $dayCapacity = $daySlots->count() > 0 ? $daySlots->sum('max_appointments_per_slot') : 0;
            $dayBooked = isset($appointments[$dateStr]) ? $appointments[$dateStr]->count() : 0;

            $utilizationRate = $dayCapacity > 0 ? ($dayBooked / $dayCapacity) * 100 : 0;

            $daysAnalysis[] = [
                'date' => $dateStr,
                'day_name' => $currentDate->format('l'),
                'capacity' => $dayCapacity,
                'booked' => $dayBooked,
                'available' => max(0, $dayCapacity - $dayBooked),
                'utilization_rate' => round($utilizationRate, 2),
                'status' => $this->getUtilizationStatus($utilizationRate),
                'recommendation' => $this->getUtilizationRecommendation($utilizationRate, $dayBooked, $dayCapacity),
            ];

            $totalCapacity += $dayCapacity;
            $totalBooked += $dayBooked;
            $currentDate->addDay();
        }

        // Time slot demand analysis
        $slotDemand = $this->analyzeTimeSlotDemand($startDate, $endDate);

        // Identify underbooked and overbooked days
        $underbookedDays = array_filter($daysAnalysis, fn($d) => $d['status'] === 'underbooked');
        $overbookedDays = array_filter($daysAnalysis, fn($d) => $d['status'] === 'overbooked');

        return [
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $dateRange,
            ],
            'overall' => [
                'total_capacity' => $totalCapacity,
                'total_booked' => $totalBooked,
                'total_available' => max(0, $totalCapacity - $totalBooked),
                'overall_utilization_rate' => round(($totalBooked / max(1, $totalCapacity)) * 100, 2),
            ],
            'days_analysis' => $daysAnalysis,
            'underbooked_days' => array_values($underbookedDays),
            'overbooked_days' => array_values($overbookedDays),
            'time_slot_demand' => $slotDemand,
            'summary' => $this->generateUtilizationSummary($underbookedDays, $overbookedDays),
        ];
    }

    /**
     * Analyze demand for each time slot
     */
    private function analyzeTimeSlotDemand($startDate, $endDate)
    {
        $slotData = Appointment::whereBetween('appointment_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->groupBy('appointment_time')
            ->select('appointment_time', DB::raw('count(*) as bookings'))
            ->orderBy('appointment_time')
            ->get();

        $analysis = [];
        foreach ($slotData as $slot) {
            $analysis[] = [
                'time' => $slot->appointment_time,
                'bookings' => $slot->bookings,
                'demand_level' => $this->getDemandLevel($slot->bookings),
            ];
        }

        return $analysis;
    }

    /**
     * Get demand level based on number of bookings
     */
    private function getDemandLevel($bookings)
    {
        if ($bookings >= 8) return 'very_high';
        if ($bookings >= 6) return 'high';
        if ($bookings >= 3) return 'medium';
        if ($bookings >= 1) return 'low';
        return 'none';
    }

    /**
     * Get utilization status
     */
    private function getUtilizationStatus($rate)
    {
        if ($rate >= 90) return 'overbooked';
        if ($rate >= 70) return 'well_utilized';
        if ($rate >= 40) return 'moderate';
        return 'underbooked';
    }

    /**
     * Get recommendation based on utilization
     */
    private function getUtilizationRecommendation($rate, $booked, $capacity)
    {
        if ($rate >= 90) {
            return 'Consider adding more time slots or extending hours to accommodate demand';
        } elseif ($rate >= 70) {
            return 'Good utilization. Monitor closely to prevent overboking';
        } elseif ($rate >= 40) {
            return 'Moderate usage. Consider running promotions to fill available slots';
        } else {
            return 'Significant unused capacity. Consider reducing hours or promoting services';
        }
    }

    /**
     * Generate summary of utilization insights
     */
    private function generateUtilizationSummary($underbooked, $overbooked)
    {
        $summary = [];

        if (!empty($overbooked)) {
            $summary[] = [
                'type' => 'alert',
                'message' => 'You have ' . count($overbooked) . ' overbooked days. Consider extending hours or adding staff.',
                'count' => count($overbooked),
            ];
        }

        if (!empty($underbooked)) {
            $summary[] = [
                'type' => 'warning',
                'message' => 'You have ' . count($underbooked) . ' underbooked days. Consider running promotions.',
                'count' => count($underbooked),
            ];
        }

        if (empty($overbooked) && empty($underbooked)) {
            $summary[] = [
                'type' => 'success',
                'message' => 'Excellent slot utilization across all days.',
                'count' => 0,
            ];
        }

        return $summary;
    }

    /**
     * No-show pattern detection
     * Identifies users with 2+ no-shows, time slots with high no-show rates, and days with frequent cancellations
     */
    public function getNoShowPatterns($dateRange = 90)
    {
        $startDate = now()->subDays($dateRange);

        // Get all cancellations and no-shows
        $problematicAppointments = Appointment::where('status', 'cancelled')
            ->orWhere('status', 'no_show')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Users with 2+ no-shows/cancellations
        $userNoShows = $problematicAppointments->groupBy('user_id')
            ->map(fn($group) => $group->count())
            ->filter(fn($count) => $count >= 2);

        $usersWithPatterns = User::whereIn('id', $userNoShows->keys())
            ->get()
            ->map(function ($user) use ($userNoShows) {
                $totalAppointments = Appointment::where('user_id', $user->id)->count();
                $cancelledCount = $userNoShows[$user->id];
                $cancelRate = ($cancelledCount / max(1, $totalAppointments)) * 100;

                return [
                    'user_id' => $user->id,
                    'user_name' => "{$user->first_name} {$user->last_name}",
                    'email' => $user->email,
                    'no_show_count' => $cancelledCount,
                    'total_appointments' => $totalAppointments,
                    'no_show_rate' => round($cancelRate, 2),
                    'risk_level' => $cancelRate >= 50 ? 'high' : ($cancelRate >= 25 ? 'medium' : 'low'),
                ];
            });

        // Time slots with high no-show rates
        $timeSlotStats = Appointment::where('created_at', '>=', $startDate)
            ->groupBy('appointment_time')
            ->select('appointment_time', DB::raw('count(*) as total'), 
                     DB::raw("SUM(CASE WHEN status IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) as cancelled_count"))
            ->get()
            ->map(function ($slot) {
                $cancelRate = ($slot->cancelled_count / max(1, $slot->total)) * 100;
                return [
                    'time' => $slot->appointment_time,
                    'total_appointments' => $slot->total,
                    'cancelled_count' => $slot->cancelled_count,
                    'no_show_rate' => round($cancelRate, 2),
                    'risk_level' => $cancelRate >= 40 ? 'high' : ($cancelRate >= 20 ? 'medium' : 'low'),
                ];
            })
            ->filter(fn($s) => $s['no_show_rate'] >= 20)
            ->values();

        // Days with frequent cancellations
        $dayStats = Appointment::where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DAYOFWEEK(appointment_date)'), DB::raw('DAYNAME(appointment_date)'))
            ->select(DB::raw('DAYNAME(appointment_date) as day_name'), 
                     DB::raw('count(*) as total'),
                     DB::raw("SUM(CASE WHEN status IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) as cancelled_count"))
            ->get()
            ->map(function ($day) {
                $cancelRate = ($day->cancelled_count / max(1, $day->total)) * 100;
                return [
                    'day' => $day->day_name,
                    'total_appointments' => $day->total,
                    'cancelled_count' => $day->cancelled_count,
                    'cancellation_rate' => round($cancelRate, 2),
                    'risk_level' => $cancelRate >= 40 ? 'high' : ($cancelRate >= 20 ? 'medium' : 'low'),
                ];
            })
            ->filter(fn($d) => $d['cancellation_rate'] >= 20)
            ->sortByDesc('cancellation_rate')
            ->values();

        return [
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
                'days' => $dateRange,
            ],
            'users_with_high_no_show' => $usersWithPatterns->sortByDesc('no_show_rate')->values(),
            'high_risk_time_slots' => $timeSlotStats->sortByDesc('no_show_rate')->values(),
            'high_risk_days' => $dayStats,
            'recommendations' => $this->generateNoShowRecommendations($usersWithPatterns, $timeSlotStats, $dayStats),
        ];
    }

    /**
     * Generate recommendations for no-show patterns
     */
    private function generateNoShowRecommendations($users, $timeSlots, $days)
    {
        $recommendations = [];

        $highRiskUsers = $users->filter(fn($u) => $u['risk_level'] === 'high');
        if ($highRiskUsers->count() > 0) {
            $recommendations[] = [
                'type' => 'user_management',
                'message' => 'You have ' . $highRiskUsers->count() . ' users with high no-show rates. Consider requiring pre-payment or blocking from future bookings.',
                'action' => 'review_high_risk_users',
            ];
        }

        if ($timeSlots->count() > 0) {
            $recommendations[] = [
                'type' => 'time_slot_management',
                'message' => 'Time slots ' . implode(', ', $timeSlots->pluck('time')->toArray()) . ' have high no-show rates. Consider limiting bookings or adding reminders.',
                'action' => 'manage_problematic_slots',
            ];
        }

        if ($days->count() > 0) {
            $dayNames = implode(', ', $days->pluck('day')->toArray());
            $recommendations[] = [
                'type' => 'day_management',
                'message' => "Days ($dayNames) have high cancellation rates. Send stronger reminders or adjust scheduling.",
                'action' => 'adjust_day_schedule',
            ];
        }

        return $recommendations;
    }

    /**
     * Demand forecasting
     * Predicts busy/slow days and expected service demand
     */
    public function getDemandForecast($daysAhead = 30)
    {
        $endDate = now();
        $historicalStart = $endDate->copy()->subDays(90);
        $forecastStart = now();
        $forecastEnd = now()->addDays($daysAhead);

        // Analyze historical data
        $historicalAppointments = Appointment::whereBetween('appointment_date', [$historicalStart, $endDate])
            ->where('status', '!=', 'cancelled')
            ->get();

        // Group by day of week
        $dayOfWeekStats = [];
        foreach (range(1, 7) as $dayOfWeek) {
            $dayName = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$dayOfWeek];
            $dayAppointments = $historicalAppointments->filter(fn($a) => Carbon::parse($a->appointment_date)->dayOfWeek === $dayOfWeek - 1);
            
            $dayOfWeekStats[] = [
                'day' => $dayName,
                'day_of_week' => $dayOfWeek,
                'avg_appointments' => round($dayAppointments->count() / 13, 2), // ~13 weeks
                'trend' => $this->calculateTrend($dayAppointments),
            ];
        }

        // Service demand analysis
        $serviceStats = Service::where('is_active', true)
            ->get()
            ->map(function ($service) use ($historicalStart, $endDate) {
                $appointments = Appointment::where('service_id', $service->id)
                    ->whereBetween('appointment_date', [$historicalStart, $endDate])
                    ->where('status', '!=', 'cancelled')
                    ->count();

                return [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'historical_bookings' => $appointments,
                    'avg_monthly' => round($appointments / 3, 2),
                    'trend' => 'stable', // Could be enhanced with trend calculation
                ];
            });

        // Forecast for upcoming days
        $forecast = [];
        $currentDate = $forecastStart->copy();
        while ($currentDate <= $forecastEnd) {
            $dayOfWeek = $currentDate->dayOfWeek + 1; // Convert to 1-7 range
            $dayStats = collect($dayOfWeekStats)->firstWhere('day_of_week', $dayOfWeek);
            
            $forecast[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('l'),
                'predicted_appointments' => $dayStats ? ceil($dayStats['avg_appointments']) : 2,
                'expected_utilization' => $dayStats ? round(($dayStats['avg_appointments'] / 10) * 100, 2) : 20,
                'forecast_level' => $dayStats ? ($dayStats['avg_appointments'] >= 6 ? 'busy' : ($dayStats['avg_appointments'] >= 3 ? 'moderate' : 'slow')) : 'uncertain',
            ];

            $currentDate->addDay();
        }

        return [
            'historical_period' => [
                'start' => $historicalStart->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'forecast_period' => [
                'start' => $forecastStart->format('Y-m-d'),
                'end' => $forecastEnd->format('Y-m-d'),
                'days' => $daysAhead,
            ],
            'day_of_week_stats' => $dayOfWeekStats,
            'service_demand' => $serviceStats,
            'forecast' => $forecast,
            'insights' => $this->generateForecastInsights($dayOfWeekStats, $serviceStats),
        ];
    }

    /**
     * Calculate trend for a collection of appointments
     */
    private function calculateTrend($appointments)
    {
        if ($appointments->count() < 2) return 'insufficient_data';
        
        $recentCount = $appointments->filter(fn($a) => Carbon::parse($a->created_at)->isAfter(now()->subDays(14)))->count();
        $olderCount = $appointments->filter(fn($a) => Carbon::parse($a->created_at)->isBefore(now()->subDays(14)))->count();

        if ($recentCount > $olderCount * 1.2) return 'increasing';
        if ($recentCount < $olderCount * 0.8) return 'decreasing';
        return 'stable';
    }

    /**
     * Generate insights from forecast
     */
    private function generateForecastInsights($dayOfWeekStats, $serviceStats)
    {
        $insights = [];

        // Busiest days
        $busiestDays = collect($dayOfWeekStats)->sortByDesc('avg_appointments')->take(2);
        foreach ($busiestDays as $day) {
            $insights[] = [
                'type' => 'busy_day_forecast',
                'message' => "{$day['day']}s are typically busy with {$day['avg_appointments']} expected appointments.",
                'action' => 'prepare_resources',
            ];
        }

        // Slowest days
        $slowestDays = collect($dayOfWeekStats)->sortBy('avg_appointments')->take(2);
        foreach ($slowestDays as $day) {
            $insights[] = [
                'type' => 'slow_day_forecast',
                'message' => "{$day['day']}s are typically slow. Consider running promotions.",
                'action' => 'marketing_campaign',
            ];
        }

        // Popular services
        $topServices = collect($serviceStats)->sortByDesc('avg_monthly')->take(2);
        foreach ($topServices as $service) {
            $insights[] = [
                'type' => 'popular_service',
                'message' => "{$service['service_name']} is one of your most popular services.",
                'action' => 'ensure_staffing',
            ];
        }

        return $insights;
    }

    /**
     * Appointment quality report
     * Shows avg duration, most/least popular services, completion stats
     */
    public function getQualityReport($dateRange = 90)
    {
        $startDate = now()->subDays($dateRange);

        // Get ALL appointments in the date range (don't filter out cancelled yet)
        $appointments = Appointment::whereBetween('created_at', [$startDate, now()])->get();

        // Average appointment duration (assuming duration comes from service)
        $avgDuration = $appointments
            ->groupBy('service_id')
            ->map(function ($group) {
                $service = Service::find($group->first()->service_id);
                return $service ? $service->duration : 30;
            })
            ->avg();

        // Service statistics
        $serviceStats = Service::where('is_active', true)
            ->get()
            ->map(function ($service) use ($startDate) {
                $serviceAppointments = Appointment::where('service_id', $service->id)
                    ->whereBetween('created_at', [$startDate, now()])
                    ->get();

                $totalCount = $serviceAppointments->count();
                $completedCount = $serviceAppointments->where('status', 'completed')->count();
                $cancelledCount = $serviceAppointments->where('status', 'cancelled')->count();

                return [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'total_appointments' => $totalCount,
                    'completed' => $completedCount,
                    'cancelled' => $cancelledCount,
                    'pending_approved' => $totalCount - $completedCount - $cancelledCount,
                    'completion_rate' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0,
                    'price' => $service->price,
                    'revenue' => $completedCount * ($service->price ?? 0),
                ];
            })
            ->sortByDesc('total_appointments')
            ->values();

        // Most and least popular services
        $mostPopular = $serviceStats->take(3);
        $leastPopular = $serviceStats->reverse()->take(3)->reverse();

        // Overall completion stats
        $totalAppointments = $appointments->count();
        $completedAppointments = $appointments->where('status', 'completed')->count();
        $cancelledAppointments = $appointments->where('status', 'cancelled')->count();
        $pendingAppointments = $appointments->whereIn('status', ['pending', 'approved'])->count();

        // Calculate trends
        $completionRate = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0;
        $cancellationRate = $totalAppointments > 0 ? round(($cancelledAppointments / $totalAppointments) * 100, 2) : 0;

        return [
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
                'days' => $dateRange,
            ],
            'overall_stats' => [
                'total_appointments' => $totalAppointments,
                'completed' => $completedAppointments,
                'cancelled' => $cancelledAppointments,
                'pending_approved' => $pendingAppointments,
                'completion_rate' => $completionRate,
                'cancellation_rate' => $cancellationRate,
            ],
            'service_stats' => $serviceStats,
            'most_popular_services' => $mostPopular,
            'least_popular_services' => $leastPopular,
            'average_appointment_duration' => round($avgDuration, 0),
            'total_revenue' => $serviceStats->sum('revenue'),
            'avg_revenue_per_appointment' => $completedAppointments > 0 ? round($serviceStats->sum('revenue') / $completedAppointments, 2) : 0,
            'recommendations' => $this->generateQualityRecommendations($mostPopular, $leastPopular, $completionRate),
        ];
    }

    /**
     * Generate quality recommendations
     */
    private function generateQualityRecommendations($mostPopular, $leastPopular, $completionRate)
    {
        $recommendations = [];

        // Most popular services
        if ($mostPopular->count() > 0) {
            $serviceNames = $mostPopular->pluck('service_name')->join(', ');
            $recommendations[] = [
                'type' => 'promote_popular',
                'message' => "Services ($serviceNames) are very popular. Ensure adequate staffing and promote these.",
                'action' => 'marketing_staffing',
            ];
        }

        // Least popular services
        if ($leastPopular->count() > 0) {
            $serviceNames = $leastPopular->pluck('service_name')->join(', ');
            $recommendations[] = [
                'type' => 'review_unpopular',
                'message' => "Services ($serviceNames) have low demand. Review pricing, description, or consider removing.",
                'action' => 'service_review',
            ];
        }

        // Completion rate
        if ($completionRate < 80) {
            $recommendations[] = [
                'type' => 'improve_completion',
                'message' => "Completion rate is {$completionRate}%. Implement stricter no-show policies or better reminders.",
                'action' => 'improve_reliability',
            ];
        }

        return $recommendations;
    }

    /**
     * Generate auto-alerts and recommendations for admin
     */
    public function getAutoAlerts()
    {
        $alerts = [];

        // Check tomorrow's schedule
        $tomorrow = now()->addDay()->format('Y-m-d');
        $tomorrowAppointments = Appointment::where('appointment_date', $tomorrow)
            ->where('status', '!=', 'cancelled')
            ->count();

        $utilizationRate = $this->estimateUtilization($tomorrowAppointments);
        if ($utilizationRate >= 85) {
            $alerts[] = [
                'type' => 'alert',
                'severity' => 'high',
                'title' => 'Tomorrow is almost full',
                'message' => "Tomorrow has $tomorrowAppointments appointments scheduled ($utilizationRate% capacity). Consider limiting new bookings.",
                'timestamp' => now(),
            ];
        }

        // Check for high no-show times
        $noShowData = $this->getNoShowPatterns(30);
        if (!empty($noShowData['high_risk_time_slots'])) {
            $alerts[] = [
                'type' => 'warning',
                'severity' => 'medium',
                'title' => 'High no-show rate detected',
                'message' => 'Some time slots have high cancellation rates. Consider adding reminders or limiting bookings.',
                'timestamp' => now(),
            ];
        }

        // Check for underutilized days
        $slotData = $this->getSlotUtilization(7);
        $underbookedCount = count($slotData['underbooked_days']);
        if ($underbookedCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'severity' => 'low',
                'title' => "$underbookedCount underbooked days this week",
                'message' => 'Consider running promotions to fill available slots.',
                'timestamp' => now(),
            ];
        }

        return $alerts;
    }

    /**
     * Estimate utilization percentage
     */
    private function estimateUtilization($appointmentCount)
    {
        // Assuming max 10 appointments per day
        return min(100, round(($appointmentCount / 10) * 100, 2));
    }

    /**
     * Get cancellation risk notice for user booking
     * Shows if a time slot is very busy
     */
    public function getCancellationRiskNotice($appointmentDate, $appointmentTime)
    {
        $appointmentsAtTime = Appointment::where('appointment_date', $appointmentDate)
            ->where('appointment_time', $appointmentTime)
            ->where('status', '!=', 'cancelled')
            ->count();

        $utilizationRate = min(100, round(($appointmentsAtTime / 10) * 100));

        if ($utilizationRate >= 85) {
            return [
                'show_notice' => true,
                'risk_level' => 'high',
                'message' => 'This time slot is very busy. If you need to cancel, it may be difficult to find another available slot.',
                'utilization_rate' => $utilizationRate,
                'current_bookings' => $appointmentsAtTime,
            ];
        } elseif ($utilizationRate >= 60) {
            return [
                'show_notice' => true,
                'risk_level' => 'medium',
                'message' => 'This time slot is moderately busy. Cancellations may be limited.',
                'utilization_rate' => $utilizationRate,
                'current_bookings' => $appointmentsAtTime,
            ];
        }

        return [
            'show_notice' => false,
            'risk_level' => 'low',
            'utilization_rate' => $utilizationRate,
        ];
    }

    /**
     * Get recommendations for alternative time slots when preferred slot is full
     */
    public function getAlternativeSlotRecommendations($appointmentDate, $appointmentTime)
    {
        $requestedDate = Carbon::parse($appointmentDate);
        $alternatives = [];

        // Check same day alternative times
        $timeSlots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', 
                     '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];

        foreach ($timeSlots as $slot) {
            if ($slot === $appointmentTime) continue;

            $count = Appointment::where('appointment_date', $appointmentDate)
                ->where('appointment_time', $slot)
                ->where('status', '!=', 'cancelled')
                ->count();

            $utilization = round(($count / 10) * 100);

            if ($utilization < 60) {
                $alternatives[] = [
                    'date' => $appointmentDate,
                    'time' => $slot,
                    'availability_rate' => 100 - $utilization,
                    'description' => "Same day, different time",
                ];
            }
        }

        // Check next day if same day is full
        if (empty($alternatives)) {
            $nextDate = $requestedDate->addDay()->format('Y-m-d');
            foreach ($timeSlots as $slot) {
                $count = Appointment::where('appointment_date', $nextDate)
                    ->where('appointment_time', $slot)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                $utilization = round(($count / 10) * 100);

                if ($utilization < 60) {
                    $alternatives[] = [
                        'date' => $nextDate,
                        'time' => $slot,
                        'availability_rate' => 100 - $utilization,
                        'description' => "Next day",
                    ];
                }
            }
        }

        return array_slice($alternatives, 0, 5); // Return top 5 alternatives
    }
}
