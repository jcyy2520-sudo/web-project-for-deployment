<?php

namespace App\Http\Controllers;

use App\Models\SystemMetrics;
use App\Services\SystemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $agg = SystemMetrics::recent($minutes)
            ->selectRaw('AVG(cpu_usage) as avg_cpu, MAX(cpu_usage) as max_cpu, MIN(cpu_usage) as min_cpu')
            ->first();

        $current = SystemMetrics::latest('timestamp')->first();

        $samples = $this->getSampledMetrics($minutes, ['timestamp', 'cpu_usage']);

        return response()->json([
            'current' => $current?->cpu_usage ?? 0,
            'average' => round($agg->avg_cpu ?? 0, 2),
            'max' => $agg->max_cpu ?? 0,
            'min' => $agg->min_cpu ?? 0,
            'samples' => $samples->map(fn ($m) => [
                'timestamp' => $m->timestamp->toIso8601String(),
                'usage' => $m->cpu_usage,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Get detailed memory metrics
     */
    public function memoryMetrics(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $agg = SystemMetrics::recent($minutes)
            ->selectRaw('AVG(memory_usage) as avg_mem, MAX(memory_usage) as max_mem')
            ->first();

        $latest = SystemMetrics::latest('timestamp')->first();

        $samples = $this->getSampledMetrics($minutes, ['timestamp', 'memory_usage', 'memory_total']);

        return response()->json([
            'current' => [
                'used_mb' => $latest ? round($latest->memory_usage / 1024 / 1024, 2) : 0,
                'total_mb' => $latest ? round($latest->memory_total / 1024 / 1024, 2) : 0,
                'percent' => $latest && $latest->memory_total ? round(($latest->memory_usage / $latest->memory_total) * 100, 2) : 0,
            ],
            'average_usage' => round(($agg->avg_mem ?? 0) / 1024 / 1024, 2),
            'peak_usage' => round(($agg->max_mem ?? 0) / 1024 / 1024, 2),
            'samples' => $samples->map(fn ($m) => [
                'timestamp' => $m->timestamp->toIso8601String(),
                'used_mb' => round($m->memory_usage / 1024 / 1024, 2),
                'percent' => $m->memory_total ? round(($m->memory_usage / $m->memory_total) * 100, 2) : 0,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Get detailed disk metrics
     */
    public function diskMetrics(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);
        $minutes = $hours * 60;

        $agg = SystemMetrics::recent($minutes)
            ->selectRaw('AVG(disk_usage) as avg_disk')
            ->first();

        $latest = SystemMetrics::latest('timestamp')->first();

        // For trend, only need first and last row
        $first = SystemMetrics::recent($minutes)->orderBy('timestamp', 'asc')->first();
        $trend = $this->calculateTrendFromTwo($first, $latest, 'disk_usage');

        $samples = $this->getSampledMetrics($minutes, ['timestamp', 'disk_usage', 'disk_total', 'disk_free']);

        return response()->json([
            'current' => [
                'used_mb' => $latest ? round($latest->disk_usage / 1024 / 1024, 2) : 0,
                'total_mb' => $latest ? round($latest->disk_total / 1024 / 1024, 2) : 0,
                'free_mb' => $latest ? round($latest->disk_free / 1024 / 1024, 2) : 0,
                'percent' => $latest && $latest->disk_total ? round(($latest->disk_usage / $latest->disk_total) * 100, 2) : 0,
            ],
            'average_usage' => round(($agg->avg_disk ?? 0) / 1024 / 1024, 2),
            'trend' => $trend,
            'samples' => $samples->map(fn ($m) => [
                'timestamp' => $m->timestamp->toIso8601String(),
                'used_mb' => round($m->disk_usage / 1024 / 1024, 2),
                'percent' => $m->disk_total ? round(($m->disk_usage / $m->disk_total) * 100, 2) : 0,
            ])->values()->toArray(),
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

        $first = SystemMetrics::recent($minutes)->orderBy('timestamp', 'asc')->first();
        $last = SystemMetrics::recent($minutes)->orderBy('timestamp', 'desc')->first();
        $count = SystemMetrics::recent($minutes)->count();

        return response()->json([
            'cpu_trend' => $this->calculateTrendFromTwo($first, $last, 'cpu_usage'),
            'memory_trend' => $this->calculateTrendFromTwo($first, $last, 'memory_usage'),
            'disk_trend' => $this->calculateTrendFromTwo($first, $last, 'disk_usage'),
            'samples_analyzed' => $count,
            'time_range_hours' => $hours,
        ]);
    }

    /**
     * Get sampled metrics to avoid loading thousands of rows.
     * Returns at most ~200 evenly-spaced data points.
     */
    private function getSampledMetrics(int $minutes, array $columns): \Illuminate\Support\Collection
    {
        $totalRows = SystemMetrics::recent($minutes)->count();
        $maxSamples = 200;

        if ($totalRows <= $maxSamples) {
            return SystemMetrics::recent($minutes)->select($columns)->orderBy('timestamp')->get();
        }

        // Use nth-row sampling via row number
        $nth = (int) ceil($totalRows / $maxSamples);
        return SystemMetrics::recent($minutes)
            ->select($columns)
            ->orderBy('timestamp')
            ->get()
            ->nth($nth);
    }

    /**
     * Calculate trend from first and last metrics rows only.
     */
    private function calculateTrendFromTwo($first, $last, string $field): string
    {
        if (!$first || !$last || !$first->{$field}) {
            return 'stable';
        }

        $change = (($last->{$field} - $first->{$field}) / $first->{$field}) * 100;

        if (abs($change) < 5) {
            return 'stable';
        }

        return $change > 0 ? 'increasing' : 'decreasing';
    }
}
