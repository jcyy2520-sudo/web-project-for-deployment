<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotLLMIntegration - Bridge between ChatbotController and LLMService
 * 
 * Handles:
 * - Context preparation from conversation history
 * - Intent-based LLM fallback (use LLM for unclear intents)
 * - Conversation enrichment with real-time data
 * - Response validation and sanitization
 */
class ChatbotLLMIntegration
{
    private LLMService $llmService;
    private ChatbotRoleAwarenessService $roleService;
    private ChatbotRealTimeDataService $dataService;

    public function __construct(
        LLMService $llmService,
        ChatbotRoleAwarenessService $roleService,
        ChatbotRealTimeDataService $dataService
    ) {
        $this->llmService = $llmService;
        $this->roleService = $roleService;
        $this->dataService = $dataService;
    }

    /**
     * Get intelligent LLM response when template-based responses aren't suitable
     * 
     * Use this for:
     * - General questions not matching specific intents
     * - Follow-up questions requiring context understanding
     * - Complex reasoning needed
     * - Low-confidence intent detection
     * 
     * @param int|null $userId
     * @param string $userMessage
     * @param string $conversationId
     * @param array $intentData
     * @param string|null $language Detected language ('filipino' or 'english')
     * @return array|null Null if should not use LLM, array if response generated
     */
    public function shouldUseLLMAndRespond(
        ?int $userId,
        string $userMessage,
        string $conversationId,
        array $intentData = [],
        ?string $language = null
    ): ?array {
        // Determine if we should use LLM
        $intentConfidence = $intentData['confidence'] ?? 0;
        $intent = $intentData['intent'] ?? 'general_question';

        // IMPORTANT: Be aggressive about using LLM for better accuracy
        // Use LLM if:
        // 1. Intent confidence is low (pattern matching failed)
        // 2. Intent is a general question or help
        // 3. Message length suggests complex query
        // 4. Intent doesn't have high confidence
        if ($intentConfidence < 0.8 || $intent === 'general_question' || $intent === 'help' || strlen($userMessage) > 100) {
            return $this->generateLLMResponse(
                $userId,
                $userMessage,
                $conversationId,
                $intentData,
                $language
            );
        }

        return null;
    }

