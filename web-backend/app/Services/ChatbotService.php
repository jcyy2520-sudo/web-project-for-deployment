<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotService - Advanced AI assistant with fuzzy understanding
 * Provides dynamic, real-time system data with intelligent message interpretation
 * Supports natural language understanding including misspellings, slang, Taglish, and broken grammar
 * 
 * ✅ Role-Aware Responses - Different handling for Client, Admin, Cashier
 * ✅ Real-Time Data Integration - Always uses current database values
 * ✅ Action-Based Capabilities - Execute system operations through chat
 * ✅ NLU with Fuzzy Matching - Handles typos, slang, Taglish
 * ✅ Security Enforcement - Role-based permission checks
 */
class ChatbotService
{
    /**
     * Action intent patterns for detecting action-based commands
     */
    private array $actionIntentPatterns = [
        'approve_appointment' => [
            'patterns' => ['approve appointment', 'approve booking', 'confirm appointment', 'accept appointment'],
            'keywords' => ['approve', 'confirm', 'accept'],
            'requires_id' => true,
            'roles' => ['admin', 'staff'],
        ],
        'decline_appointment' => [
            'patterns' => ['decline appointment', 'reject appointment', 'deny appointment'],
            'keywords' => ['decline', 'reject', 'deny'],
            'requires_id' => true,
            'roles' => ['admin', 'staff'],
        ],
        'cancel_appointment' => [
            'patterns' => ['cancel appointment', 'cancel booking', 'cancel my appointment'],
            'keywords' => ['cancel'],
            'requires_id' => true,
            'roles' => ['client', 'admin', 'staff'],
        ],
        'complete_appointment' => [
            'patterns' => ['complete appointment', 'mark complete', 'finish appointment', 'mark as done'],
            'keywords' => ['complete', 'finish', 'done'],
            'requires_id' => true,
            'roles' => ['admin', 'staff'],
        ],
        'process_payment' => [
            'patterns' => ['process payment', 'collect payment', 'mark as paid', 'payment received'],
            'keywords' => ['process payment', 'collect', 'paid'],
            'requires_id' => true,
            'roles' => ['cashier', 'admin'],
        ],
        'approve_refund' => [
            'patterns' => ['approve refund', 'accept refund', 'confirm refund'],
            'keywords' => ['approve refund', 'accept refund'],
            'requires_id' => true,
            'roles' => ['cashier', 'admin'],
        ],
        'process_refund' => [
            'patterns' => ['process refund', 'complete refund', 'issue refund'],
            'keywords' => ['process refund', 'issue refund'],
            'requires_id' => true,
            'roles' => ['cashier', 'admin'],
        ],
        'request_refund' => [
            'patterns' => ['request refund', 'want refund', 'get refund', 'ask for refund'],
            'keywords' => ['request refund', 'want refund', 'my money back'],
            'requires_id' => true,
            'roles' => ['client'],
        ],
        'view_pending_appointments' => [
            'patterns' => ['show pending', 'pending appointments', 'what needs approval', 'appointments to approve'],
            'keywords' => ['pending', 'needs approval', 'waiting'],
            'requires_id' => false,
            'roles' => ['admin', 'staff', 'cashier'],
        ],
        'view_pending_payments' => [
            'patterns' => ['pending payments', 'unpaid appointments', 'who needs to pay'],
            'keywords' => ['pending payment', 'unpaid', 'collect payment'],
            'requires_id' => false,
            'roles' => ['cashier', 'admin'],
        ],
        'view_pending_refunds' => [
            'patterns' => ['pending refunds', 'refund requests', 'refunds to process'],
            'keywords' => ['pending refund', 'refund request'],
            'requires_id' => false,
            'roles' => ['cashier', 'admin'],
        ],
        'shift_report' => [
            'patterns' => ['shift report', 'daily report', 'today report', 'my transactions'],
            'keywords' => ['shift', 'daily report', 'cash summary'],
            'requires_id' => false,
            'roles' => ['cashier'],
        ],
        'system_health' => [
            'patterns' => ['system status', 'system health', 'check system', 'any issues'],
            'keywords' => ['system', 'health', 'status check'],
            'requires_id' => false,
            'roles' => ['admin'],
        ],
    ];

    /**
     * Get system context data for the current user or guest
     * This data is used to build a more intelligent system prompt
     */
    public function getSystemContext($userId)
    {
        // Handle guest users (no userId)
        if (!$userId) {
            return [
                'user_id' => null,
                'user_role' => 'guest',
                'available_services' => $this->getAvailableServices(),
                'business_info' => $this->getBusinessInfo(),
                'availability' => $this->getAvailabilitySummary(),
                'trends' => $this->getTrends(),
                'security' => ['auth' => false],
            ];
        }

        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        $role = $user->getRoleNames()->first() ?? 'user';

        $context = [
            'user_id' => $userId,
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'user_role' => $role,
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'user_verified' => !empty($user->email_verified_at),
            'user_active' => (bool)($user->is_active ?? true),
            'available_services' => $this->getAvailableServices(),
            'business_info' => $this->getBusinessInfo(),
            'role_signals' => $this->getRoleSignals($userId),
            'appointments' => $this->getAppointmentsSummary($userId, $role),
            'availability' => $this->getAvailabilitySummary(),
            'admin_metrics' => in_array($role, ['admin', 'cashier']) ? $this->getAdminMetrics() : null,
            'trends' => $this->getTrends(),
            'limits' => $this->getUserLimits($userId),
            'messages' => $this->getMessagesSummary($userId),
            'notifications' => $this->getNotificationsSummary($userId),
            'security' => $this->getSecurityContext($user),
        ];

        // Add role-specific context
        if ($role === 'client') {
            $context['client_data'] = $this->getClientData($userId);
        } elseif ($role === 'staff') {
            $context['staff_data'] = $this->getStaffData($userId);
        } elseif ($role === 'admin') {
            $context['admin_data'] = $this->getAdminData();
        } elseif ($role === 'cashier') {
            $context['cashier_data'] = $this->getCashierData($userId);
            $context['admin_data'] = $this->getAdminData(); // Cashiers also need some admin metrics
        }

        return $context;
    }

    /**
     * Get cashier-specific data
     */
    private function getCashierData($userId)
    {
        return ChatbotServiceEnhancements::getCashierData($userId);
    }

