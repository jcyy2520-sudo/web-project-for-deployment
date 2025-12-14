<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SmartActionSuggestionService - Predictive Action Recommendations
 * 
 * Features:
 * - Context-aware action suggestions
 * - ML-based action ranking (using historical data)
 * - Proactive notifications
 * - Intent-based action mapping
 * - Role-specific action filtering
 */
class SmartActionSuggestionService
{
    // Action definitions with metadata
    private const ACTIONS = [
        'book_appointment' => [
            'label' => 'Book Appointment',
            'description' => 'Schedule a new appointment',
            'icon' => 'calendar-plus',
            'roles' => ['client', 'guest'],
            'requires_auth' => false,
            'url' => '/book',
            'priority_weight' => 10,
        ],
        'view_appointments' => [
            'label' => 'View My Appointments',
            'description' => 'See your scheduled appointments',
            'icon' => 'calendar',
            'roles' => ['client'],
            'requires_auth' => true,
            'url' => '/dashboard/appointments',
            'priority_weight' => 8,
        ],
        'check_payment_status' => [
            'label' => 'Check Payment Status',
            'description' => 'View pending and completed payments',
            'icon' => 'credit-card',
            'roles' => ['client'],
            'requires_auth' => true,
            'url' => '/dashboard/payments',
            'priority_weight' => 7,
        ],
        'request_refund' => [
            'label' => 'Request Refund',
            'description' => 'Submit a refund request',
            'icon' => 'arrow-left',
            'roles' => ['client'],
            'requires_auth' => true,
            'url' => '/dashboard/refunds/request',
            'priority_weight' => 5,
        ],
        'view_services' => [
            'label' => 'Browse Services',
            'description' => 'See all available services',
            'icon' => 'list',
            'roles' => ['client', 'guest'],
            'requires_auth' => false,
            'url' => '/services',
            'priority_weight' => 9,
        ],
        'contact_support' => [
            'label' => 'Contact Support',
            'description' => 'Get help from our team',
            'icon' => 'help-circle',
            'roles' => ['client', 'guest'],
            'requires_auth' => false,
            'url' => '/contact',
            'priority_weight' => 4,
        ],
        // Admin/Cashier actions
        'approve_appointments' => [
            'label' => 'Review Pending Appointments',
            'description' => 'Approve or decline pending bookings',
            'icon' => 'check-circle',
            'roles' => ['admin', 'cashier'],
            'requires_auth' => true,
            'url' => '/admin/appointments/pending',
            'priority_weight' => 10,
        ],
        'process_payments' => [
            'label' => 'Process Payments',
            'description' => 'Handle pending payments',
            'icon' => 'dollar-sign',
            'roles' => ['cashier', 'admin'],
            'requires_auth' => true,
            'url' => '/cashier/payments',
            'priority_weight' => 9,
        ],
        'process_refunds' => [
            'label' => 'Process Refunds',
            'description' => 'Review and process refund requests',
            'icon' => 'refresh-cw',
            'roles' => ['cashier', 'admin'],
            'requires_auth' => true,
            'url' => '/cashier/refunds',
            'priority_weight' => 8,
        ],
        'view_shift_report' => [
            'label' => 'Shift Report',
            'description' => 'View today\'s transactions and summary',
            'icon' => 'file-text',
            'roles' => ['cashier'],
            'requires_auth' => true,
            'url' => '/cashier/shift-report',
            'priority_weight' => 7,
        ],
        'view_analytics' => [
            'label' => 'View Analytics',
            'description' => 'System-wide statistics and reports',
            'icon' => 'bar-chart',
            'roles' => ['admin'],
            'requires_auth' => true,
            'url' => '/admin/analytics',
            'priority_weight' => 6,
        ],
    ];

