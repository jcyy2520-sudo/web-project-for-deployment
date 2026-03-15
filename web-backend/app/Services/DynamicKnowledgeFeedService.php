<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * DynamicKnowledgeFeedService
 *
 * Dynamically feeds structured system information to the AI at runtime.
 * Serves as the "system knowledge ingestion layer" that keeps the AI
 * continuously updated about:
 *
 * - Database structure changes
 * - New/modified API endpoints
 * - UI navigation flows
 * - Business rule updates
 * - Workflow changes
 * - Error patterns and resolutions
 *
 * This service does NOT hard-code responses. It discovers and structures
 * system information so the LLM can reason about it intelligently.
 */
class DynamicKnowledgeFeedService
{
    private const CACHE_TTL = 1800; // 30 minutes for structural data
    private const RUNTIME_CACHE_TTL = 300; // 5 min for runtime stats

    /**
     * Get the full system knowledge feed for a given role/context.
     * This is designed to be injected into the system prompt or context window.
     */
    public function getSystemKnowledgeFeed(string $role, ?int $userId = null): array
    {
        return [
            'database_schema' => $this->getDatabaseAwareness($role),
            'api_capabilities' => $this->getAPICapabilities($role),
            'ui_navigation' => $this->getUINavigationMap($role),
            'business_rules' => $this->getBusinessRules(),
            'error_patterns' => $this->getCommonErrorPatterns(),
            'recent_changes' => $this->getRecentSystemChanges(),
            'user_behavior_patterns' => $userId ? $this->getUserBehaviorPatterns($userId) : [],
        ];
    }

    /**
     * Convert the full knowledge feed into a compact string for the system prompt.
     */
    public function getKnowledgeFeedAsPromptSection(string $role, ?int $userId = null): string
    {
        $feed = $this->getSystemKnowledgeFeed($role, $userId);
        $sections = [];

        if (!empty($feed['ui_navigation'])) {
            $sections[] = "### UI Navigation Guide\n" . $feed['ui_navigation'];
        }
        if (!empty($feed['error_patterns'])) {
            $sections[] = "### Common Issues & Resolutions\n" . $feed['error_patterns'];
        }
        if (!empty($feed['recent_changes'])) {
            $sections[] = "### Recent System Updates\n" . $feed['recent_changes'];
        }
        if (!empty($feed['user_behavior_patterns'])) {
            $sections[] = "### This User's Patterns\n" . $feed['user_behavior_patterns'];
        }

        return implode("\n\n", $sections);
    }

    /**
     * Discover and describe the database schema in natural language.
     */
    public function getDatabaseAwareness(string $role): string
    {
        return Cache::remember("knowledge_feed_db_{$role}", self::CACHE_TTL, function () use ($role) {
            $desc = [];

            // Core tables everyone should know about conceptually
            $desc[] = "The system manages: Services, Appointments, Payments, Refunds, Users, Messages, Documents, Notifications.";

            // Appointment statuses from actual data
            try {
                $statuses = DB::table('appointments')
                    ->select('status')
                    ->distinct()
                    ->pluck('status')
                    ->toArray();
                if (!empty($statuses)) {
                    $desc[] = "Appointment statuses in use: " . implode(', ', $statuses);
                }
            } catch (\Exception $e) {
                $desc[] = "Appointment statuses: pending, approved, completed, cancelled, declined";
            }

            // Payment methods from actual data
            try {
                $methods = DB::table('payment_methods')
                    ->where('is_active', true)
                    ->pluck('name')
                    ->toArray();
                if (!empty($methods)) {
                    $desc[] = "Payment methods: " . implode(', ', $methods);
                }
            } catch (\Exception $e) {
                // ignore
            }

            // Discount types
            try {
                $discounts = DB::table('discount_rates')
                    ->where('is_active', true)
                    ->get(['discount_type', 'discount_percentage']);
                if ($discounts->isNotEmpty()) {
                    $discountList = $discounts->map(fn($d) => "{$d->discount_type}: {$d->discount_percentage}%")->toArray();
                    $desc[] = "Available discounts: " . implode(', ', $discountList);
                }
            } catch (\Exception $e) {
                // ignore
            }

            if ($role === 'admin') {
                // Admin gets more structural info
                try {
                    $userRoles = DB::table('users')
                        ->select('role')
                        ->distinct()
                        ->pluck('role')
                        ->toArray();
                    $desc[] = "User roles in system: " . implode(', ', $userRoles);
                } catch (\Exception $e) {
                    // ignore
                }
            }

            return implode("\n", $desc);
        });
    }

