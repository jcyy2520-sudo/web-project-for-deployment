<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ActionIntelligenceService - Smart action execution and recommendations
 * 
 * Features:
 * - Intent-based action ranking
 * - Predictive action suggestions
 * - Context-aware recommendations
 * - Action confidence scoring
 */
class ActionIntelligenceService
{
    /**
     * Define available actions and their contexts
     */
    private const AVAILABLE_ACTIONS = [
        'book_appointment' => [
            'required_role' => ['client', 'admin', 'guest'],
            'confidence_threshold' => 0.7,
            'keywords' => ['book', 'schedule', 'appointment', 'reserve', 'set up'],
            'risk_level' => 'high', // Requires confirmation
            'steps' => 5,
        ],
        'reschedule_appointment' => [
            'required_role' => ['client', 'admin'],
            'confidence_threshold' => 0.75,
            'keywords' => ['reschedule', 'change', 'move', 'different time', 'reschedule'],
            'risk_level' => 'high',
            'steps' => 3,
            'prerequisites' => ['has_upcoming_appointment'],
        ],
        'cancel_appointment' => [
            'required_role' => ['client', 'admin'],
            'confidence_threshold' => 0.75,
            'keywords' => ['cancel', 'delete', 'remove', 'appointment'],
            'risk_level' => 'high',
            'steps' => 2,
            'prerequisites' => ['has_upcoming_appointment'],
        ],
        'process_payment' => [
            'required_role' => ['client', 'admin', 'cashier'],
            'confidence_threshold' => 0.8,
            'keywords' => ['pay', 'payment', 'invoice', 'charge', 'bill'],
            'risk_level' => 'high',
            'steps' => 4,
            'prerequisites' => ['has_pending_payment'],
        ],
        'request_refund' => [
            'required_role' => ['client', 'admin', 'cashier'],
            'confidence_threshold' => 0.75,
            'keywords' => ['refund', 'money back', 'return', 'reimbursement'],
            'risk_level' => 'high',
            'steps' => 3,
            'prerequisites' => ['has_completed_appointment', 'has_paid_appointment'],
        ],
        'view_services' => [
            'required_role' => ['client', 'guest', 'admin'],
            'confidence_threshold' => 0.6,
            'keywords' => ['services', 'what do', 'offer', 'available', 'types'],
            'risk_level' => 'low',
            'steps' => 1,
        ],
        'check_appointment_status' => [
            'required_role' => ['client', 'admin'],
            'confidence_threshold' => 0.7,
            'keywords' => ['status', 'check', 'appointment', 'pending', 'approved'],
            'risk_level' => 'low',
            'steps' => 1,
            'prerequisites' => ['has_appointment'],
        ],
        'request_human_support' => [
            'required_role' => ['client', 'guest', 'admin', 'cashier'],
            'confidence_threshold' => 0.5,
            'keywords' => ['help', 'support', 'agent', 'human', 'speak to someone'],
            'risk_level' => 'low',
            'steps' => 1,
        ],
    ];

    /**
     * Get ranked action suggestions based on context
     * 
     * Returns actions sorted by relevance and confidence
     */
    public function getSuggestedActions(
        string $userMessage,
        string $role,
        array $userContext = []
    ): array {
        $messageLower = strtolower($userMessage);
        $suggestedActions = [];

        foreach (self::AVAILABLE_ACTIONS as $actionName => $config) {
            // Check role permission
            if (!in_array($role, $config['required_role'] ?? [])) {
                continue;
            }

            // Calculate confidence based on keyword matching
            $confidence = $this->calculateActionConfidence($messageLower, $config);

            if ($confidence < $config['confidence_threshold']) {
                continue;
            }

            // Check prerequisites
            if (!$this->checkPrerequisites($actionName, $config, $userContext)) {
                continue;
            }

            $suggestedActions[] = [
                'action' => $actionName,
                'confidence' => $confidence,
                'risk_level' => $config['risk_level'],
                'steps_required' => $config['steps'],
                'display_name' => $this->getActionDisplayName($actionName),
                'description' => $this->getActionDescription($actionName),
                'requires_confirmation' => $config['risk_level'] === 'high',
            ];
        }

        // Sort by confidence descending
        usort($suggestedActions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestedActions, 0, 3); // Return top 3 suggestions
    }

