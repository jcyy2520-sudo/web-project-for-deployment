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

        // Use LLM if:
        // 1. Intent confidence is low (pattern matching failed)
        // 2. Intent is a general question
        // 3. User is asking something that requires reasoning
        if ($intentConfidence < 0.6 || $intent === 'general_question' || $intent === 'help') {
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
     * Generate response using LLM with full context
     */
    public function generateLLMResponse(
        ?int $userId,
        string $userMessage,
        string $conversationId,
        array $intentData = [],
        ?string $language = null
    ): array {
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
            $systemContext = [
                'role' => $role,
                'language' => $language,
                'system_data' => $this->gatherSystemData($role),
                'user_info' => $userId ? $this->gatherUserData($userId, $role) : [],
            ];

            // Log what we're sending to LLM for debugging
            Log::debug('LLM Context', [
                'role' => $role,
                'language' => $language,
                'user_id' => $userId,
                'has_system_data' => !empty($systemContext['system_data']),
                'has_user_info' => !empty($systemContext['user_info']),
            ]);

            // Call LLM service
            $result = $this->llmService->generateResponse(
                $userMessage,
                $conversationHistory,
                $systemContext
            );

            if (!$result['success'] ?? false) {
                Log::warning('LLM generation failed', $result);
                return null;
            }

            return [
                'response' => $result['response'],
                'meta' => [
                    'source' => 'llm',
                    'llm_provider' => $result['provider'] ?? 'unknown',
                    'llm_model' => $result['model'] ?? 'unknown',
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'intent' => $intentData['intent'] ?? 'general_question',
                    'intent_confidence' => $intentData['confidence'] ?? 0,
                    'language' => $language,
                    'role_detected' => $role,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('LLM integration error: ' . $e->getMessage());
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
     * Gather real system data to inform LLM responses
     * This data is critical for accurate, real-time responses
     */
    private function gatherSystemData(string $role): array
    {
        try {
            $data = [];

            // Admin and Cashier see pending items and system-wide data
            if (in_array($role, ['admin', 'cashier'])) {
                $data['pending_appointments'] = \App\Models\Appointment::where('status', 'pending')->count();
                $data['pending_refunds'] = \App\Models\Refund::where('status', 'pending')->count();
                $data['approved_appointments_today'] = \App\Models\Appointment::where('status', 'approved')
                    ->whereDate('appointment_date', now()->toDateString())
                    ->count();
            }
            
            // Admin-specific data
            if ($role === 'admin') {
                $data['total_users'] = \App\Models\User::count();
                $data['active_users'] = \App\Models\User::where('is_active', true)->count();
                $data['completed_today'] = \App\Models\Appointment::where('status', 'completed')
                    ->whereDate('updated_at', now()->toDateString())
                    ->count();
                    
                // Get pending refunds with details for admin
                $pendingRefunds = \App\Models\Refund::where('status', 'pending')
                    ->with(['payment.appointment'])
                    ->limit(5)
                    ->get();
                if ($pendingRefunds->count() > 0) {
                    $data['pending_refund_details'] = $pendingRefunds->map(fn($r) => [
                        'id' => $r->id,
                        'amount' => $r->amount,
                        'status' => $r->status,
                    ])->toArray();
                }
            }
            
            // Cashier-specific data
            if ($role === 'cashier') {
                $data['pending_payments'] = \App\Models\Appointment::where('status', 'approved')
                    ->where(function($q) {
                        $q->whereNull('payment_status')
                          ->orWhere('payment_status', 'pending')
                          ->orWhere('payment_status', 'unpaid');
                    })
                    ->count();
                $data['approved_refunds'] = \App\Models\Refund::where('status', 'approved')->count();
                $data['today_collections'] = \App\Models\Payment::whereDate('created_at', now()->toDateString())
                    ->where('payment_status', 'paid')
                    ->sum('amount');
            }

            // Everyone can see services
            $services = \App\Models\Service::where('is_active', true)->pluck('name')->toArray();
            $data['services_available'] = $services;
            $data['services_count'] = count($services);

            // Business hours
            $settings = \App\Models\AppointmentSettings::first();
            if ($settings) {
                $data['business_hours'] = $settings->business_hours ?? 'Check appointment booking page';
            }
            
            // Today's date for context
            $data['current_date'] = now()->format('F j, Y');
            $data['current_day'] = now()->format('l');

            return $data;
        } catch (\Exception $e) {
            Log::debug('Failed to gather system data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gather user-specific data for context
     * Provides personalized data based on user role
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
            ];

            // Client-specific data
            if ($role === 'client') {
                $data['appointment_count'] = \App\Models\Appointment::where('user_id', $userId)->count();
                $data['pending_appointments'] = \App\Models\Appointment::where('user_id', $userId)
                    ->where('status', 'pending')->count();
                $data['approved_appointments'] = \App\Models\Appointment::where('user_id', $userId)
                    ->where('status', 'approved')->count();
                    
                // Get upcoming appointments with details
                $upcomingApts = \App\Models\Appointment::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('appointment_date', '>=', now()->toDateString())
                    ->orderBy('appointment_date', 'asc')
                    ->limit(5)
                    ->get();
                    
                if ($upcomingApts->count() > 0) {
                    $data['upcoming_appointments'] = $upcomingApts->map(fn($apt) => [
                        'id' => $apt->id,
                        'date' => $apt->appointment_date?->format('Y-m-d'),
                        'time' => $apt->appointment_time,
                        'service' => $apt->service_type,
                        'status' => $apt->status,
                    ])->toArray();
                }
                
                // Check for pending refunds
                $pendingRefunds = \App\Models\Refund::whereHas('payment.appointment', fn($q) => $q->where('user_id', $userId))
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();
                if ($pendingRefunds > 0) {
                    $data['pending_refunds'] = $pendingRefunds;
                }
            }
            
            // Admin/Cashier see system-wide pending items
            if (in_array($role, ['admin', 'cashier'])) {
                $data['pending_items'] = \App\Models\Appointment::where('status', 'pending')->count()
                    + \App\Models\Refund::where('status', 'pending')->count();
            }

            return $data;
        } catch (\Exception $e) {
            Log::debug('Failed to gather user data: ' . $e->getMessage());
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