    /**
     * Discover API capabilities from registered routes.
     */
    public function getAPICapabilities(string $role): string
    {
        return Cache::remember("knowledge_feed_api_{$role}", self::CACHE_TTL, function () use ($role) {
            $caps = [];

            // Describe capabilities by domain, not raw endpoints
            $allCaps = [
                'guest' => [
                    'View public services and pricing',
                    'View business information and hours',
                    'Register for an account',
                    'Contact the office',
                ],
                'client' => [
                    'Book, view, cancel appointments',
                    'View payment history and receipts',
                    'Request refunds',
                    'Update profile',
                    'Send/receive messages',
                    'View notifications',
                    'Upload documents for appointments',
                    'Submit feedback',
                ],
                'cashier' => [
                    'Process payments',
                    'Generate shift reports',
                    'Process approved refund payouts',
                    'View daily transaction summaries',
                    'Verify payment receipts',
                ],
                'admin' => [
                    'Full appointment management (approve, decline, complete)',
                    'User account management',
                    'Service management (create, edit, disable)',
                    'Payment and refund oversight',
                    'System analytics and reports',
                    'Announcement management',
                    'System settings and configuration',
                    'Calendar and blackout date management',
                    'Audit log review',
                    'Feedback moderation',
                ],
            ];

            // Include own role + lower roles
            $roleHierarchy = ['guest', 'client', 'cashier', 'admin'];
            $roleIndex = array_search($role, $roleHierarchy);
            if ($roleIndex === false) $roleIndex = 0;

            for ($i = 0; $i <= $roleIndex; $i++) {
                $r = $roleHierarchy[$i];
                if (isset($allCaps[$r])) {
                    foreach ($allCaps[$r] as $cap) {
                        $caps[] = "- {$cap}";
                    }
                }
            }

            return implode("\n", array_unique($caps));
        });
    }

    /**
     * Provide UI navigation guidance dynamically.
     */
    public function getUINavigationMap(string $role): string
    {
        return Cache::remember("knowledge_feed_ui_{$role}", self::CACHE_TTL, function () use ($role) {
            $nav = [];

            // Common navigation
            $nav[] = "- Homepage: Landing page with office info and service overview";
            $nav[] = "- Services page: Shows all available services with pricing";
            $nav[] = "- Login/Register: Account access";

            if (in_array($role, ['client', 'admin', 'cashier'])) {
                $nav[] = "- Dashboard: Central hub after login — shows role-specific overview";
                $nav[] = "- My Appointments: View/manage personal appointments";
                $nav[] = "- Profile: Update personal info and preferences";
                $nav[] = "- Messages: Internal messaging between users and staff";
                $nav[] = "- Notifications: System alerts and updates";
            }

            if ($role === 'admin') {
                $nav[] = "- Admin Dashboard: System overview with pending items, stats, and quick actions";
                $nav[] = "- User Management: View, search, edit, activate/deactivate accounts";
                $nav[] = "- Service Management: Add, edit, or disable services";
                $nav[] = "- Appointment Management: View all appointments, filter by status, approve/decline";
                $nav[] = "- Payment Management: View all payments, process refunds";
                $nav[] = "- Analytics: Charts and reports on system usage, revenue, and trends";
                $nav[] = "- Settings: Business hours, blackout dates, appointment limits";
                $nav[] = "- Announcements: Create and manage announcements";
                $nav[] = "- Audit Log: Review all system actions";
            }

            if ($role === 'cashier') {
                $nav[] = "- Cashier Dashboard: Today's summary, pending payments, shift overview";
                $nav[] = "- Payment Processing: Process pending payments for approved appointments";
                $nav[] = "- Refund Processing: Process approved refund payouts";
                $nav[] = "- Reports: Generate daily/shift reports";
            }

            return implode("\n", $nav);
        });
    }

