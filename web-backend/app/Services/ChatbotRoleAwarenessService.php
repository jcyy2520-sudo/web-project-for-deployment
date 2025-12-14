<?php

namespace App\Services;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ChatbotRoleAwarenessService
 * 
 * Detects and manages user roles with detailed capabilities and permissions.
 * Supports: User (Client), Admin, Cashier, Staff, and Guest roles
 * 
 * Features:
 * - Accurate role detection from authentication
 * - Role capability management
 * - Permission checking for actions
 * - Role-specific context building
 * - Intent-to-permission mapping
 * - Dynamic capability checking
 * - Role-based response customization
 */
class ChatbotRoleAwarenessService
{
    /**
     * Role capabilities mapping
     * Defines what each role can do
     */
    private array $roleCapabilities = [
        'client' => [
            'display_name' => 'User',
            'view_own_appointments' => true,
            'view_own_payments' => true,
            'view_own_refunds' => true,
            'request_refund' => true,
            'cancel_own_appointment' => true,
            'view_own_profile' => true,
            'edit_own_profile' => true,
            'view_services' => true,
            'view_schedules' => true,
            'check_appointment_status' => true,
            'view_payment_history' => true,
            'view_notifications' => true,
            'view_system_status' => true,
            'book_appointment' => true,
            'get_help' => true,
            'search_own_data' => true,
        ],
        'admin' => [
            'display_name' => 'Administrator',
            'view_all_appointments' => true,
            'manage_appointments' => true,
            'approve_decline_appointments' => true,
            'complete_appointments' => true,
            'manage_users' => true,
            'view_user_details' => true,
            'view_system_analytics' => true,
            'manage_system_settings' => true,
            'view_all_payments' => true,
            'view_all_refunds' => true,
            'approve_refunds' => true,
            'reject_refunds' => true,
            'process_refunds' => true,
            'view_staff_performance' => true,
            'manage_services' => true,
            'view_action_logs' => true,
            'generate_reports' => true,
            'view_all_messages' => true,
            'manage_notifications' => true,
            'access_admin_dashboard' => true,
            'view_system_health' => true,
            'search_all_data' => true,
            'get_help' => true,
            // Admin inherits client capabilities
            'view_own_appointments' => true,
            'view_own_payments' => true,
            'view_own_refunds' => true,
            'request_refund' => true,
            'cancel_own_appointment' => true,
            'view_own_profile' => true,
            'edit_own_profile' => true,
            'view_services' => true,
            'view_schedules' => true,
            'book_appointment' => true,
            // Admin can also do cashier tasks
            'view_pending_payments' => true,
            'process_payments' => true,
            'verify_receipts' => true,
            'generate_shift_reports' => true,
            'view_daily_summary' => true,
        ],
        'cashier' => [
            'display_name' => 'Cashier',
            'view_pending_payments' => true,
            'process_payments' => true,
            'verify_receipts' => true,
            'view_pending_refunds' => true,
            'complete_refunds' => true,
            'view_transactions' => true,
            'generate_shift_reports' => true,
            'view_appointment_details_for_payment' => true,
            'view_payment_methods' => true,
            'record_discount' => true,
            'view_refund_requests' => true,
            'view_system_status' => true,
            'view_daily_summary' => true,
            'search_transactions' => true,
            'get_help' => true,
        ],
        'staff' => [
            'display_name' => 'Staff',
            'view_all_appointments' => true,
            'approve_decline_appointments' => true,
            'complete_appointments' => true,
            'view_system_analytics' => true,
            'view_services' => true,
            'view_schedules' => true,
            'get_help' => true,
            'search_appointments' => true,
        ],
        'guest' => [
            'display_name' => 'Guest',
            'view_services' => true,
            'view_schedules' => true,
            'register' => true,
            'view_public_info' => true,
            'view_system_status' => true,
            'get_help' => true,
        ],
    ];
    
