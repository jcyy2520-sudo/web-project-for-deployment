<?php

namespace App\Http\Controllers;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Get dashboard with all alerts
     */
    public function dashboard(Request $request): JsonResponse
    {
        $alertService = app(AlertService::class);
        
        return response()->json([
            'summary' => $alertService->getAlertSummary($request->get('hours', 24)),
            'recent_alerts' => $alertService->getUnacknowledgedAlerts(20),
            'rules' => AlertRule::where('enabled', true)->get(),
            'statistics' => $this->getAlertStatistics($request->get('hours', 24)),
        ]);
    }

    /**
     * Get all alerts with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = AlertEvent::query();

        // Filter by severity
        if ($request->has('severity')) {
            $query->where('severity', $request->get('severity'));
        }

        // Filter by acknowledged
        if ($request->get('acknowledged') === 'false') {
            $query->where('acknowledged', false);
        } elseif ($request->get('acknowledged') === 'true') {
            $query->where('acknowledged', true);
        }

        // Filter by time period
        if ($request->has('hours')) {
            $hours = (int) $request->get('hours', 24);
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        // Filter by rule
        if ($request->has('rule_id')) {
            $query->where('alert_rule_id', $request->get('rule_id'));
        }

        $alerts = $query
            ->with('alertRule')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($alerts);
    }

    /**
     * Get specific alert
     */
    public function show($id): JsonResponse
    {
        $alert = AlertEvent::with(['alertRule', 'acknowledgedBy'])
            ->findOrFail($id);

        return response()->json($alert);
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledge($id, Request $request): JsonResponse
    {
        $alert = AlertEvent::findOrFail($id);
        
        $alert->acknowledge(
            auth()->id() ?? 0,
            $request->get('note', '')
        );

        return response()->json([
            'message' => 'Alert acknowledged',
            'alert' => $alert,
        ]);
    }

    /**
     * Acknowledge multiple alerts
     */
    public function acknowledgeMultiple(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);
        $note = $request->get('note', '');

        AlertEvent::whereIn('id', $ids)->each(function ($alert) use ($note) {
            $alert->acknowledge(auth()->id() ?? 0, $note);
        });

        return response()->json([
            'message' => count($ids) . ' alerts acknowledged',
            'count' => count($ids),
        ]);
    }

    /**
     * Get alert rules
     */
    public function rules(Request $request): JsonResponse
    {
        $query = AlertRule::query();

        if ($request->get('enabled') === 'true') {
            $query->where('enabled', true);
        } elseif ($request->get('enabled') === 'false') {
            $query->where('enabled', false);
        }

        $rules = $query->orderBy('name')->get();

        return response()->json($rules);
    }

    /**
     * Create alert rule
     */
    public function createRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:error_level,response_time,disk_space,memory,error_count',
            'condition' => 'required|in:>,<,>=,<=,==,!=,contains',
            'threshold' => 'required',
            'channel' => 'required|in:slack,email',
            'slack_webhook' => 'nullable|url',
            'email_recipients' => 'nullable|string',
            'enabled' => 'boolean',
            'cooldown_minutes' => 'integer|min:1|max:1440',
        ]);

        $rule = AlertRule::create($validated);

        return response()->json([
            'message' => 'Alert rule created',
            'rule' => $rule,
        ], 201);
    }

    /**
     * Update alert rule
     */
    public function updateRule($id, Request $request): JsonResponse
    {
        $rule = AlertRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'in:error_level,response_time,disk_space,memory,error_count',
            'condition' => 'in:>,<,>=,<=,==,!=,contains',
            'threshold' => 'string',
            'channel' => 'in:slack,email',
            'slack_webhook' => 'nullable|url',
            'email_recipients' => 'nullable|string',
            'enabled' => 'boolean',
            'cooldown_minutes' => 'integer|min:1|max:1440',
        ]);

        $rule->update($validated);

        return response()->json([
            'message' => 'Alert rule updated',
            'rule' => $rule,
        ]);
    }

    /**
     * Delete alert rule
     */
    public function deleteRule($id): JsonResponse
    {
        $rule = AlertRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'message' => 'Alert rule deleted',
        ]);
    }

    /**
     * Get alert statistics
     */
    private function getAlertStatistics($hours): array
    {
        $query = AlertEvent::where('created_at', '>=', now()->subHours($hours));

        return [
            'by_severity' => $query
                ->clone()
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->get()
                ->pluck('count', 'severity')
                ->toArray(),
            'by_type' => $query
                ->clone()
                ->join('alert_rules', 'alert_events.alert_rule_id', '=', 'alert_rules.id')
                ->selectRaw('alert_rules.type, count(*) as count')
                ->groupBy('alert_rules.type')
                ->get()
                ->pluck('count', 'type')
                ->toArray(),
            'acknowledged_rate' => [
                'acknowledged' => (clone $query)->where('acknowledged', true)->count(),
                'unacknowledged' => (clone $query)->where('acknowledged', false)->count(),
            ],
        ];
    }
}