    // Intent to action mapping
    private const INTENT_ACTION_MAP = [
        'book_appointment' => ['book_appointment', 'view_services'],
        'view_appointments' => ['view_appointments', 'book_appointment'],
        'check_appointment_status' => ['view_appointments'],
        'cancel_appointment' => ['view_appointments', 'request_refund'],
        'reschedule_appointment' => ['view_appointments'],
        'view_payments' => ['check_payment_status'],
        'check_payment_status' => ['check_payment_status', 'view_appointments'],
        'process_payment' => ['process_payments'],
        'request_refund' => ['request_refund', 'check_payment_status'],
        'check_refund_status' => ['request_refund', 'check_payment_status'],
        'view_services' => ['view_services', 'book_appointment'],
        'service_details' => ['view_services', 'book_appointment'],
        'general_question' => ['view_services', 'contact_support'],
        'help' => ['contact_support', 'view_services'],
        'greeting' => ['view_services', 'book_appointment'],
        // Admin/Cashier intents
        'view_pending_appointments' => ['approve_appointments'],
        'approve_appointment' => ['approve_appointments'],
        'decline_appointment' => ['approve_appointments'],
        'view_pending_payments' => ['process_payments'],
        'view_pending_refunds' => ['process_refunds'],
        'approve_refund' => ['process_refunds'],
        'shift_report' => ['view_shift_report'],
        'system_health' => ['view_analytics'],
    ];

    /**
     * Get smart action suggestions based on context
     */
    public function getSuggestions(
        ?int $userId,
        string $role,
        ?string $intent = null,
        array $context = []
    ): array {
        $suggestions = [];

        // Get role-appropriate base actions
        $roleActions = $this->getActionsForRole($role);

        // If we have an intent, prioritize related actions
        if ($intent && isset(self::INTENT_ACTION_MAP[$intent])) {
            $intentActions = self::INTENT_ACTION_MAP[$intent];
            foreach ($intentActions as $actionKey) {
                if (isset(self::ACTIONS[$actionKey]) && in_array($role, self::ACTIONS[$actionKey]['roles'])) {
                    $suggestions[] = $this->formatAction($actionKey, self::ACTIONS[$actionKey], 100);
                }
            }
        }

        // Add proactive suggestions based on user state
        if ($userId) {
            $proactiveSuggestions = $this->getProactiveSuggestions($userId, $role);
            $suggestions = array_merge($suggestions, $proactiveSuggestions);
        }

        // Add default role actions if we don't have enough
        if (count($suggestions) < 3) {
            foreach ($roleActions as $actionKey => $action) {
                $alreadyAdded = collect($suggestions)->pluck('action')->contains($actionKey);
                if (!$alreadyAdded) {
                    $suggestions[] = $this->formatAction($actionKey, $action, $action['priority_weight']);
                }
                if (count($suggestions) >= 4) break;
            }
        }

        // Sort by score and deduplicate
        $suggestions = collect($suggestions)
            ->unique('action')
            ->sortByDesc('score')
            ->values()
            ->take(4)
            ->toArray();

        return $suggestions;
    }

