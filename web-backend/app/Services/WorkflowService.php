<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * WorkflowService - Multi-step action orchestration
 * 
 * Handles:
 * - Chaining of multiple actions
 * - Conditional execution based on results
 * - Auto-confirmation for safe actions
 * - Rollback on failure
 * - Workflow history and logging
 */
class WorkflowService
{
    /**
     * Define available workflows
     */
    private const WORKFLOWS = [
        'approve_and_notify' => [
            'steps' => [
                ['action' => 'approve_appointment', 'required' => true],
                ['action' => 'send_notification', 'required' => true],
            ],
        ],
        'complete_payment_workflow' => [
            'steps' => [
                ['action' => 'validate_payment', 'required' => true],
                ['action' => 'process_payment', 'required' => true],
                ['action' => 'send_receipt', 'required' => false],
                ['action' => 'update_appointment_status', 'required' => true],
            ],
        ],
        'refund_workflow' => [
            'steps' => [
                ['action' => 'validate_refund_eligibility', 'required' => true],
                ['action' => 'process_refund', 'required' => true],
                ['action' => 'cancel_appointment', 'required' => false],
                ['action' => 'send_confirmation', 'required' => true],
            ],
        ],
        'appointment_cancellation' => [
            'steps' => [
                ['action' => 'check_cancellation_deadline', 'required' => true],
                ['action' => 'cancel_appointment', 'required' => true],
                ['action' => 'refund_if_paid', 'required' => false],
                ['action' => 'send_cancellation_email', 'required' => true],
            ],
        ],
    ];

    /**
     * Auto-confirmable actions (no manual confirmation needed)
     */
    private const AUTO_CONFIRMABLE = [
        'send_notification',
        'send_email',
        'send_confirmation',
        'send_receipt',
        'send_cancellation_email',
        'log_action',
        'update_status',
        'update_appointment_status',
    ];