    /**
     * Discover business rules from system configuration.
     */
    public function getBusinessRules(): string
    {
        return Cache::remember('knowledge_feed_rules', self::CACHE_TTL, function () {
            $rules = [];

            $rules[] = "- Appointment booking requires a registered account";
            $rules[] = "- Appointments go through approval: Pending → Approved → Completed";
            $rules[] = "- Only admins can approve or decline appointment requests";
            $rules[] = "- Payments are processed by cashiers/admins after appointment approval";
            $rules[] = "- Refunds must be requested, then approved by admin, then processed by cashier";
            $rules[] = "- Users can cancel their own pending appointments";
            $rules[] = "- Approved appointments cannot be cancelled by the client (must contact admin)";

            // Dynamic rules from database
            try {
                $settings = DB::table('appointment_settings')->first();
                if ($settings) {
                    if (isset($settings->daily_booking_limit_per_user) && $settings->daily_booking_limit_per_user > 0) {
                        $rules[] = "- Maximum {$settings->daily_booking_limit_per_user} appointment(s) per user per day";
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            // Refund reasons
            try {
                $reasons = DB::table('refund_reasons')
                    ->where('is_active', true)
                    ->pluck('label')
                    ->toArray();
                if (!empty($reasons)) {
                    $rules[] = "- Valid refund reasons: " . implode(', ', $reasons);
                }
            } catch (\Exception $e) {
                // ignore
            }

            return implode("\n", $rules);
        });
    }

    /**
     * Get common error patterns and their resolutions from interaction logs.
     */
    public function getCommonErrorPatterns(): string
    {
        return Cache::remember('knowledge_feed_errors', self::RUNTIME_CACHE_TTL, function () {
            $patterns = [];

            try {
                // Find common negative feedback categories
                $feedbackPatterns = DB::table('chatbot_feedback')
                    ->where('is_helpful', false)
                    ->whereNotNull('feedback_category')
                    ->select('feedback_category', DB::raw('COUNT(*) as count'))
                    ->groupBy('feedback_category')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get();

                if ($feedbackPatterns->isNotEmpty()) {
                    foreach ($feedbackPatterns as $pattern) {
                        $patterns[] = "- Common issue: {$pattern->feedback_category} (reported {$pattern->count} times)";
                    }
                }
            } catch (\Exception $e) {
                // Tables might not exist yet
            }

            try {
                // Recent corrections users provided
                $corrections = DB::table('chatbot_feedback')
                    ->whereNotNull('correction_text')
                    ->where('correction_text', '!=', '')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->pluck('correction_text')
                    ->toArray();

                if (!empty($corrections)) {
                    $patterns[] = "- Recent user corrections to learn from:";
                    foreach ($corrections as $c) {
                        $patterns[] = "  - " . mb_substr($c, 0, 200);
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            if (empty($patterns)) {
                return '';
            }

            return implode("\n", $patterns);
        });
    }

    /**
     * Get recent system changes (new services, price updates, etc.)
     */
    public function getRecentSystemChanges(): string
    {
        return Cache::remember('knowledge_feed_changes', self::RUNTIME_CACHE_TTL, function () {
            $changes = [];

            try {
                // Recently added/updated services
                $recentServices = DB::table('services')
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->orderByDesc('updated_at')
                    ->limit(5)
                    ->get(['name', 'price', 'is_active', 'updated_at']);

                if ($recentServices->isNotEmpty()) {
                    $changes[] = "Recently updated services:";
                    foreach ($recentServices as $svc) {
                        $status = $svc->is_active ? 'active' : 'disabled';
                        $changes[] = "- {$svc->name}: ₱" . number_format($svc->price, 2) . " ({$status}, updated {$svc->updated_at})";
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            try {
                // Recent announcements
                $announcements = DB::table('announcements')
                    ->where('is_active', true)
                    ->where('published_at', '>=', now()->subDays(7))
                    ->orderByDesc('published_at')
                    ->limit(3)
                    ->get(['title', 'message', 'priority']);

                if ($announcements->isNotEmpty()) {
                    $changes[] = "Recent announcements:";
                    foreach ($announcements as $a) {
                        $changes[] = "- [{$a->priority}] {$a->title}: " . mb_substr($a->message, 0, 150);
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            if (empty($changes)) {
                return '';
            }

            return implode("\n", $changes);
        });
    }

    /**
     * Discover behavior patterns for a specific user (for personalization).
     */
    public function getUserBehaviorPatterns(?int $userId): string
    {
        if (!$userId) return '';

        return Cache::remember("knowledge_feed_user_{$userId}", self::RUNTIME_CACHE_TTL, function () use ($userId) {
            $patterns = [];

            try {
                // User's most common intents
                $topIntents = DB::table('chatbot_analytics')
                    ->where('user_id', $userId)
                    ->whereNotNull('detected_intent')
                    ->select('detected_intent', DB::raw('COUNT(*) as count'))
                    ->groupBy('detected_intent')
                    ->orderByDesc('count')
                    ->limit(3)
                    ->get();

                if ($topIntents->isNotEmpty()) {
                    $intents = $topIntents->map(fn($i) => $i->detected_intent)->toArray();
                    $patterns[] = "User's most frequent topics: " . implode(', ', $intents);
                }
            } catch (\Exception $e) {
                // ignore
            }

            try {
                // Preferred language
                $langPref = DB::table('chatbot_conversations')
                    ->where('user_id', $userId)
                    ->whereNotNull('detected_language')
                    ->select('detected_language', DB::raw('COUNT(*) as count'))
                    ->groupBy('detected_language')
                    ->orderByDesc('count')
                    ->first();

                if ($langPref) {
                    $patterns[] = "Preferred language: {$langPref->detected_language}";
                }
            } catch (\Exception $e) {
                // ignore
            }

            try {
                // Long-term memory
                $memories = DB::table('user_long_term_memory')
                    ->where('user_id', $userId)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->orderByDesc('relevance_score')
                    ->limit(5)
                    ->get(['key', 'value', 'category']);

                if ($memories->isNotEmpty()) {
                    foreach ($memories as $m) {
                        $patterns[] = "Remembered: [{$m->category}] {$m->key} = {$m->value}";
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            return implode("\n", $patterns);
        });
    }

    /**
     * Invalidate all knowledge feed caches.
     * Call this when system configuration, services, or rules change.
     */
    public function invalidateAll(): void
    {
        $roles = ['guest', 'client', 'admin', 'cashier'];
        foreach ($roles as $role) {
            Cache::forget("knowledge_feed_db_{$role}");
            Cache::forget("knowledge_feed_api_{$role}");
            Cache::forget("knowledge_feed_ui_{$role}");
        }
        Cache::forget('knowledge_feed_rules');
        Cache::forget('knowledge_feed_errors');
        Cache::forget('knowledge_feed_changes');
    }
}
