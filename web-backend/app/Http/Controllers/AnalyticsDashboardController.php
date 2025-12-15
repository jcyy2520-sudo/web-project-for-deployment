<?php

namespace App\Http\Controllers;

use App\Models\SystemMetrics;
use App\Services\SystemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsDashboardController extends Controller
{
    public function __construct(private SystemMetricsService $metricsService)
    {
    }

    /**
     * Get comprehensive analytics dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $latestMetrics = $this->metricsService->getLatestMetrics();
        $metricsRange = $this->metricsService->getMetricsRange($minutes);

        return response()->json([
            'latest' => $latestMetrics,
            'historical' => $metricsRange,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get detailed CPU metrics
     */
    public function cpuMetrics(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $metrics = SystemMetrics::recent($minutes)->get();

        return response()->json([
            'current' => $metrics->last()?->cpu_usage ?? 0,
            'average' => round($metrics->avg('cpu_usage'), 2),
            'max' => $metrics->max('cpu_usage'),
            'min' => $metrics->min('cpu_usage'),
            'samples' => $metrics->map(function ($m) {
                return [
                    'timestamp' => $m->timestamp->toIso8601String(),
                    'usage' => $m->cpu_usage,
                ];
            })->toArray(),
        ]);
    }

    /**
     * Get detailed memory metrics
     */
    public function memoryMetrics(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $metrics = SystemMetrics::recent($minutes)->get();

        $latest = $metrics->last();

        return response()->json([
            'current' => [
                'used_mb' => $latest ? round($latest->memory_usage / 1024 / 1024, 2) : 0,
                'total_mb' => $latest ? round($latest->memory_total / 1024 / 1024, 2) : 0,
                'percent' => $latest ? round(($latest->memory_usage / $latest->memory_total) * 100, 2) : 0,
            ],
            'average_usage' => round($metrics->avg('memory_usage') / 1024 / 1024, 2),
            'peak_usage' => round($metrics->max('memory_usage') / 1024 / 1024, 2),
            'samples' => $metrics->map(function ($m) {
                return [
                    'timestamp' => $m->timestamp->toIso8601String(),
                    'used_mb' => round($m->memory_usage / 1024 / 1024, 2),
                    'percent' => round(($m->memory_usage / $m->memory_total) * 100, 2),
                ];
            })->toArray(),
        ]);
    }

    /**
     * Get detailed disk metrics
     */
    public function diskMetrics(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $metrics = SystemMetrics::recent($minutes)->get();
        $latest = $metrics->last();

        return response()->json([
            'current' => [
                'used_mb' => $latest ? round($latest->disk_usage / 1024 / 1024, 2) : 0,
                'total_mb' => $latest ? round($latest->disk_total / 1024 / 1024, 2) : 0,
                'free_mb' => $latest ? round($latest->disk_free / 1024 / 1024, 2) : 0,
                'percent' => $latest ? round(($latest->disk_usage / $latest->disk_total) * 100, 2) : 0,
            ],
            'average_usage' => round($metrics->avg('disk_usage') / 1024 / 1024, 2),
            'trend' => $this->calculateTrend($metrics, 'disk_usage'),
            'samples' => $metrics->map(function ($m) {
                return [
                    'timestamp' => $m->timestamp->toIso8601String(),
                    'used_mb' => round($m->disk_usage / 1024 / 1024, 2),
                    'percent' => round(($m->disk_usage / $m->disk_total) * 100, 2),
                ];
            })->toArray(),
        ]);
    }

    /**
     * Get system health overview
     */
    public function healthOverview(Request $request): JsonResponse
    {
        $latest = $this->metricsService->getLatestMetrics();

        $healthComponents = [
            [
                'name' => 'CPU',
                'status' => $latest['cpu']['status'],
                'value' => $latest['cpu']['usage'],
                'unit' => '%',
                'threshold' => ['warning' => 70, 'critical' => 85],
            ],
            [
                'name' => 'Memory',
                'status' => $latest['memory']['status'],
                'value' => $latest['memory']['percent'],
                'unit' => '%',
                'threshold' => ['warning' => 75, 'critical' => 90],
            ],
            [
                'name' => 'Disk',
                'status' => $latest['disk']['status'],
                'value' => $latest['disk']['percent'],
                'unit' => '%',
                'threshold' => ['warning' => 80, 'critical' => 95],
            ],
            [
                'name' => 'Database Connections',
                'status' => $latest['database']['connections'] < 50 ? 'healthy' : 'warning',
                'value' => $latest['database']['connections'],
                'unit' => '',
            ],
            [
                'name' => 'Failed Jobs',
                'status' => $latest['queue']['failed'] > 0 ? 'warning' : 'healthy',
                'value' => $latest['queue']['failed'],
                'unit' => '',
            ],
        ];

        $overallStatus = 'healthy';
        foreach ($healthComponents as $component) {
            if ($component['status'] === 'critical') {
                $overallStatus = 'critical';
                break;
            }
            if ($component['status'] === 'warning') {
                $overallStatus = 'warning';
            }
        }

        return response()->json([
            'overall_status' => $overallStatus,
            'components' => $healthComponents,
            'timestamp' => $latest['timestamp'],
        ]);
    }

    /**
     * Get system performance trends
     */
    public function trends(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $metrics = SystemMetrics::recent($minutes)->get();

        return response()->json([
            'cpu_trend' => $this->calculateTrend($metrics, 'cpu_usage'),
            'memory_trend' => $this->calculateTrend($metrics, 'memory_usage'),
            'disk_trend' => $this->calculateTrend($metrics, 'disk_usage'),
            'samples_analyzed' => $metrics->count(),
            'time_range_hours' => $hours,
        ]);
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrend($metrics, $field)
    {
        if ($metrics->count() < 2) {
            return 'stable';
        }

        $first = $metrics->first()->{$field};
        $last = $metrics->last()->{$field};

        $change = (($last - $first) / $first) * 100;

        if (abs($change) < 5) {
            return 'stable';
        }

        return $change > 0 ? 'increasing' : 'decreasing';
    }
}