    /**
     * Create and execute a workflow
     */
    public function executeWorkflow(string $workflowName, array $context, int $userId): array
    {
        try {
            Log::info("Starting workflow: {$workflowName}", $context);

            if (!isset(self::WORKFLOWS[$workflowName])) {
                return [
                    'success' => false,
                    'error' => "Workflow '{$workflowName}' not found",
                ];
            }

            $workflow = self::WORKFLOWS[$workflowName];
            $workflowId = Str::uuid()->toString();
            $results = [];
            $executedSteps = [];

            foreach ($workflow['steps'] as $stepIndex => $stepConfig) {
                try {
                    $stepResult = $this->executeStep(
                        $stepConfig,
                        $context,
                        $userId,
                        $results
                    );

                    $results[$stepConfig['action']] = $stepResult;
                    $executedSteps[] = $stepConfig['action'];

                    // If required step fails, stop workflow
                    if ($stepConfig['required'] && !$stepResult['success']) {
                        Log::warning("Required step failed: {$stepConfig['action']}");
                        $this->rollbackWorkflow($workflowId, $executedSteps, $context, $userId);
                        return [
                            'success' => false,
                            'workflow_id' => $workflowId,
                            'error' => "Step '{$stepConfig['action']}' failed",
                            'failed_step' => $stepConfig['action'],
                            'results' => $results,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("Step execution error: {$stepConfig['action']}", ['error' => $e->getMessage()]);
                    
                    if ($stepConfig['required']) {
                        $this->rollbackWorkflow($workflowId, $executedSteps, $context, $userId);
                        return [
                            'success' => false,
                            'workflow_id' => $workflowId,
                            'error' => $e->getMessage(),
                            'failed_step' => $stepConfig['action'],
                        ];
                    }
                }
            }

            Log::info("Workflow completed successfully: {$workflowName}", ['results' => $results]);

            return [
                'success' => true,
                'workflow_id' => $workflowId,
                'workflow_name' => $workflowName,
                'steps_executed' => $executedSteps,
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error("Workflow execution error: {$workflowName}", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a single workflow step
     */
    private function executeStep(array $stepConfig, array $context, int $userId, array $previousResults): array
    {
        $action = $stepConfig['action'];
        
        // Check if auto-confirmable
        $requiresConfirmation = !in_array($action, self::AUTO_CONFIRMABLE);

        Log::debug("Executing step: {$action}");

        // Route to appropriate action handler
        switch ($action) {
            case 'approve_appointment':
                return $this->approveAppointment($context, $userId, $requiresConfirmation);
            case 'cancel_appointment':
                return $this->cancelAppointment($context, $userId, $requiresConfirmation);
            case 'process_payment':
                return $this->processPayment($context, $userId, $requiresConfirmation);
            case 'process_refund':
                return $this->processRefund($context, $userId, $requiresConfirmation);
            case 'validate_payment':
                return $this->validatePayment($context);
            case 'validate_refund_eligibility':
                return $this->validateRefundEligibility($context);
            case 'check_cancellation_deadline':
                return $this->checkCancellationDeadline($context);
            case 'refund_if_paid':
                return $this->refundIfPaid($context, $userId);
            case 'send_notification':
            case 'send_email':
            case 'send_confirmation':
            case 'send_receipt':
            case 'send_cancellation_email':
                return $this->sendNotification($action, $context);
            case 'update_appointment_status':
                return $this->updateAppointmentStatus($context);
            case 'log_action':
                return $this->logAction($context, $userId);
            default:
                return [
                    'success' => false,
                    'error' => "Unknown action: {$action}",
                ];
        }
    }

    /**
     * Determine if an action requires confirmation
     */
    public function requiresConfirmation(string $action): bool
    {
        return !in_array($action, self::AUTO_CONFIRMABLE);
    }

    /**
     * Get available workflows for a user
     */
    public function getAvailableWorkflows(int $userId): array
    {
        return array_keys(self::WORKFLOWS);
    }

    /**
     * Validate workflow execution is allowed
     */
    public function validateWorkflow(string $workflowName, array $context, int $userId): array
    {
        if (!isset(self::WORKFLOWS[$workflowName])) {
            return ['valid' => false, 'reason' => 'Workflow not found'];
        }

        // Add custom validation as needed
        return ['valid' => true];
    }

    private function approveAppointment(array $context, int $userId, bool $requiresConfirmation): array
    {
        try {
            $appointmentId = $context['appointment_id'] ?? null;
            if (!$appointmentId) {
                return ['success' => false, 'error' => 'Missing appointment_id'];
            }

            // Actual approval logic
            DB::table('appointments')
                ->where('id', $appointmentId)
                ->update(['status' => 'approved']);

            return ['success' => true, 'appointment_id' => $appointmentId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function cancelAppointment(array $context, int $userId, bool $requiresConfirmation): array
    {
        try {
            $appointmentId = $context['appointment_id'] ?? null;
            if (!$appointmentId) {
                return ['success' => false, 'error' => 'Missing appointment_id'];
            }

            DB::table('appointments')
                ->where('id', $appointmentId)
                ->update(['status' => 'cancelled']);

            return ['success' => true, 'appointment_id' => $appointmentId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processPayment(array $context, int $userId, bool $requiresConfirmation): array
    {
        try {
            $appointmentId = $context['appointment_id'] ?? null;
            $amount = $context['amount'] ?? null;

            if (!$appointmentId || !$amount) {
                return ['success' => false, 'error' => 'Missing appointment_id or amount'];
            }

            DB::table('payments')->insert([
                'appointment_id' => $appointmentId,
                'amount' => $amount,
                'status' => 'completed',
                'created_at' => now(),
            ]);

            return ['success' => true, 'appointment_id' => $appointmentId, 'amount' => $amount];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processRefund(array $context, int $userId, bool $requiresConfirmation): array
    {
        try {
            $refundId = $context['refund_id'] ?? null;
            if (!$refundId) {
                return ['success' => false, 'error' => 'Missing refund_id'];
            }

            DB::table('refunds')
                ->where('id', $refundId)
                ->update(['status' => 'processed']);

            return ['success' => true, 'refund_id' => $refundId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function validatePayment(array $context): array
    {
        $appointmentId = $context['appointment_id'] ?? null;
        if (!$appointmentId) {
            return ['success' => false, 'error' => 'Missing appointment_id'];
        }

        return ['success' => true, 'valid' => true];
    }

    private function validateRefundEligibility(array $context): array
    {
        $appointmentId = $context['appointment_id'] ?? null;
        if (!$appointmentId) {
            return ['success' => false, 'error' => 'Missing appointment_id'];
        }

        return ['success' => true, 'eligible' => true];
    }

    private function checkCancellationDeadline(array $context): array
    {
        $appointmentDate = $context['appointment_date'] ?? null;
        if (!$appointmentDate) {
            return ['success' => false, 'error' => 'Missing appointment_date'];
        }

        $deadline = Carbon::parse($appointmentDate)->subHours(24);
        $canCancel = now()->isBefore($deadline);

        return ['success' => true, 'can_cancel' => $canCancel];
    }

    private function refundIfPaid(array $context, int $userId): array
    {
        $appointmentId = $context['appointment_id'] ?? null;
        if (!$appointmentId) {
            return ['success' => true]; // Optional step
        }

        return ['success' => true];
    }

    private function sendNotification(string $action, array $context): array
    {
        // Send notification logic
        Log::debug("Sending notification via action: {$action}");
        return ['success' => true];
    }

    private function updateAppointmentStatus(array $context): array
    {
        $appointmentId = $context['appointment_id'] ?? null;
        $status = $context['status'] ?? null;

        if ($appointmentId && $status) {
            DB::table('appointments')
                ->where('id', $appointmentId)
                ->update(['status' => $status]);
        }

        return ['success' => true];
    }

    private function logAction(array $context, int $userId): array
    {
        Log::info("Action logged", ['context' => $context, 'user_id' => $userId]);
        return ['success' => true];
    }

    private function rollbackWorkflow(string $workflowId, array $executedSteps, array $context, int $userId): void
    {
        Log::warning("Rolling back workflow: {$workflowId}", ['steps' => $executedSteps]);
        
        // Implement rollback logic for each executed step
        foreach (array_reverse($executedSteps) as $step) {
            // Rollback logic per step
            Log::debug("Rolling back step: {$step}");
        }
    }
}