    /**
     * Get proactive suggestions based on user's current state
     */
    private function getProactiveSuggestions(int $userId, string $role): array
    {
        $suggestions = [];
        $cacheKey = "proactive_suggestions_{$userId}";

        // Cache for 5 minutes to avoid repeated queries
        return Cache::remember($cacheKey, 300, function() use ($userId, $role, &$suggestions) {
            
            if ($role === 'client') {
                // Check for pending appointments
                $pendingCount = Appointment::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->count();

                if ($pendingCount > 0) {
                    $suggestions[] = [
                        'action' => 'view_appointments',
                        'label' => "You have {$pendingCount} pending appointment(s)",
                        'description' => 'Check status of your bookings',
                        'icon' => 'clock',
                        'url' => '/dashboard/appointments',
                        'score' => 95,
                        'highlight' => true,
                    ];
                }

                // Check for upcoming appointments today
                $todayAppointments = Appointment::where('user_id', $userId)
                    ->where('status', 'approved')
                    ->whereDate('appointment_date', today())
                    ->count();

                if ($todayAppointments > 0) {
                    $suggestions[] = [
                        'action' => 'view_appointments',
                        'label' => "You have {$todayAppointments} appointment(s) today!",
                        'description' => 'View your schedule',
                        'icon' => 'alert-circle',
                        'url' => '/dashboard/appointments',
                        'score' => 100,
                        'urgent' => true,
                    ];
                }

                // Check for unpaid appointments
                $unpaidCount = Appointment::where('user_id', $userId)
                    ->where('status', 'approved')
                    ->where('payment_status', 'pending')
                    ->count();

                if ($unpaidCount > 0) {
                    $suggestions[] = [
                        'action' => 'check_payment_status',
                        'label' => "{$unpaidCount} appointment(s) awaiting payment",
                        'description' => 'Complete your payment',
                        'icon' => 'credit-card',
                        'url' => '/dashboard/payments',
                        'score' => 90,
                        'highlight' => true,
                    ];
                }

                // Check for pending refunds
                $pendingRefunds = Refund::whereHas('appointment', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->where('status', 'pending')->count();

                if ($pendingRefunds > 0) {
                    $suggestions[] = [
                        'action' => 'check_refund_status',
                        'label' => "Refund request in progress",
                        'description' => 'Check your refund status',
                        'icon' => 'refresh-cw',
                        'url' => '/dashboard/refunds',
                        'score' => 85,
                    ];
                }
            }

            if (in_array($role, ['admin', 'cashier'])) {
                // Check for items needing attention
                $pendingAppts = Appointment::where('status', 'pending')->count();
                $pendingPayments = Payment::where('status', 'pending')->count();
                $pendingRefunds = Refund::where('status', 'pending')->count();

                if ($pendingAppts > 0) {
                    $suggestions[] = [
                        'action' => 'approve_appointments',
                        'label' => "{$pendingAppts} appointment(s) need review",
                        'description' => 'Approve or decline bookings',
                        'icon' => 'check-circle',
                        'url' => '/admin/appointments/pending',
                        'score' => 100,
                        'urgent' => $pendingAppts > 5,
                    ];
                }

                if ($pendingPayments > 0 && $role === 'cashier') {
                    $suggestions[] = [
                        'action' => 'process_payments',
                        'label' => "{$pendingPayments} payment(s) to process",
                        'description' => 'Handle pending payments',
                        'icon' => 'dollar-sign',
                        'url' => '/cashier/payments',
                        'score' => 95,
                    ];
                }

                if ($pendingRefunds > 0) {
                    $suggestions[] = [
                        'action' => 'process_refunds',
                        'label' => "{$pendingRefunds} refund request(s)",
                        'description' => 'Review refund requests',
                        'icon' => 'refresh-cw',
                        'url' => '/cashier/refunds',
                        'score' => 90,
                    ];
                }
            }

            return $suggestions;
        });
    }

    /**
     * Get actions appropriate for a role
     */
    private function getActionsForRole(string $role): array
    {
        return array_filter(self::ACTIONS, function($action) use ($role) {
            return in_array($role, $action['roles']);
        });
    }

    /**
     * Format action for response
     */
    private function formatAction(string $key, array $action, int $score): array
    {
        return [
            'action' => $key,
            'label' => $action['label'],
            'description' => $action['description'],
            'icon' => $action['icon'],
            'url' => $action['url'],
            'requires_auth' => $action['requires_auth'],
            'score' => $score,
        ];
    }

    /**
     * Get quick action buttons based on detected intent
     */
    public function getQuickActions(
        ?int $userId,
        string $role,
        string $intent,
        array $entities = []
    ): array {
        $quickActions = [];

        // Intent-specific quick actions
        switch ($intent) {
            case 'book_appointment':
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'Book Now',
                    'url' => '/book',
                    'style' => 'primary',
                ];
                $quickActions[] = [
                    'action' => 'show',
                    'label' => 'View Services',
                    'intent' => 'view_services',
                    'style' => 'secondary',
                ];
                break;

            case 'view_appointments':
            case 'check_appointment_status':
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'My Appointments',
                    'url' => '/dashboard/appointments',
                    'style' => 'primary',
                ];
                if (isset($entities['appointment_id'])) {
                    $quickActions[] = [
                        'action' => 'execute',
                        'label' => 'Check Status',
                        'intent' => 'check_appointment_status',
                        'params' => ['appointment_id' => $entities['appointment_id']],
                        'style' => 'secondary',
                    ];
                }
                break;

            case 'cancel_appointment':
                if (isset($entities['appointment_id'])) {
                    $quickActions[] = [
                        'action' => 'confirm',
                        'label' => 'Cancel Appointment',
                        'intent' => 'cancel_appointment',
                        'params' => ['appointment_id' => $entities['appointment_id']],
                        'style' => 'danger',
                        'requires_confirmation' => true,
                    ];
                }
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'View Appointments',
                    'url' => '/dashboard/appointments',
                    'style' => 'secondary',
                ];
                break;

