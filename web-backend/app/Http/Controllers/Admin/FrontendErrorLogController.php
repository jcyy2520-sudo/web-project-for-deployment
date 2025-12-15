<?php

namespace App\Http\Controllers\Admin;

use App\Models\FrontendErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontendErrorLogController
{
    public function index(Request $request): JsonResponse
    {
        $query = FrontendErrorLog::query();

        // Filtering
        if ($request->has('type')) {
            $query->where('error_type', $request->query('type'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->has('hours')) {
            $hours = (int) $request->query('hours', 24);
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $errors = $query->paginate($request->query('per_page', 20));

        return response()->json($errors);
    }

    public function show(FrontendErrorLog $frontendErrorLog): JsonResponse
    {
        $frontendErrorLog->load('user');

        return response()->json($frontendErrorLog);
    }

    public function report(Request $request, FrontendErrorLog $frontendErrorLog): JsonResponse
    {
        $frontendErrorLog->update([
            'status' => 'reported',
            'report_notes' => $request->input('notes'),
            'reported_at' => now(),
            'reported_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Error marked as reported',
            'error' => $frontendErrorLog,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);

        $stats = [
            'total' => FrontendErrorLog::recent($hours)->count(),
            'critical' => FrontendErrorLog::recent($hours)->where('severity', 'critical')->count(),
            'warning' => FrontendErrorLog::recent($hours)->where('severity', 'warning')->count(),
            'info' => FrontendErrorLog::recent($hours)->where('severity', 'info')->count(),
            'reported' => FrontendErrorLog::recent($hours)->where('status', 'reported')->count(),
            'unreported' => FrontendErrorLog::recent($hours)->where('status', 'unreported')->count(),
            'by_type' => FrontendErrorLog::recent($hours)
                ->groupBy('error_type')
                ->selectRaw('error_type, count(*) as count')
                ->get()
                ->pluck('count', 'error_type'),
            'by_browser' => FrontendErrorLog::recent($hours)
                ->groupBy('browser')
                ->selectRaw('browser, count(*) as count')
                ->get()
                ->pluck('count', 'browser'),
            'affected_users' => FrontendErrorLog::recent($hours)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count(),
        ];

        return response()->json($stats);
    }

    public function cleanup(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $deleted = FrontendErrorLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} old errors",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkReport(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $notes = $request->input('notes', '');

        $updated = FrontendErrorLog::whereIn('id', $ids)->update([
            'status' => 'reported',
            'report_notes' => $notes,
            'reported_at' => now(),
            'reported_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Updated {$updated} errors",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Store frontend error log from public endpoint (secured with auth + rate limiting)
     * Rate limited by middleware to prevent abuse/spam
     */
    public function storePublic(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'error_type' => 'required|string|max:100',
                'severity' => 'in:info,warning,critical',
                'stack_trace' => 'nullable|string|max:5000',
                'user_agent' => 'nullable|string',
                'browser' => 'nullable|string|max:100',
                'url' => 'nullable|string|max:1000',
            ]);

            FrontendErrorLog::create([
                'message' => $validated['message'],
                'error_type' => $validated['error_type'],
                'severity' => $validated['severity'] ?? 'warning',
                'stack_trace' => $validated['stack_trace'],
                'user_agent' => $validated['user_agent'],
                'browser' => $validated['browser'],
                'url' => $validated['url'],
                'user_id' => auth()->id(),
                'status' => 'unreported',
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Error logged successfully'], 201);
        } catch (\Exception $e) {
            \Log::error('Frontend error logging failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to log error'], 500);
        }
    }
}
