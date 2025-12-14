<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ActionPermissionService - Granular role-based access control
 * 
 * Provides per-action permission checking with dynamic capabilities
 * Replaces broad "admin", "cashier" with granular permissions
 */
class ActionPermissionService
{
    /**
     * Define granular action permissions per role
     */
    private const ACTION_PERMISSIONS = [
        'admin' => [
            // Appointment Management
            'view_all_appointments' => true,
            'approve_appointment' => true,
            'decline_appointment' => true,
            'cancel_appointment' => true,
            'complete_appointment' => true,
            'modify_appointment' => true,
            'view_pending_appointments' => true,

            // User Management
            'view_all_users' => true,
            'manage_user_roles' => true,
            'view_user_details' => true,
            'deactivate_user' => true,

            // Financial
            'view_all_payments' => true,
            'process_payment' => true,
            'approve_refund' => true,
            'process_refund' => true,
            'generate_financial_reports' => true,

            // System
            'view_system_health' => true,
            'view_audit_logs' => true,
            'manage_settings' => true,
            'manage_blackout_dates' => true,
            'export_data' => true,

            // Analytics
            'view_analytics' => true,
            'view_performance_metrics' => true,
        ],
        'cashier' => [
            // Appointment Management (limited)
            'view_assigned_appointments' => true,
            'complete_appointment' => true,
            'view_pending_payments' => true,

            // Financial
            'view_own_payments' => true,
            'process_payment' => true,
            'approve_refund' => false,
            'process_refund' => true,
            'view_shift_report' => true,

            // Limited Analytics
            'view_own_analytics' => true,
        ],
        'staff' => [
            // Appointment Management
            'view_assigned_appointments' => true,
            'approve_appointment' => false,
            'decline_appointment' => false,
            'cancel_appointment' => false,
            'complete_appointment' => true,
            'modify_appointment' => false,

            // User Management (limited)
            'view_user_details' => true,

            // Financial (view only)
            'view_own_payments' => true,
            'view_pending_payments' => false,
        ],
        'client' => [
            // Own Appointments Only
            'view_own_appointments' => true,
            'cancel_own_appointment' => true,
            'view_appointment_history' => true,

            // Financial (own only)
            'view_own_payments' => true,
            'request_refund' => true,
            'view_own_refund_status' => true,

            // Profile
            'view_own_profile' => true,
            'update_own_profile' => true,
            'change_password' => true,
        ],
    ];

    /**
     * Define action prerequisites and constraints
     */
    private const ACTION_CONSTRAINTS = [
        'approve_refund' => [
            'requires_status' => ['pending'],
            'requires_data' => ['refund_id'],
            'max_daily_limit' => 100,
        ],
        'process_refund' => [
            'requires_status' => ['approved'],
            'requires_data' => ['refund_id', 'amount'],
            'max_daily_limit' => 50000,
        ],
        'process_payment' => [
            'requires_status' => ['pending_payment'],
            'requires_data' => ['appointment_id', 'amount'],
            'max_transaction' => 100000,
        ],
        'approve_appointment' => [
            'requires_status' => ['pending'],
            'requires_data' => ['appointment_id'],
        ],
    ];

