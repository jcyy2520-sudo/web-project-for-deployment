<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use Illuminate\Http\Request;

class ActionLogController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = ActionLog::with('user');

            // If user is not admin, only show their own logs
            if (!$request->user()->isAdmin()) {
                $query->where('user_id', $request->user()->id);
            }

            // Apply filters
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            if ($request->has('model_type')) {
                $query->where('model_type', $request->model_type);
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 10));

            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch action logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function userLogs(Request $request)
    {
        try {
            $query = ActionLog::where('user_id', $request->user()->id)
                ->with('user');

            // Filter by action type
            if ($request->has('action') && $request->action) {
                $query->where('action', $request->action);
            }

            // Search by description
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('description', 'like', "%{$search}%");
            }

            // Sort direction (default: desc)
            $sortDir = $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';

            $logs = $query->orderBy('created_at', $sortDir)
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'data' => $logs->items(),
                'pagination' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage()
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch your action logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function adminLogs(Request $request)
    {
        try {
            $query = ActionLog::with('user');

            // Filter by user if specified
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by action if specified
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            // Search by description or user name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 10));

            return response()->json([
                'data' => $logs->items(),
                'pagination' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage()
                ],
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch admin logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $query = ActionLog::query();

            if (!$request->user()->isAdmin()) {
                $query->where('user_id', $request->user()->id);
            }

            $today = now()->startOfDay();
            $thisMonth = now()->startOfMonth();

            $stats = [
                'total_actions' => (clone $query)->count(),
                'today_actions' => (clone $query)->whereDate('created_at', $today)->count(),
                'this_month_actions' => (clone $query)->whereDate('created_at', '>=', $thisMonth)->count(),
                'by_action' => (clone $query)->selectRaw('action, COUNT(*) as count')
                    ->groupBy('action')
                    ->get()
                    ->pluck('count', 'action')
                    ->toArray()
            ];

            return response()->json([
                'data' => $stats,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch action log statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }
}