    /**
     * Get next recommended action for conversation flow
     */
    public function getNextRecommendedAction(
        string $lastAction,
        string $role,
        array $userContext = []
    ): ?array {
        $actionFlows = [
            'view_services' => 'book_appointment', // After viewing services, suggest booking
            'check_appointment_status' => 'reschedule_appointment', // After checking status, suggest changes
            'cancel_appointment' => 'request_human_support', // After cancellation, offer support
            'process_payment' => 'request_human_support', // After payment, offer help
        ];

        $nextActionName = $actionFlows[$lastAction] ?? null;

        if (!$nextActionName) {
            return null;
        }

        $config = self::AVAILABLE_ACTIONS[$nextActionName] ?? null;

        if (!$config || !in_array($role, $config['required_role'])) {
            return null;
        }

        return [
            'action' => $nextActionName,
            'display_name' => $this->getActionDisplayName($nextActionName),
            'suggestion_type' => 'flow_based',
            'confidence' => 0.85,
        ];
    }

    /**
     * Calculate confidence score for an action
     */
    private function calculateActionConfidence(string $message, array $actionConfig): float
    {
        $baseConfidence = 0.0;
        $keywords = $actionConfig['keywords'] ?? [];

        // Count keyword matches
        $matches = 0;
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $matches++;
            }
        }

        // Calculate confidence based on matches
        $baseConfidence = min($matches / max(1, count($keywords)), 1.0);

        // Boost confidence for exact phrase matches
        $exactPhrases = [
            'book_appointment' => 'book appointment',
            'cancel_appointment' => 'cancel appointment',
            'process_payment' => 'payment',
            'request_refund' => 'refund',
        ];

        if (isset($exactPhrases[$this->getCurrentActionName()]) && 
            strpos($message, $exactPhrases[$this->getCurrentActionName()]) !== false) {
            $baseConfidence = min($baseConfidence + 0.2, 1.0);
        }

        return $baseConfidence;
    }

    /**
     * Check if action prerequisites are met
     */
    private function checkPrerequisites(
        string $actionName,
        array $config,
        array $userContext
    ): bool {
        $prerequisites = $config['prerequisites'] ?? [];

        foreach ($prerequisites as $prerequisite) {
            $met = match($prerequisite) {
                'has_appointment' => !empty($userContext['appointments']),
                'has_upcoming_appointment' => !empty($userContext['upcoming_appointments']),
                'has_completed_appointment' => !empty($userContext['completed_appointments']),
                'has_pending_payment' => !empty($userContext['pending_payments']),
                'has_paid_appointment' => !empty($userContext['paid_appointments']),
                default => true,
            };

            if (!$met) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get human-readable action name
     */
    private function getActionDisplayName(string $action): string
    {
        return match($action) {
            'book_appointment' => 'Book Appointment',
            'reschedule_appointment' => 'Reschedule Appointment',
            'cancel_appointment' => 'Cancel Appointment',
            'process_payment' => 'Process Payment',
            'request_refund' => 'Request Refund',
            'view_services' => 'View Services',
            'check_appointment_status' => 'Check Status',
            'request_human_support' => 'Speak with Support',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }

    /**
     * Get action description for UI
     */
    private function getActionDescription(string $action): string
    {
        return match($action) {
            'book_appointment' => 'Schedule a new appointment',
            'reschedule_appointment' => 'Change your appointment time',
            'cancel_appointment' => 'Cancel your appointment',
            'process_payment' => 'Complete payment for appointment',
            'request_refund' => 'Request a refund',
            'view_services' => 'See available services',
            'check_appointment_status' => 'View appointment details',
            'request_human_support' => 'Chat with our support team',
            default => 'Perform action',
        };
    }

    /**
     * Get current action based on intent
     * This is a placeholder - should be updated based on actual intent
     */
    private function getCurrentActionName(): string
    {
        return 'book_appointment'; // Default
    }

    /**
     * Rank actions by priority
     * 
     * Considers: confidence, risk level, and user context
     */
    public function rankActions(array $actions): array
    {
        usort($actions, function ($a, $b) {
            // Higher confidence = higher priority
            $confidenceDiff = $b['confidence'] <=> $a['confidence'];
            if ($confidenceDiff !== 0) {
                return $confidenceDiff;
            }

            // Lower risk = higher priority (easier for user)
            $riskOrder = ['low' => 3, 'medium' => 2, 'high' => 1];
            return ($riskOrder[$b['risk_level']] ?? 0) <=> ($riskOrder[$a['risk_level']] ?? 0);
        });

        return $actions;
    }

    /**
     * Validate action is appropriate for user
     */
    public function validateAction(
        string $action,
        string $role,
        array $userContext = []
    ): bool {
        $config = self::AVAILABLE_ACTIONS[$action] ?? null;

        if (!$config) {
            return false;
        }

        // Check role
        if (!in_array($role, $config['required_role'])) {
            return false;
        }

        // Check prerequisites
        if (!$this->checkPrerequisites($action, $config, $userContext)) {
            return false;
        }

        return true;
    }
}
