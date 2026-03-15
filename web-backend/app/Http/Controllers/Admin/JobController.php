<?php

namespace App\Http\Controllers\Admin;

use App\Models\JobMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController
{
    public function dashboard(): JsonResponse
    {
        $stats = JobMetric::getStatistics(24);
        $recentJobs = JobMetric::recent(24)->orderBy('created_at', 'desc')->limit(10)->get();
        $failedJobs = JobMetric::recent(24)->failed()->limit(10)->get();

        return response()->json([
            'stats' => $stats,
            'recent_jobs' => $recentJobs,
            'failed_jobs' => $failedJobs,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = JobMetric::query();

        // Filtering
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('queue')) {
            $query->byQueue($request->query('queue'));
        }

        if ($request->has('job_name')) {
            $query->byName($request->query('job_name'));
        }

        if ($request->has('hours')) {
            $hours = (int) $request->query('hours', 24);
            $query->recent($hours);
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $jobs = $query->paginate($request->query('per_page', 20));

        return response()->json($jobs);
    }

    public function show(JobMetric $job): JsonResponse
    {
        return response()->json($job);
    }

    public function stats(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);

        // Single aggregation query instead of 8 separate COUNT queries
        $counts = JobMetric::recent($hours)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'retried' THEN 1 ELSE 0 END) as retried,
                AVG(CASE WHEN duration_seconds IS NOT NULL THEN duration_seconds END) as avg_duration,
                SUM(CASE WHEN status = 'processing' AND started_at < ? THEN 1 ELSE 0 END) as stuck_jobs
            ", [now()->subMinutes(30)])
            ->first();

        $total = (int) $counts->total;
        $completed = (int) $counts->completed;
        $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return response()->json([
            'total' => $total,
            'completed' => $completed,
            'failed' => (int) $counts->failed,
            'processing' => (int) $counts->processing,
            'pending' => (int) $counts->pending,
            'retried' => (int) $counts->retried,
            'average_duration_seconds' => $counts->avg_duration,
            'success_rate' => $successRate,
            'stuck_jobs' => (int) $counts->stuck_jobs,
        ]);
    }

    public function byQueue(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);

        $queues = JobMetric::recent($hours)
            ->groupBy('queue')
            ->selectRaw('queue, status, count(*) as count')
            ->get()
            ->groupBy('queue');

        return response()->json($queues);
    }

    public function byName(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);

        $jobs = JobMetric::recent($hours)
            ->groupBy('job_name')
            ->selectRaw('job_name, status, count(*) as count')
            ->get()
            ->groupBy('job_name');

        return response()->json($jobs);
    }

    public function slowJobs(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);
        $minSeconds = (int) $request->query('min_seconds', 300); // 5 minutes

        $slowJobs = JobMetric::recent($hours)
            ->where('status', 'completed')
            ->where('duration_seconds', '>=', $minSeconds)
            ->orderBy('duration_seconds', 'desc')
            ->limit(20)
            ->get();

        return response()->json($slowJobs);
    }

    public function failedJobs(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);

        $failedJobs = JobMetric::recent($hours)
            ->failed()
            ->orderBy('failed_at', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json($failedJobs);
    }

    public function stuckJobs(Request $request): JsonResponse
    {
        $minutes = (int) $request->query('minutes', 30);

        $stuckJobs = JobMetric::where('status', 'processing')
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->orderBy('started_at', 'asc')
            ->get();

        return response()->json($stuckJobs);
    }

    public function retry(JobMetric $job): JsonResponse
    {
        if ($job->status !== 'failed') {
            return response()->json(['error' => 'Only failed jobs can be retried'], 400);
        }

        if ($job->attempts >= $job->max_attempts) {
            return response()->json(['error' => 'Maximum retry attempts reached'], 400);
        }

        $job->retry();

        return response()->json([
            'message' => 'Job marked for retry',
            'job' => $job,
        ]);
    }

    public function cleanup(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 7);
        $deleted = JobMetric::where('created_at', '<', now()->subDays($days))
            ->whereIn('status', ['completed', 'failed'])
            ->delete();

        return response()->json([
            'message' => "Deleted {$deleted} old job records",
            'deleted_count' => $deleted,
        ]);
    }
}
