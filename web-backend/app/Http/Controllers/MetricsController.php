<?php

namespace App\Http\Controllers;

use App\Models\RequestMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Get performance metrics dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);

        $metrics = RequestMetric::recent($hours);

        return response()->json([
            'summary' => [
                'total_requests' => $metrics->count(),
                'total_errors' => $metrics->errors()->count(),
                'error_rate' => $this->calculateErrorRate($metrics),
                'avg_response_time' => round($metrics->avg('response_time_ms'), 2),
                'max_response_time' => $metrics->max('response_time_ms'),
                'min_response_time' => $metrics->min('response_time_ms'),
                'avg_memory_usage' => round($metrics->avg('memory_usage') / 1024 / 1024, 2) . ' MB',
            ],
            'by_status_code' => $metrics
                ->selectRaw('status_code, count(*) as count')
                ->groupBy('status_code')
                ->get()
                ->pluck('count', 'status_code')
                ->toArray(),
            'by_method' => $metrics
                ->selectRaw('method, count(*) as count, AVG(response_time_ms) as avg_time')
                ->groupBy('method')
                ->get(),
            'slow_endpoints' => RequestMetric::topSlowEndpoints(10, $hours),
            'error_endpoints' => RequestMetric::errorRateByEndpoint($hours)
                ->where('error_rate', '>', 0)
                ->sortByDesc('error_rate')
                ->take(10),
            'recent_errors' => $metrics->errors()->orderBy('created_at', 'desc')->limit(10)->get(),
        ]);
    }

    /**
     * Get metrics for a specific endpoint
     */
    public function endpoint(Request $request): JsonResponse
    {
        $path = $request->get('path');
        if (!$path) {
            return response()->json(['error' => 'Path parameter required'], 400);
        }

        $hours = (int) $request->get('hours', 24);

        $metrics = RequestMetric::where('path', $path)->recent($hours);

        return response()->json([
            'path' => $path,
            'total_requests' => $metrics->count(),
            'total_errors' => $metrics->errors()->count(),
            'error_rate' => $this->calculateErrorRate($metrics),
            'response_times' => [
                'avg' => round($metrics->avg('response_time_ms'), 2),
                'max' => $metrics->max('response_time_ms'),
                'min' => $metrics->min('response_time_ms'),
            ],
            'by_method' => $metrics
                ->selectRaw('method, count(*) as count')
                ->groupBy('method')
                ->get()
                ->pluck('count', 'method')
                ->toArray(),
            'by_status_code' => $metrics
                ->selectRaw('status_code, count(*) as count')
                ->groupBy('status_code')
                ->get()
                ->pluck('count', 'status_code')
                ->toArray(),
            'recent_requests' => $metrics->orderBy('created_at', 'desc')->limit(20)->get(),
        ]);
    }

    /**
     * Get slow requests
     */
    public function slowRequests(Request $request): JsonResponse
    {
        $threshold = (int) $request->get('threshold', 1000);
        $hours = (int) $request->get('hours', 24);

        $slowRequests = RequestMetric::recent($hours)
            ->slow($threshold)
            ->orderBy('response_time_ms', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'threshold_ms' => $threshold,
            'count' => $slowRequests->count(),
            'requests' => $slowRequests,
        ]);
    }

    /**
     * Get error metrics
     */
    public function errors(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);

        $errors = RequestMetric::recent($hours)->errors();

        return response()->json([
            'total_errors' => $errors->count(),
            'by_status_code' => $errors
                ->selectRaw('status_code, count(*) as count')
                ->groupBy('status_code')
                ->get()
                ->pluck('count', 'status_code')
                ->toArray(),
            'by_path' => $errors
                ->selectRaw('path, count(*) as count')
                ->groupBy('path')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_error_type' => $errors
                ->selectRaw('error_type, count(*) as count')
                ->groupBy('error_type')
                ->orderByDesc('count')
                ->get(),
            'recent_errors' => $errors->orderBy('created_at', 'desc')->limit(20)->get(),
        ]);
    }

    /**
     * Cleanup old metrics
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);

        $deleted = RequestMetric::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} metrics older than {$days} days",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Calculate error rate from query
     */
    private function calculateErrorRate($query): float
    {
        $total = $query->count();
        if ($total === 0) {
            return 0;
        }

        $errors = (clone $query)->where('is_error', true)->count();
        return round(($errors / $total) * 100, 2);
    }
}
