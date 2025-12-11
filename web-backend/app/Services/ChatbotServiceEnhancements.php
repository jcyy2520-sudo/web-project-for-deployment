<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced ChatbotService - Advanced NLU with Role-Based Intelligence
 *
 * ✅ Natural Language Understanding (NLU) - Handles messy, misspelled, slang, Taglish
 * ✅ Fuzzy Intent Recognition - Detects intent even with unclear messages
 * ✅ Role-Based Response System - Different responses for User, Admin, Cashier
 * ✅ Action-Based Capabilities - Execute system actions through chat
 * ✅ Real-Time Data Integration - Always uses current database values
 */
class ChatbotServiceEnhancements
{
    // ================== ENTITY EXTRACTION ==================
    
    /**
     * Extract entities from normalized text (dates, numbers, service names)
     * Helps with contextual understanding beyond simple intent detection
     */
    public static function extractEntities(string $text, array $context): array
    {
        $entities = [
            'dates' => [],
            'times' => [],
            'numbers' => [],
            'services' => [],
            'actions' => [],
            'statuses' => [],
            'amounts' => [],
            'appointment_ids' => [],
        ];

        // Extract date references with Carbon parsing
        $datePatterns = [
            'today' => Carbon::today(),
            'tomorrow' => Carbon::tomorrow(),
            'yesterday' => Carbon::yesterday(),
            'next week' => Carbon::now()->addWeek(),
            'next month' => Carbon::now()->addMonth(),
            'this week' => Carbon::now(),
            'monday' => Carbon::parse('next monday'),
            'tuesday' => Carbon::parse('next tuesday'),
            'wednesday' => Carbon::parse('next wednesday'),
            'thursday' => Carbon::parse('next thursday'),
            'friday' => Carbon::parse('next friday'),
            'saturday' => Carbon::parse('next saturday'),
            'sunday' => Carbon::parse('next sunday'),
        ];
        
        foreach ($datePatterns as $pattern => $date) {
            if (stripos($text, $pattern) !== false) {
                $entities['dates'][] = [
                    'text' => $pattern,
                    'date' => $date->format('Y-m-d'),
                ];
            }
        }

        // Extract specific date formats (MM/DD/YYYY, YYYY-MM-DD, etc.)
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $text, $matches)) {
            try {
                $date = Carbon::parse($matches[0]);
                $entities['dates'][] = [
                    'text' => $matches[0],
                    'date' => $date->format('Y-m-d'),
                ];
            } catch (\Exception $e) {
                // Invalid date format - ignore
            }
        }

        // Extract numbers (for "how many", counts, IDs, etc.)
        preg_match_all('/\b\d+\b/', $text, $matches);
        if (!empty($matches[0])) {
            $entities['numbers'] = array_map('intval', $matches[0]);
        }

        // Extract money amounts
        if (preg_match_all('/(?:₱|php|peso|P|\$)?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i', $text, $matches)) {
            $entities['amounts'] = array_map(function($amt) {
                return floatval(str_replace(',', '', $amt));
            }, $matches[1]);
        }

        // Extract service references if available in context
        if (!empty($context['available_services'])) {
            foreach ($context['available_services'] as $service) {
                $serviceName = strtolower($service['name'] ?? '');
                if (!empty($serviceName) && stripos($text, $serviceName) !== false) {
                    $entities['services'][] = $service;
                }
            }
        }

        // Extract action verbs
        $actionWords = [
            'book' => 'create', 'cancel' => 'cancel', 'reschedule' => 'update',
            'change' => 'update', 'update' => 'update', 'check' => 'read',
            'show' => 'read', 'get' => 'read', 'view' => 'read', 'list' => 'read',
            'approve' => 'approve', 'decline' => 'decline', 'reject' => 'decline',
            'complete' => 'complete', 'process' => 'process', 'verify' => 'verify',
            'refund' => 'refund', 'pay' => 'payment',
        ];
        
        foreach ($actionWords as $word => $action) {
            if (stripos($text, $word) !== false) {
                $entities['actions'][] = ['word' => $word, 'action' => $action];
            }
        }

        // Extract status references
        $statusWords = ['pending', 'approved', 'confirmed', 'completed', 'cancelled', 'rejected', 'paid', 'unpaid', 'partial'];
        foreach ($statusWords as $status) {
            if (stripos($text, $status) !== false) {
                $entities['statuses'][] = $status;
            }
        }

        // Extract appointment ID patterns
        if (preg_match('/appointment\s*#?\s*(\d+)/i', $text, $matches)) {
            $entities['appointment_ids'][] = intval($matches[1]);
        }
        if (preg_match('/id\s*#?\s*(\d+)/i', $text, $matches)) {
            $entities['appointment_ids'][] = intval($matches[1]);
        }

        return $entities;
    }

    // ================== SIMILARITY FUNCTIONS ==================

    /**
     * Normalize text for similarity comparisons
     */
    public static function normalizeForSimilarity(string $text): string
    {
        $t = mb_strtolower($text);
        $t = preg_replace('/([a-z])\1{2,}/u', '$1', $t);
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);
        return trim($t);
    }

    /**
     * Calculate semantic similarity between two texts with synonym expansion
     */
    public static function semanticSimilarity(string $text1, string $text2): float
    {
        $text1 = strtolower(trim($text1));
        $text2 = strtolower(trim($text2));
        
        if ($text1 === $text2) return 1.0;
        
        // Synonym groups for semantic expansion
        $synonymGroups = [
            'appointment' => ['booking', 'schedule', 'reservation', 'meeting', 'visit', 'session', 'sched', 'appt'],
            'cancel' => ['remove', 'delete', 'withdraw', 'stop', 'end', 'void', 'terminate'],
            'reschedule' => ['move', 'change', 'modify', 'shift', 'adjust', 'transfer', 'postpone'],
            'payment' => ['pay', 'fee', 'charge', 'cost', 'price', 'amount', 'bill', 'invoice', 'bayad'],
            'refund' => ['return', 'reimbursement', 'money back', 'credit', 'cashback'],
            'pending' => ['waiting', 'processing', 'queue', 'unconfirmed', 'awaiting'],
            'approved' => ['confirmed', 'accepted', 'verified', 'ok', 'granted'],
            'completed' => ['done', 'finished', 'ended', 'closed', 'fulfilled'],
            'today' => ['now', 'this day', 'ngayon', 'current'],
            'tomorrow' => ['next day', 'bukas', 'the day after'],
            'user' => ['client', 'customer', 'member', 'person'],
            'admin' => ['administrator', 'manager', 'supervisor', 'owner'],
            'cashier' => ['teller', 'payment handler', 'counter'],
            'service' => ['offering', 'product', 'serbisyo', 'option'],
            'show' => ['view', 'display', 'see', 'list', 'get', 'fetch', 'tingnan', 'ipakita'],
            'help' => ['assist', 'support', 'guide', 'aid', 'tulong'],
        ];
        
        $words1 = array_filter(explode(' ', $text1));
        $words2 = array_filter(explode(' ', $text2));
        
        $expandedWords1 = self::expandWithSynonyms($words1, $synonymGroups);
        $expandedWords2 = self::expandWithSynonyms($words2, $synonymGroups);
        
        $intersection = count(array_intersect($expandedWords1, $expandedWords2));
        $union = count(array_unique(array_merge($expandedWords1, $expandedWords2)));
        
        return $union > 0 ? $intersection / $union : 0.0;
    }
    
    /**
     * Expand words with their synonyms
     */
    private static function expandWithSynonyms(array $words, array $synonymGroups): array
    {
        $expanded = $words;
        
        foreach ($words as $word) {
            foreach ($synonymGroups as $canonical => $synonyms) {
                if ($word === $canonical || in_array($word, $synonyms)) {
                    $expanded[] = $canonical;
                    $expanded = array_merge($expanded, $synonyms);
                }
            }
        }
        
        return array_unique($expanded);
    }

    /**
     * Fuzzy similarity using combination of methods
     */
    public static function fuzzySimilarity(string $a, string $b): float
    {
        $na = self::normalizeForSimilarity($a);
        $nb = self::normalizeForSimilarity($b);

        if ($na === $nb) return 1.0;
        if (empty($na) || empty($nb)) return 0.0;

        // Check if one contains the other
        if (strpos($na, $nb) !== false || strpos($nb, $na) !== false) {
            return 0.85;
        }

        // Token overlap
        $tokensA = array_filter(explode(' ', $na));
        $tokensB = array_filter(explode(' ', $nb));
        $common = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        $tokenOverlap = $union > 0 ? $common / $union : 0.0;

        // Semantic similarity
        $semantic = self::semanticSimilarity($na, $nb);

        // Normalized Levenshtein
        $la = strlen($na);
        $lb = strlen($nb);
        if ($la > 0 || $lb > 0) {
            $lev = levenshtein($na, $nb);
            $max = max(1, max($la, $lb));
            $levScore = 1.0 - min(1.0, $lev / $max);
        } else {
            $levScore = 0.0;
        }

        // Similar_text percentage
        similar_text($na, $nb, $percent);
        $similarText = $percent / 100;

        // Combine weights
        $combined = ($tokenOverlap * 0.25) + ($semantic * 0.35) + ($levScore * 0.20) + ($similarText * 0.20);
        return max(0.0, min(1.0, $combined));
    }

    /**
     * Phonetic similarity using metaphone
     */
    public static function phoneticSimilarity(string $a, string $b): float
    {
        $na = self::normalizeForSimilarity($a);
        $nb = self::normalizeForSimilarity($b);
        $tokensA = array_filter(explode(' ', $na));
        $tokensB = array_filter(explode(' ', $nb));
        
        if (empty($tokensA) || empty($tokensB)) return 0.0;

        $matches = 0;
        foreach ($tokensA as $ta) {
            $ma = @metaphone($ta);
            $sa = @soundex($ta);
            foreach ($tokensB as $tb) {
                $mb = @metaphone($tb);
                $sb = @soundex($tb);
                if ($ma && $mb && $ma === $mb) {
                    $matches += 0.5;
                }
                if ($sa && $sb && $sa === $sb) {
                    $matches += 0.5;
                }
            }
        }

        $den = max(1, max(count($tokensA), count($tokensB)));
        return min(1.0, $matches / $den);
    }

    // ================== INTENT CONFIDENCE ==================

    /**
     * Calculate intent confidence score
     */
    public static function calculateIntentConfidence(string $text, string $intent, array $patterns): float
    {
        $score = 0.0;
        $maxScore = 0.0;

        if (!isset($patterns[$intent])) {
            return 0.0;
        }

        $rules = $patterns[$intent];

        // Pattern matches give highest confidence
        if (isset($rules['patterns'])) {
            $maxScore += 10;
            foreach ($rules['patterns'] as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    $score += 10;
                    break;
                }
            }
        }

        // Keyword matches give medium confidence
        if (isset($rules['keywords'])) {
            $maxScore += 5;
            $keywordMatches = 0;
            foreach ($rules['keywords'] as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $keywordMatches++;
                }
            }
            $score += min($keywordMatches * 2, 5);
        }

        // Semantic matches give lower confidence
        if (isset($rules['semantic'])) {
            $maxScore += 3;
            foreach ($rules['semantic'] as $semantic) {
                if (stripos($text, $semantic) !== false) {
                    $score += 3;
                    break;
                }
            }
        }

        return $maxScore > 0 ? $score / $maxScore : 0.0;
    }

    // ================== ROLE-BASED SUGGESTIONS ==================

    /**
     * Generate contextual suggestions based on user role
     */
    public static function generateContextualSuggestions(array $context, string $lastIntent, string $role = 'client'): array
    {
        $suggestions = [];
        
        switch ($role) {
            case 'admin':
                $pending = $context['admin_data']['pending_appointments'] ?? 0;
                $today = $context['admin_data']['today_appointments'] ?? 0;
                
                if ($pending > 0) {
                    $suggestions[] = "Review {$pending} pending appointments";
                }
                if ($today > 0) {
                    $suggestions[] = "View today's {$today} appointments";
                }
                $suggestions[] = 'Show performance analytics';
                $suggestions[] = 'View pending refunds';
                $suggestions[] = 'Show system statistics';
                $suggestions[] = 'Check system health';
                break;
                
            case 'cashier':
                $cashierData = $context['cashier_data'] ?? [];
                $approvedForPayment = $cashierData['approved_appointments_for_payment'] ?? 0;
                $pendingRefunds = $cashierData['pending_refunds'] ?? 0;
                
                if ($approvedForPayment > 0) {
                    $suggestions[] = "Process {$approvedForPayment} pending payment(s)";
                }
                if ($pendingRefunds > 0) {
                    $suggestions[] = "Review {$pendingRefunds} refund request(s)";
                }
                $suggestions[] = 'Generate shift report';
                $suggestions[] = 'View today\'s transactions';
                $suggestions[] = 'Check payment status';
                break;
                
            default: // client/user
                $upcomingCount = 0;
                if (isset($context['client_data']['upcoming_appointments'])) {
                    $upcomingCount = count($context['client_data']['upcoming_appointments']);
                }
                
                if ($upcomingCount > 0) {
                    $suggestions[] = 'When is my next appointment?';
                    $suggestions[] = 'Can I reschedule my appointment?';
                    $suggestions[] = 'Check my payment status';
                } else {
                    $suggestions[] = 'How do I book an appointment?';
                    $suggestions[] = 'What services do you offer?';
                    $suggestions[] = 'View available time slots';
                }
                $suggestions[] = 'What should I bring to my appointment?';
                $suggestions[] = 'Request a refund';
                break;
        }
        
        return array_slice($suggestions, 0, 5);
    }

    // ================== CASHIER-SPECIFIC DATA METHODS ==================
    
    /**
     * Get cashier-specific context data
     */
    public static function getCashierData(int $userId): array
    {
        $today = Carbon::now()->startOfDay();
        
        try {
            // Approved appointments ready for payment
            $approvedAppointments = Appointment::where('status', 'approved')
                ->whereDate('appointment_date', '>=', $today)
                ->whereDoesntHave('payments', function($q) {
                    $q->where('payment_status', 'paid');
                })
                ->count();
            
            // Today's completed payments
            $todayPayments = Payment::whereDate('payment_date', $today)
                ->where('payment_status', 'paid')
                ->count();
            
            $todayRevenue = Payment::whereDate('payment_date', $today)
                ->where('payment_status', 'paid')
                ->sum('amount_paid');
            
            // Pending refund requests
            $pendingRefunds = Refund::where('status', 'pending')->count();
            
            // Approved refunds awaiting processing
            $approvedRefunds = Refund::where('status', 'approved')
                ->whereNull('completed_at')
                ->count();
            
            return [
                'approved_appointments_for_payment' => $approvedAppointments,
                'today_payments_processed' => $todayPayments,
                'today_revenue' => number_format($todayRevenue, 2),
                'pending_refunds' => $pendingRefunds,
                'approved_refunds_awaiting' => $approvedRefunds,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::warning('Error fetching cashier data: ' . $e->getMessage());
            return [
                'approved_appointments_for_payment' => 0,
                'today_payments_processed' => 0,
                'today_revenue' => '0.00',
                'pending_refunds' => 0,
                'approved_refunds_awaiting' => 0,
                'error' => 'Unable to fetch data',
            ];
        }
    }
    
    /**
     * Get appointments ready for payment processing
     */
    public static function getAppointmentsForPayment(int $limit = 10): array
    {
        try {
            $appointments = Appointment::with(['user:id,first_name,last_name,email', 'service:id,name,price'])
                ->where('status', 'approved')
                ->whereDoesntHave('payments', function($q) {
                    $q->where('payment_status', 'paid');
                })
                ->orderBy('appointment_date')
                ->limit($limit)
                ->get();
            
            return $appointments->map(function($apt) {
                return [
                    'id' => $apt->id,
                    'client_name' => trim(($apt->user->first_name ?? '') . ' ' . ($apt->user->last_name ?? '')),
                    'client_email' => $apt->user->email ?? '',
                    'service' => $apt->service->name ?? 'N/A',
                    'service_price' => number_format($apt->service->price ?? 0, 2),
                    'date' => $apt->appointment_date->format('M d, Y'),
                    'time' => $apt->appointment_time,
                    'status' => $apt->status,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Error fetching appointments for payment: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending refund requests
     */
    public static function getPendingRefunds(int $limit = 10): array
    {
        try {
            $refunds = Refund::with(['requestedBy:id,first_name,last_name,email', 'appointment:id,appointment_date,service_id'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
            
            return $refunds->map(function($refund) {
                return [
                    'id' => $refund->id,
                    'client_name' => trim(($refund->requestedBy->first_name ?? '') . ' ' . ($refund->requestedBy->last_name ?? '')),
                    'amount' => number_format($refund->refund_amount, 2),
                    'original_amount' => number_format($refund->original_amount ?? 0, 2),
                    'reason' => $refund->reason ?? 'Not specified',
                    'appointment_date' => optional($refund->appointment)->appointment_date?->format('M d, Y') ?? 'N/A',
                    'requested_at' => $refund->created_at->format('M d, Y H:i'),
                    'status' => $refund->status,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Error fetching pending refunds: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get shift report data for cashier
     */
    public static function getShiftReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfDay();
        $endDate = $endDate ?? Carbon::now()->endOfDay();
        
        try {
            $payments = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->get();
            
            $refunds = Refund::whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->get();
            
            $totalCollected = $payments->sum('amount_paid');
            $totalRefunded = $refunds->sum('refund_amount');
            
            return [
                'period' => [
                    'start' => $startDate->format('M d, Y H:i'),
                    'end' => $endDate->format('M d, Y H:i'),
                ],
                'payments' => [
                    'count' => $payments->count(),
                    'total_collected' => number_format($totalCollected, 2),
                    'discounts_applied' => number_format($payments->sum('total_discount_applied'), 2),
                ],
                'refunds' => [
                    'count' => $refunds->count(),
                    'total_refunded' => number_format($totalRefunded, 2),
                ],
                'net_revenue' => number_format($totalCollected - $totalRefunded, 2),
            ];
        } catch (\Exception $e) {
            Log::warning('Error generating shift report: ' . $e->getMessage());
            return ['error' => 'Unable to generate shift report'];
        }
    }

    // ================== USER PAYMENT/REFUND METHODS ==================
    
    /**
     * Get user's payment history
     */
    public static function getUserPayments(int $userId): array
    {
        try {
            $appointments = Appointment::with(['service:id,name,price', 'payments'])
                ->where('user_id', $userId)
                ->whereHas('payments')
                ->orderBy('appointment_date', 'desc')
                ->limit(10)
                ->get();
            
            return $appointments->map(function($apt) {
                $payment = $apt->payments->first();
                return [
                    'appointment_id' => $apt->id,
                    'service' => $apt->service->name ?? 'N/A',
                    'date' => $apt->appointment_date->format('M d, Y'),
                    'amount_paid' => number_format($payment->amount_paid ?? 0, 2),
                    'payment_status' => $payment->payment_status ?? 'unknown',
                    'payment_date' => optional($payment)->payment_date?->format('M d, Y') ?? 'N/A',
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Error fetching user payments: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's refund requests
     */
    public static function getUserRefunds(int $userId): array
    {
        try {
            $refunds = Refund::with(['appointment:id,appointment_date,service_id'])
                ->where('requested_by', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            return $refunds->map(function($refund) {
                return [
                    'id' => $refund->id,
                    'amount' => number_format($refund->refund_amount, 2),
                    'reason' => $refund->reason ?? 'Not specified',
                    'status' => $refund->status,
                    'requested_at' => $refund->created_at->format('M d, Y'),
                    'completed_at' => optional($refund->completed_at)?->format('M d, Y'),
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Error fetching user refunds: ' . $e->getMessage());
            return [];
        }
    }

    // ================== ADMIN-SPECIFIC ENHANCED METHODS ==================
    
    /**
     * Get comprehensive admin analytics
     */
    public static function getAdminAnalytics(): array
    {
        try {
            $today = Carbon::now()->startOfDay();
            $weekAgo = Carbon::now()->subDays(7);
            $monthAgo = Carbon::now()->subDays(30);
            
            // Revenue analytics
            $monthlyRevenue = Payment::whereBetween('payment_date', [$monthAgo, $today])
                ->where('payment_status', 'paid')
                ->sum('amount_paid');
            
            $weeklyRevenue = Payment::whereBetween('payment_date', [$weekAgo, $today])
                ->where('payment_status', 'paid')
                ->sum('amount_paid');
            
            // Refund analytics
            $pendingRefundsTotal = Refund::where('status', 'pending')
                ->sum('refund_amount');
            
            // Top performing services
            $topServices = Service::withCount(['appointments' => function($q) use ($monthAgo) {
                    $q->where('appointment_date', '>=', $monthAgo);
                }])
                ->where('is_active', true)
                ->orderBy('appointments_count', 'desc')
                ->limit(5)
                ->get(['name', 'appointments_count']);
            
            // User growth
            $newUsersThisWeek = User::where('created_at', '>=', $weekAgo)->count();
            $newUsersThisMonth = User::where('created_at', '>=', $monthAgo)->count();
            
            return [
                'revenue' => [
                    'weekly' => number_format($weeklyRevenue, 2),
                    'monthly' => number_format($monthlyRevenue, 2),
                ],
                'pending_refunds_total' => number_format($pendingRefundsTotal, 2),
                'top_services' => $topServices->map(fn($s) => [
                    'name' => $s->name,
                    'appointments' => $s->appointments_count,
                ])->toArray(),
                'user_growth' => [
                    'weekly' => $newUsersThisWeek,
                    'monthly' => $newUsersThisMonth,
                ],
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::warning('Error fetching admin analytics: ' . $e->getMessage());
            return ['error' => 'Unable to fetch analytics'];
        }
    }
    
    /**
     * Get system health status for admin
     */
    public static function getSystemHealth(): array
    {
        try {
            $issues = [];
            $warnings = [];
            
            // Check for stale pending appointments (older than 3 days)
            $stalePending = Appointment::where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subDays(3))
                ->count();
            if ($stalePending > 0) {
                $issues[] = "{$stalePending} appointment(s) pending for more than 3 days";
            }
            
            // Check for pending refunds older than 7 days
            $staleRefunds = Refund::where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subDays(7))
                ->count();
            if ($staleRefunds > 0) {
                $issues[] = "{$staleRefunds} refund request(s) pending for more than 7 days";
            }
            
            // Check for unread notifications
            $unreadNotifications = Notification::where('is_read', false)
                ->where('created_at', '<', Carbon::now()->subDays(1))
                ->count();
            if ($unreadNotifications > 10) {
                $warnings[] = "{$unreadNotifications} unread notification(s) in the system";
            }
            
            // Database connection test
            try {
                DB::connection()->getPdo();
                $dbStatus = 'connected';
            } catch (\Exception $e) {
                $dbStatus = 'disconnected';
                $issues[] = 'Database connection issue detected';
            }
            
            return [
                'status' => empty($issues) ? (empty($warnings) ? 'healthy' : 'warning') : 'needs_attention',
                'database' => $dbStatus,
                'issues' => $issues,
                'warnings' => $warnings,
                'checked_at' => Carbon::now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::warning('Error checking system health: ' . $e->getMessage());
            return ['status' => 'unknown', 'error' => 'Unable to check system health'];
        }
    }

    /**
     * Get appointment by ID with full details
     */
    public static function getAppointmentDetails(int $appointmentId, ?int $userId = null, ?string $role = null): ?array
    {
        try {
            $query = Appointment::with(['user:id,first_name,last_name,email,phone', 'service:id,name,price,duration', 'payments', 'refunds']);
            
            // Security: Non-admin users can only see their own appointments
            if ($role !== 'admin' && $role !== 'cashier' && $userId) {
                $query->where('user_id', $userId);
            }
            
            $apt = $query->find($appointmentId);
            
            if (!$apt) {
                return null;
            }
            
            $payment = $apt->payments->first();
            $refund = $apt->refunds->first();
            
            return [
                'id' => $apt->id,
                'client' => [
                    'name' => trim(($apt->user->first_name ?? '') . ' ' . ($apt->user->last_name ?? '')),
                    'email' => $apt->user->email ?? '',
                    'phone' => $apt->user->phone ?? '',
                ],
                'service' => [
                    'name' => $apt->service->name ?? 'N/A',
                    'price' => number_format($apt->service->price ?? 0, 2),
                    'duration' => $apt->service->duration ?? 'N/A',
                ],
                'date' => $apt->appointment_date->format('M d, Y'),
                'time' => $apt->appointment_time,
                'status' => $apt->status,
                'payment' => $payment ? [
                    'status' => $payment->payment_status,
                    'amount_paid' => number_format($payment->amount_paid, 2),
                    'date' => optional($payment->payment_date)?->format('M d, Y'),
                ] : null,
                'refund' => $refund ? [
                    'status' => $refund->status,
                    'amount' => number_format($refund->refund_amount, 2),
                ] : null,
                'created_at' => $apt->created_at->format('M d, Y H:i'),
            ];
        } catch (\Exception $e) {
            Log::warning('Error fetching appointment details: ' . $e->getMessage());
            return null;
        }
    }
}