    /**
     * Check if user can perform an action
     */
    public function canPerformAction(int $userId, string $action, array $context = []): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return [
                    'allowed' => false,
                    'reason' => 'User not found',
                ];
            }

            $role = $user->getRoleNames()->first() ?? 'user';

            // Check if action exists
            if (!isset(self::ACTION_PERMISSIONS[$role][$action])) {
                return [
                    'allowed' => false,
                    'reason' => 'Action not available for role: ' . $role,
                ];
            }

            // Check if role is permitted
            if (!self::ACTION_PERMISSIONS[$role][$action]) {
                return [
                    'allowed' => false,
                    'reason' => "Role '{$role}' does not have permission for action '{$action}'",
                ];
            }

            // Check action constraints
            if (isset(self::ACTION_CONSTRAINTS[$action])) {
                $constraintCheck = $this->checkConstraints($userId, $action, $context);
                if (!$constraintCheck['allowed']) {
                    return $constraintCheck;
                }
            }

            // Log successful permission check
            Log::debug("Permission granted: user {$userId} can perform {$action}");

            return [
                'allowed' => true,
                'role' => $role,
                'action' => $action,
            ];
        } catch (\Exception $e) {
            Log::error('Permission check error: ' . $e->getMessage());
            return [
                'allowed' => false,
                'reason' => 'Permission check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check action constraints (daily limits, status requirements, etc.)
     */
    private function checkConstraints(int $userId, string $action, array $context): array
    {
        $constraints = self::ACTION_CONSTRAINTS[$action];

        // Check required data
        if (isset($constraints['requires_data'])) {
            foreach ($constraints['requires_data'] as $field) {
                if (!isset($context[$field]) || empty($context[$field])) {
                    return [
                        'allowed' => false,
                        'reason' => "Missing required data: {$field}",
                    ];
                }
            }
        }

        // Check status requirements
        if (isset($constraints['requires_status'])) {
            if (!isset($context['current_status'])) {
                return [
                    'allowed' => false,
                    'reason' => 'Current status not provided',
                ];
            }

            if (!in_array($context['current_status'], $constraints['requires_status'])) {
                return [
                    'allowed' => false,
                    'reason' => "Current status '{$context['current_status']}' not suitable for this action",
                ];
            }
        }

        // Check daily limits
        if (isset($constraints['max_daily_limit'])) {
            $todayUsage = $this->getDailyActionCount($userId, $action);
            if ($todayUsage >= $constraints['max_daily_limit']) {
                return [
                    'allowed' => false,
                    'reason' => "Daily limit reached for action '{$action}'",
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Get all permitted actions for a user
     */
    public function getPermittedActions(int $userId): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return [];
            }

            $role = $user->getRoleNames()->first() ?? 'user';
            $permissions = self::ACTION_PERMISSIONS[$role] ?? [];

            // Filter to only allowed actions
            return array_keys(array_filter($permissions, function ($allowed) {
                return $allowed === true;
            }));
        } catch (\Exception $e) {
            Log::error('Failed to get permitted actions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get action metadata for UI presentation
     */
    public function getActionMetadata(string $action): array
    {
        return [
            'action' => $action,
            'requires_confirmation' => !in_array($action, [
                'view_own_profile',
                'view_own_appointments',
                'view_pending_payments',
                'view_shift_report',
            ]),
            'requires_data' => self::ACTION_CONSTRAINTS[$action]['requires_data'] ?? [],
            'description' => $this->getActionDescription($action),
        ];
    }

    /**
     * Get human-readable action description
     */
    private function getActionDescription(string $action): string
    {
        $descriptions = [
            'approve_appointment' => 'Approve a pending appointment',
            'decline_appointment' => 'Decline an appointment',
            'cancel_appointment' => 'Cancel an existing appointment',
            'complete_appointment' => 'Mark appointment as completed',
            'process_payment' => 'Process a payment',
            'approve_refund' => 'Approve a refund request',
            'process_refund' => 'Process an approved refund',
            'request_refund' => 'Request a refund for your appointment',
        ];

        return $descriptions[$action] ?? $action;
    }

    /**
     * Get daily action count for rate limiting
     */
    private function getDailyActionCount(int $userId, string $action): int
    {
        $cacheKey = "action_daily_count:{$userId}:{$action}:" . now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Increment daily action count
     */
    public function incrementActionCount(int $userId, string $action): void
    {
        $cacheKey = "action_daily_count:{$userId}:{$action}:" . now()->format('Y-m-d');
        Cache::increment($cacheKey);
        Cache::put($cacheKey, Cache::get($cacheKey) + 1, 86400); // 24 hours
    }

    /**
     * Get role capabilities summary
     */
    public function getRoleCapabilities(string $role): array
    {
        return [
            'role' => $role,
            'permitted_actions' => array_keys(array_filter(
                self::ACTION_PERMISSIONS[$role] ?? [],
                function ($allowed) { return $allowed === true; }
            )),
            'denied_actions' => array_keys(array_filter(
                self::ACTION_PERMISSIONS[$role] ?? [],
                function ($allowed) { return $allowed === false; }
            )),
            'total_actions' => count(self::ACTION_PERMISSIONS[$role] ?? []),
        ];
    }

    /**
     * Validate permission for bulk actions
     */
    public function validateBulkActions(int $userId, array $actions, array $context): array
    {
        $results = [];
        foreach ($actions as $action) {
            $results[$action] = $this->canPerformAction($userId, $action, $context);
        }
        return $results;
    }
}
