<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotService - Advanced AI assistant with fuzzy understanding
 * Provides dynamic, real-time system data with intelligent message interpretation
 * Supports natural language understanding including misspellings, slang, Taglish, and broken grammar
 */
class ChatbotService
{
    /**
     * Get system context data for the current user
     * This data is used to build a more intelligent system prompt
     */
    public function getSystemContext($userId)
    {
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
            'admin_metrics' => $role === 'admin' ? $this->getAdminMetrics() : null,
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
        }

        return $context;
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
        if (!class_exists(\App\Models\AppointmentSettings::class)) {
            return [
                'max_per_week' => null,
                'same_day_allowed' => false,
                'reschedule_window_days' => null,
                'cancellation_window_days' => null,
            ];
        }

        $settings = AppointmentSettings::query()->latest()->first();
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
            'total_appointments' => Appointment::count(),
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
    public function getSuggestedQuestions($userId)
    {
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
     */
    public function interpretAndRespond(int $userId, string $message): array
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
        $actualRole = $context['user_role'] ?? 'client';
        $intentBasedRole = $this->detectRoleByIntent($normalized);
        
        // Use actual role if user is admin/staff, otherwise use intent-based detection
        $role = ($actualRole === 'admin' || $actualRole === 'staff' || $intentBasedRole === 'admin') 
            ? 'admin' 
            : 'client';

        // Deterministic intents: for factual queries we prefer server-side answers
        $deterministicIntents = ['my_appointment','admin_counts','services','price','hours','address','availability','status'];
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
        $deterministicIntents = ['my_appointment','admin_counts','services','price','hours','address','availability','status'];
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
        $adminSignals = [
            'analytics', 'report', 'no show', 'popular', 'forecast', 'workload', 
            'cancellation', 'underutilized', 'high risk', 'pending confirmation', 
            'staff performance', 'appointments today', 'how many appointments', 
            'new clients', 'how many user', 'how many client', 'total user', 
            'total client', 'total appointment', 'user count', 'client count',
            'system status', 'dashboard', 'overview'
        ];
        foreach ($adminSignals as $s) {
            if (strpos($t, $s) !== false) return 'admin';
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

        // 1) explicit counting queries -> admin_counts or my_appointment
        if (strpos($lower, 'how many') !== false || strpos($lower, 'ilan') !== false || strpos($lower, 'how much') !== false) {
            // differentiate between price vs counts
            if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'how much') >= 0.35 || strpos($lower, 'price') !== false || strpos($lower, 'magkano') !== false) {
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
        if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'how much') >= 0.40
            || ChatbotServiceEnhancements::fuzzySimilarity($lower, 'what price') >= 0.40
            || strpos($lower, 'presyo') !== false || strpos($lower, 'magkano') !== false
        ) {
            return 'price';
        }

        // 3) time/location queries
        if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'what time') >= 0.35
            || strpos($lower, 'oras') !== false || strpos($lower, 'anong oras') !== false
        ) {
            return 'hours';
        }

        if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'where is office') >= 0.35
            || strpos($lower, 'saan') !== false || strpos($lower, 'address') !== false
        ) {
            return 'address';
        }

        // 4) service enquiries
        if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'what services') >= 0.4
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
            if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'what time') >= 0.25) return 'hours';
            if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'where is office') >= 0.25) return 'address';
            if (ChatbotServiceEnhancements::fuzzySimilarity($lower, 'how much') >= 0.25) return 'price';
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
    private function handleDeterministicIntent(string $intent, int $userId, ?array $context, array $entities = [], array $analysis = []): ?array
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
                $totalAppointments = Appointment::count();
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
        }

        // No static FAQs; assistant relies on current DB-backed context.
        $basePrompt .= "=== GUIDELINES ===\n";
        $basePrompt .= "1. Use provided real data to give accurate, specific responses\n";
        $basePrompt .= "2. For {$role}s, prioritize relevant features and workflows\n";
        $basePrompt .= "3. Be professional, courteous, knowledgeable, and concise\n";
        $basePrompt .= "4. Provide actionable guidance for common tasks\n";
        $basePrompt .= "5. When unsure, suggest contacting support at {$context['business_info']['phone']} or {$context['business_info']['email']}\n";
        $basePrompt .= "6. Never make up features that don't exist in the system\n";
        $basePrompt .= "7. Always maintain professional demeanor\n";
        $basePrompt .= "8. Help clients understand the appointment process clearly\n";

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