            case 'request_refund':
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'Request Refund',
                    'url' => '/dashboard/refunds/request',
                    'style' => 'primary',
                ];
                $quickActions[] = [
                    'action' => 'show',
                    'label' => 'Refund Policy',
                    'intent' => 'refund_policy',
                    'style' => 'secondary',
                ];
                break;

            case 'view_services':
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'View All Services',
                    'url' => '/services',
                    'style' => 'primary',
                ];
                $quickActions[] = [
                    'action' => 'navigate',
                    'label' => 'Book Appointment',
                    'url' => '/book',
                    'style' => 'secondary',
                ];
                break;

            // Admin/Cashier actions
            case 'view_pending_appointments':
            case 'approve_appointment':
                if ($role === 'admin' || $role === 'cashier') {
                    $quickActions[] = [
                        'action' => 'navigate',
                        'label' => 'Review Pending',
                        'url' => '/admin/appointments/pending',
                        'style' => 'primary',
                    ];
                }
                break;

            case 'shift_report':
                if ($role === 'cashier') {
                    $quickActions[] = [
                        'action' => 'navigate',
                        'label' => 'View Shift Report',
                        'url' => '/cashier/shift-report',
                        'style' => 'primary',
                    ];
                }
                break;
        }

        // Add a default help action if we have room
        if (count($quickActions) < 3) {
            $quickActions[] = [
                'action' => 'show',
                'label' => 'More Help',
                'intent' => 'help',
                'style' => 'link',
            ];
        }

        return array_slice($quickActions, 0, 3);
    }

    /**
     * Rank actions based on user's historical behavior
     */
    public function rankActionsByHistory(int $userId, array $actions): array
    {
        try {
            // Get user's action history from analytics
            $actionCounts = DB::table('chatbot_analytics')
                ->where('user_id', $userId)
                ->whereNotNull('detected_intent')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('detected_intent')
                ->selectRaw('detected_intent, count(*) as count')
                ->pluck('count', 'detected_intent');

            // Boost scores for frequently used actions
            foreach ($actions as &$action) {
                $relatedIntents = array_keys(array_filter(
                    self::INTENT_ACTION_MAP,
                    fn($mapped) => in_array($action['action'], $mapped)
                ));

                $boost = 0;
                foreach ($relatedIntents as $intent) {
                    $boost += ($actionCounts[$intent] ?? 0) * 2;
                }

                $action['score'] = ($action['score'] ?? 50) + min($boost, 30);
            }

            // Re-sort by score
            usort($actions, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        } catch (\Exception $e) {
            Log::debug('Failed to rank actions by history: ' . $e->getMessage());
        }

        return $actions;
    }

    /**
     * Get suggested follow-up questions based on context
     */
    public function getSuggestedQuestions(string $role, ?string $lastIntent = null): array
    {
        $suggestions = [];

        // Role-based default suggestions
        $roleQuestions = [
            'guest' => [
                "What services do you offer?",
                "How do I book an appointment?",
                "What are your business hours?",
                "How do I register?",
            ],
            'client' => [
                "Show my appointments",
                "What's my payment status?",
                "How do I reschedule?",
                "Can I request a refund?",
            ],
            'admin' => [
                "Show pending appointments",
                "System status report",
                "Today's analytics",
                "Pending approvals",
            ],
            'cashier' => [
                "Show shift report",
                "Pending payments",
                "Process refunds",
                "Today's transactions",
            ],
        ];

        // Intent-based follow-up suggestions
        $intentFollowUps = [
            'book_appointment' => [
                "What times are available?",
                "How much does it cost?",
                "Can I book for someone else?",
            ],
            'view_appointments' => [
                "Can I reschedule?",
                "How do I cancel?",
                "Payment options?",
            ],
            'check_payment_status' => [
                "How do I pay?",
                "Can I get a receipt?",
                "Payment methods?",
            ],
            'request_refund' => [
                "What's the refund policy?",
                "How long does it take?",
                "Check refund status",
            ],
        ];

        // Add intent-specific suggestions if available
        if ($lastIntent && isset($intentFollowUps[$lastIntent])) {
            $suggestions = array_merge($suggestions, $intentFollowUps[$lastIntent]);
        }

        // Add role-based suggestions
        if (isset($roleQuestions[$role])) {
            $suggestions = array_merge($suggestions, $roleQuestions[$role]);
        }

        // Deduplicate and limit
        return array_slice(array_unique($suggestions), 0, 4);
    }
}
