<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    /**
     * Get error logs dashboard with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = ErrorLog::query();

        // Filter by level
        if ($request->has('level')) {
            $query->level($request->get('level'));
        }

        // Filter by time period (hours)
        if ($request->has('hours')) {
            $hours = (int) $request->get('hours', 24);
            $query->recent($hours);
        } else {
            $query->recent(24); // Default to last 24 hours
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Get error summary stats
        $stats = [
            'total_errors' => $query->count(),
            'critical_errors' => ErrorLog::critical()->recent($request->get('hours', 24))->count(),
            'by_level' => ErrorLog::recent($request->get('hours', 24))
                ->selectRaw('level, count(*) as count')
                ->groupBy('level')
                ->get()
                ->pluck('count', 'level')
                ->toArray(),
        ];

        // Get paginated errors
        $errors = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'stats' => $stats,
            'errors' => $errors,
        ]);
    }

    /**
     * Get single error details
     */
    public function show($id): JsonResponse
    {
        $error = ErrorLog::findOrFail($id);

        return response()->json([
            'error' => $error,
            'user' => $error->user,
        ]);
    }

    /**
     * Get error summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $hours = (int) $request->get('hours', 24);

        $recentErrors = ErrorLog::recent($hours);

        return response()->json([
            'total_errors' => $recentErrors->count(),
            'critical_errors' => $recentErrors->critical()->count(),
            'by_level' => $recentErrors
                ->selectRaw('level, count(*) as count')
                ->groupBy('level')
                ->get()
                ->pluck('count', 'level')
                ->toArray(),
            'recent_errors' => $recentErrors
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->makeHidden(['stack_trace', 'request_data']),
            'by_file' => $recentErrors
                ->selectRaw('file, count(*) as count')
                ->groupBy('file')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->pluck('count', 'file')
                ->toArray(),
        ]);
    }

    /**
     * Clear old error logs (cleanup)
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);

        $deleted = ErrorLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} error logs older than {$days} days",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Clear all error logs (use with caution)
     */
    public function clear(Request $request): JsonResponse
    {
        // Require confirmation
        if ($request->get('confirmed') !== true) {
            return response()->json([
                'message' => 'Confirmation required. Set confirmed=true to clear all logs.',
            ], 400);
        }

        $count = ErrorLog::count();
        ErrorLog::truncate();

        return response()->json([
            'message' => "Cleared {$count} error logs",
            'cleared_count' => $count,
        ]);
    }
}