    /**
     * Intent to capability mapping
     * Maps chatbot intents to required capabilities
     */
    private array $intentCapabilityMap = [
        // Client intents
        'check_appointment' => 'view_own_appointments',
        'appointment_status' => 'view_own_appointments',
        'view_appointments' => 'view_own_appointments',
        'my_appointments' => 'view_own_appointments',
        'cancel_appointment' => 'cancel_own_appointment',
        'check_payment' => 'view_own_payments',
        'payment_status' => 'view_own_payments',
        'request_refund' => 'request_refund',
        'check_refund' => 'view_own_refunds',
        'refund_status' => 'view_own_refunds',
        'view_profile' => 'view_own_profile',
        'edit_profile' => 'edit_own_profile',
        'book_appointment' => 'book_appointment',
        'view_services' => 'view_services',
        'available_services' => 'view_services',
        
        // Admin intents
        'approve_appointment' => 'approve_decline_appointments',
        'decline_appointment' => 'approve_decline_appointments',
        'reject_appointment' => 'approve_decline_appointments',
        'complete_appointment' => 'complete_appointments',
        'view_all_appointments' => 'view_all_appointments',
        'manage_appointments' => 'manage_appointments',
        'view_analytics' => 'view_system_analytics',
        'analytics' => 'view_system_analytics',
        'system_health' => 'view_system_health',
        'manage_users' => 'manage_users',
        'view_user' => 'view_user_details',
        'approve_refund' => 'approve_refunds',
        'reject_refund' => 'reject_refunds',
        
        // Cashier intents
        'process_payment' => 'process_payments',
        'pending_payments' => 'view_pending_payments',
        'pending_refunds' => 'view_pending_refunds',
        'complete_refund' => 'complete_refunds',
        'process_refund' => 'complete_refunds',
        'shift_report' => 'generate_shift_reports',
        'daily_summary' => 'view_daily_summary',
        'verify_receipt' => 'verify_receipts',
        
        // General intents
        'help' => 'get_help',
        'search' => 'search_own_data', // Will be upgraded for admin
    ];