    /**
     * Generate response using LLM with full context and comprehensive data
     * CRITICAL: This ensures the LLM has all real-time data it needs for accurate responses
     */
    public function generateLLMResponse(
        ?int $userId,
        string $userMessage,
        string $conversationId,
        array $intentData = [],
        ?string $language = null
    ): ?array {
        try {
            // Get role and capabilities
            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'guest';

            // Get conversation history for context (last 5 messages)
            $conversationHistory = $this->getConversationContext(
                $userId,
                $conversationId,
                5
            );

            // Detect language if not provided
            if (!$language) {
                $language = $this->detectLanguageFromMessage($userMessage);
            }

            // Build system context with comprehensive real-time data
            // CRITICAL: This is what makes the LLM accurate - it has real system data
            $systemContext = [
                'role' => $role,
                'language' => $language,
                'system_data' => $this->gatherSystemData($role),
                'user_info' => $userId ? $this->gatherUserData($userId, $role) : [],
            ];

            // Log what we're sending to LLM for debugging
            Log::debug('LLM Context Prepared', [
                'role' => $role,
                'language' => $language,
                'user_id' => $userId,
                'has_system_data' => !empty($systemContext['system_data']),
                'has_user_info' => !empty($systemContext['user_info']),
                'system_data_keys' => !empty($systemContext['system_data']) ? array_keys($systemContext['system_data']) : [],
            ]);

            // Verify LLM is available before calling
            if (!$this->isAvailable()) {
                Log::warning('LLM not available when attempting generation');
                return null;
            }

            // Call LLM service
            $result = $this->llmService->generateResponse(
                $userMessage,
                $conversationHistory,
                $systemContext
            );

            // Validate LLM response
            if (!$result || !($result['success'] ?? false)) {
                Log::warning('LLM generation failed', [
                    'success' => $result['success'] ?? false,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return null;
            }

            // Ensure response is not empty
            $responseText = $result['response'] ?? '';
            if (!$responseText || strlen(trim($responseText)) === 0) {
                Log::warning('LLM returned empty response');
                return null;
            }

            return [
                'response' => $responseText,
                'meta' => [
                    'source' => 'llm',
                    'llm_provider' => $result['provider'] ?? 'unknown',
                    'llm_model' => $result['model'] ?? 'unknown',
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'intent' => $intentData['intent'] ?? 'general_question',
                    'intent_confidence' => $intentData['confidence'] ?? 0,
                    'language' => $language,
                    'role_detected' => $role,
                    'has_system_context' => !empty($systemContext['system_data']),
                    'has_user_context' => !empty($systemContext['user_info']),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('LLM integration error: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $userId ?? 'guest',
                'message_snippet' => substr($userMessage, 0, 50),
            ]);
            return null;
        }
    }
    
    /**
     * Detect language from message (simple detection for LLM context)
     */
    private function detectLanguageFromMessage(string $message): string
    {
        $lower = mb_strtolower($message);
        
        // Filipino/Tagalog indicators
        $filipinoWords = [
            'ako', 'ikaw', 'siya', 'po', 'opo', 'naman', 'mga', 'ang', 'ng', 'sa',
            'ano', 'sino', 'saan', 'kailan', 'kelan', 'bakit', 'paano', 'pano', 'magkano',
            'pwede', 'gusto', 'kailangan', 'hindi', 'wala', 'may', 'meron',
            'salamat', 'kamusta', 'kumusta', 'musta', 'sige', 'oo',
            'bayad', 'ibalik', 'tulong', 'tingnan', 'tignan'
        ];
        
        $count = 0;
        foreach ($filipinoWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $lower)) {
                $count++;
            }
        }
        
        return $count >= 2 ? 'filipino' : 'english';
    }

    /**
     * Get recent conversation history for context
     */
    private function getConversationContext(
        ?int $userId,
        string $conversationId,
        int $limit = 5
    ): array {
        try {
            if (!$userId) {
                return [];
            }

            $messages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            return $messages->map(fn($msg) => [
                'role' => $msg->role,
                'message' => $msg->message,
                'created_at' => $msg->created_at,
            ])->toArray();
        } catch (\Exception $e) {
            Log::debug('Failed to get conversation context: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gather comprehensive real system data to inform LLM responses
     * This data is critical for accurate, real-time, data-driven responses
     * LLM will cite actual numbers and facts from this data
     */
    private function gatherSystemData(string $role): array
    {
        try {
            $data = [];

            // === BUSINESS INFORMATION - CRITICAL FOR LOCATION/CONTACT QUERIES ===
            $data['business_info'] = [
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

            // === SYSTEM-WIDE DATA - Available to all users ===
            
            // Services and pricing information (used to answer "what services do you offer")
            $services = \App\Models\Service::where('is_active', true)
                ->select(['id', 'name', 'description', 'price', 'duration'])
                ->get();
            if ($services->count() > 0) {
                $data['services_available'] = $services->map(fn($s) => [
                    'name' => $s->name,
                    'price' => $s->price ? '₱' . number_format($s->price, 2) : 'Price on inquiry',
                    'duration' => $s->duration ? $s->duration . ' min' : 'N/A',
                ])->toArray();
                $data['services_count'] = $services->count();
            }

            // Business settings - critical for answering system questions
            $settings = \App\Models\AppointmentSettings::first();
            if ($settings) {
                $data['business_hours'] = $settings->business_hours ?? 'Check appointment booking page';
                $data['holidays'] = $settings->holidays ?? 'None specified';
                $data['max_appointments_per_day'] = $settings->max_appointments_per_day ?? 'Unlimited';
                $data['appointment_buffer_time'] = $settings->appointment_buffer_time ?? '0 minutes';
                $data['auto_confirm_enabled'] = $settings->auto_confirm_appointments ?? false;
                $data['allow_same_day_booking'] = $settings->allow_same_day_booking ?? false;
            }

            // === ADMIN-LEVEL SYSTEM DATA ===
            if ($role === 'admin') {
                // System overview
                $data['total_users'] = \App\Models\User::count();
                $data['active_users'] = \App\Models\User::where('is_active', true)->count();
                
                // Appointment metrics
                $data['total_appointments'] = \App\Models\Appointment::count();
                $data['pending_appointments'] = \App\Models\Appointment::where('status', 'pending')->count();
                $data['approved_appointments'] = \App\Models\Appointment::where('status', 'approved')->count();
                $data['completed_appointments'] = \App\Models\Appointment::where('status', 'completed')->count();
                $data['cancelled_appointments'] = \App\Models\Appointment::where('status', 'cancelled')->count();
                $data['appointments_today'] = \App\Models\Appointment::whereDate('appointment_date', now())->count();
                $data['appointments_this_week'] = \App\Models\Appointment::whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count();
                
                // Pending items requiring action
                $data['appointments_pending_approval'] = \App\Models\Appointment::where('status', 'pending')->count();
                $data['refunds_pending_approval'] = \App\Models\Refund::where('status', 'pending')->count();
                
                // Revenue overview
                $data['total_revenue'] = \App\Models\Payment::where('payment_status', 'paid')->sum('amount');
                $data['pending_revenue'] = \App\Models\Appointment::where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending');
                    })->sum('payment_amount');
                
                // Pending refunds details
                $pendingRefunds = \App\Models\Refund::where('status', 'pending')
                    ->with(['payment.appointment.user'])
                    ->limit(10)
                    ->get();
                if ($pendingRefunds->count() > 0) {
                    $data['pending_refund_details'] = $pendingRefunds->map(fn($r) => [
                        'id' => $r->id,
                        'amount' => '₱' . number_format($r->amount, 2),
                        'reason' => $r->reason ?? 'No reason provided',
                        'requested_date' => $r->created_at?->format('M d, Y'),
                    ])->toArray();
                }
            }

            // === CASHIER-LEVEL SYSTEM DATA ===
            if ($role === 'cashier') {
                $data['pending_payments'] = \App\Models\Appointment::where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending')
                          ->orWhere('payment_status', 'unpaid');
                    })->count();
                
                $data['pending_refunds'] = \App\Models\Refund::where('status', 'pending')->count();
                $data['approved_refunds'] = \App\Models\Refund::where('status', 'approved')->count();
                
                // Today's transactions
                $data['today_collections'] = \App\Models\Payment::whereDate('created_at', now()->toDateString())
                    ->where('payment_status', 'paid')
                    ->sum('amount');
                
                $data['today_refunds_processed'] = \App\Models\Refund::where('status', 'completed')
                    ->whereDate('updated_at', now()->toDateString())
                    ->sum('amount');
                
                $data['appointments_for_payment_today'] = \App\Models\Appointment::where('status', 'approved')
                    ->whereDate('appointment_date', now())
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending');
                    })->count();
            }

            // === CLIENT/USER-SPECIFIC SYSTEM DATA ===
            // Available to all users but might be filtered per-user context
            
            // Today's status
            $data['current_date'] = now()->format('F j, Y');
            $data['current_day'] = now()->format('l');
            $data['current_time'] = now()->format('H:i:s');
            
            // System status
            $data['system_status'] = 'operational';

            return $data;
        } catch (\Exception $e) {
            Log::debug('Failed to gather system data: ' . $e->getMessage());
            Log::error('System data gathering error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gather comprehensive user-specific data for context
     * Provides personalized, real-time data based on user role and history
     * This ensures LLM can answer specific questions about that user's data
     */
    private function gatherUserData(int $userId, string $role = 'client'): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return [];
            }

            $data = [
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
                'role' => $role,
                'member_since' => $user->created_at?->format('M d, Y') ?? 'Unknown',
            ];

            // === CLIENT-SPECIFIC USER DATA ===
            if ($role === 'client') {
                // Appointment history and status
                $allAppointments = \App\Models\Appointment::where('user_id', $userId)->get();
                $data['total_appointments'] = $allAppointments->count();
                
                // Status breakdown
                $data['pending_appointments'] = $allAppointments->where('status', 'pending')->count();
                $data['approved_appointments'] = $allAppointments->where('status', 'approved')->count();
                $data['completed_appointments'] = $allAppointments->where('status', 'completed')->count();
                $data['cancelled_appointments'] = $allAppointments->where('status', 'cancelled')->count();
                
                // Upcoming appointments with FULL details for answering specific questions
                $upcomingApts = \App\Models\Appointment::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('appointment_date', '>=', now()->toDateString())
                    ->orderBy('appointment_date', 'asc')
                    ->limit(10)
                    ->get();
                    
                if ($upcomingApts->count() > 0) {
                    $data['upcoming_appointments'] = $upcomingApts->map(fn($apt) => [
                        'id' => $apt->id,
                        'date' => $apt->appointment_date?->format('M d, Y'),
                        'time' => $apt->appointment_time ?? 'TBD',
                        'service' => $apt->service_type ?? 'General Service',
                        'status' => $apt->status,
                        'payment_status' => $apt->payment_status ?? 'Pending',
                        'payment_amount' => $apt->payment_amount ? '₱' . number_format($apt->payment_amount, 2) : 'TBD',
                    ])->toArray();
                }
                
                // Payment history and status
                $payments = \App\Models\Payment::whereHas('appointment', fn($q) => $q->where('user_id', $userId))->get();
                $data['total_payments_made'] = $payments->where('payment_status', 'paid')->count();
                $data['total_amount_paid'] = $payments->where('payment_status', 'paid')->sum('amount');
                $data['pending_payments'] = \App\Models\Appointment::where('user_id', $userId)
                    ->where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending');
                    })->count();
                
