<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Events\AnalyticsUpdated;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    use SafeExperimentalFeature;

    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get smart slot utilization analysis
     * EXPERIMENTAL: Wrapped with safety handler
     * GET /api/analytics/slot-utilization
     */
    public function slotUtilization(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'days' => 'nullable|integer|min:7|max:365',
            ]);

            $days = $request->query('days', 30);
            $cacheKey = "analytics_slot_utilization_{$days}";
            $ttl = 3600; // Cache for 1 hour

            $data = Cache::remember($cacheKey, $ttl, function () use ($days) {
                return $this->analyticsService->getSlotUtilization($days);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => true,
                'experimental' => true,
            ]);
        }, 'analytics.slot_utilization');
    }

    /**
     * Get no-show pattern detection
     * GET /api/analytics/no-show-patterns
     */
    public function noShowPatterns(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'days' => 'nullable|integer|min:7|max:365',
            ]);

            $days = $request->query('days', 90);
            $cacheKey = "analytics_no_show_patterns_{$days}";
            $ttl = 3600; // Cache for 1 hour

            try {
                $data = Cache::remember($cacheKey, $ttl, function () use ($days) {
                    return $this->analyticsService->getNoShowPatterns($days);
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => true,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving no-show patterns: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.no_show_patterns');
    }

    /**
     * Get demand forecasting
     * GET /api/analytics/demand-forecast
     */
    public function demandForecast(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'days_ahead' => 'nullable|integer|min:1|max:90',
            ]);

            $daysAhead = $request->query('days_ahead', 30);
            $cacheKey = "analytics_demand_forecast_{$daysAhead}";
            $ttl = 3600; // Cache for 1 hour

            try {
                $data = Cache::remember($cacheKey, $ttl, function () use ($daysAhead) {
                    return $this->analyticsService->getDemandForecast($daysAhead);
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => true,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving demand forecast: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.demand_forecast');
    }

    /**
     * Get appointment quality report
     * GET /api/analytics/quality-report
     */
    public function qualityReport(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'days' => 'nullable|integer|min:7|max:365',
            ]);

            $days = $request->query('days', 90);
            $cacheKey = "analytics_quality_report_{$days}";
            $ttl = 3600; // Cache for 1 hour

            try {
                $data = Cache::remember($cacheKey, $ttl, function () use ($days) {
                    return $this->analyticsService->getQualityReport($days);
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => true,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving quality report: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.quality_report');
    }

    /**
     * Get auto-alerts and recommendations
     * GET /api/analytics/auto-alerts
     */
    public function autoAlerts(Request $request)
    {
        return $this->wrapExperimental(function () {
            $cacheKey = "analytics_auto_alerts";
            $ttl = 300; // Cache for 5 minutes (near real-time)

            try {
                $data = Cache::remember($cacheKey, $ttl, function () {
                    return $this->analyticsService->getAutoAlerts();
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => true,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving alerts: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.auto_alerts');
    }

    /**
     * Get comprehensive analytics dashboard
     * GET /api/admin/analytics/dashboard
     */
    public function dashboard(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            // Don't validate the realtime parameter - just extract it safely
            $realtimeParam = $request->query('realtime', 'false');
            $realtime = filter_var($realtimeParam, FILTER_VALIDATE_BOOLEAN);
            $cacheKey = "analytics_dashboard_comprehensive";
            $ttl = $realtime ? 60 : 3600; // 1 min if realtime, 1 hour otherwise

            try {
                $data = Cache::remember($cacheKey, $ttl, function () {
                    return [
                        'slot_utilization' => $this->analyticsService->getSlotUtilization(30),
                        'no_show_patterns' => $this->analyticsService->getNoShowPatterns(90),
                        'demand_forecast' => $this->analyticsService->getDemandForecast(30),
                        'quality_report' => $this->analyticsService->getQualityReport(90),
                        'auto_alerts' => $this->analyticsService->getAutoAlerts(),
                        'generated_at' => now(),
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached' => !$realtime,
                    'timestamp' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Analytics dashboard error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving analytics dashboard: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.dashboard');
    }

    /**
     * Get cancellation risk notice for user booking
     * GET /api/analytics/cancellation-risk
     */
    public function cancellationRisk(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'appointment_date' => 'required|date',
                'appointment_time' => 'required|date_format:H:i',
            ]);

            try {
                $riskNotice = $this->analyticsService->getCancellationRiskNotice(
                    $request->appointment_date,
                    $request->appointment_time
                );

                return response()->json([
                    'success' => true,
                    'data' => $riskNotice,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving risk notice: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.cancellation_risk');
    }

    /**
     * Get alternative slot recommendations
     * GET /api/analytics/alternative-slots
     */
    public function alternativeSlots(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'appointment_date' => 'required|date',
                'appointment_time' => 'required|date_format:H:i',
            ]);

            try {
                $alternatives = $this->analyticsService->getAlternativeSlotRecommendations(
                    $request->appointment_date,
                    $request->appointment_time
                );

                return response()->json([
                    'success' => true,
                    'data' => $alternatives,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving alternative slots: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.alternative_slots');
    }

    /**
     * Clear analytics cache (for testing or manual refresh)
     * POST /api/admin/analytics/clear-cache
     */
    public function clearCache()
    {
        return $this->wrapExperimental(function () {
            try {
                // Clear all analytics caches
                Cache::forget('analytics_slot_utilization_30');
                Cache::forget('analytics_slot_utilization_7');
                Cache::forget('analytics_no_show_patterns_90');
                Cache::forget('analytics_demand_forecast_30');
                Cache::forget('analytics_quality_report_90');
                Cache::forget('analytics_auto_alerts');
                Cache::forget('analytics_dashboard_comprehensive');

                // Fetch fresh data
                $freshData = [
                    'slot_utilization' => $this->analyticsService->getSlotUtilization(30),
                    'no_show_patterns' => $this->analyticsService->getNoShowPatterns(90),
                    'demand_forecast' => $this->analyticsService->getDemandForecast(30),
                    'quality_report' => $this->analyticsService->getQualityReport(90),
                    'auto_alerts' => $this->analyticsService->getAutoAlerts(),
                    'generated_at' => now(),
                ];

                // Broadcast the update to all connected admin clients
                broadcast(new AnalyticsUpdated($freshData))->toOthers();

                return response()->json([
                    'success' => true,
                    'message' => 'Analytics cache cleared and data refreshed',
                    'data' => $freshData,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error clearing cache: ' . $e->getMessage(),
                ], 500);
            }
        }, 'analytics.clear_cache');
    }
}