    /**
     * Get the current user's role and detailed information
     * 
     * @param int|null $userId
     * @return array User role details with capabilities
     */
    public function detectUserRole(?int $userId = null): array
    {
        try {
            // Use auth()->id() if no userId provided
            if (!$userId && auth()->check()) {
                $userId = auth()->id();
            }

            // If no user ID, treat as guest
            if (!$userId) {
                return $this->buildGuestRoleInfo();
            }
            
            // Cache role info for 5 minutes
            $cacheKey = "chatbot_role_info_{$userId}";
            
            return Cache::remember($cacheKey, 300, function() use ($userId) {
                // Fetch user from database
                $user = User::find($userId);

                if (!$user) {
                    Log::warning('User not found for role detection', ['user_id' => $userId]);
                    return $this->buildGuestRoleInfo();
                }

                // Determine primary role
                $primaryRole = $this->determinePrimaryRole($user);

                // Build comprehensive role info
                return [
                    'user_id' => $user->id,
                    'username' => $user->username ?? $user->name,
                    'email' => $user->email,
                    'first_name' => $user->first_name ?? explode(' ', $user->name)[0] ?? 'User',
                    'last_name' => $user->last_name ?? (explode(' ', $user->name)[1] ?? ''),
                    'name' => $user->name,
                    'primary_role' => $primaryRole,
                    'display_name' => $this->roleCapabilities[$primaryRole]['display_name'],
                    'is_authenticated' => true,
                    'is_active' => $user->is_active ?? true,
                    'capabilities' => $this->getCapabilities($primaryRole),
                    'permissions_count' => count($this->getCapabilities($primaryRole)),
                    'role_description' => $this->getRoleDescription($primaryRole),
                    'detected_at' => now()->toDateTimeString(),
                    'context_hints' => $this->buildRoleContextHints($primaryRole, $user),
                    'pending_items' => $this->getPendingItemsForRole($primaryRole, $userId),
                    'quick_actions' => $this->getQuickActionsForRole($primaryRole),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error detecting user role', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->buildGuestRoleInfo();
        }
    }
    
    /**
     * Clear cached role info (call when user role changes)
     */
    public function clearRoleCache(int $userId): void
    {
        Cache::forget("chatbot_role_info_{$userId}");
    }

    /**
     * Determine the primary role of a user
     * 
     * @param User $user
     * @return string Primary role (client, admin, cashier, staff)
     */
    private function determinePrimaryRole(User $user): string
    {
        // Check if user has specific roles (priority order)
        if ($user->hasRole('admin')) {
            return 'admin';
        }

        if ($user->hasRole('cashier')) {
            return 'cashier';
        }
        
        if ($user->hasRole('staff')) {
            return 'staff';
        }

        // Default to client
        return 'client';
    }
    
    /**
     * Get pending items count for role context
     */
    private function getPendingItemsForRole(string $role, int $userId): array
    {
        try {
            switch ($role) {
                case 'admin':
                    return [
                        'pending_appointments' => Appointment::where('status', 'pending')->count(),
                        'pending_refunds' => Refund::where('status', 'pending')->count(),
                    ];
                case 'cashier':
                    return [
                        'pending_payments' => Appointment::where('status', 'approved')
                            ->whereDoesntHave('payments', fn($q) => $q->where('payment_status', 'paid'))
                            ->count(),
                        'approved_refunds' => Refund::where('status', 'approved')->count(),
                    ];
                case 'client':
                    return [
                        'upcoming_appointments' => Appointment::where('user_id', $userId)
                            ->whereIn('status', ['pending', 'approved'])
                            ->where('appointment_date', '>=', now()->toDateString())
                            ->count(),
                        'pending_refunds' => Refund::whereHas('payment.appointment', fn($q) => $q->where('user_id', $userId))
                            ->whereIn('status', ['pending', 'approved'])
                            ->count(),
                    ];
                default:
                    return [];
            }
        } catch (\Exception $e) {
            Log::warning('Error getting pending items', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get quick actions available for a role
     */
    private function getQuickActionsForRole(string $role): array
    {
        return match($role) {
            'admin' => [
                ['action' => 'view_pending', 'label' => 'View Pending Appointments', 'command' => 'show pending appointments'],
                ['action' => 'analytics', 'label' => 'View Analytics', 'command' => 'show analytics'],
                ['action' => 'system_health', 'label' => 'System Health', 'command' => 'system health'],
                ['action' => 'pending_refunds', 'label' => 'Pending Refunds', 'command' => 'show pending refunds'],
            ],
            'cashier' => [
                ['action' => 'pending_payments', 'label' => 'Pending Payments', 'command' => 'show pending payments'],
                ['action' => 'shift_report', 'label' => 'Shift Report', 'command' => 'shift report'],
                ['action' => 'daily_summary', 'label' => 'Daily Summary', 'command' => 'daily summary'],
                ['action' => 'pending_refunds', 'label' => 'Process Refunds', 'command' => 'show approved refunds'],
            ],
            'staff' => [
                ['action' => 'view_pending', 'label' => 'Pending Appointments', 'command' => 'show pending appointments'],
                ['action' => 'today_schedule', 'label' => "Today's Schedule", 'command' => 'show today appointments'],
            ],
            'client' => [
                ['action' => 'my_appointments', 'label' => 'My Appointments', 'command' => 'show my appointments'],
                ['action' => 'book', 'label' => 'Book Appointment', 'command' => 'I want to book'],
                ['action' => 'services', 'label' => 'View Services', 'command' => 'show services'],
            ],
            default => [
                ['action' => 'services', 'label' => 'View Services', 'command' => 'show services'],
                ['action' => 'register', 'label' => 'Register', 'command' => 'how to register'],
            ],
        };
    }

    /**
     * Get capabilities for a specific role
     * 
     * @param string $role
     * @return array List of capabilities
     */
    public function getCapabilities(string $role): array
    {
        return $this->roleCapabilities[strtolower($role)] ?? $this->roleCapabilities['guest'];
    }
    
    /**
     * Check if an intent is allowed for a user's role
     * 
     * @param string $intent The detected intent
     * @param int|null $userId The user's ID
     * @return array ['allowed' => bool, 'reason' => string|null, 'required_role' => string|null]
     */
    public function canPerformIntent(?int $userId, string $intent): array
    {
        $roleInfo = $this->detectUserRole($userId);
        $role = $roleInfo['primary_role'];
        $capabilities = $roleInfo['capabilities'];
        
        // Get required capability for this intent
        $requiredCapability = $this->intentCapabilityMap[$intent] ?? null;
        
        if (!$requiredCapability) {
            // Intent not mapped, allow by default for known roles
            return [
                'allowed' => true,
                'reason' => null,
                'required_capability' => null,
            ];
        }
        
        // Check if role has this capability
        if (isset($capabilities[$requiredCapability]) && $capabilities[$requiredCapability] === true) {
            return [
                'allowed' => true,
                'reason' => null,
                'required_capability' => $requiredCapability,
            ];
        }
        
        // Find which role(s) have this capability
        $rolesWithCapability = [];
        foreach ($this->roleCapabilities as $roleName => $caps) {
            if (isset($caps[$requiredCapability]) && $caps[$requiredCapability] === true) {
                $rolesWithCapability[] = $caps['display_name'];
            }
        }
        
        return [
            'allowed' => false,
            'reason' => "This action requires the '{$requiredCapability}' permission.",
            'required_capability' => $requiredCapability,
            'roles_with_access' => $rolesWithCapability,
            'current_role' => $roleInfo['display_name'],
        ];
    }

    /**
     * Check if user can perform an action based on their role
     * 
     * @param int|null $userId
     * @param string $action
     * @return bool True if user has permission
     */
    public function canPerformAction(?int $userId, string $action): bool
    {
        try {
            $roleInfo = $this->detectUserRole($userId);
            $capabilities = $roleInfo['capabilities'];

            // Check if action is in capabilities (case-insensitive)
            $action = strtolower(str_replace('-', '_', $action));
            
            return isset($capabilities[$action]) && $capabilities[$action] === true;
        } catch (\Exception $e) {
            Log::warning('Error checking action permission', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get role-specific greeting message
     */
    public function getRoleGreeting(?int $userId): string
    {
        $roleInfo = $this->detectUserRole($userId);
        $name = $roleInfo['first_name'] ?? 'there';
        $role = $roleInfo['primary_role'];
        $pending = $roleInfo['pending_items'] ?? [];
        
        $greeting = match($role) {
            'admin' => "Hello {$name}! As an Administrator, I can help you manage appointments, users, refunds, and view system analytics.",
            'cashier' => "Hi {$name}! Ready to help with payments, refunds, and financial reports.",
            'staff' => "Hello {$name}! I can help you manage appointments and view schedules.",
            'client' => "Hi {$name}! I can help you with your appointments, payments, and answer your questions.",
            default => "Hello! I'm here to help you learn about our services. Would you like to register?",
        };
        
        // Add pending items alert
        if (!empty($pending)) {
            $alerts = [];
            if (($pending['pending_appointments'] ?? 0) > 0) {
                $alerts[] = "{$pending['pending_appointments']} pending appointment(s)";
            }
            if (($pending['pending_refunds'] ?? 0) > 0) {
                $alerts[] = "{$pending['pending_refunds']} pending refund(s)";
            }
            if (($pending['pending_payments'] ?? 0) > 0) {
                $alerts[] = "{$pending['pending_payments']} payment(s) to process";
            }
            if (($pending['approved_refunds'] ?? 0) > 0) {
                $alerts[] = "{$pending['approved_refunds']} refund(s) to complete";
            }
            if (($pending['upcoming_appointments'] ?? 0) > 0) {
                $alerts[] = "{$pending['upcoming_appointments']} upcoming appointment(s)";
            }
            
            if (!empty($alerts)) {
                $greeting .= "\n\n📌 Quick update: You have " . implode(', ', $alerts) . ".";
            }
        }
        
        return $greeting;
    }
    
    /**
     * Get suggested commands for a role
     */
    public function getSuggestedCommands(?int $userId): array
    {
        $roleInfo = $this->detectUserRole($userId);
        return $roleInfo['quick_actions'] ?? [];
    }

    /**
     * Check if user can view specific resource based on role
     * 
     * @param int|null $userId
     * @param string $resourceType (appointments, payments, refunds, users, etc)
     * @param string $scope (own, all)
     * @return bool
     */
    public function canViewResource(?int $userId, string $resourceType, string $scope = 'own'): bool
    {
        try {
            $roleInfo = $this->detectUserRole($userId);
            $role = $roleInfo['primary_role'];

            $viewPermissions = [
                'appointments' => [
                    'client' => 'view_own_appointments',
                    'admin' => 'view_all_appointments',
                    'cashier' => 'view_appointment_details_for_payment',
                ],
                'payments' => [
                    'client' => 'view_own_payments',
                    'admin' => 'view_all_payments',
                    'cashier' => 'view_pending_payments',
                ],
                'refunds' => [
                    'client' => 'view_own_refunds',
                    'admin' => 'view_all_refunds',
                    'cashier' => 'view_pending_refunds',
                ],
                'users' => [
                    'admin' => 'manage_users',
                    'cashier' => null,
                    'client' => null,
                ],
                'analytics' => [
                    'admin' => 'view_system_analytics',
                    'cashier' => 'generate_shift_reports',
                    'client' => null,
                ],
                'settings' => [
                    'admin' => 'manage_system_settings',
                    'cashier' => null,
                    'client' => null,
                ],
            ];

            $resourceType = strtolower($resourceType);
            
            if (!isset($viewPermissions[$resourceType])) {
                return false;
            }

            $requiredCapability = $viewPermissions[$resourceType][$role] ?? null;

            if (!$requiredCapability) {
                return false;
            }

            return $this->canPerformAction($userId, $requiredCapability);
        } catch (\Exception $e) {
            Log::warning('Error checking resource view permission', [
                'user_id' => $userId,
                'resource' => $resourceType,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build a guest role information array
     * 
     * @return array Guest role info
     */
    private function buildGuestRoleInfo(): array
    {
        return [
            'user_id' => null,
            'username' => 'Guest',
            'email' => null,
            'first_name' => 'Guest',
            'last_name' => 'User',
            'name' => 'Guest User',
            'primary_role' => 'guest',
            'display_name' => 'Guest',
            'is_authenticated' => false,
            'is_active' => true,
            'capabilities' => $this->getCapabilities('guest'),
            'permissions_count' => count($this->getCapabilities('guest')),
            'role_description' => 'Guest user with limited access to public information.',
            'detected_at' => now()->toDateTimeString(),
            'context_hints' => [
                'can_browse_services' => true,
                'can_view_schedule' => true,
                'needs_registration' => true,
                'limited_features' => true,
                'focus_area' => 'public_information',
            ],
            'pending_items' => [],
            'quick_actions' => $this->getQuickActionsForRole('guest'),
        ];
    }

    /**
     * Get a human-readable description of a role
     * 
     * @param string $role
     * @return string Role description
     */
    private function getRoleDescription(string $role): string
    {
        $descriptions = [
            'client' => 'Regular user who can book appointments, view their status, manage payments and refunds.',
            'admin' => 'System administrator with full control over appointments, users, payments, settings, and analytics.',
            'cashier' => 'Financial staff member who handles payment processing, receipts, refunds, and transaction reports.',
            'staff' => 'Staff member who can manage appointments and view schedules.',
            'guest' => 'Unregistered visitor with access to public information, services, and schedules only.',
        ];

        return $descriptions[strtolower($role)] ?? 'Unknown role';
    }

    /**
     * Build context hints for the chatbot based on user's role
     * 
     * @param string $role
     * @param User|null $user
     * @return array Context hints for tailored responses
     */
    private function buildRoleContextHints(string $role, ?User $user = null): array
    {
        $baseHints = [];

        switch (strtolower($role)) {
            case 'admin':
                $baseHints = [
                    'focus_area' => 'system_operations',
                    'show_analytics' => true,
                    'show_pending_items' => true,
                    'show_system_health' => true,
                    'provide_insights' => true,
                    'ask_about_schedule' => false,
                ];
                break;

            case 'cashier':
                $baseHints = [
                    'focus_area' => 'financial_operations',
                    'show_pending_payments' => true,
                    'show_refund_requests' => true,
                    'show_shift_data' => true,
                    'highlight_urgent_payments' => true,
                    'ask_about_user_profile' => false,
                ];
                break;

            case 'client':
                $baseHints = [
                    'focus_area' => 'personal_appointments',
                    'show_own_appointments' => true,
                    'show_payment_status' => true,
                    'show_refund_options' => true,
                    'personalize_responses' => true,
                    'suggest_next_steps' => true,
                ];
                break;

            case 'guest':
                $baseHints = [
                    'focus_area' => 'public_information',
                    'show_services' => true,
                    'show_schedule' => true,
                    'show_registration_prompt' => true,
                    'encourage_signup' => true,
                ];
                break;
        }

        // Add user-specific hints
        if ($user) {
            $baseHints['user_name'] = $user->first_name ?? ($user->name ? explode(' ', $user->name)[0] : 'User');
            $baseHints['is_new_user'] = $user->created_at ? $user->created_at->diffInDays(now()) < 7 : false;
        }

        return $baseHints;
    }

    /**
     * Get all available roles
     * 
     * @return array
     */
    public function getAvailableRoles(): array
    {
        return array_keys($this->roleCapabilities);
    }

    /**
     * Get summary of role information for logging/debugging
     * 
     * @param int|null $userId
     * @return array Summary info
     */
    public function getSummary(?int $userId = null): array
    {
        $roleInfo = $this->detectUserRole($userId);

        return [
            'user_id' => $roleInfo['user_id'],
            'role' => $roleInfo['primary_role'],
            'display_name' => $roleInfo['display_name'],
            'is_authenticated' => $roleInfo['is_authenticated'],
            'total_capabilities' => $roleInfo['permissions_count'],
            'can_manage_appointments' => isset($roleInfo['capabilities']['manage_appointments']),
            'can_process_payments' => isset($roleInfo['capabilities']['process_payments']),
            'can_manage_refunds' => isset($roleInfo['capabilities']['approve_refunds']),
            'can_view_analytics' => isset($roleInfo['capabilities']['view_system_analytics']),
        ];
    }
    
    /**
     * Get role-appropriate error message when action is not allowed
     */
    public function getPermissionDeniedMessage(string $intent, ?int $userId): string
    {
        $roleInfo = $this->detectUserRole($userId);
        $currentRole = $roleInfo['display_name'];
        
        $intentCheck = $this->canPerformIntent($userId, $intent);
        
        if ($intentCheck['allowed']) {
            return ''; // No error - action is allowed
        }
        
        $rolesWithAccess = $intentCheck['roles_with_access'] ?? [];
        
        if (empty($rolesWithAccess)) {
            return "Sorry, this feature is not available.";
        }
        
        if ($roleInfo['primary_role'] === 'guest') {
            return "Please log in to access this feature. This is available for registered users.";
        }
        
        $rolesList = implode(' or ', $rolesWithAccess);
        return "This action requires {$rolesList} access. You are currently logged in as {$currentRole}.";
    }
    
    /**
     * Check if user should see admin-level information
     */
    public function hasAdminPrivileges(?int $userId): bool
    {
        $roleInfo = $this->detectUserRole($userId);
        return $roleInfo['primary_role'] === 'admin';
    }
    
    /**
     * Check if user should see financial information
     */
    public function hasFinancialAccess(?int $userId): bool
    {
        $roleInfo = $this->detectUserRole($userId);
        return in_array($roleInfo['primary_role'], ['admin', 'cashier']);
    }
    
    /**
     * Get context for building responses
     */
    public function getResponseContext(?int $userId): array
    {
        $roleInfo = $this->detectUserRole($userId);
        
        return [
            'role' => $roleInfo['primary_role'],
            'name' => $roleInfo['first_name'] ?? 'User',
            'is_authenticated' => $roleInfo['is_authenticated'],
            'tone' => $this->getResponseTone($roleInfo['primary_role']),
            'include_quick_actions' => true,
            'quick_actions' => $roleInfo['quick_actions'] ?? [],
            'pending_alerts' => $this->formatPendingAlerts($roleInfo['pending_items'] ?? []),
            'context_hints' => $roleInfo['context_hints'] ?? [],
        ];
    }
    
    /**
     * Get response tone based on role
     */
    private function getResponseTone(string $role): string
    {
        return match($role) {
            'admin' => 'professional',
            'cashier' => 'efficient',
            'staff' => 'helpful',
            'client' => 'friendly',
            default => 'welcoming',
        };
    }
    
    /**
     * Format pending items into alerts
     */
    private function formatPendingAlerts(array $pendingItems): array
    {
        $alerts = [];
        
        foreach ($pendingItems as $key => $count) {
            if ($count > 0) {
                $label = str_replace('_', ' ', $key);
                $alerts[] = [
                    'type' => $key,
                    'count' => $count,
                    'label' => ucwords($label),
                    'message' => "{$count} {$label}",
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get intent capability requirement
     */
    public function getIntentCapability(string $intent): ?string
    {
        return $this->intentCapabilityMap[$intent] ?? null;
    }
    
    /**
     * Check if role can access another user's data
     */
    public function canAccessUserData(?int $requesterId, int $targetUserId): bool
    {
        if ($requesterId === $targetUserId) {
            return true; // Users can always access their own data
        }
        
        $roleInfo = $this->detectUserRole($requesterId);
        
        // Admin can access all user data
        if ($roleInfo['primary_role'] === 'admin') {
            return true;
        }
        
        // Cashier can access payment-related data
        if ($roleInfo['primary_role'] === 'cashier') {
            return isset($roleInfo['capabilities']['view_appointment_details_for_payment']);
        }
        
        return false;
    }
}