    /**
     * Recent role signals inferred from actions
     */
    private function getRoleSignals(int $userId): array
    {
        $recentBookings = Appointment::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
        $recentCancels = Appointment::where('user_id', $userId)
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'recent_bookings_7d' => $recentBookings,
            'recent_cancellations_7d' => $recentCancels,
        ];
    }

    /**
     * Appointments summary per user or system depending on role
     */
    private function getAppointmentsSummary(int $userId, string $role): array
    {
        $today = Carbon::now()->startOfDay();
        $query = Appointment::query();
        if ($role === 'admin') {
            // System-wide snapshot
            $next = Appointment::where('status', 'approved')
                ->where('appointment_date', '>=', $today)
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->first();
            return [
                'total' => Appointment::count(),
                'pending' => Appointment::where('status', 'pending')->count(),
                'approved' => Appointment::where('status', 'approved')->count(),
                'completed' => Appointment::where('status', 'completed')->count(),
                'cancelled' => Appointment::where('status', 'cancelled')->count(),
                'today' => Appointment::whereDate('appointment_date', $today)->count(),
                'next_appointment' => $next ? [
                    'date' => $next->appointment_date->format('M d, Y'),
                    'time' => $next->appointment_time,
                    'service' => $next->service?->name,
                    'status' => $next->status,
                ] : null,
            ];
        }

        // Per-user view
        $next = Appointment::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->where('appointment_date', '>=', $today)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->first();

        return [
            'total' => Appointment::where('user_id', $userId)->count(),
            'pending' => Appointment::where('user_id', $userId)->where('status', 'pending')->count(),
            'approved' => Appointment::where('user_id', $userId)->where('status', 'approved')->count(),
            'completed' => Appointment::where('user_id', $userId)->where('status', 'completed')->count(),
            'cancelled' => Appointment::where('user_id', $userId)->where('status', 'cancelled')->count(),
            'today' => Appointment::where('user_id', $userId)->whereDate('appointment_date', $today)->count(),
            'next_appointment' => $next ? [
                'date' => $next->appointment_date->format('M d, Y'),
                'time' => $next->appointment_time,
                'service' => $next->service?->name,
                'status' => $next->status,
            ] : null,
        ];
    }

    /**
     * Availability summary (public endpoints exist; here we provide a compact snapshot)
     */
    private function getAvailabilitySummary(): array
    {
        // Minimal placeholders from DB we control: counts only (avoid fabricating slot details)
        $blackoutCount = \App\Models\BlackoutDate::query()->count();
        $capacityRecords = \App\Models\TimeSlotCapacity::query()->count();

        return [
            'blackout_dates_count' => $blackoutCount,
            'slot_capacity_rules' => $capacityRecords,
        ];
    }

    /** Admin metrics snapshot */
    private function getAdminMetrics(): array
    {
        $today = Carbon::now()->startOfDay();
        return [
            'appointments_today' => Appointment::whereDate('appointment_date', $today)->count(),
            'pending' => Appointment::where('status', 'pending')->count(),
            'approved' => Appointment::where('status', 'approved')->count(),
            'completed' => Appointment::where('status', 'completed')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
            'completion_rate' => $this->getCompletionRate(),
            'cancellation_rate' => $this->getCancellationRate(),
        ];
    }

    /** Trends snapshot */
    private function getTrends(): array
    {
        return [
            'top_services' => $this->getTopServices(5),
            // Further trends like no-show patterns, utilization, forecast are available via analytics endpoints
        ];
    }

    /** Per-user booking limits and rules */
    private function getUserLimits(int $userId): array
    {
        // Support either `AppointmentSettings` (preferred) or the legacy
        // `AppointmentSetting` alias. This prevents fatal errors when one of
        // the class names is missing due to legacy code or naming drift.
        $settingsModel = null;
        if (class_exists(\App\Models\AppointmentSettings::class)) {
            $settingsModel = \App\Models\AppointmentSettings::class;
        } elseif (class_exists(\App\Models\AppointmentSetting::class)) {
            $settingsModel = \App\Models\AppointmentSetting::class;
        }

        if (!$settingsModel) {
            return [
                'max_per_week' => null,
                'same_day_allowed' => false,
                'reschedule_window_days' => null,
                'cancellation_window_days' => null,
            ];
        }

        try {
            $settings = $settingsModel::query()->latest()->first();
        } catch (\Throwable $e) {
            Log::warning('Chatbot getUserLimits error: ' . $e->getMessage());
            return [
                'max_per_week' => null,
                'same_day_allowed' => false,
                'reschedule_window_days' => null,
                'cancellation_window_days' => null,
            ];
        }

        return [
            'max_per_week' => $settings->max_per_week ?? null,
            'same_day_allowed' => (bool)($settings->allow_same_day ?? false),
            'reschedule_window_days' => $settings->reschedule_window_days ?? null,
            'cancellation_window_days' => $settings->cancellation_window_days ?? null,
        ];
    }

    /** Messages summary */
    private function getMessagesSummary(int $userId): array
    {
        try {
            $total = \App\Models\Message::where(function($q) use ($userId){
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })->count();

            // The messages table uses a `read` boolean column (not `is_read`). Use the correct
            // column name and guard against schema mismatches to avoid runtime exceptions.
            $unresolved = 0;
            if (\Illuminate\Support\Facades\Schema::hasColumn('messages', 'read')) {
                $unresolved = \App\Models\Message::where('receiver_id', $userId)->where('read', false)->count();
            }

        } catch (\Throwable $e) {
            // If anything goes wrong, avoid crashing the chatbot. Log and return safe defaults.
            Log::warning('Chatbot getMessagesSummary error: ' . $e->getMessage());
            $total = 0;
            $unresolved = 0;
        }
        return [
            'total' => $total,
            'unresolved' => $unresolved,
        ];
    }

    /** Notifications summary */
    private function getNotificationsSummary(int $userId): array
    {
        $unread = \App\Models\Notification::where('user_id', $userId)->where('is_read', false)->count();
        return [
            'unread' => $unread,
        ];
    }

    /** Security context */
    private function getSecurityContext(User $user): array
    {
        return [
            'auth' => true,
            'token_expires_at' => null, // Token expiry not stored on user; controller can supply
            'permissions' => $user->getRoleNames()->toArray(),
        ];
    }

    /**
     * Get client-specific data
     */
    private function getClientData($userId)
    {
        $today = Carbon::now()->startOfDay();
        
        return [
            'upcoming_appointments' => Appointment::where('user_id', $userId)
                ->where('appointment_date', '>=', $today)
                ->where('status', '!=', 'cancelled')
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get(['id', 'appointment_date', 'appointment_time', 'status', 'service_id'])
                ->map(function ($apt) {
                    return [
                        'id' => $apt->id,
                        'date' => $apt->appointment_date->format('M d, Y'),
                        'time' => $apt->appointment_time,
                        'status' => $apt->status,
                        'service' => $apt->service?->name,
                    ];
                }),
            'total_appointments' => Appointment::where('user_id', $userId)->count(),
            'pending_appointments' => Appointment::where('user_id', $userId)
                ->where('status', 'pending')
                ->count(),
            'confirmed_appointments' => Appointment::where('user_id', $userId)
                ->where('status', 'approved')
                ->count(),
            'cancelled_appointments' => Appointment::where('user_id', $userId)
                ->where('status', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Get staff-specific data
     */
    private function getStaffData($userId)
    {
        $today = Carbon::now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        
        return [
            'today_appointments' => Appointment::where('staff_id', $userId)
                ->whereDate('appointment_date', $today)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'tomorrow_appointments' => Appointment::where('staff_id', $userId)
                ->whereDate('appointment_date', $tomorrow)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'pending_confirmations' => Appointment::where('staff_id', $userId)
                ->where('status', 'pending')
                ->count(),
            'approved_appointments' => Appointment::where('staff_id', $userId)
                ->where('status', 'approved')
                ->where('appointment_date', '>=', $today)
                ->count(),
            'completed_this_month' => Appointment::where('staff_id', $userId)
                ->where('status', 'completed')
                ->whereMonth('completed_at', Carbon::now()->month)
                ->count(),
            'total_cancellations' => Appointment::where('staff_id', $userId)
                ->where('status', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Get admin-specific data
     */
    private function getAdminData()
    {
        $today = Carbon::now()->startOfDay();
        
        // Safe role-based counts: Spatie roles may not be present in some installs
        try {
            $totalClients = User::role('client')->count();
        } catch (\Throwable $e) {
            $totalClients = 0;
        }

        try {
            $totalStaff = User::role('staff')->count();
        } catch (\Throwable $e) {
            $totalStaff = 0;
        }

        try {
            $totalAdmins = User::role('admin')->count();
        } catch (\Throwable $e) {
            $totalAdmins = 0;
        }

        return [
            'total_users' => User::count(),
            'total_clients' => $totalClients,
            'total_staff' => $totalStaff,
            'total_admins' => $totalAdmins,
            // Include soft-deleted appointments so totals reflect full system volume
            'total_appointments' => Appointment::withTrashed()->count(),
            'today_appointments' => Appointment::whereDate('appointment_date', $today)->count(),
            'pending_appointments' => Appointment::where('status', 'pending')->count(),
            'approved_appointments' => Appointment::where('status', 'approved')->count(),
            'completed_appointments' => Appointment::where('status', 'completed')->count(),
            'cancelled_appointments' => Appointment::where('status', 'cancelled')->count(),
            'total_services' => Service::count(),
            'active_services' => Service::where('is_active', true)->count(),
            'top_services' => $this->getTopServices(5),
            'appointment_completion_rate' => $this->getCompletionRate(),
            'cancellation_rate' => $this->getCancellationRate(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Get available services
     */
    private function getAvailableServices()
    {
        return Service::where('is_active', true)
            ->get(['id', 'name', 'duration', 'price'])
            ->map(function ($service) {
                return [
                    'name' => $service->name,
                    'duration' => $service->duration . ' minutes',
                    'price' => $service->price ? '$' . $service->price : 'Contact for pricing',
                ];
            })
            ->toArray();
    }

    /**
     * Get top services by appointment count
     */
    private function getTopServices($limit = 5)
    {
        return Service::withCount('appointments')
            ->where('is_active', true)
            ->orderBy('appointments_count', 'desc')
            ->limit($limit)
            ->get(['name', 'appointments_count'])
            ->map(function ($service) {
                return [
                    'name' => $service->name,
                    'appointments' => $service->appointments_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get business information
     */
    private function getBusinessInfo()
    {
        return [
            'company_name' => 'Peejayy De Guzman Legal',
            'email' => 'peejaydeguzmanlegal@gmail.com',
            'phone' => '09765075274',
            'address' => '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro',
            'type' => 'Notary Services & Legal Consultation',
            'specialties' => [
                'Notary Services',
                'Legal Consultations',
                'Document Review',
                'Contract Drafting',
                'Court Representation',
                'Legal Opinions',
                'Case Evaluations'
            ]
        ];
    }

    /**
     * Get completion rate percentage
     */
    private function getCompletionRate()
    {
        $total = Appointment::whereNotNull('status')
            ->where('status', '!=', 'cancelled')
            ->count();
        
        if ($total === 0) return 0;
        
        $completed = Appointment::where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get cancellation rate percentage
     */
    private function getCancellationRate()
    {
        $total = Appointment::count();
        
        if ($total === 0) return 0;
        
        $cancelled = Appointment::where('status', 'cancelled')->count();
        return round(($cancelled / $total) * 100, 2);
    }

    /**
     * Get suggested questions based on user role and system state
     */
    public function getSuggestedQuestions(?int $userId)
    {
        // For guests, return general questions
        if (!$userId) {
            return $this->getGeneralSuggestedQuestions();
        }

        $user = User::find($userId);
        
        if (!$user) {
            return [];
        }

        $role = $user->getRoleNames()->first() ?? 'user';
        
        switch ($role) {
            case 'client':
                return $this->getClientSuggestedQuestions($userId);
            case 'staff':
                return $this->getStaffSuggestedQuestions($userId);
            case 'admin':
                return $this->getAdminSuggestedQuestions();
            case 'cashier':
                return $this->getCashierSuggestedQuestions($userId);
            default:
                return $this->getGeneralSuggestedQuestions();
        }
    }

    /**
     * Get suggested questions for clients
     */
    private function getClientSuggestedQuestions($userId)
    {
        $upcomingCount = Appointment::where('user_id', $userId)
            ->where('appointment_date', '>=', Carbon::now()->startOfDay())
            ->where('status', '!=', 'cancelled')
            ->count();

        $questions = [
            "How do I book an appointment?",
            "What are the available appointment types?",
            "What services do you offer?",
            "Can I reschedule my appointment?",
        ];

        if ($upcomingCount > 0) {
            $questions = [
                "When is my next appointment?",
                "Can I reschedule my appointment?",
                "Can I cancel my appointment?",
                "What should I bring to my appointment?",
            ];
        } else {
            $questions = [
                "How do I book an appointment?",
                "What time slots are available?",
                "How far in advance can I book?",
                "What services do you offer?",
            ];
        }

        return array_slice($questions, 0, 4);
    }

    /**
     * Get suggested questions for staff
     */
    private function getStaffSuggestedQuestions($userId)
    {
        $today = Carbon::now()->startOfDay();
        $todayCount = Appointment::where('staff_id', $userId)
            ->whereDate('appointment_date', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        $pendingCount = Appointment::where('staff_id', $userId)
            ->where('status', 'pending')
            ->count();

        $completedThisMonth = Appointment::where('staff_id', $userId)
            ->where('status', 'completed')
            ->whereMonth('completed_at', Carbon::now()->month)
            ->count();

        $questions = [
            "What's my workload today?",
            "Show me all pending appointments",
            "How many appointments have I completed this month?",
            "What's my performance this month?",
        ];

        if ($todayCount > 0) {
            $questions[0] = "How many appointments do I have today?";
        }

        if ($pendingCount > 0) {
            $questions[1] = "Which appointments need my attention?";
        }

        if ($completedThisMonth > 0) {
            $questions[2] = "How many appointments have I completed this month?";
        }

        return array_slice($questions, 0, 4);
    }

    /**
     * Get suggested questions for admins
     */
    private function getAdminSuggestedQuestions()
    {
        $pendingCount = Appointment::where('status', 'pending')->count();
        $today = Carbon::now()->startOfDay();
        $todayCount = Appointment::whereDate('appointment_date', $today)->count();
        $totalUsers = User::count();
        
        $questions = [
            "How many total appointments do we have?",
            "Show me all pending appointments",
            "What's the system status?",
            "How many active users do we have?",
        ];

        if ($pendingCount > 0) {
            $questions[1] = "How many appointments need attention? ({$pendingCount} pending)";
        }

        if ($todayCount > 0) {
            $questions[0] = "How many appointments are scheduled today? ({$todayCount})";
        }

        if ($totalUsers > 0) {
            $questions[3] = "How many active users do we have? ({$totalUsers})";
        }

        return array_slice($questions, 0, 4);
    }

    /**
     * Get suggested questions for cashiers
     */
    private function getCashierSuggestedQuestions($userId)
    {
        $today = Carbon::now()->startOfDay();
        
        // Get today's payment statistics
        $todayPayments = Payment::whereDate('created_at', $today)->count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $todayRevenue = Payment::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('amount');
        
        // Get pending refunds
        $pendingRefunds = Refund::where('status', 'pending')->count();
        
        $questions = [
            "What's my shift summary today?",
            "Show pending payments",
            "How much revenue was collected today?",
            "Are there any pending refunds?",
        ];

        if ($todayPayments > 0) {
            $questions[0] = "What's my shift summary? ({$todayPayments} transactions today)";
        }

        if ($pendingPayments > 0) {
            $questions[1] = "Show {$pendingPayments} pending payments";
        }

        if ($todayRevenue > 0) {
            $questions[2] = "Today's revenue: ₱" . number_format($todayRevenue, 2);
        }

        if ($pendingRefunds > 0) {
            $questions[3] = "Process {$pendingRefunds} pending refunds";
        }

        return array_slice($questions, 0, 4);
    }

    /**
     * Get general suggested questions
     */
    private function getGeneralSuggestedQuestions()
    {
        return [
            "How do I book an appointment?",
            "What services do you offer?",
            "How do I contact support?",
            "Can I reschedule my appointment?",
        ];
    }

    /**
     * Normalize, sanitize, and score a raw user message.
     * Returns normalized text, profanity list, toxicity score, and sentiment label for downstream logic.
     */
    private function analyzeMessage(string $message): array
    {
        $normalized = $this->normalizeText($message);
        $profanity = $this->detectProfanity($normalized);
        $cleaned = $this->sanitizeProfanity($normalized, $profanity);
        $sentiment = $this->detectSentiment($normalized, $profanity);

        return [
            'normalized' => $normalized,
            'cleaned' => $cleaned,
            'sentiment' => $sentiment,
        ];
    }

    /**
     * Detect likely profanity/toxic terms (English + Tagalog variants).
     */
    private function detectProfanity(string $text): array
    {
        $badWords = [
            'tangina', 'putangina', 'puta', 'pota', 'gago', 'ulol', 'bobo', 'bwisit',
            'leche', 'tanginamo', 'tngna', 'pakshet', 'piste', 'shet', 'shit', 'fuck',
            'fck', 'fucking', 'damn', 'damnit', 'stupid', 'idiot'
        ];

        $found = [];
        foreach ($badWords as $word) {
            if (strpos($text, $word) !== false) {
                $found[] = $word;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Strip profanity without losing the surrounding meaning.
     */
    private function sanitizeProfanity(string $text, array $profanity): string
    {
        $clean = $text;
        foreach ($profanity as $word) {
            $clean = preg_replace('/\b' . preg_quote($word, '/') . '\b/', ' ', $clean);
        }
        // collapse whitespace after removal
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Lightweight sentiment/anger detection tuned for support chats.
     */
    private function detectSentiment(string $text, array $profanity): array
    {
        $negWords = ['angry', 'bad', 'worst', 'upset', 'pissed', 'galit', 'inis', 'asar', 'bwisit', 'stress', 'stressed'];
        $posWords = ['thanks', 'thank you', 'salamat', 'okay', 'ayos', 'great', 'good'];
        $stressScore = count($profanity) * 2;

        foreach ($negWords as $w) {
            if (strpos($text, $w) !== false) {
                $stressScore += 1;
            }
        }

        foreach ($posWords as $w) {
            if (strpos($text, $w) !== false) {
                $stressScore -= 1;
            }
        }

        $label = 'neutral';
        if ($stressScore >= 4) {
            $label = 'angry';
        } elseif ($stressScore >= 2) {
            $label = 'frustrated';
        } elseif ($stressScore <= -1) {
            $label = 'positive';
        }

        return [
            'label' => $label,
            'score' => $stressScore,
            'toxicity' => $stressScore >= 2,
            'profanity_terms' => $profanity,
        ];
    }

    /**
     * Apply calm, steady tone when message looks angry/abusive.
     */
    private function applyToneToReply(string $reply, array $analysis): string
    {
        $tone = $analysis['sentiment']['label'] ?? 'neutral';
        if (in_array($tone, ['angry', 'frustrated'], true)) {
            return "I can help with that. Let's sort this out: " . $reply;
        }
        return $reply;
    }

    /**
     * Interpret a user message with fuzzy understanding and respond using real system data.
     * - Detect role intent (CLIENT vs ADMIN)
     * - Normalize misspellings/Taglish/slang
     * - Use DB-backed info where applicable
     * - Never invent data; if exact data requires deeper access, state limitation
     *
     * @param int|null $userId User ID (null for guests)
     * @param string $message The user's message
     * @return array Response with reply and metadata
     */
    public function interpretAndRespond(?int $userId, string $message): array
    {
        $context = $this->getSystemContext($userId);
        $analysis = $this->analyzeMessage($message);
        $normalized = $analysis['cleaned'];
        $intent = $this->detectIntent($normalized);

        // Debug logging to help diagnose intent detection issues
        try {
            \Illuminate\Support\Facades\Log::info('chatbot_nlu_debug', [
                'user_id' => $userId,
                'raw' => $message,
                'normalized' => $analysis['normalized'],
                'cleaned' => $analysis['cleaned'],
                'detected_intent' => $intent,
                'has_enhancements' => class_exists(\App\Services\ChatbotServiceEnhancements::class),
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        // Extract entities using enhancements if available; otherwise use a safe default.
        if (class_exists(\App\Services\ChatbotServiceEnhancements::class)) {
            $entities = ChatbotServiceEnhancements::extractEntities($normalized, $context ?? []);
        } else {
            $entities = [
                'dates' => [], 'times' => [], 'numbers' => [], 'services' => [], 'actions' => []
            ];
        }
        
        // Check actual user role first, then detect by intent
        $actualRole = $context['user_role'] ?? 'guest';
        
        // ==================== ACTION-BASED COMMAND DETECTION ====================
        // Check if this is an action command that should execute a system operation
        $actionResult = $this->detectAndExecuteAction($normalized, $userId, $actualRole, $entities, $context);
        if ($actionResult !== null) {
            // Action was detected and processed
            $actionResult['nlu'] = [
                'normalized' => $analysis['normalized'],
                'cleaned' => $analysis['cleaned'],
                'intent' => $actionResult['action_intent'] ?? 'action',
                'sentiment' => $analysis['sentiment']['label'] ?? null,
                'sentiment_score' => $analysis['sentiment']['score'] ?? null,
                'toxicity' => $analysis['sentiment']['toxicity'] ?? null,
            ];
            $actionResult['entities'] = $entities;
            $actionResult['role_source'] = ['actual' => $actualRole, 'detected' => 'action'];
            $actionResult['meta_source'] = 'action_handler';
            $actionResult['reply'] = $this->applyToneToReply($actionResult['reply'], $analysis);
            
            // Add contextual suggestions based on the action result
            if (empty($actionResult['suggestions'])) {
                $actionResult['suggestions'] = $this->getActionFollowUpSuggestions($actionResult, $actualRole);
            }
            
            return $actionResult;
        }
        // ==================== END ACTION DETECTION ====================
        $intentBasedRole = $this->detectRoleByIntent($normalized);
        
        // Determine final role for handling based on actual authentication
        if ($actualRole === 'admin') {
            $role = 'admin';
        } elseif ($actualRole === 'cashier') {
            $role = 'cashier';
        } elseif ($actualRole === 'staff') {
            $role = 'admin'; // Staff uses admin-like responses
        }
        // If intent strongly suggests admin/cashier query but user is client, check permissions
        elseif ($intentBasedRole === 'admin' && !empty($context['user_id'])) {
            // Don't elevate client to admin based on intent alone - security measure
            $role = 'client';
        } elseif ($intentBasedRole === 'cashier' && !empty($context['user_id'])) {
            // Don't elevate client to cashier based on intent alone - security measure
            $role = 'client';
        }
        // Default to client
        else {
            $role = 'client';
        }

        // Deterministic intents: for factual queries we prefer server-side answers
        $deterministicIntents = ['my_appointment','admin_counts','services','price','hours','address','availability','status','contact'];
        if (in_array($intent, $deterministicIntents, true)) {
            try {
                try {
                    Log::info('chatbot_nlu_debug', ['user_id' => $userId, 'deterministic_intent' => $intent]);
                } catch (\Throwable $_e) {
                }
                $detResp = $this->handleDeterministicIntent($intent, $userId, $context, $entities, $analysis);
                if ($detResp) {
                    // Ensure consistent response shape
                    $detResp['nlu'] = [
                        'normalized' => $analysis['normalized'],
                        'cleaned' => $analysis['cleaned'],
                        'intent' => $intent,
                        'sentiment' => $analysis['sentiment']['label'] ?? null,
                        'sentiment_score' => $analysis['sentiment']['score'] ?? null,
                        'toxicity' => $analysis['sentiment']['toxicity'] ?? null,
                    ];
                    $detResp['entities'] = $entities;
                    $detResp['role_source'] = ['actual' => $actualRole, 'detected' => $intentBasedRole];
                    // mark as deterministic
                    $detResp['meta_source'] = 'deterministic';
                    // Apply tone
                    $detResp['reply'] = $this->applyToneToReply($detResp['reply'], $analysis);
                    return $detResp;
                }
            } catch (\Throwable $e) {
                Log::warning('Deterministic handler failed: ' . $e->getMessage());
            }
        }

        // If intent is still generic, try a refinement pass using entities, fuzzy and phonetic cues
        if ($intent === 'general') {
            $refined = $this->refineIntentUsingEntities($normalized, $context, $entities, $analysis);
            if ($refined && $refined !== 'general') {
                $intent = $refined;
            }
        }

        // Re-check deterministic intents after refinement (cover cases where detection upgraded from 'general')
        $deterministicIntents = ['my_appointment','admin_counts','services','price','hours','address','availability','status','contact'];
        if (in_array($intent, $deterministicIntents, true)) {
            try {
                try {
                    Log::info('chatbot_nlu_debug', ['user_id' => $userId, 'deterministic_intent_after_refine' => $intent]);
                } catch (\Throwable $_e) {}

                $detResp = $this->handleDeterministicIntent($intent, $userId, $context, $entities, $analysis);
                if ($detResp) {
                    $detResp['nlu'] = [
                        'normalized' => $analysis['normalized'],
                        'cleaned' => $analysis['cleaned'],
                        'intent' => $intent,
                        'sentiment' => $analysis['sentiment']['label'] ?? null,
                        'sentiment_score' => $analysis['sentiment']['score'] ?? null,
                        'toxicity' => $analysis['sentiment']['toxicity'] ?? null,
                    ];
                    $detResp['entities'] = $entities;
                    $detResp['role_source'] = ['actual' => $actualRole, 'detected' => $intentBasedRole];
                    $detResp['meta_source'] = 'deterministic';
                    $detResp['reply'] = $this->applyToneToReply($detResp['reply'], $analysis);
                    return $detResp;
                }
            } catch (\Throwable $e) {
                Log::warning('Deterministic handler failed (after refine): ' . $e->getMessage());
            }
        }

        if ($role === 'admin') {
            $resp = $this->handleAdminIntent($normalized, $context, $intent, $entities, $analysis);
        } elseif ($role === 'cashier') {
            $resp = $this->handleCashierIntent($normalized, $context, $intent, $entities, $analysis);
        } else {
            $resp = $this->handleClientIntent($normalized, $context, $intent, $entities, $analysis);
        }

        // Log NLU decision for debugging
        try {
            Log::info('chatbot_nlu', [
                'user_id' => $userId,
                'raw' => $message,
                'normalized' => $analysis['normalized'],
                'cleaned' => $analysis['cleaned'],
                'initial_intent' => $this->detectIntent($analysis['cleaned']),
                'final_intent' => $intent,
                'entities' => $entities,
                'sentiment' => $analysis['sentiment'],
            ]);
        } catch (\Throwable $e) {
            // avoid crashing on logging issues
        }

        // annotate with NLU meta for transparency in the API response
        $resp['nlu'] = [
            'normalized' => $analysis['normalized'],
            'cleaned' => $analysis['cleaned'],
            'intent' => $intent,
            'sentiment' => $analysis['sentiment']['label'],
            'sentiment_score' => $analysis['sentiment']['score'],
            'toxicity' => $analysis['sentiment']['toxicity'],
            'profanity_terms' => $analysis['sentiment']['profanity_terms'],
        ];
        $resp['entities'] = $entities;
        $resp['role_source'] = ['actual' => $actualRole, 'detected' => $intentBasedRole];

        $resp['reply'] = $this->applyToneToReply($resp['reply'], $analysis);

        return $resp;
    }

    /**
     * Detect and execute action-based commands
     * Returns null if no action detected, or the action result if executed
     */
    private function detectAndExecuteAction(string $normalized, ?int $userId, string $role, array $entities, ?array $context): ?array
    {
        // Check if ChatbotActionHandler exists
        if (!class_exists(\App\Services\ChatbotActionHandler::class)) {
            return null;
        }

        // Detect action intent
        $actionIntent = $this->detectActionIntent($normalized, $role);
        
        if (!$actionIntent) {
            return null;
        }

        Log::info('chatbot_action_detected', [
            'user_id' => $userId,
            'role' => $role,
            'action_intent' => $actionIntent,
            'normalized' => $normalized,
        ]);

        // Extract resource ID from message
        $resourceId = $this->extractResourceId($normalized, $entities);

        // Map action intent to action handler parameters
        $actionParams = $this->mapActionIntentToParams($actionIntent, $resourceId, $userId, $role, $normalized, $entities);

        if (!$actionParams) {
            return null;
        }

        // Check if action requires ID but none provided
        $intentConfig = $this->actionIntentPatterns[$actionIntent] ?? [];
        if (($intentConfig['requires_id'] ?? false) && !$resourceId) {
            return $this->buildMissingIdResponse($actionIntent, $role, $context);
        }

        // Execute the action
        try {
            $result = ChatbotActionHandler::executeAction($actionParams);
            
            // Build response
            return [
                'success' => $result['success'] ?? false,
                'reply' => $result['message'] ?? 'Action processed.',
                'action_intent' => $actionIntent,
                'action_result' => $result,
                'data' => $result['data'] ?? null,
                'suggestions' => $this->getActionFollowUpSuggestions($result, $role),
            ];
        } catch (\Exception $e) {
            Log::error('chatbot_action_error', [
                'action_intent' => $actionIntent,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'reply' => 'I encountered an error while processing that action. Please try again or contact support.',
                'action_intent' => $actionIntent,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect action intent from normalized text
     */
    private function detectActionIntent(string $normalized, string $role): ?string
    {
        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($this->actionIntentPatterns as $intent => $config) {
            // Check if user's role is allowed for this action
            $allowedRoles = $config['roles'] ?? [];
            if (!empty($allowedRoles) && !in_array($role, $allowedRoles) && !in_array('client', $allowedRoles)) {
                continue;
            }

            $score = 0.0;

            // Check exact patterns
            foreach ($config['patterns'] ?? [] as $pattern) {
                if (stripos($normalized, $pattern) !== false) {
                    $score = max($score, 0.9);
                }
            }

            // Check keywords
            foreach ($config['keywords'] ?? [] as $keyword) {
                if (stripos($normalized, $keyword) !== false) {
                    $score = max($score, 0.7);
                }
            }

            // Fuzzy matching if enhancements available
            if (class_exists(\App\Services\ChatbotServiceEnhancements::class)) {
                foreach ($config['patterns'] ?? [] as $pattern) {
                    $fuzzyScore = ChatbotServiceEnhancements::fuzzySimilarity($normalized, $pattern);
                    if ($fuzzyScore > 0.6) {
                        $score = max($score, $fuzzyScore * 0.8);
                    }
                }
            }

            if ($score > $bestScore && $score >= 0.5) {
                $bestScore = $score;
                $bestMatch = $intent;
            }
        }

        return $bestMatch;
    }

    /**
     * Extract resource ID from the message
     */
    private function extractResourceId(string $normalized, array $entities): ?int
    {
        // Check entities for numbers/IDs
        if (!empty($entities['appointment_ids'])) {
            return $entities['appointment_ids'][0];
        }

        if (!empty($entities['numbers'])) {
            return $entities['numbers'][0];
        }

        // Try to extract ID from patterns like "#123", "id 123", "appointment 123"
        $patterns = [
            '/(?:appointment|booking|payment|refund)\s*#?\s*(\d+)/i',
            '/(?:id|number|#)\s*(\d+)/i',
            '/(\d+)\s*(?:appointment|booking)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Map action intent to ChatbotActionHandler parameters
     */
    private function mapActionIntentToParams(string $actionIntent, ?int $resourceId, ?int $userId, string $role, string $normalized, array $entities): ?array
    {
        $baseParams = [
            'user_id' => $userId,
            'role' => $role,
            'resource_id' => $resourceId,
            'data' => [],
        ];

        // Extract reason if present
        $reason = $this->extractReason($normalized);
        if ($reason) {
            $baseParams['data']['reason'] = $reason;
        }

        // Extract date/time for reschedule
        if (!empty($entities['dates'])) {
            $baseParams['data']['new_date'] = $entities['dates'][0]['date'];
        }
        if (!empty($entities['times'])) {
            $baseParams['data']['new_time'] = $entities['times'][0];
        }

        return match($actionIntent) {
            'approve_appointment' => array_merge($baseParams, ['action' => 'approve', 'resource' => 'appointment']),
            'decline_appointment' => array_merge($baseParams, ['action' => 'decline', 'resource' => 'appointment']),
            'cancel_appointment' => array_merge($baseParams, ['action' => 'cancel', 'resource' => 'appointment']),
            'complete_appointment' => array_merge($baseParams, ['action' => 'complete', 'resource' => 'appointment']),
            'reschedule_appointment' => array_merge($baseParams, ['action' => 'reschedule', 'resource' => 'appointment']),
            'process_payment' => array_merge($baseParams, ['action' => 'process', 'resource' => 'payment', 'data' => array_merge($baseParams['data'], ['appointment_id' => $resourceId])]),
            'approve_refund' => array_merge($baseParams, ['action' => 'approve', 'resource' => 'refund']),
            'process_refund' => array_merge($baseParams, ['action' => 'process', 'resource' => 'refund']),
            'request_refund' => array_merge($baseParams, ['action' => 'request', 'resource' => 'refund', 'data' => array_merge($baseParams['data'], ['appointment_id' => $resourceId])]),
            'view_pending_appointments' => array_merge($baseParams, ['action' => 'view', 'resource' => 'appointment', 'resource_id' => null, 'data' => ['status' => 'pending']]),
            'view_pending_payments' => array_merge($baseParams, ['action' => 'view', 'resource' => 'payment', 'resource_id' => null, 'data' => ['status' => 'pending']]),
            'view_pending_refunds' => array_merge($baseParams, ['action' => 'view', 'resource' => 'refund', 'resource_id' => null, 'data' => ['status' => 'pending']]),
            'shift_report' => array_merge($baseParams, ['action' => 'view', 'resource' => 'system', 'data' => ['report_type' => 'shift']]),
            'system_health' => array_merge($baseParams, ['action' => 'view', 'resource' => 'system']),
            default => null,
        };
    }

    /**
     * Extract reason from message for declines/cancellations
     */
    private function extractReason(string $normalized): ?string
    {
        $patterns = [
            '/(?:because|reason|due to|kasi|dahil)\s*:?\s*(.+)/i',
            '/(?:reason)\s*:?\s*(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Build response when action requires ID but none provided
     */
    private function buildMissingIdResponse(string $actionIntent, string $role, ?array $context): array
    {
        $actionNames = [
            'approve_appointment' => 'approve an appointment',
            'decline_appointment' => 'decline an appointment',
            'cancel_appointment' => 'cancel an appointment',
            'complete_appointment' => 'complete an appointment',
            'process_payment' => 'process a payment',
            'approve_refund' => 'approve a refund',
            'process_refund' => 'process a refund',
            'request_refund' => 'request a refund',
        ];

        $actionName = $actionNames[$actionIntent] ?? 'perform this action';

        // Get relevant pending items to suggest
        $suggestions = [];
        $dataHints = [];

        if (in_array($actionIntent, ['approve_appointment', 'decline_appointment']) && in_array($role, ['admin', 'staff'])) {
            $pending = Appointment::where('status', 'pending')
                ->with(['user:id,first_name,last_name', 'service:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            if ($pending->count() > 0) {
                $dataHints = $pending->map(fn($a) => [
                    'id' => $a->id,
                    'client' => trim(($a->user->first_name ?? '') . ' ' . ($a->user->last_name ?? '')),
                    'service' => $a->service->name ?? 'N/A',
                    'date' => $a->appointment_date->format('M d'),
                ])->toArray();
                
                $suggestions = array_map(fn($a) => "Approve appointment #{$a['id']}", array_slice($dataHints, 0, 3));
            }
        }

        if ($actionIntent === 'cancel_appointment' && $role === 'client' && !empty($context['client_data']['upcoming_appointments'])) {
            $upcoming = $context['client_data']['upcoming_appointments'];
            $dataHints = array_slice($upcoming, 0, 3);
            $suggestions = array_map(fn($a) => "Cancel appointment #{$a['id']}", $dataHints);
        }

        if (in_array($actionIntent, ['process_payment', 'approve_refund', 'process_refund']) && in_array($role, ['cashier', 'admin'])) {
            if ($actionIntent === 'process_payment') {
                $pending = Payment::where('payment_status', '!=', 'paid')
                    ->with(['appointment.user:id,first_name,last_name'])
                    ->limit(5)
                    ->get();
                $suggestions = $pending->map(fn($p) => "Process payment #{$p->id}")->toArray();
            } else {
                $pending = Refund::where('status', 'pending')
                    ->limit(5)
                    ->get();
                $actionVerb = $actionIntent === 'approve_refund' ? 'Approve' : 'Process';
                $suggestions = $pending->map(fn($r) => "{$actionVerb} refund #{$r->id}")->toArray();
            }
        }

        return [
            'success' => false,
            'reply' => "To {$actionName}, please specify the ID. For example: '{$actionName} #123'" . 
                       ($dataHints ? "\n\nHere are some items that need attention:" : ''),
            'action_intent' => $actionIntent,
            'action_required' => 'provide_id',
            'data' => $dataHints ? ['pending_items' => $dataHints] : null,
            'suggestions' => $suggestions ?: ["Show pending appointments", "Show my appointments"],
        ];
    }

    /**
     * Get follow-up suggestions after an action
     */
    private function getActionFollowUpSuggestions(array $result, string $role): array
    {
        $action = $result['action_intent'] ?? '';
        $success = $result['success'] ?? false;

        if (!$success) {
            return match($role) {
                'admin', 'staff' => ['Show pending appointments', 'Show system status'],
                'cashier' => ['Show pending payments', 'Show pending refunds'],
                default => ['Show my appointments', 'How do I book?'],
            };
        }

        return match($action) {
            'approve_appointment', 'decline_appointment' => ['Show more pending', 'How many appointments today?'],
            'cancel_appointment' => ['Book a new appointment', 'Show my appointments'],
            'complete_appointment' => ['Show next appointment', 'Today\'s summary'],
            'process_payment' => ['Show pending payments', 'Shift report'],
            'approve_refund', 'process_refund' => ['Show pending refunds', 'Today\'s transactions'],
            'request_refund' => ['Check refund status', 'Contact support'],
            'view_pending_appointments' => ['Approve all pending', 'Show details'],
            'view_pending_payments' => ['Process next payment', 'Show shift report'],
            'view_pending_refunds' => ['Approve first refund', 'Show refund policy'],
            default => match($role) {
                'admin', 'staff' => ['Show analytics', 'System health'],
                'cashier' => ['Shift report', 'Pending tasks'],
                default => ['My appointments', 'Available services'],
            },
        };
    }

    /**
     * Advanced fuzzy normalization for natural language understanding
     * Handles: misspellings, Taglish, slang, abbreviations, phonetic variations, grammar errors
     */
    private function normalizeText(string $text): string
    {
        $t = mb_strtolower(trim($text));
        
        // collapse repeated letters: e.g., plss -> pls, hellooo -> helo
        $t = preg_replace('/([a-z])\1{2,}/', '$1', $t);
        
        // Comprehensive mapping: typos, shortcuts, Taglish, slang, phonetic variations
        $map = [
            // Appointment variations
            'apntmnt' => 'appointment', 'apointment' => 'appointment', 'apoinment' => 'appointment',
            'apoyntment' => 'appointment', 'appointmnt' => 'appointment', 'appt' => 'appointment',
            'apt' => 'appointment', 'scheds' => 'appointment', 'sched' => 'appointment',
            'appointmeny' => 'appointment', 'appoitment' => 'appointment', 'apointmnt' => 'appointment',
            
            // Booking variations
            'bok' => 'book', 'buk' => 'book', 'b0ok' => 'book', 'boook' => 'book',
            'magbook' => 'book', 'magpa book' => 'book', 'pakibook' => 'book',
            'gusto ko magbook' => 'want to book', 'paano magbook' => 'how to book',
            'pano book' => 'how to book', 'papaano' => 'how', 'paano' => 'how',
            
            // Reschedule variations
            'resked' => 'reschedule', 'rebook' => 'reschedule', 'resched' => 'reschedule',
            'move date' => 'reschedule', 'change date' => 'reschedule', 'lipat' => 'reschedule',
            'palitan' => 'reschedule', 'ilipat' => 'reschedule', 'move' => 'reschedule',
            'pwede bang palitan' => 'can i reschedule', 'pwedeng ilipat' => 'can i reschedule',
            
            // Cancel variations
            'cncl' => 'cancel', 'cancelled' => 'cancel', 'kansela' => 'cancel',
            'kanselahin' => 'cancel', 'magcancel' => 'cancel', 'icancel' => 'cancel',
            'tanggalin' => 'cancel', 'alisin' => 'cancel',
            
            // Time expressions
            'tmrw' => 'tomorrow', 'tmr' => 'tomorrow', '2mrw' => 'tomorrow', 'bukas' => 'tomorrow',
            'tomorow' => 'tomorrow', 'tommorow' => 'tomorrow', 'tomoro' => 'tomorrow',
            'nxt wik' => 'next week', 'next wik' => 'next week', 'nx wk' => 'next week',
            'susunod na linggo' => 'next week', 'next wk' => 'next week',
            '2day' => 'today', 'ngayon' => 'today', 'this day' => 'today',
            'kahapon' => 'yesterday', 'yestrday' => 'yesterday',

            // Time and hours variations
            'ano oras' => 'what time', 'anong oras' => 'what time', 'anong oras kayo' => 'what time open',
            'anung oras' => 'what time', 'ano oras nyo' => 'what time open', 'anong oras bukas' => 'what time open',
            'oras nyo' => 'hours', 'oras mo' => 'hours', 'oras open' => 'opening hours',
            'hour' => 'hours', 'hrs' => 'hours', 'sked' => 'schedule', 'oras' => 'time',
            'ano oras bukas kau' => 'what time open',
            
            // Service variations
            'srvce' => 'service', 'servis' => 'service', 'serbis' => 'service',
            'serbisyo' => 'service', 'services' => 'service', 'servces' => 'service',
            'ano mga service' => 'what services', 'anong serbisyo' => 'what services',
            'may ano ano' => 'what are available', 'meron ba' => 'do you have',
            
            // Price/Cost variations
            'hm' => 'how much', 'prce' => 'price', 'mach' => 'much', 'magkano' => 'how much',
            'presyo' => 'price', 'halaga' => 'price', 'bayad' => 'payment',
            'magkanu' => 'how much', 'magkna' => 'how much', 'quanto' => 'how much',
            'hm mch dat ting' => 'how much is that service', 'magkano yan' => 'how much is that',
            
            // Location variations
            'loksyon' => 'location', 'loc' => 'location', 'saan' => 'where',
            'nasaan' => 'where', 'lokasyon' => 'location', 'address' => 'location',
            'opisina' => 'office', 'office' => 'location',
            
            // Availability variations
            'avalbl' => 'available', 'avail' => 'available', 'availble' => 'available',
            'may slot' => 'available slots', 'may oras' => 'available time',
            'slot' => 'slots', 'timeslot' => 'slots', 'time slot' => 'slots',
            'free slot' => 'available slots', 'pwede pa' => 'still available',
            'may bakante' => 'available', 'bakante' => 'available',
            
            // Account/Password variations
            'paswrd' => 'password', 'pword' => 'password', 'pw' => 'password',
            'pasword' => 'password', 'passw0rd' => 'password', 'nakalimutan' => 'forgot',
            'reset' => 'reset password', 'forgot pw' => 'forgot password',
            
            // Status/Count queries (Admin)
            'ilan' => 'how many', 'gaano karami' => 'how many', 'bilang' => 'count',
            'total' => 'total', 'lahat' => 'all', 'buong' => 'total',
            'user' => 'users', 'client' => 'clients', 'appointment' => 'appointments',
            
            // Admin/Analytics terms
            'admin' => 'admin', 'analytics' => 'analytics', 'report' => 'report',
            'popular' => 'popular', 'pinakamarami' => 'most popular',
            'dashboard' => 'dashboard', 'overview' => 'overview', 'stats' => 'statistics',
            'performance' => 'performance', 'metrics' => 'metrics',
            
            // Action words
            'update' => 'update', 'change' => 'change', 'edit' => 'edit',
            'palitan' => 'change', 'baguhin' => 'change', 'i update' => 'update',
            'show' => 'show', 'ipakita' => 'show', 'tingnan' => 'view',
            'check' => 'check', 'tignan' => 'check', 'verify' => 'check',
            
            // Polite expressions (normalize to intent)
            'pls' => 'please', 'plz' => 'please', 'paki' => 'please',
            'pwede' => 'can', 'pwede ba' => 'can', 'maaari' => 'can',
            'gusto' => 'want', 'nais' => 'want', 'ibig' => 'want',
            'kailangan' => 'need', 'kelangan' => 'need', 'need' => 'need',
            
            // Common typos and l33t speak
            'thx' => 'thanks', 'thnks' => 'thanks', 'tnx' => 'thanks',
            'thnx' => 'thanks', 'ty' => 'thank you', 'salamat' => 'thank you',
            'ok' => 'okay', '0k' => 'okay', 'okey' => 'okay', 'k' => 'okay',
            'u' => 'you', 'ur' => 'your', 'r' => 'are', 'y' => 'why',
            '2' => 'to', '4' => 'for', 'b4' => 'before', 'bcuz' => 'because',
        ];
        
        // Apply mappings (order matters: longer phrases first)
        $sortedMap = $map;
        uksort($sortedMap, function($a, $b) { return strlen($b) - strlen($a); });
        foreach ($sortedMap as $k => $v) {
            // Replace only whole words/phrases to avoid corrupting words by single-letter keys (e.g. 'u' -> 'you')
            $pattern = '/\b' . preg_quote($k, '/') . '\b/iu';
            $t = preg_replace($pattern, $v, $t);
        }
        
        // remove emojis/non-word except spaces
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $t);
        // collapse whitespace
        $t = preg_replace('/\s+/', ' ', $t);
        return trim($t);
    }

    /**
     * Advanced intent detection with semantic understanding and contextual patterns
     * Uses fuzzy matching, synonym expansion, and multi-word pattern recognition
     */
    private function detectIntent(string $t): string
    {
        // Multi-level intent patterns: exact phrases, keyword combinations, semantic patterns
        $intentPatterns = [
            'book' => [
                'patterns' => ['want to book', 'how to book', 'book appointment', 'create appointment', 'set appointment', 'make appointment'],
                'keywords' => ['book', 'schedule', 'reserve', 'set up', 'arrange', 'paano book', 'gawa ng appointment'],
                'semantic' => ['need appointment', 'get appointment', 'have appointment'],
            ],
            'reschedule' => [
                'patterns' => ['reschedule', 'change date', 'change time', 'move date', 'move appointment', 'different date', 'another date'],
                'keywords' => ['reschedule', 'move', 'change', 'switch', 'transfer', 'adjust'],
                'semantic' => ['can i change', 'want to move', 'need different time'],
            ],
            'cancel' => [
                'patterns' => ['cancel appointment', 'cancel booking', 'remove appointment', 'delete appointment'],
                'keywords' => ['cancel', 'remove', 'delete', 'withdraw', 'drop'],
                'semantic' => ['want to cancel', 'need to cancel', 'cant make it', 'not coming'],
            ],
            'my_appointment' => [
                'patterns' => ['my appointment', 'my booking', 'my schedule', 'next appointment', 'upcoming appointment', 'when is my'],
                'keywords' => ['my appointment', 'next appointment', 'upcoming', 'scheduled for'],
                'semantic' => ['when appointment', 'what time appointment', 'appointment schedule'],
            ],
            'availability' => [
                'patterns' => ['available slots', 'available time', 'any slots', 'free slots', 'open slots', 'time slots', 'what times'],
                'keywords' => ['available', 'availability', 'slots', 'free', 'open', 'still available', 'any time'],
                'semantic' => ['when can i', 'what days', 'what times', 'is there time'],
            ],
            'services' => [
                'patterns' => ['what services', 'available services', 'what do you offer', 'service list', 'types of services'],
                'keywords' => ['services', 'offer', 'provide', 'types', 'options', 'what services'],
                'semantic' => ['what can you do', 'help me with', 'looking for service'],
            ],
            'price' => [
                'patterns' => ['how much', 'what price', 'what cost', 'how expensive', 'pricing'],
                'keywords' => ['price', 'cost', 'fee', 'charge', 'payment', 'how much', 'expensive'],
                'semantic' => ['what will i pay', 'do i pay', 'cost me'],
            ],
            'requirements' => [
                'patterns' => ['what to bring', 'what documents', 'what requirements', 'need to bring', 'requirements'],
                'keywords' => ['bring', 'requirements', 'documents', 'needed', 'prepare'],
                'semantic' => ['should i bring', 'what documents need', 'prepare what'],
            ],
            'address' => [
                'patterns' => ['where located', 'where is office', 'what address', 'find office', 'location'],
                'keywords' => ['location', 'address', 'where', 'office', 'find', 'directions'],
                'semantic' => ['how to get there', 'where are you', 'office location'],
            ],
            'hours' => [
                'patterns' => ['what hours', 'opening hours', 'what time open', 'when open', 'business hours', 'what time do you open'],
                'keywords' => ['hours', 'open', 'opening', 'closing', 'schedule', 'what time'],
                'semantic' => ['when are you open', 'time you open', 'open today'],
            ],
            'contact' => [
                'patterns' => ['how to contact', 'contact info', 'phone number', 'email address', 'reach you'],
                'keywords' => ['contact', 'phone', 'email', 'reach', 'call', 'message'],
                'semantic' => ['how can i contact', 'get in touch', 'talk to someone'],
            ],
            'account' => [
                'patterns' => ['reset password', 'forgot password', 'change email', 'update profile', 'account settings'],
                'keywords' => ['password', 'account', 'profile', 'email', 'reset', 'forgot', 'update'],
                'semantic' => ['cant login', 'change password', 'update information'],
            ],
            'status' => [
                'patterns' => ['appointment status', 'check status', 'status of', 'approved yet', 'confirmed yet'],
                'keywords' => ['status', 'approved', 'confirmed', 'pending', 'accepted'],
                'semantic' => ['is it approved', 'did you confirm', 'has been approved'],
            ],
            'admin_analytics' => [
                'patterns' => ['show analytics', 'performance report', 'no show rate', 'cancellation patterns', 'popular service', 'demand forecast'],
                'keywords' => ['analytics', 'report', 'performance', 'metrics', 'forecast', 'patterns', 'trends', 'insights', 'statistics'],
                'semantic' => ['how is performance', 'show me data', 'business insights'],
            ],
            'admin_counts' => [
                'patterns' => ['how many users', 'how many clients', 'how many appointments', 'total users', 'total clients', 'user count'],
                'keywords' => ['how many', 'total', 'count', 'number of', 'users', 'clients', 'appointments', 'today'],
                'semantic' => ['users do i have', 'appointments today', 'total appointments'],
            ],
            'admin_pending' => [
                'patterns' => ['pending appointments', 'needs confirmation', 'pending confirmations', 'awaiting approval'],
                'keywords' => ['pending', 'waiting', 'needs attention', 'unconfirmed', 'approval'],
                'semantic' => ['what needs approval', 'appointments to confirm', 'needs my attention'],
            ],
            // Cashier-specific intents
            'cashier_payments' => [
                'patterns' => ['process payment', 'pending payments', 'payments to process', 'approved for payment', 'ready for payment'],
                'keywords' => ['payment', 'pay', 'collect', 'fee', 'charge', 'billing', 'invoice'],
                'semantic' => ['who needs to pay', 'collect payment', 'payments today'],
            ],
            'cashier_refunds' => [
                'patterns' => ['pending refunds', 'refund requests', 'process refund', 'refund queue', 'refunds to process'],
                'keywords' => ['refund', 'reimbursement', 'money back', 'return money'],
                'semantic' => ['refunds waiting', 'refund approval', 'issue refund'],
            ],
            'cashier_shift' => [
                'patterns' => ['shift report', 'daily report', 'my transactions', 'today report', 'cash summary'],
                'keywords' => ['shift', 'report', 'summary', 'transactions', 'daily'],
                'semantic' => ['how much collected', 'end of day report', 'cash register'],
            ],
            'cashier_receipt' => [
                'patterns' => ['send receipt', 'email receipt', 'print receipt', 'generate receipt'],
                'keywords' => ['receipt', 'invoice', 'confirmation'],
                'semantic' => ['give receipt', 'client receipt'],
            ],
            // User payment/refund intents
            'user_payment_status' => [
                'patterns' => ['my payment', 'payment status', 'have i paid', 'my payments', 'payment history'],
                'keywords' => ['payment', 'paid', 'pay', 'balance', 'owe'],
                'semantic' => ['did i pay', 'what do i owe', 'payment record'],
            ],
            'user_refund' => [
                'patterns' => ['request refund', 'get refund', 'my refund', 'refund status', 'want refund'],
                'keywords' => ['refund', 'money back', 'reimbursement'],
                'semantic' => ['i want my money back', 'can i get refund'],
            ],
        ];

        $scores = [];
        foreach ($intentPatterns as $intent => $rules) {
            // base pattern/keyword/semantic confidence
            $base = class_exists(\App\Services\ChatbotServiceEnhancements::class)
                ? ChatbotServiceEnhancements::calculateIntentConfidence($t, $intent, $intentPatterns)
                : 0.0;

            // fuzzy match against patterns and keywords
            $patternSim = 0.0;
            foreach (array_merge($rules['patterns'] ?? [], $rules['keywords'] ?? []) as $phrase) {
                $s = class_exists(\App\Services\ChatbotServiceEnhancements::class)
                    ? ChatbotServiceEnhancements::fuzzySimilarity($t, $phrase)
                    : 0.0;
                if ($s > $patternSim) $patternSim = $s;
            }

            // phonetic signal (helps with misspellings)
            $phon = 0.0;
            foreach (array_merge($rules['patterns'] ?? [], $rules['keywords'] ?? []) as $phrase) {
                $p = class_exists(\App\Services\ChatbotServiceEnhancements::class)
                    ? ChatbotServiceEnhancements::phoneticSimilarity($t, $phrase)
                    : 0.0;
                if ($p > $phon) $phon = $p;
            }

            // semantic similarity against semantic corpus
            $semanticSim = 0.0;
            foreach ($rules['semantic'] ?? [] as $phrase) {
                $ss = class_exists(\App\Services\ChatbotServiceEnhancements::class)
                    ? ChatbotServiceEnhancements::semanticSimilarity($t, $phrase)
                    : 0.0;
                if ($ss > $semanticSim) $semanticSim = $ss;
            }

            // Combine scores with tuned weights
            // base: 35%, patternSim: 35%, semanticSim: 20%, phonetic: 10%
            $combined = ($base * 0.35) + ($patternSim * 0.35) + ($semanticSim * 0.2) + ($phon * 0.1);
            $scores[$intent] = $combined;
        }

        arsort($scores);
        $topIntent = array_key_first($scores);
        // threshold depends on how noisy the input is; 0.18 is a permissive threshold
        if (!empty($scores) && $scores[$topIntent] >= 0.18) {
            return $topIntent;
        }

        return 'general';
    }

    /** Role detection based on intent */
    private function detectRoleByIntent(string $t): string
    {
        // Lowercase for case-insensitive matching
        $lower = mb_strtolower($t);
        
        // Cashier signals - check first as they're more specific
        $cashierSignals = [
            'process payment', 'collect payment', 'pending payment', 'payment processing',
            'shift report', 'daily report', 'cash summary', 'transactions today',
            'send receipt', 'email receipt', 'print receipt',
            'process refund', 'refund queue', 'pending refund', 'approved refund',
            'cashier dashboard', 'my shift', 'today revenue', 'cash collected'
        ];
        
        foreach ($cashierSignals as $signal) {
            if (strpos($lower, $signal) !== false) {
                return 'cashier';
            }
        }
        
        $adminSignals = [
            'analytics', 'report', 'no show', 'popular', 'forecast', 'workload', 
            'cancellation', 'underutilized', 'high risk', 'pending confirmation', 
            'staff performance', 'appointments today', 'how many appointments', 
            'new clients', 'how many user', 'how many client', 'total user', 
            'total client', 'total appointment', 'user count', 'client count',
            'system status', 'dashboard', 'overview', 'staff', 'all appointments',
            'pending appointments', 'completed appointments', 'cancelled appointments',
            'show me', 'list all', 'generate', 'create report', 'export',
            'system health', 'admin analytics', 'revenue report', 'user management'
        ];
        
        // Check for admin signals with fuzzy matching
        foreach ($adminSignals as $signal) {
            if (strpos($lower, $signal) !== false) {
                return 'admin';
            }
        }
        
        return 'client';
    }

    /**
     * Try to refine a 'general' intent using entity signals, fuzzy/phonetic cues
     * and contextual hints. Returns a more specific intent key or 'general'.
     */
    private function refineIntentUsingEntities(string $t, ?array $context, array $entities, array $analysis): string
    {
        // quick heuristics
        $lower = $t;

        // Safe similarity helper to avoid fatal errors when enhancements class is absent
        $fuzzy = function(string $haystack, string $needle): float {
            return class_exists(\App\Services\ChatbotServiceEnhancements::class)
                ? ChatbotServiceEnhancements::fuzzySimilarity($haystack, $needle)
                : 0.0;
        };

        // 1) explicit counting queries -> admin_counts or my_appointment
        if (strpos($lower, 'how many') !== false || strpos($lower, 'ilan') !== false || strpos($lower, 'how much') !== false) {
            // differentiate between price vs counts
            if ($fuzzy($lower, 'how much') >= 0.35 || strpos($lower, 'price') !== false || strpos($lower, 'magkano') !== false) {
                return 'price';
            }

            // if admin context or role signal exists, return admin_counts
            if (!empty($context['user_role']) && $context['user_role'] === 'admin') {
                return 'admin_counts';
            }

            // otherwise treat as a user count or appointment count request
            if (strpos($lower, 'user') !== false || strpos($lower, 'users') !== false || strpos($lower, 'client') !== false) {
                return 'admin_counts';
            }

            if (strpos($lower, 'appointment') !== false || strpos($lower, 'appointments') !== false) {
                // if client, show my appointment status
                return 'my_appointment';
            }
        }

        // 2) price-like or service-like question even if noisy
        if ($fuzzy($lower, 'how much') >= 0.40
            || $fuzzy($lower, 'what price') >= 0.40
            || strpos($lower, 'presyo') !== false || strpos($lower, 'magkano') !== false
        ) {
            return 'price';
        }

        // 3) time/location queries
        if ($fuzzy($lower, 'what time') >= 0.35
            || strpos($lower, 'oras') !== false || strpos($lower, 'anong oras') !== false
        ) {
            return 'hours';
        }

        if ($fuzzy($lower, 'where is office') >= 0.35
            || strpos($lower, 'saan') !== false || strpos($lower, 'address') !== false
        ) {
            return 'address';
        }

        // 4) service enquiries
        if ($fuzzy($lower, 'what services') >= 0.4
            || strpos($lower, 'service') !== false || strpos($lower, 'serbisyo') !== false
        ) {
            return 'services';
        }

        // 5) booking/reschedule/cancel hints via entities/actions
        if (!empty($entities['actions'])) {
            foreach ($entities['actions'] as $a) {
                if (in_array($a, ['book', 'schedule', 'reserve'], true)) return 'book';
                if (in_array($a, ['reschedule', 'move', 'change'], true)) return 'reschedule';
                if (in_array($a, ['cancel', 'remove', 'delete'], true)) return 'cancel';
            }
        }

        // 6) profanity with direct question -> treat as question, try fuzzy mapping
        if (!empty($analysis['sentiment']['profanity_terms'])) {
            // if includes question words, map to hours/address/price based on similarity
            if ($fuzzy($lower, 'what time') >= 0.25) return 'hours';
            if ($fuzzy($lower, 'where is office') >= 0.25) return 'address';
            if ($fuzzy($lower, 'how much') >= 0.25) return 'price';
        }

        return 'general';
    }

    /** Client intent handler */
    private function handleClientIntent(string $t, ?array $context, ?string $intent = null, array $entities = [], array $analysis = []): array
    {
        $resp = [ 'role' => 'CLIENT', 'reply' => '', 'suggestions' => [] ];
        $intent = $intent ?? $this->detectIntent($t);

        switch ($intent) {
            case 'book':
                $resp['reply'] = 'You can book via your dashboard. Choose a service, pick a date and time, then submit for approval.';
                if ($context) {
                    $resp['reply'] .= ' ' . $this->summarizeServices($context, $entities);
                }
                $resp['suggestions'] = ['What services do you offer?', 'What time slots are available?', 'What should I bring?'];
                break;
            case 'reschedule':
                $resp['reply'] = 'Open your appointment, tap Reschedule, then choose a new date and time that fits you.';
                if ($context) {
                    $resp['reply'] .= ' ' . $this->summarizeNextAppointment($context);
                }
                $resp['suggestions'] = ['Next available dates?', 'Any slots tomorrow?', 'How do I cancel?'];
                break;
            case 'cancel':
                $resp['reply'] = 'Open Appointments, select the appointment, and tap Cancel to confirm.';
                $resp['suggestions'] = ['Can I reschedule instead?', 'What are the cancellation rules?'];
                break;
            case 'availability':
                $resp['reply'] = $this->summarizeAvailability($context);
                $resp['suggestions'] = ['Any slot tomorrow?', 'Next week availability?', 'What services are available?'];
                break;
            case 'services':
                $resp['reply'] = $this->summarizeServices($context, $entities);
                $resp['suggestions'] = ['How much is the service?', 'How do I book an appointment?'];
                break;
            case 'price':
                $resp['reply'] = $this->summarizePrice($context, $entities);
                $resp['suggestions'] = ['Where is your office?', 'What services do you offer?'];
                break;
            case 'requirements':
                $resp['reply'] = $this->summarizeRequirements($context, $entities);
                $resp['suggestions'] = ['What is the price for this service?', 'Do you have same-day appointments?'];
                break;
            case 'address':
                $resp['reply'] = $context['business_info']['address'] ?? 'The office address is available once connected to the system.';
                $resp['reply'] .= isset($context['business_info']['phone']) ? ' Contact: ' . $context['business_info']['phone'] . '.' : '';
                $resp['suggestions'] = ['What are your hours?', 'How do I book an appointment?'];
                break;
            case 'hours':
                if (isset($context['business_info']['phone'], $context['business_info']['email'])) {
                    $resp['reply'] = 'Office hours vary by day. You can confirm by calling ' . $context['business_info']['phone'] . ' or emailing ' . $context['business_info']['email'] . '.';
                } else {
                    $resp['reply'] = 'Office hours can be confirmed once connected to the system. Feel free to ask for today.';
                }
                $resp['suggestions'] = ['Where is your office?', 'Do you have same-day appointments?'];
                break;
            case 'payment':
            case 'my_payment':
            case 'payment_status':
                // Get user's payment history
                if ($context && isset($context['user_id'])) {
                    $userId = $context['user_id'];
                    $payments = Payment::whereHas('appointment', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })
                    ->with(['appointment.service'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                    if ($payments->count() > 0) {
                        $latestPayment = $payments->first();
                        $resp['reply'] = "You have {$payments->count()} recent payment(s). Your latest payment of PHP " . 
                            number_format($latestPayment->amount, 2) . " for " . 
                            ($latestPayment->appointment->service->name ?? 'service') . 
                            " is " . ucfirst($latestPayment->status) . ".";
                        $resp['data'] = ['payments' => $payments->toArray()];
                    } else {
                        $resp['reply'] = "You don't have any payment records yet. Payments are processed after your appointment is confirmed.";
                    }
                } else {
                    $resp['reply'] = "Please log in to view your payment history and status.";
                }
                $resp['suggestions'] = ['Request a refund', 'View my appointments', 'How do payments work?'];
                break;
            case 'refund':
            case 'request_refund':
            case 'refund_status':
                // Get user's refund requests
                if ($context && isset($context['user_id'])) {
                    $userId = $context['user_id'];
                    $refunds = Refund::whereHas('appointment', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })
                    ->with(['appointment.service'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                    if ($refunds->count() > 0) {
                        $latestRefund = $refunds->first();
                        $resp['reply'] = "You have {$refunds->count()} refund request(s). Your latest request for PHP " . 
                            number_format($latestRefund->amount, 2) . " is " . ucfirst($latestRefund->status) . ".";
                        $resp['data'] = ['refunds' => $refunds->toArray()];
                    } else {
                        $resp['reply'] = "You don't have any refund requests. To request a refund, go to your appointment details and click 'Request Refund'.";
                    }
                } else {
                    $resp['reply'] = "Please log in to view or request refunds.";
                }
                $resp['suggestions'] = ['How do I request a refund?', 'What is the refund policy?', 'Check payment status'];
                break;
            case 'account':
                $resp['reply'] = 'Use Account Settings to update profile details, change email, or reset your password.';
                $resp['suggestions'] = ['How do I book an appointment?', 'What services do you offer?'];
                break;
            case 'status':
            case 'my_appointment':
                $resp['reply'] = $this->summarizeNextAppointment($context);
                $resp['suggestions'] = ['Can I reschedule my appointment?', 'What should I bring?'];
                break;
            default:
                if ($context && isset($context['client_data'])) {
                    $upcoming = $context['client_data']['upcoming_appointments'] ?? collect();
                    if (count($upcoming) > 0) {
                        $next = $upcoming[0];
                        $resp['reply'] = "Your next appointment is on {$next['date']} at {$next['time']} for {$next['service']} (Status: {$next['status']}).";
                        $resp['suggestions'] = ['Can I reschedule my appointment?', 'What should I bring?'];
                        break;
                    }
                }
                $resp['reply'] = 'How can I help with booking, rescheduling, cancelling, services, or availability?';
                $resp['suggestions'] = ['How do I book an appointment?', 'What time slots are available?'];
                break;
        }

        return $resp;
    }

    /**
     * Deterministic intent handler: returns structured, DB-backed answers for factual intents.
     */
    private function handleDeterministicIntent(string $intent, ?int $userId, ?array $context, array $entities = [], array $analysis = []): ?array
    {
        // Base response shape
        $resp = [ 'role' => 'SYSTEM', 'reply' => '', 'suggestions' => [], 'data' => null ];

        switch ($intent) {
            case 'my_appointment':
            case 'status':
                // Use client data if available
                $client = $context['client_data'] ?? null;
                if ($client && !empty($client['upcoming_appointments'])) {
                    $next = $client['upcoming_appointments'][0];
                    $resp['data'] = ['next_appointment' => $next];
                    $resp['reply'] = "Your next appointment is on {$next['date']} at {$next['time']} for {$next['service']} (Status: {$next['status']}).";
                    $resp['suggestions'] = ['Can I reschedule my appointment?', 'Cancel appointment'];
                } else {
                    $resp['reply'] = 'You do not have any upcoming appointments.';
                    $resp['suggestions'] = ['How do I book an appointment?', 'What services do you offer?'];
                }
                return $resp;

            case 'admin_counts':
                // Admin metrics are available via getAdminData
                $adminData = $this->getAdminData();
                $resp['data'] = $adminData;
                $resp['reply'] = "Total Users: {$adminData['total_users']}, Total Appointments: {$adminData['total_appointments']}, Today: {$adminData['today_appointments']}, Pending: {$adminData['pending_appointments']}";
                $resp['suggestions'] = ['Show pending confirmations', 'Which services are most popular?'];
                return $resp;

            case 'services':
                $services = $context['available_services'] ?? $this->getAvailableServices();
                $resp['data'] = ['services' => $services];
                if (!empty($services)) {
                    $names = array_map(fn($s) => $s['name'], $services);
                    $resp['reply'] = 'Active services: ' . implode(', ', $names) . (count($names) > 3 ? '…' : '.') ;
                } else {
                    $resp['reply'] = 'No active services are listed in the system.';
                }
                $resp['suggestions'] = ['How much is the service?', 'How do I book an appointment?'];
                return $resp;

            case 'price':
                // If a service was detected in entities, return its price
                if (!empty($entities['services'])) {
                    $service = $entities['services'][0];
                    $resp['data'] = ['service' => $service];
                    $resp['reply'] = ($service['name'] ?? 'Service') . ' costs ' . ($service['price'] ?? 'Contact for pricing') . '.';
                    $resp['suggestions'] = ['How do I book this service?', 'What should I bring?'];
                    return $resp;
                }

                // otherwise return first priced service
                $services = $context['available_services'] ?? $this->getAvailableServices();
                $priced = array_filter($services, fn($s) => isset($s['price']) && $s['price'] !== 'Contact for pricing');
                if (!empty($priced)) {
                    $first = array_values($priced)[0];
                    $resp['data'] = ['service' => $first];
                    $resp['reply'] = $first['name'] . ' is ' . $first['price'] . '.';
                } else {
                    $resp['reply'] = 'Pricing depends on the service selected and is paid in person at the office.';
                }
                $resp['suggestions'] = ['What services do you offer?', 'How do I book an appointment?'];
                return $resp;

            case 'hours':
            case 'address':
                $biz = $context['business_info'] ?? $this->getBusinessInfo();
                if ($intent === 'hours') {
                    $resp['reply'] = 'Office hours vary by day. You can call ' . ($biz['phone'] ?? 'our number') . ' to confirm today\'s hours.';
                } else {
                    $resp['reply'] = ($biz['address'] ?? 'Address not available') . (isset($biz['phone']) ? ' — Contact: ' . $biz['phone'] : '');
                }
                $resp['data'] = ['business_info' => $biz];
                $resp['suggestions'] = ['How do I book an appointment?', 'What services do you offer?'];
                return $resp;

            case 'availability':
                $availability = $context['availability'] ?? $this->getAvailabilitySummary();
                $resp['data'] = ['availability' => $availability];
                $blackouts = $availability['blackout_dates_count'] ?? 0;
                $rules = $availability['slot_capacity_rules'] ?? 0;
                $resp['reply'] = "Tracking {$blackouts} blackout date(s) and {$rules} slot capacity rule(s). Open the calendar for exact times.";
                $resp['suggestions'] = ['Any slot tomorrow?', 'What services are available?'];
                return $resp;

            case 'contact':
                $biz = $context['business_info'] ?? $this->getBusinessInfo();
                $resp['data'] = ['business_info' => $biz];
                $resp['reply'] = 'You can contact us at: Phone: ' . ($biz['phone'] ?? 'Not available') . ', Email: ' . ($biz['email'] ?? 'Not available') . ', Address: ' . ($biz['address'] ?? 'Not available') . '.';
                $resp['suggestions'] = ['What are your hours?', 'How do I book an appointment?'];
                return $resp;

            default:
                return null;
        }
    }

    /** Admin intent handler */
    private function handleAdminIntent(string $t, ?array $context, ?string $intent = null, array $entities = [], array $analysis = []): array
    {
        $resp = [ 'role' => 'ADMIN', 'reply' => '', 'metrics' => [], 'suggestions' => [] ];
        $intent = $intent ?? $this->detectIntent($t);

        switch ($intent) {
            case 'admin_counts':
                // Provide safe real-time counts available via models
                $today = Carbon::now()->startOfDay();
                $totalUsers = User::count();
                try {
                    $totalClients = User::role('client')->count();
                } catch (\Throwable $e) {
                    $totalClients = 0;
                }
                // Include soft-deleted appointments to mirror full system totals
                $totalAppointments = Appointment::withTrashed()->count();
                $todayAppointments = Appointment::whereDate('appointment_date', $today)->count();
                $pendingAppointments = Appointment::where('status', 'pending')->count();
                
                $resp['metrics'] = [
                    'total_users' => $totalUsers,
                    'total_clients' => $totalClients,
                    'total_appointments' => $totalAppointments,
                    'appointments_today' => $todayAppointments,
                    'pending' => $pendingAppointments,
                    'approved' => Appointment::where('status', 'approved')->count(),
                    'completed' => Appointment::where('status', 'completed')->count(),
                    'cancelled' => Appointment::where('status', 'cancelled')->count(),
                    'generated_at' => Carbon::now()->toIso8601String(),
                ];
                
                // Generate a more detailed response based on what was asked
                if (strpos($t, 'user') !== false) {
                    $resp['reply'] = "You currently have {$totalUsers} total users in the system";
                    if ($totalClients > 0) {
                        $resp['reply'] .= ", including {$totalClients} clients.";
                    } else {
                        $resp['reply'] .= ".";
                    }
                } elseif (strpos($t, 'appointment') !== false) {
                    $resp['reply'] = "System has {$totalAppointments} total appointments. Today: {$todayAppointments}, Pending: {$pendingAppointments}.";
                } else {
                    $resp['reply'] = "Total Users: {$totalUsers}, Total Clients: {$totalClients}, Total Appointments: {$totalAppointments}, Today: {$todayAppointments}, Pending: {$pendingAppointments}.";
                }
                
                $resp['suggestions'] = ['Show cancellation patterns', 'Which services are most popular?', 'How many appointments today?'];
                break;
            case 'admin_analytics':
                // Provide analytics we can compute now; avoid fabricating unknowns
                $resp['metrics'] = [
                    'completion_rate' => $this->getCompletionRate(),
                    'cancellation_rate' => $this->getCancellationRate(),
                    'top_services' => $this->getTopServices(5),
                ];
                $resp['reply'] = 'Current analytics available. For deeper insights, I can check that once I am connected to the system’s database.';
                $resp['suggestions'] = ['Any high-risk appointments?', 'Are time slots underutilized?'];
                break;
            default:
                if ($context && isset($context['admin_data'])) {
                    $data = $context['admin_data'];
                    $resp['metrics'] = $data;
                    $resp['reply'] = 'Admin overview is ready.';
                    $resp['suggestions'] = ['How many appointments tomorrow?', 'Which services are most popular?'];
                    break;
                }
                $resp['reply'] = 'What admin data do you need: counts, trends, or forecasts?';
                $resp['suggestions'] = ['Show pending confirmations.', 'Show demand forecast.'];
                break;
        }

        return $resp;
    }

    /**
     * Handle cashier-specific intents
     */
    private function handleCashierIntent(string $t, ?array $context, ?string $intent = null, array $entities = [], array $analysis = []): array
    {
        $resp = ['role' => 'CASHIER', 'reply' => '', 'metrics' => [], 'suggestions' => []];
        $intent = $intent ?? $this->detectIntent($t);

        switch ($intent) {
            case 'payment':
            case 'process_payment':
                // Get today's pending payments
                $today = Carbon::now()->startOfDay();
                $pendingPayments = Payment::where('status', 'pending')
                    ->whereDate('created_at', '>=', $today)
                    ->with(['appointment.user', 'appointment.service'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                $resp['metrics'] = [
                    'pending_count' => $pendingPayments->count(),
                    'pending_payments' => $pendingPayments->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'amount' => $p->amount,
                            'client' => $p->appointment->user->name ?? 'Unknown',
                            'service' => $p->appointment->service->name ?? 'Unknown',
                            'created_at' => $p->created_at->format('M d, Y h:i A'),
                        ];
                    })->toArray(),
                ];

                if ($pendingPayments->count() > 0) {
                    $resp['reply'] = "You have {$pendingPayments->count()} pending payment(s) to process today. The most recent is for " . 
                        ($pendingPayments->first()->appointment->user->name ?? 'a client') . 
                        " - PHP " . number_format($pendingPayments->first()->amount, 2) . ".";
                } else {
                    $resp['reply'] = "No pending payments to process at the moment. All payments are up to date.";
                }
                $resp['suggestions'] = ['Show all pending payments', 'Process a payment', 'View payment history'];
                break;

            case 'refund':
            case 'process_refund':
                // Get pending refund requests
                $pendingRefunds = Refund::where('status', 'pending')
                    ->with(['appointment.user', 'appointment.service'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                $resp['metrics'] = [
                    'pending_count' => $pendingRefunds->count(),
                    'pending_refunds' => $pendingRefunds->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'amount' => $r->amount,
                            'reason' => $r->reason,
                            'client' => $r->appointment->user->name ?? 'Unknown',
                            'service' => $r->appointment->service->name ?? 'Unknown',
                            'requested_at' => $r->created_at->format('M d, Y h:i A'),
                        ];
                    })->toArray(),
                ];

                if ($pendingRefunds->count() > 0) {
                    $resp['reply'] = "There are {$pendingRefunds->count()} pending refund request(s). " .
                        "The latest is from " . ($pendingRefunds->first()->appointment->user->name ?? 'a client') .
                        " for PHP " . number_format($pendingRefunds->first()->amount, 2) . ".";
                } else {
                    $resp['reply'] = "No pending refund requests at the moment.";
                }
                $resp['suggestions'] = ['Show refund details', 'Approve refund', 'View refund history'];
                break;

            case 'receipt':
            case 'generate_receipt':
                $resp['reply'] = "To generate a receipt, please provide the payment ID or appointment reference number. " .
                    "You can also access receipts through the Payments section in your dashboard.";
                $resp['suggestions'] = ['Show recent payments', 'Search payment by ID', 'View today\'s receipts'];
                break;

            case 'verify_payment':
                $resp['reply'] = "To verify a payment, please provide the payment ID, reference number, or the client's name. " .
                    "I can then check the payment status and details for you.";
                $resp['suggestions'] = ['Search by payment ID', 'Search by client name', 'Show unverified payments'];
                break;

            case 'daily_sales':
            case 'sales_report':
                $today = Carbon::now()->startOfDay();
                $todayPayments = Payment::whereDate('created_at', $today)
                    ->where('status', 'completed')
                    ->get();
                
                $totalSales = $todayPayments->sum('amount');
                $transactionCount = $todayPayments->count();

                $resp['metrics'] = [
                    'total_sales' => $totalSales,
                    'transaction_count' => $transactionCount,
                    'date' => $today->format('M d, Y'),
                ];

                $resp['reply'] = "Today's sales report: PHP " . number_format($totalSales, 2) . 
                    " from {$transactionCount} completed transaction(s).";
                $resp['suggestions'] = ['Show breakdown by service', 'Compare with yesterday', 'View pending payments'];
                break;

            case 'shift_report':
                $today = Carbon::now();
                $shiftStart = $today->copy()->setTime(8, 0, 0); // Assuming 8 AM shift start
                
                $shiftPayments = Payment::where('created_at', '>=', $shiftStart)
                    ->where('status', 'completed')
                    ->get();
                
                $shiftRefunds = Refund::where('created_at', '>=', $shiftStart)
                    ->where('status', 'approved')
                    ->get();

                $resp['metrics'] = [
                    'shift_start' => $shiftStart->format('h:i A'),
                    'payments_processed' => $shiftPayments->count(),
                    'total_collected' => $shiftPayments->sum('amount'),
                    'refunds_processed' => $shiftRefunds->count(),
                    'total_refunded' => $shiftRefunds->sum('amount'),
                    'net_amount' => $shiftPayments->sum('amount') - $shiftRefunds->sum('amount'),
                ];

                $resp['reply'] = "Shift report since " . $shiftStart->format('h:i A') . ": " .
                    "Processed {$shiftPayments->count()} payment(s) totaling PHP " . number_format($shiftPayments->sum('amount'), 2) . ". " .
                    "Refunds: {$shiftRefunds->count()} for PHP " . number_format($shiftRefunds->sum('amount'), 2) . ". " .
                    "Net: PHP " . number_format($shiftPayments->sum('amount') - $shiftRefunds->sum('amount'), 2) . ".";
                $resp['suggestions'] = ['End shift summary', 'Print shift report', 'View payment details'];
                break;

            default:
                // General cashier assistance
                if ($context && isset($context['cashier_data'])) {
                    $data = $context['cashier_data'];
                    $resp['metrics'] = $data;
                    $resp['reply'] = "Welcome! You have {$data['pending_payments']} pending payment(s) and " .
                        "{$data['pending_refunds']} pending refund request(s) to review.";
                } else {
                    $resp['reply'] = "How can I assist you today? I can help with payments, refunds, receipts, and sales reports.";
                }
                $resp['suggestions'] = ['Show pending payments', 'Check refund requests', 'Today\'s sales report', 'Generate receipt'];
                break;
        }

        return $resp;
    }

    /** Build a short services blurb, prioritizing the service mentioned. */
    private function summarizeServices(?array $context, array $entities = []): string
    {
        $services = $context['available_services'] ?? [];
        if (!empty($entities['services'])) {
            $service = $entities['services'][0];
            $price = $service['price'] ?? null;
            $duration = $service['duration'] ?? null;
            $parts = ["You mentioned {$service['name']}."];
            if ($duration) {
                $parts[] = "Duration: {$duration}.";
            }
            if ($price) {
                $parts[] = "Price: {$price}.";
            }
            return implode(' ', $parts);
        }

        if (!empty($services)) {
            $top = array_slice($services, 0, 3);
            $names = array_map(fn ($s) => $s['name'], $top);
            $suffix = count($services) > 3 ? '…' : '';
            return 'Active services: ' . implode(', ', $names) . $suffix . '.';
        }

        return 'I can list active services once connected to the system database.';
    }

    /** Summarize availability without fabricating slot details. */
    private function summarizeAvailability(?array $context): string
    {
        $availability = $context['availability'] ?? null;
        if ($availability) {
            $blackouts = $availability['blackout_dates_count'] ?? 0;
            $rules = $availability['slot_capacity_rules'] ?? 0;
            return "I can check open slots. Currently tracking {$blackouts} blackout date(s) and {$rules} slot capacity rule(s). Please open the calendar to see exact times.";
        }

        return 'I can check available slots once I am connected to the system’s database.';
    }

    /** Summarize price with any detected service reference. */
    private function summarizePrice(?array $context, array $entities = []): string
    {
        if (!empty($entities['services'])) {
            $service = $entities['services'][0];
            if (!empty($service['price'])) {
                return $service['name'] . ' costs ' . $service['price'] . '. Payment is in person at the office.';
            }
        }

        $services = $context['available_services'] ?? [];
        if (!empty($services)) {
            $priced = array_filter($services, fn ($s) => isset($s['price']) && $s['price'] !== 'Contact for pricing');
            if (!empty($priced)) {
                $first = array_values($priced)[0];
                return $first['name'] . ' is ' . $first['price'] . '. Other services vary; payment is handled in-office.';
            }
        }

        return 'Pricing depends on the service selected and is paid in person at the office.';
    }

    /** Summarize requirements using detected service if present. */
    private function summarizeRequirements(?array $context, array $entities = []): string
    {
        $base = 'Please bring a valid government ID and any documents relevant to your chosen service.';
        if (!empty($entities['services'])) {
            return $base . ' This is for ' . ($entities['services'][0]['name'] ?? 'the selected service') . '.';
        }
        return $base;
    }

    /** Next appointment summary for status inquiries. */
    private function summarizeNextAppointment(?array $context): string
    {
        if (!$context || empty($context['client_data'])) {
            return 'I can show your appointment details once connected to your account.';
        }

        $upcoming = $context['client_data']['upcoming_appointments'] ?? [];
        if (!empty($upcoming)) {
            $next = $upcoming[0];
            return "Your next appointment is on {$next['date']} at {$next['time']} for {$next['service']} (Status: {$next['status']}).";
        }

        return 'You do not have any upcoming appointments yet.';
    }

    /**
     * Build an enhanced system prompt with real system data
     */
    public function buildEnhancedSystemPrompt($userId)
    {
        $context = $this->getSystemContext($userId);
        
        if (!$context) {
            return $this->getDefaultSystemPrompt();
        }

        $role = $context['user_role'];
        $basePrompt = "You are a professional and knowledgeable assistant for Peejayy De Guzman Legal - a premier notary services and legal consultation provider.\n\n";
        
        $basePrompt .= "=== BUSINESS INFORMATION ===\n";
        $basePrompt .= "Company: {$context['business_info']['company_name']}\n";
        $basePrompt .= "Specialization: {$context['business_info']['type']}\n";
        $basePrompt .= "Address: {$context['business_info']['address']}\n";
        $basePrompt .= "Phone: {$context['business_info']['phone']}\n";
        $basePrompt .= "Email: {$context['business_info']['email']}\n";
        $basePrompt .= "Payment Terms: Personal and happens in the office\n\n";

        $basePrompt .= "=== SPECIALTIES ===\n";
        foreach ($context['business_info']['specialties'] as $specialty) {
            $basePrompt .= "• {$specialty}\n";
        }
        $basePrompt .= "\n";

        // Appointment types are derived from active services below (no hardcoded types)

        $basePrompt .= "=== SERVICES OFFERED ===\n";
        foreach ($context['available_services'] as $service) {
            $basePrompt .= "• {$service['name']} ({$service['duration']}) - {$service['price']}\n";
        }
        $basePrompt .= "\n";

        $basePrompt .= "=== SYSTEM CAPABILITIES ===\n";
        $basePrompt .= "• Online appointment booking and scheduling\n";
        $basePrompt .= "• Appointment rescheduling and cancellation\n";
        $basePrompt .= "• Document upload support\n";
        $basePrompt .= "• Real-time appointment status tracking\n";
        $basePrompt .= "• Message center for client-staff communication\n";
        $basePrompt .= "• Calendar management and slot capacity tracking\n";
        $basePrompt .= "• User profile and account management\n";
        $basePrompt .= "• Admin dashboard for staff and analytics\n\n";

        $basePrompt .= "=== USER CONTEXT ===\n";
        $basePrompt .= "User: {$context['user_name']} (Role: {$role})\n";
        $basePrompt .= "Email: {$context['user_email']}\n";
        $basePrompt .= "Phone: {$context['user_phone']}\n\n";

        // Add role-specific context
        if ($role === 'client' && isset($context['client_data'])) {
            $data = $context['client_data'];
            $basePrompt .= "=== CLIENT PROFILE ===\n";
            $basePrompt .= "Total Appointments: {$data['total_appointments']}\n";
            $basePrompt .= "Upcoming Appointments: " . count($data['upcoming_appointments']) . "\n";
            $basePrompt .= "Status Breakdown: {$data['pending_appointments']} Pending | {$data['confirmed_appointments']} Confirmed | {$data['cancelled_appointments']} Cancelled\n\n";
            
            if (count($data['upcoming_appointments']) > 0) {
                $basePrompt .= "Upcoming Appointments:\n";
                foreach ($data['upcoming_appointments'] as $apt) {
                    $basePrompt .= "  - {$apt['date']} at {$apt['time']} ({$apt['service']}) - Status: {$apt['status']}\n";
                }
                $basePrompt .= "\n";
            }
        } elseif ($role === 'staff' && isset($context['staff_data'])) {
            $data = $context['staff_data'];
            $basePrompt .= "=== STAFF DASHBOARD ===\n";
            $basePrompt .= "Today's Appointments: {$data['today_appointments']}\n";
            $basePrompt .= "Tomorrow's Appointments: {$data['tomorrow_appointments']}\n";
            $basePrompt .= "Pending Confirmations: {$data['pending_confirmations']}\n";
            $basePrompt .= "Approved Appointments (Upcoming): {$data['approved_appointments']}\n";
            $basePrompt .= "Completed This Month: {$data['completed_this_month']}\n";
            $basePrompt .= "Total Cancellations: {$data['total_cancellations']}\n\n";
        } elseif ($role === 'admin' && isset($context['admin_data'])) {
            $data = $context['admin_data'];
            $basePrompt .= "=== SYSTEM STATISTICS ===\n";
            $basePrompt .= "Total Users: {$data['total_users']} ({$data['total_clients']} clients | {$data['total_staff']} staff | {$data['total_admins']} admins)\n";
            $basePrompt .= "Total Appointments: {$data['total_appointments']}\n";
            $basePrompt .= "  - Today: {$data['today_appointments']}\n";
            $basePrompt .= "  - Pending: {$data['pending_appointments']}\n";
            $basePrompt .= "  - Approved: {$data['approved_appointments']}\n";
            $basePrompt .= "  - Completed: {$data['completed_appointments']}\n";
            $basePrompt .= "  - Cancelled: {$data['cancelled_appointments']}\n";
            $basePrompt .= "Services: {$data['active_services']} active out of {$data['total_services']}\n";
            $basePrompt .= "Completion Rate: {$data['appointment_completion_rate']}%\n";
            $basePrompt .= "Cancellation Rate: {$data['cancellation_rate']}%\n\n";

            if (!empty($data['top_services'])) {
                $basePrompt .= "Top Services:\n";
                foreach ($data['top_services'] as $service) {
                    $basePrompt .= "  - {$service['name']}: {$service['appointments']} appointments\n";
                }
                $basePrompt .= "\n";
            }
        } elseif ($role === 'cashier' && isset($context['cashier_data'])) {
            $data = $context['cashier_data'];
            $basePrompt .= "=== CASHIER DASHBOARD ===\n";
            $basePrompt .= "Today's Transactions: " . ($data['today_transactions'] ?? 0) . "\n";
            $basePrompt .= "Today's Revenue: ₱" . number_format($data['today_revenue'] ?? 0, 2) . "\n";
            $basePrompt .= "Pending Payments: " . ($data['pending_payments'] ?? 0) . "\n";
            $basePrompt .= "Pending Refunds: " . ($data['pending_refunds'] ?? 0) . "\n";
            $basePrompt .= "\n";
        }

        // No static FAQs; assistant relies on current DB-backed context.
        $basePrompt .= "=== GUIDELINES ===\n";
        $basePrompt .= "1. KEEP RESPONSES SHORT: 1-3 sentences maximum. Be direct and concise.\n";
        $basePrompt .= "2. Use provided real data to give accurate, specific responses\n";
        $basePrompt .= "3. For {$role}s, prioritize relevant features and workflows\n";
        $basePrompt .= "4. Be professional and friendly but brief\n";
        $basePrompt .= "5. When unsure, suggest contacting support at {$context['business_info']['phone']}\n";
        $basePrompt .= "6. Never make up features or data that doesn't exist\n";
        $basePrompt .= "7. DO NOT repeat information already shown in context\n";
        $basePrompt .= "8. Current user role is: {$role} - respond appropriately for this role\n";

        return $basePrompt;
    }

    /**
     * Get default system prompt
     */
    private function getDefaultSystemPrompt()
    {
        // Minimal default prompt without hardcoded Q&A or services; relies on DB when available
        return "You are the AI assistant for a law-notary appointment system. Use real-time system data when available. If data access is unavailable, respond concisely and note that you can check once connected to the system’s database.";
    }
}