                // Refund history
                $refunds = \App\Models\Refund::whereHas('payment.appointment', fn($q) => $q->where('user_id', $userId))->get();
                if ($refunds->count() > 0) {
                    $data['refund_count'] = $refunds->count();
                    $data['pending_refunds'] = $refunds->whereIn('status', ['pending', 'approved'])->count();
                    $data['completed_refunds'] = $refunds->where('status', 'completed')->count();
                    $data['total_refunded'] = $refunds->where('status', 'completed')->sum('amount');
                }
                
                // Last appointment info for context
                $lastApt = \App\Models\Appointment::where('user_id', $userId)
                    ->orderBy('appointment_date', 'desc')
                    ->first();
                if ($lastApt) {
                    $data['last_appointment'] = [
                        'date' => $lastApt->appointment_date?->format('M d, Y'),
                        'service' => $lastApt->service_type,
                        'status' => $lastApt->status,
                    ];
                }
            }
            
            // === ADMIN-SPECIFIC USER DATA ===
            if ($role === 'admin') {
                $data['system_pending_items'] = \App\Models\Appointment::where('status', 'pending')->count()
                    + \App\Models\Refund::where('status', 'pending')->count();
                $data['pending_appointments'] = \App\Models\Appointment::where('status', 'pending')->count();
                $data['pending_refunds'] = \App\Models\Refund::where('status', 'pending')->count();
                $data['unassigned_appointments'] = \App\Models\Appointment::whereNull('staff_id')->where('status', 'approved')->count();
            }
            
            // === CASHIER-SPECIFIC USER DATA ===
            if ($role === 'cashier') {
                $data['pending_items'] = \App\Models\Appointment::where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending');
                    })->count() 
                    + \App\Models\Refund::where('status', 'pending')->count();
                
                $data['pending_payments'] = \App\Models\Appointment::where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending');
                    })->count();
                    
                $data['pending_refunds'] = \App\Models\Refund::where('status', 'pending')->count();
                $data['today_transactions'] = \App\Models\Payment::whereDate('created_at', now())
                    ->count();
            }

            return $data;
        } catch (\Exception $e) {
            Log::debug('Failed to gather user data: ' . $e->getMessage());
            Log::error('User data gathering error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify LLM service is available
     */
    public function isAvailable(): bool
    {
        try {
            $health = $this->llmService->healthCheck();
            return $health['available_provider'] !== null;
        } catch (\Exception $e) {
            Log::debug('LLM availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get LLM availability status
     */
    public function getStatus(): array
    {
        try {
            return $this->llmService->healthCheck();
        } catch (\Exception $e) {
            Log::debug('LLM status check failed: ' . $e->getMessage());
            return [
                'claude' => false,
                'ollama' => false,
                'available_provider' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
