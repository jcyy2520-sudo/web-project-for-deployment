<?php

namespace App\Services;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\ErrorLog;
use App\Models\RequestMetric;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Check if alert should be triggered based on error
     */
    public function checkErrorAlert(ErrorLog $error): void
    {
        $rules = AlertRule::where('type', 'error_level')
            ->where('enabled', true)
            ->get();

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, ['level' => $error->level])) {
                $this->createAlert($rule, $error);
            }
        }
    }

    /**
     * Check performance-based alerts
     */
    public function checkPerformanceAlert(RequestMetric $metric): void
    {
        $rules = AlertRule::where('type', 'response_time')
            ->where('enabled', true)
            ->get();

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, ['response_time' => $metric->response_time_ms])) {
                $this->createAlert($rule, $metric);
            }
        }
    }

    /**
     * Check system health alerts
     */
    public function checkHealthAlert(array $healthData): void
    {
        // Check disk space
        $diskRules = AlertRule::where('type', 'disk_space')
            ->where('enabled', true)
            ->get();

        if (isset($healthData['storage'])) {
            foreach ($diskRules as $rule) {
                if ($this->evaluateRule($rule, ['disk_usage' => $healthData['storage']['usage_percent']])) {
                    $this->createAlert($rule, $healthData);
                }
            }
        }

        // Check memory
        $memoryRules = AlertRule::where('type', 'memory')
            ->where('enabled', true)
            ->get();

        if (isset($healthData['system'])) {
            foreach ($memoryRules as $rule) {
                if ($this->evaluateRule($rule, ['memory_percent' => $healthData['system']['memory_percent']])) {
                    $this->createAlert($rule, $healthData);
                }
            }
        }
    }

    /**
     * Evaluate if a rule should trigger
     */
    private function evaluateRule(AlertRule $rule, array $data): bool
    {
        if (!$rule->canTrigger()) {
            return false;
        }

        $value = $this->extractValue($rule->type, $data);
        if ($value === null) {
            return false;
        }

        return $this->compareValues($value, $rule->condition, $rule->threshold);
    }

    /**
     * Extract the value to compare
     */
    private function extractValue(string $type, array $data)
    {
        return match ($type) {
            'error_level' => $data['level'] ?? null,
            'response_time' => $data['response_time'] ?? null,
            'disk_space' => $data['disk_usage'] ?? null,
            'memory' => $data['memory_percent'] ?? null,
            'error_count' => $data['count'] ?? null,
            default => null,
        };
    }

    /**
     * Compare two values
     */
    private function compareValues($value, string $condition, $threshold): bool
    {
        return match ($condition) {
            '>' => $value > $threshold,
            '<' => $value < $threshold,
            '>=' => $value >= $threshold,
            '<=' => $value <= $threshold,
            '==' => $value == $threshold,
            '!=' => $value != $threshold,
            'contains' => strpos((string) $value, (string) $threshold) !== false,
            default => false,
        };
    }

    /**
     * Create an alert event
     */
    private function createAlert(AlertRule $rule, $data): void
    {
        try {
            $severity = $this->determineSeverity($rule, $data);
            $message = $this->buildMessage($rule, $data);

            $event = AlertEvent::create([
                'alert_rule_id' => $rule->id,
                'severity' => $severity,
                'message' => $message,
                'context' => is_array($data) ? $data : ['data' => $data],
                'channel' => $rule->channel,
                'sent' => false,
            ]);

            // Send the alert
            if ($rule->channel === 'slack' && $rule->slack_webhook) {
                $slackService = app(SlackAlertService::class);
                $slackService->sendAlert($rule, $event);
            }

            // Mark rule as triggered
            $rule->markTriggered();
        } catch (\Exception $e) {
            Log::error('Failed to create alert: ' . $e->getMessage());
        }
    }

    /**
     * Determine alert severity
     */
    private function determineSeverity(AlertRule $rule, $data): string
    {
        if ($rule->type === 'error_level') {
            $level = $data instanceof ErrorLog ? $data->level : $data['level'] ?? 'info';
            return match ($level) {
                'error', 'critical' => 'critical',
                'warning' => 'warning',
                default => 'info',
            };
        }

        if ($rule->type === 'response_time') {
            $time = $data instanceof RequestMetric ? $data->response_time_ms : $data['response_time'] ?? 0;
            return $time > 2000 ? 'critical' : ($time > 1000 ? 'warning' : 'info');
        }

        if ($rule->type === 'disk_space') {
            $usage = is_array($data) ? $data['disk_usage'] ?? 0 : 0;
            return $usage > 90 ? 'critical' : ($usage > 75 ? 'warning' : 'info');
        }

        if ($rule->type === 'memory') {
            $usage = is_array($data) ? $data['memory_percent'] ?? 0 : 0;
            return $usage > 90 ? 'critical' : ($usage > 75 ? 'warning' : 'info');
        }

        return 'info';
    }

    /**
     * Build alert message
     */
    private function buildMessage(AlertRule $rule, $data): string
    {
        if ($data instanceof ErrorLog) {
            return "🔴 Error Alert: {$data->message} ({$data->level}) in {$data->file}:{$data->line}";
        }

        if ($data instanceof RequestMetric) {
            return "⚠️ Slow Request: {$data->method} {$data->path} took {$data->response_time_ms}ms";
        }

        if (is_array($data)) {
            return match ($rule->type) {
                'disk_space' => "⚠️ Disk Usage High: {$data['disk_usage']}% used",
                'memory' => "⚠️ Memory Usage High: {$data['memory_percent']}% used",
                'error_count' => "🔴 Multiple Errors: {$data['count']} errors detected",
                default => "Alert: " . json_encode($data),
            };
        }

        return "Alert triggered for rule: {$rule->name}";
    }

    /**
     * Get unacknowledged alerts for dashboard
     */
    public function getUnacknowledgedAlerts($limit = 50)
    {
        return AlertEvent::unacknowledged()
            ->with('alertRule')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get alert summary
     */
    public function getAlertSummary($hours = 24)
    {
        $query = AlertEvent::where('created_at', '>=', now()->subHours($hours));

        return [
            'total' => $query->count(),
            'critical' => (clone $query)->critical()->count(),
            'warning' => (clone $query)->where('severity', 'warning')->count(),
            'info' => (clone $query)->where('severity', 'info')->count(),
            'unacknowledged' => (clone $query)->unacknowledged()->count(),
        ];
    }
}
