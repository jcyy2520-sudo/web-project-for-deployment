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
    private SemanticEmbeddingsService $embeddingsService;
    private ChatbotMemoryService $memoryService;
    private LanguageDetectionService $languageService;
    private PersonalityService $personalityService;

    public function __construct(
        LLMService $llmService,
        ChatbotRoleAwarenessService $roleService,
        ChatbotRealTimeDataService $dataService,
        SemanticEmbeddingsService $embeddingsService,
        ChatbotMemoryService $memoryService,
        LanguageDetectionService $languageService,
        PersonalityService $personalityService
    ) {
        $this->llmService = $llmService;
        $this->roleService = $roleService;
        $this->dataService = $dataService;
        $this->embeddingsService = $embeddingsService;
        $this->memoryService = $memoryService;
        $this->languageService = $languageService;
        $this->personalityService = $personalityService;
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

            // 1. ENHANCED MEMORY: Get comprehensive conversation context
            $memoryContext = $this->memoryService->getConversationContext($userId, $conversationId);
            $conversationHistory = $memoryContext['recent_messages'] ?? [];

            // 2. ENHANCED LANGUAGE DETECTION
            if (!$language) {
                $langResult = $this->languageService->detect($userMessage);
                $language = $langResult['language'] === 'tl' ? 'filipino' : 'english';
            }

            // 3. ENHANCED RAG: Get relevant knowledge base information
            $ragContext = $this->embeddingsService->getRAGContext($userMessage);

            // 4. ENHANCED PERSONALITY: Get personalized system prompt
            $sentiment = $intentData['sentiment'] ?? 'neutral';
            $personalityPrompt = $this->personalityService->getPersonalizedSystemPrompt($role, $sentiment);

            // Build system context with comprehensive real-time data
            // CRITICAL: This is what makes the LLM accurate - it has real system data
            $systemContext = [
                'role' => $role,
                'language' => $language,
                'personality_prompt' => $personalityPrompt,
                'system_data' => $this->gatherSystemData($role),
                'user_info' => $userId ? $this->gatherUserData($userId, $role) : [],
                'rag_context' => $ragContext,
                'memory_context' => [
                    'summary' => $memoryContext['conversation_summary'],
                    'preferences' => $memoryContext['user_preferences'],
                    'topics' => $memoryContext['topics_discussed'],
                ],
            ];

            // Log what we're sending to LLM for debugging
            Log::debug('LLM Context Prepared with RAG and Memory', [
                'role' => $role,
                'language' => $language,
                'user_id' => $userId,
                'has_rag' => !empty($ragContext),
                'has_memory' => !empty($memoryContext['conversation_summary']),
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
                    'has_rag_context' => !empty($ragContext),
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
     * @deprecated Use LanguageDetectionService instead
     */
    private function detectLanguageFromMessage(string $message): string
    {
        $langResult = $this->languageService->detect($message);
        return $langResult['language'] === 'tl' ? 'filipino' : 'english';
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

            // Use the RealTimeDataService for consistent, cached, real-time data
            $data['services_available'] = $this->dataService->getAvailableServices();
            $data['business_hours'] = $this->dataService->getBusinessHours();
            
            if ($role === 'admin') {
                $data['system_stats'] = $this->dataService->getSystemStats();
                $data['pending_items'] = $this->dataService->getPendingAppointments(10);
            }
            
            if ($role === 'cashier') {
                $data['today_summary'] = $this->dataService->getTodaysSummary();
                $data['pending_payments'] = $this->dataService->getPendingPayments(10);
            }

            // Today's status
            $data['current_date'] = now()->format('F j, Y');
            $data['current_day'] = now()->format('l');
            $data['current_time'] = now()->format('H:i:s');
            
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
            $data = [];
            
            // Fetch real-time user data from the dedicated service
            $data['appointments'] = $this->dataService->getUserAppointments($userId);
            $data['payments'] = $this->dataService->getUserPayments($userId);
            $data['refunds'] = $this->dataService->getUserRefunds($userId);
            
            // Add summary metrics
            $data['total_appointments'] = count($data['appointments']);
            $data['pending_appointments'] = count(array_filter($data['appointments'], fn($a) => $a['status'] === 'pending'));
            
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
