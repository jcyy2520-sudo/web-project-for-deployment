<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatbotConversation;
use App\Models\ChatbotRateLimit;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\ChatbotAnalyticsService;
use App\Services\ChatbotNLUService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\ChatbotSmartResponseBuilder;
use App\Services\ChatbotActionService;
use App\Services\ChatbotLLMIntegration;
use App\Services\ChatbotGuardService;
use App\Services\LLMService;
use App\Services\SmartActionSuggestionService;
use App\Services\LanguageDetectionService;
use App\Services\AdvancedIntelligenceService;
use App\Services\ClarificationEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class ChatbotController extends Controller
{
    private ChatbotService $chatbotService;
    private ChatbotRoleAwarenessService $roleService;
    private ChatbotNLUService $nluService;
    private ChatbotRealTimeDataService $dataService;
    private ChatbotSmartResponseBuilder $responseBuilder;
    private ChatbotLLMIntegration $llmIntegration;
    private ChatbotGuardService $guardService;
    private SmartActionSuggestionService $suggestionService;
    private LanguageDetectionService $languageService;
    private AdvancedIntelligenceService $intelligenceService;
    private ClarificationEngineService $clarificationEngine;
    private ?ChatbotAnalyticsService $analyticsService = null;

    private const MAX_HISTORY = 10;

    public function __construct(
        ChatbotService $chatbotService,
        ChatbotRoleAwarenessService $roleService,
        ChatbotNLUService $nluService,
        ChatbotRealTimeDataService $dataService,
        ChatbotSmartResponseBuilder $responseBuilder,
        ChatbotLLMIntegration $llmIntegration,
        ChatbotGuardService $guardService,
        SmartActionSuggestionService $suggestionService,
        LanguageDetectionService $languageService,
        AdvancedIntelligenceService $intelligenceService,
        ClarificationEngineService $clarificationEngine
    ) {
        $this->chatbotService = $chatbotService;
        $this->roleService = $roleService;
        $this->nluService = $nluService;
        $this->dataService = $dataService;
        $this->responseBuilder = $responseBuilder;
        $this->llmIntegration = $llmIntegration;
        $this->guardService = $guardService;
        $this->suggestionService = $suggestionService;
        $this->languageService = $languageService;
        $this->intelligenceService = $intelligenceService;
        $this->clarificationEngine = $clarificationEngine;
        
        try {
            $this->analyticsService = app(ChatbotAnalyticsService::class);
        } catch (\Exception $e) {
            Log::debug('Analytics service not available: ' . $e->getMessage());
        }
    }
    
    /**
     * Get chatbot capabilities and status
     * Returns available features based on user role
     */
    public function getCapabilities(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $roleInfo = $this->roleService->detectUserRole($userId);
            
            // Get LLM status
            $llmStatus = $this->llmIntegration->getStatus();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $roleInfo['primary_role'],
                    'display_name' => $roleInfo['display_name'],
                    'capabilities' => array_keys(array_filter($roleInfo['capabilities'])),
                    'quick_actions' => $roleInfo['quick_actions'] ?? [],
                    'pending_items' => $roleInfo['pending_items'] ?? [],
                    'greeting' => $this->roleService->getRoleGreeting($userId),
                    'suggested_commands' => $this->roleService->getSuggestedCommands($userId),
                    'llm_available' => $llmStatus['available_provider'] !== null,
                    'llm_status' => $llmStatus,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get capabilities error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get capabilities',
            ], 500);
        }
    }
    
    /**
     * Execute a chatbot action directly
     * Used for quick actions and confirmations
     */
    public function executeAction(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|string',
                'params' => 'nullable|array',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to perform actions.',
                ], 401);
            }

            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'client';

            $action = $request->input('action');
            $params = $request->input('params') ?? [];

            $result = ChatbotActionService::executeAction($userId, $role, $action, $params);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Chatbot executeAction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute action: ' . $e->getMessage(),
            ], 500);
        }
    }
            
    /**
     * Confirm a pending action
     */
    public function confirmAction(Request $request)
    {
        try {
            $request->validate([
                'confirmation_key' => 'required|string',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to confirm actions.',
                ], 401);
            }

            $confirmationKey = $request->input('confirmation_key');
            $pending = ChatbotActionService::getPendingAction($userId, $confirmationKey);

            if (!$pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmation expired or invalid. Please try the action again.',
                ], 400);
            }

            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'client';

            $result = ChatbotActionService::executeAction(
                $userId, 
                $role, 
                $pending['action'], 
                $pending['params'], 
                true
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Chatbot confirmAction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm action: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Get chat history for the current user
     * Protected endpoint - authenticated users only
     * Supports filtering by conversation_id to load specific conversation
     */
    public function getHistory(Request $request)
    {
        try {
            $userId = auth()->id();
            
            // This endpoint requires authentication (enforced by middleware)
            // If we reach here without a userId, something is wrong
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'data' => []
                ], 401);
            }
            
            $limit = $request->query('limit', 20);
            $conversationId = $request->query('conversation_id');

            $query = ChatMessage::where('user_id', $userId);
            
            // If conversation_id is provided, filter by it
            // This is crucial for loading the correct conversation after switching
            if ($conversationId) {
                $query->where('conversation_id', $conversationId);
            }
            
            $messages = $query->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
                'conversation_id' => $conversationId
            ]);
        } catch (\Exception $e) {
            Log::error('ChatBot history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chat history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message and get INTELLIGENT AI response
     * 
     * Features:
     * - Role-aware responses (User, Admin, Cashier, Guest)
     * - Real-time system data integration
     * - Advanced NLU with fuzzy matching
     * - Intent detection and action execution
     * - Smart response building based on context
     * - Rate limiting and analytics
     */
    public function sendMessage(Request $request)
    {
        $startTime = microtime(true);
        $userId = null;
        $userMessage = null;
        
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
                'conversation_id' => 'nullable|string'
            ]);

            // IMPORTANT: Try to authenticate user from the request token
            // Even though this is a public endpoint, authenticated users should be recognized
            $userId = null;
            $isGuest = true;
            
            // Check if user is authenticated via Sanctum token
            if (auth('sanctum')->check()) {
                $userId = auth('sanctum')->id();
                $isGuest = false;
            } elseif (auth()->check()) {
                // Fallback to default guard
                $userId = auth()->id();
                $isGuest = false;
            }
            
            $userMessage = $request->input('message');
            $conversationId = $request->input('conversation_id') ?? uniqid('chat_');
            $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId() ?? uniqid('session_');
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // ========== SAFETY & CONTENT CHECKS (BEFORE ANY PROCESSING) ==========
            
            // Step 0A: Check for inappropriate/offensive content
            $contentCheck = $this->guardService->checkContent($userMessage);
            if (!$contentCheck['safe']) {
                Log::warning('Chatbot: Inappropriate content blocked', [
                    'user_id' => $userId,
                    'ip' => $ipAddress,
                    'type' => $contentCheck['type'],
                    'message_snippet' => substr($userMessage, 0, 50),
                ]);
                
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $contentCheck['response'],
                    'meta' => [
                        'source' => 'content_filter',
                        'content_filtered' => true,
                        'filter_type' => $contentCheck['type'],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
            
            // Step 0B: Check if request is within system scope
            $scopeCheck = $this->guardService->checkScope($userMessage);
            if (!$scopeCheck['in_scope']) {
                Log::info('Chatbot: Out-of-scope request', [
                    'user_id' => $userId,
                    'message_snippet' => substr($userMessage, 0, 50),
                ]);
                
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $scopeCheck['response'],
                    'meta' => [
                        'source' => 'scope_filter',
                        'out_of_scope' => true,
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }


            // ========== INTELLIGENT CHATBOT FLOW ==========

            // Step 1: Detect User Role (using the authenticated user ID)
            $roleInfo = null;
            $userRole = 'guest';
            
            try {
                // IMPORTANT: Clear role cache to ensure real-time role detection
                // This ensures the chatbot always uses the current user role
                if ($userId) {
                    $this->roleService->clearRoleCache($userId);
                }
                
                $roleInfo = $this->roleService->detectUserRole($userId);
                $userRole = $roleInfo['primary_role'] ?? 'guest';
                
                // Log role detection for debugging
                Log::info('Chatbot role detection', [
                    'user_id' => $userId,
                    'is_guest' => $isGuest,
                    'detected_role' => $userRole,
                    'role_info' => $roleInfo,
                    'auth_sanctum_check' => auth('sanctum')->check(),
                    'auth_check' => auth()->check(),
                ]);
            } catch (\Exception $roleEx) {
                Log::warning('Failed to detect user role: ' . $roleEx->getMessage());
                $userRole = $isGuest ? 'guest' : 'client';
                $roleInfo = ['primary_role' => $userRole, 'is_authenticated' => !$isGuest];
            }

            // Step 2: Analyze User Input (NLU) with Enhanced Intelligence
            $intentData = [];
            $entities = [];
            $sentiment = 'neutral';
            $sentimentScore = 3;
            $isFollowUp = false;
            $detectedLanguage = 'english';
            
            try {
                // Detect language for bilingual support (Filipino/English/Taglish)
                $langResult = $this->languageService->detect($userMessage);
                $detectedLanguage = $langResult['language'] === 'tl' ? 'filipino' : 'english';
                
                // ENHANCED: Pre-process message for typos and normalize input
                $enhancedMessage = $this->intelligenceService->enhanceMessage($userMessage);
                $normalizedMessage = $enhancedMessage['normalized'];
                $messageLanguage = $enhancedMessage['detected_language'];
                
                // Use normalized message for better intent detection
                $nluContext = [
                    'role' => $userRole,
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'language' => $detectedLanguage,
                    'original_message' => $userMessage,
                    'normalized_message' => $normalizedMessage,
                ];
                
                $intentData = $this->nluService->detectIntent($normalizedMessage, $nluContext);
                $entities = $this->nluService->extractEntities($normalizedMessage);
                $sentimentData = $this->nluService->analyzeSentiment($userMessage);
                $sentiment = $sentimentData['sentiment'];
                $sentimentScore = $sentimentData['score'];
                
                // Check if this is a follow-up to previous conversation
                $isFollowUp = $intentData['is_follow_up'] ?? false;
                
                // Check for confirmation/cancellation intents
                if (in_array($intentData['intent'], ['confirmation', 'cancellation'])) {
                    // Handle as action confirmation
                    $confirmationHandled = $this->handleConfirmationResponse(
                        $userId, 
                        $intentData['intent'], 
                        $conversationId
                    );
                    if ($confirmationHandled) {
                        $intentData = $confirmationHandled;
                    }
                }
                
            } catch (\Exception $nluEx) {
                Log::warning('Failed NLU analysis: ' . $nluEx->getMessage());
                $intentData = ['intent' => 'general_question', 'confidence' => 0.5];
                $entities = [];
                $sentiment = 'neutral';
                $sentimentScore = 3;
            }
            
            // === CLARIFICATION-FIRST LOGIC ===
            // Check if user's request is ambiguous and needs clarification BEFORE processing
            try {
                $clarificationContext = [
                    'user_id' => $userId,
                    'role' => $userRole,
                    'conversation_id' => $conversationId,
                    'language' => $detectedLanguage,
                    'intent' => $intentData['intent'] ?? 'unknown',
                    'entities' => $entities,
                    'confidence' => $intentData['confidence'] ?? 0.5,
                    'is_follow_up' => $isFollowUp,
                ];
                
                $clarificationResult = $this->clarificationEngine->analyze(
                    $normalizedMessage ?? $userMessage,
                    $clarificationContext
                );
                
                if ($clarificationResult['needs_clarification'] && !$isFollowUp) {
                    Log::info('Chatbot: Clarification needed', [
                        'user_id' => $userId,
                        'reason' => $clarificationResult['clarification_type'] ?? 'ambiguous',
                        'confidence' => $intentData['confidence'] ?? 0,
                    ]);
                    
                    // Format clarification response in user's preferred language
                    $clarificationResponse = $this->clarificationEngine->formatClarificationResponse(
                        $clarificationResult,
                        $detectedLanguage
                    );
                    
                    // Save the pending clarification to context for follow-up handling
                    $this->intelligenceService->storeContext($conversationId, [
                        'pending_clarification' => $clarificationResult,
                        'original_message' => $userMessage,
                        'detected_intent' => $intentData['intent'] ?? 'unknown',
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'conversation_id' => $conversationId,
                        'user_message' => $userMessage,
                        'ai_response' => $clarificationResponse,
                        'meta' => [
                            'source' => 'clarification_engine',
                            'intent' => $intentData['intent'] ?? 'unknown',
                            'role' => $userRole,
                            'needs_clarification' => true,
                            'clarification_type' => $clarificationResult['clarification_type'] ?? 'general',
                            'suggestions' => $clarificationResult['suggestions'] ?? [],
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }
            } catch (\Exception $clarEx) {
                Log::warning('Clarification analysis failed: ' . $clarEx->getMessage());
                // Continue without clarification if engine fails
            }
            
            // Step 2B: Check if user is trying to request bot to perform actions
            $detectedIntent = $intentData['intent'] ?? 'general_question';
            $actionCheck = $this->guardService->checkActionRequest($userMessage, $detectedIntent);
            if ($actionCheck['is_action_request']) {
                // User is asking bot to DO something - provide guidance instead
                Log::info('Chatbot: Action request detected, providing guidance', [
                    'user_id' => $userId,
                    'intent' => $detectedIntent,
                ]);
                
                $guidanceResponse = $this->responseBuilder->buildActionGuidanceResponse(
                    $detectedIntent,
                    $detectedLanguage ?? 'english'
                );
                
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $guidanceResponse['response'],
                    'meta' => [
                        'source' => 'action_guidance',
                        'intent' => $detectedIntent,
                        'role' => $userRole,
                        'action_guidance' => true,
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
            
            // Check role permission for the detected intent
            $permissionCheck = $this->roleService->canPerformIntent($userId, $detectedIntent);
            if (!$permissionCheck['allowed'] && ($intentData['confidence'] ?? 0) > 0.7) {
                // User is trying to access something they don't have permission for
                Log::info('Permission denied for intent', [
                    'user_id' => $userId,
                    'role' => $userRole,
                    'intent' => $detectedIntent,
                ]);
                
                // Provide role restriction response
                $restrictionResponse = $this->responseBuilder->buildRoleRestrictionResponse(
                    $detectedIntent,
                    $userRole,
                    $permissionCheck['roles_with_access'] ?? ['admin'],
                    $detectedLanguage ?? 'english'
                );
                
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $restrictionResponse['response'],
                    'meta' => [
                        'source' => 'role_restriction',
                        'intent' => $detectedIntent,
                        'role' => $userRole,
                        'role_restricted' => true,
                        'required_roles' => $permissionCheck['roles_with_access'] ?? [],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            // === RATE LIMITING CHECK ===
            try {
                $rateLimitStatus = ChatbotRateLimit::isRateLimited($userId, $sessionId, $ipAddress, $conversationId);
            } catch (\Exception $rateLimitEx) {
                Log::warning('Rate limit check failed: ' . $rateLimitEx->getMessage());
                $rateLimitStatus = ['limited' => false, 'remaining' => 100];
            }
            
            if ($rateLimitStatus['limited']) {
                try {
                    $this->logAnalytics([
                        'start_time' => $startTime,
                        'user_id' => $userId,
                        'conversation_id' => $conversationId,
                        'session_id' => $sessionId,
                        'user_message' => $userMessage,
                        'is_rate_limited' => true,
                        'success' => false,
                        'failure_reason' => $rateLimitStatus['reason'],
                        'user_role' => $userRole,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                } catch (\Exception $e) {
                    Log::debug('Analytics logging failed: ' . $e->getMessage());
                }

                return response()->json([
                    'success' => false,
                    'rate_limited' => true,
                    'rate_limit_info' => $rateLimitStatus,
                    'message' => $rateLimitStatus['message'],
                    'must_start_new_conversation' => $rateLimitStatus['must_start_new'] ?? false,
                    'conversation_id' => $conversationId,
                ], 429);
            }

            // For guest users, provide quick response without saving
            if ($isGuest) {
                $aiResponse = $this->getGuestResponse($userMessage);
                
                try {
                    $this->logAnalytics([
                        'start_time' => $startTime,
                        'user_id' => null,
                        'conversation_id' => $conversationId,
                        'session_id' => $sessionId,
                        'user_message' => $userMessage,
                        'bot_response' => $aiResponse,
                        'response_source' => 'guest_response',
                        'success' => true,
                        'user_role' => 'guest',
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                } catch (\Exception $e) {
                    Log::debug('Analytics logging failed for guest: ' . $e->getMessage());
                }

                try {
                    ChatbotRateLimit::incrementCount(null, $sessionId, $ipAddress, $conversationId);
                } catch (\Exception $e) {
                    Log::debug('Rate limit increment failed: ' . $e->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $aiResponse,
                    'meta' => [
                        'source' => 'guest_response',
                        'role' => 'guest',
                        'rate_limit_remaining' => ChatbotRateLimit::MESSAGES_PER_CONVERSATION - 1,
                    ],
                    'timestamp' => now()->toIso8601String()
                ]);
            }

            // Save user message
            try {
                ChatMessage::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'message' => $userMessage,
                    'role' => 'user',
                    'source' => 'user'
                ]);
                
                // Ensure conversation is tracked in ChatbotConversation table
                ChatbotConversation::updateOrCreate(
                    ['conversation_id' => $conversationId],
                    [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'status' => 'active',
                        'last_activity_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Failed to save user message: ' . $e->getMessage());
            }

            // Get conversation context (last messages)
            try {
                $recentMessages = ChatMessage::where('user_id', $userId)
                    ->where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc')
                    ->limit(self::MAX_HISTORY)
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Failed to fetch conversation history: ' . $e->getMessage());
                $recentMessages = collect([]);
            }

            // === BUILD INTELLIGENT RESPONSE ===
            
            // Check if this is a priority/urgent message
            $isPriority = ($sentimentScore >= 4 || in_array($sentiment, ['angry', 'frustrated']));

            // Detect user's language for bilingual response
            $langResult = $this->languageService->detect($userMessage);
            $detectedLanguage = $langResult['language'] === 'tl' ? 'filipino' : 'english';
            
            // Build response using all available services
            // IMPORTANT: Pass both 'message' and 'user_message' for compatibility
            // Also pass 'role_info' which the SmartResponseBuilder expects
            $responseContext = [
                'user_id' => $userId,
                'user_role' => $userRole,
                'role_info' => $roleInfo,
                'message' => $userMessage,
                'user_message' => $userMessage,
                'intent' => $intentData['intent'],
                'intent_confidence' => $intentData['confidence'],
                'entities' => $entities,
                'sentiment' => $sentiment,
                'sentiment_score' => $sentimentScore,
                'is_priority' => $isPriority,
                'conversation_history' => $recentMessages,
                'language' => $detectedLanguage,
            ];

            try {
                // Use SmartResponseBuilder to create role-aware response
                $responseData = $this->responseBuilder->build($responseContext);
                $aiResponse = $responseData['response'] ?? null;
                $meta = $responseData['meta'] ?? [];
                $actionExecuted = $responseData['action_executed'] ?? false;
                $actionResult = $responseData['action_result'] ?? null;
                
                // Check if SmartResponseBuilder is signaling to use LLM
                $shouldUseLLM = $responseData['should_use_llm'] ?? false;
                $isFallback = ($responseData['meta']['source'] ?? null) === 'fallback';
                
                // If SmartResponseBuilder returned a generic response or flagged for LLM, try LLM for better intelligence
                if (($shouldUseLLM || $isFallback) && $this->llmIntegration->isAvailable()) {
                    Log::debug('Using LLM for intelligent response', [
                        'intent' => $intentData['intent'],
                        'confidence' => $intentData['confidence'],
                        'language' => $detectedLanguage,
                        'should_use_llm_flag' => $shouldUseLLM,
                        'is_fallback' => $isFallback,
                    ]);
                    
                    $llmResult = $this->llmIntegration->shouldUseLLMAndRespond(
                        $userId,
                        $userMessage,
                        $conversationId,
                        $intentData,
                        $detectedLanguage
                    );
                    
                    if ($llmResult && !empty($llmResult['response'])) {
                        $aiResponse = $llmResult['response'];
                        $meta = array_merge($meta, $llmResult['meta']);
                        Log::debug('LLM response generated successfully');
                    } else {
                        Log::debug('LLM did not generate response, keeping SmartResponseBuilder result');
                    }
                }
            } catch (\Exception $e) {
                Log::warning('SmartResponseBuilder failed: ' . $e->getMessage());
                
                // Try LLM before fallback
                $llmResult = null;
                try {
                    if ($this->llmIntegration->isAvailable()) {
                        $llmResult = $this->llmIntegration->generateLLMResponse(
                            $userId,
                            $userMessage,
                            $conversationId,
                            $intentData,
                            $detectedLanguage
                        );
                    }
                } catch (\Exception $llmEx) {
                    Log::debug('LLM fallback also failed: ' . $llmEx->getMessage());
                }
                
                if ($llmResult) {
                    $aiResponse = $llmResult['response'];
                    $meta = $llmResult['meta'];
                } else {
                    // Last resort: simple fallback
                    $aiResponse = $this->getFallbackResponse($userMessage);
                    $meta = ['source' => 'fallback', 'fallback_reason' => 'builder_error'];
                }
                
                $actionExecuted = false;
                $actionResult = null;
            }

            // Ensure response is valid
            if (!$aiResponse || !is_string($aiResponse) || strlen(trim($aiResponse)) === 0) {
                Log::warning('Empty AI response, using fallback', [
                    'user_id' => $userId,
                    'message' => $userMessage,
                ]);
                $aiResponse = "I'm here to help! I can assist you with appointments, services, payments, refunds, and more. How can I help you today?";
                $meta['source'] = 'fallback';
                $meta['fallback_reason'] = 'empty_response';
            }

            // Truncate if too long
            if (strlen($aiResponse) > 5000) {
                $aiResponse = substr($aiResponse, 0, 4997) . '...';
            }

            // Save AI response
            try {
                ChatMessage::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'message' => $aiResponse,
                    'role' => 'assistant',
                    'source' => $meta['source'] ?? 'smart_builder'
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to save AI response: ' . $e->getMessage());
            }

            // Build final metadata
            $finalMeta = array_merge($meta, [
                'user_role' => $userRole,
                'intent' => $intentData['intent'],
                'intent_confidence' => $intentData['confidence'],
                'sentiment' => $sentiment,
                'sentiment_score' => $sentimentScore,
                'is_priority' => $isPriority,
                'entities' => count($entities) > 0 ? $entities : null,
                'rate_limit_remaining' => max(0, ($rateLimitStatus['remaining'] ?? ChatbotRateLimit::MESSAGES_PER_CONVERSATION) - 1),
                'conversation_message_count' => $recentMessages->count() + 2,
                'context_refreshed_at' => now()->toIso8601String(),
            ]);

            // Include action buttons if present from response builder
            $actionButtons = $responseData['action_buttons'] ?? null;
            if ($actionButtons && is_array($actionButtons) && !empty($actionButtons)) {
                $finalMeta['action_buttons'] = $actionButtons;
            }

            if ($isPriority) {
                $finalMeta['priority_reason'] = $this->getPriorityReason($sentiment, $sentimentScore, $userMessage);
            }

            // === LOG ANALYTICS ===
            try {
                $this->logAnalytics([
                    'start_time' => $startTime,
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'session_id' => $sessionId,
                    'user_message' => $userMessage,
                    'bot_response' => $aiResponse,
                    'intent' => $intentData['intent'],
                    'sentiment' => $sentiment,
                    'sentiment_score' => $sentimentScore,
                    'response_source' => $meta['source'] ?? 'smart_builder',
                    'success' => true,
                    'is_priority' => $isPriority,
                    'action_type' => $intentData['intent'],
                    'action_executed' => $actionExecuted,
                    'action_result' => $actionResult,
                    'user_role' => $userRole,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'entities_count' => count($entities),
                ]);
            } catch (\Exception $e) {
                Log::debug('Analytics logging failed: ' . $e->getMessage());
            }

            // Increment rate limit counter
            try {
                ChatbotRateLimit::incrementCount($userId, $sessionId, $ipAddress, $conversationId);
            } catch (\Exception $e) {
                Log::debug('Failed to increment rate limit: ' . $e->getMessage());
            }

            // Get smart suggestions for next steps
            $suggestions = [];
            try {
                $suggestions = $this->suggestionService->getSuggestions(
                    $userId,
                    $userRole,
                    $userMessage,
                    $intentData
                );
            } catch (\Exception $e) {
                Log::debug('Failed to get suggestions: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'suggestions' => $suggestions,
                'meta' => $finalMeta,
                'timestamp' => now()->toIso8601String()
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ChatBot sendMessage error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'message' => $userMessage,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process message',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your message.'
            ], 500);
        }
    }

    /**
     * Log analytics data
     */
    private function logAnalytics(array $data): void
    {
        try {
            if ($this->analyticsService) {
                $this->analyticsService->logInteraction($data);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log chatbot analytics: ' . $e->getMessage());
        }
    }

    /**
     * Get priority reason description
     */
    private function getPriorityReason(string $sentiment, int $sentimentScore, string $message): string
    {
        $reasons = [];
        
        if ($sentimentScore >= 4) {
            $reasons[] = 'high_negative_sentiment';
        }
        
        if (in_array($sentiment, ['angry', 'frustrated'])) {
            $reasons[] = 'user_' . $sentiment;
        }
        
        $lower = mb_strtolower($message);
        if (preg_match('/(urgent|emergency|critical|asap|immediately)/i', $lower)) {
            $reasons[] = 'urgency_keywords';
        }
        
        if (preg_match('/(help me|please help|need help)/i', $lower)) {
            $reasons[] = 'help_request';
        }
        
        return implode(', ', $reasons) ?: 'elevated_attention';
    }
    
    /**
     * Handle confirmation/cancellation response in conversation flow
     */
    private function handleConfirmationResponse(int $userId, string $intent, string $conversationId): ?array
    {
        try {
            // Look for any pending action in cache
            $cachePattern = "chatbot_confirm_{$userId}_";
            
            // Get the most recent pending action (within last 5 minutes)
            // This is a simplified approach - in production you might want to track this better
            $recentMessage = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->where('role', 'assistant')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first();
            
            if (!$recentMessage) {
                return null;
            }
            
            // Check if the message contained a confirmation request
            $message = $recentMessage->message;
            if (strpos($message, 'confirm') !== false || strpos($message, 'Are you sure') !== false) {
                // There was a pending confirmation
                if ($intent === 'confirmation') {
                    return [
                        'intent' => 'execute_pending_action',
                        'confidence' => 0.95,
                        'confirmed' => true,
                    ];
                } else {
                    return [
                        'intent' => 'action_cancelled',
                        'confidence' => 0.95,
                        'confirmed' => false,
                    ];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Error handling confirmation response: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get real-time data based on intent (for AJAX requests)
     */
    public function getRealTimeData(Request $request)
    {
        try {
            $request->validate([
                'data_type' => 'required|string',
                'params' => 'nullable|array',
            ]);
            
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }
            
            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'];
            $dataType = $request->input('data_type');
            $params = $request->input('params', []);
            
            // Map data types to data service methods
            $data = match($dataType) {
                'appointments' => $role === 'client' 
                    ? $this->dataService->getUserAppointments($userId)
                    : $this->dataService->getAllAppointments($params),
                'pending_appointments' => $this->dataService->getPendingAppointments(),
                'payments' => $this->dataService->getUserPaymentStatus($userId),
                'refunds' => $this->dataService->getUserRefundStatus($userId),
                'services' => $this->dataService->getServices(),
                'analytics' => $this->roleService->hasAdminPrivileges($userId) 
                    ? $this->dataService->getSystemAnalytics()
                    : ['error' => 'Permission denied'],
                'shift_report' => $this->roleService->hasFinancialAccess($userId)
                    ? $this->dataService->getCashierShiftData($params['start_date'] ?? null, $params['end_date'] ?? null)
                    : ['error' => 'Permission denied'],
                'user_stats' => $this->dataService->getUserStats($userId),
                default => ['error' => 'Unknown data type'],
            };
            
            if (isset($data['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $data['error'],
                ], $data['error'] === 'Permission denied' ? 403 : 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'data_type' => $dataType,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get real-time data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data',
            ], 500);
        }
    }

    /**
     * Get rate limit status for the current user/session
     */
    public function getRateLimitStatus(Request $request)
    {
        try {
            $userId = auth()->id();
            $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId();
            $conversationId = $request->input('conversation_id');
            
            $status = ChatbotRateLimit::getStatus($userId, $sessionId, $conversationId);
            $limitCheck = ChatbotRateLimit::isRateLimited($userId, $sessionId, $request->ip(), $conversationId);
            
            return response()->json([
                'success' => true,
                'data' => array_merge($status, [
                    'is_limited' => $limitCheck['limited'],
                    'limit_reason' => $limitCheck['reason'] ?? null,
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get rate limit status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chatbot analytics (admin only)
     */
    public function getAnalytics(Request $request)
    {
        try {
            $period = $request->input('period', 'day');
            
            if (!$this->analyticsService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analytics service not available'
                ], 503);
            }
            
            $summary = $this->analyticsService->getDashboardSummary($period);
            $performance = $this->analyticsService->getPerformanceMetrics($period);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'performance' => $performance,
                    'period' => $period,
                    'generated_at' => now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get priority conversations needing attention (admin only)
     */
    public function getPriorityConversations(Request $request)
    {
        try {
            if (!$this->analyticsService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analytics service not available'
                ], 503);
            }
            
            $priority = $this->analyticsService->getPriorityMessages(50);
            $needsAttention = $this->analyticsService->getConversationsNeedingAttention();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'priority_messages' => $priority,
                    'conversations_needing_attention' => $needsAttention,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch priority conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get failed questions for training (admin only)
     */
    public function getTrainingData(Request $request)
    {
        try {
            $limit = min($request->input('limit', 100), 500);
            
            if (!$this->analyticsService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analytics service not available'
                ], 503);
            }
            
            $trainingData = $this->analyticsService->getTrainingData($limit);
            
            return response()->json([
                'success' => true,
                'data' => $trainingData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch training data',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Get a fallback response when API fails
     * Uses dynamic data from database instead of hardcoded responses
     */
    private function getFallbackResponse($userMessage)
    {
        // Use AdvancedIntelligenceService to generate a dynamic response based on live data
        try {
            $analysis = $this->intelligenceService->analyzeForAmbiguity($userMessage, []);
            $topics = $analysis['detected_topics'] ?? [];
            $topic = $topics[0] ?? 'general';

            $data = [];
            // Pass lightweight context where possible (counts etc.)
            if ($topic === 'services') {
                $data['services_count'] = \App\Models\Service::where('is_active', true)->count();
            }

            if ($topic === 'appointment' || $topic === 'appointments') {
                $data['appointments_count'] = \App\Models\Appointment::count();
            }

            $structured = $this->intelligenceService->buildStructuredResponse($topic, $data, [], 'en');
            $nl = $this->intelligenceService->structuredToNaturalLanguage($structured);
            return $nl;
        } catch (\Exception $e) {
            Log::debug('Fallback dynamic generation failed: ' . $e->getMessage());
        }

        return "I can help you with appointments, services, pricing, and more. Please ask a specific question and I'll fetch the latest information.";
    }

    /**
     * Get response for guest users (not authenticated)
     * Uses dynamic data when available
     */
    private function getGuestResponse($userMessage)
    {
        try {
            $analysis = $this->intelligenceService->analyzeForAmbiguity($userMessage, []);
            $topics = $analysis['detected_topics'] ?? [];
            $topic = $topics[0] ?? 'general';

            $data = [];
            if ($topic === 'services') {
                $data['services_count'] = \App\Models\Service::where('is_active', true)->count();
            }

            $structured = $this->intelligenceService->buildStructuredResponse($topic, $data, [], 'en');
            $nl = $this->intelligenceService->structuredToNaturalLanguage($structured);

            // For guests, include prompt to register but keep content dynamic
            $nl .= "\n\nTo perform account-specific actions, please register or log in so I can fetch your personal data.";
            return $nl;
        } catch (\Exception $e) {
            Log::debug('Guest dynamic response failed: ' . $e->getMessage());
            return "Thanks for your question! To get personalized assistance and access all features, please register or log in.";
        }
    }

    /**
     * Get suggested questions based on user role and system state
     */
    public function getSuggestedQuestions(Request $request)
    {
        try {
            // Try to get user ID from Sanctum first, then fallback to default auth
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            // Log for debugging
            Log::debug('getSuggestedQuestions auth check', [
                'sanctum_id' => auth('sanctum')->id(),
                'default_id' => auth()->id(),
                'resolved_id' => $userId,
            ]);

            // For guests, try service first, fallback to hardcoded
            if (!$userId) {
                try {
                    $questions = $this->chatbotService->getSuggestedQuestions(null);
                    if (!empty($questions)) {
                        return response()->json([
                            'success' => true,
                            'data' => $questions,
                            'dynamic_updates' => [],
                            'role' => 'guest'
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Service failed for guest suggested questions: ' . $e->getMessage());
                }

                // Fallback to hardcoded questions
                return response()->json([
                    'success' => true,
                    'data' => [
                        "How do I book an appointment?",
                        "What services do you offer?",
                        "How do I register?",
                        "What are your business hours?"
                    ],
                    'dynamic_updates' => [],
                    'role' => 'guest'
                ]);
            }

            // Get user role for dynamic suggestions
            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'client';
            
            $questions = $this->chatbotService->getSuggestedQuestions($userId);
            
            // Get dynamic updates based on role
            $dynamicUpdates = [];
            try {
                switch ($role) {
                    case 'admin':
                    case 'administrator':
                        $dynamicUpdates = $this->chatbotService->getAdminDynamicSuggestions();
                        break;
                    case 'cashier':
                        $dynamicUpdates = $this->chatbotService->getCashierDynamicSuggestions($userId);
                        break;
                    case 'client':
                    case 'user':
                        $dynamicUpdates = $this->chatbotService->getClientDynamicSuggestions($userId);
                        break;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get dynamic suggestions: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $questions,
                'dynamic_updates' => $dynamicUpdates,
                'role' => $role
            ]);
        } catch (\Exception $e) {
            Log::error('Get suggested questions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggested questions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear chat history
     */
    public function clearHistory(Request $request)
    {
        try {
            $userId = auth()->id();
            $conversationId = $request->input('conversation_id');

            if ($conversationId) {
                ChatMessage::where('user_id', $userId)
                    ->where('conversation_id', $conversationId)
                    ->delete();
            } else {
                ChatMessage::where('user_id', $userId)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Chat history cleared'
            ]);
        } catch (\Exception $e) {
            Log::error('Clear history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save chatbot message to Messages section for both user and admin visibility
     * Supports both authenticated users and guests (silently skips for guests)
     */
    public function saveMessageToMessageCenter(Request $request)
    {
        try {
            $request->validate([
                'message' => 'required|string',
                'role' => 'required|in:user,assistant',
                'conversation_id' => 'nullable|string'
            ]);

            $userId = auth()->id();
            $message = $request->input('message');
            $role = $request->input('role');
            $conversationId = $request->input('conversation_id');

            // For guests, silently skip persistence but return success
            // This prevents 401 errors on frontend and allows graceful degradation
            if (!$userId) {
                Log::debug('Skipping message persistence for guest user', [
                    'conversation_id' => $conversationId,
                    'message_length' => strlen($message)
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Message not persisted (guest user)',
                    'is_guest' => true
                ]);
            }

            // Determine sender and receiver based on role
            // For user messages: sender is the current user, receiver is the admin
            // For assistant messages: sender is the admin, receiver is the current user
            if ($role === 'user') {
                $senderId = $userId;
                // Get the first admin user (or create one if needed)
                $receiverId = $this->getAdminUserId();
            } else {
                // Assistant response - save as from admin to user
                $senderId = $this->getAdminUserId();
                $receiverId = $userId;
            }

            // If we couldn't determine a sender or receiver (eg. no admin user),
            // skip persisting to the Message center to avoid DB constraint errors.
            if (empty($senderId) || empty($receiverId)) {
                Log::warning('Skipping saveMessageToMessageCenter: missing sender or receiver', [
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'user_id' => $userId
                ]);

                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No admin found; message not persisted.'
                ]);
            }

            // Create message in Message model with chatbot type for filtering
            $messageModel = \App\Models\Message::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => $message,
                'conversation_id' => $conversationId,
                'type' => 'chatbot',
                'subject' => 'AI Chat Assistant',
                'read' => false
            ]);

            return response()->json([
                'success' => true,
                'data' => $messageModel
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Save message to Message Center error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save message',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while saving your message.'
            ], 500);
        }
    }

    /**
     * Get the admin user ID (first user with admin role)
     * Falls back gracefully if no admin role is found
     */
    private function getAdminUserId()
    {
        try {
            // Allow explicit override via env/config so deployments can pin an admin
            $configuredId = (int) env('CHATBOT_ADMIN_USER_ID', 0);
            if ($configuredId > 0) {
                $configuredUser = User::find($configuredId);
                if ($configuredUser) {
                    return $configuredUser->id;
                }
            }

            // Try to find a user with admin role
            try {
                $adminUser = User::where('role', 'admin')->first();
                if ($adminUser) {
                    return $adminUser->id;
                }
            } catch (\Exception $roleError) {
                Log::debug('Admin user not found, trying alternative methods: ' . $roleError->getMessage());
            }

            // Fallback: Try to find an existing chatbot admin system user (created in previous requests)
            $chatbotUser = User::where('username', 'ai-chatbot-assistant')
                ->where('email', 'chatbot@system.local')
                ->first();
            
            if ($chatbotUser) {
                return $chatbotUser->id;
            }

            // Try to get the most privileged user (usually first user or creator)
            // Look for users with is_active status (more likely to be admin)
            $user = User::where('is_active', true)
                ->where('role', 'admin')
                ->orderBy('id', 'asc')
                ->first();
            
            if ($user) {
                return $user->id;
            }

            // Try any active user
            $user = User::where('is_active', true)
                ->orderBy('id', 'asc')
                ->first();
            
            if ($user) {
                return $user->id;
            }

            // Last resort: get any user
            $user = User::first();
            if ($user) {
                return $user->id;
            }

            // If no user exists at all, create a lightweight system user to avoid NULL FK errors
            $systemUser = User::create([
                'username' => 'ai-chatbot-assistant',
                'email' => 'chatbot@system.local',
                'password' => Hash::make(Str::random(32)),
                'role' => 'admin',
                'first_name' => 'AI',
                'last_name' => 'Chatbot Assistant',
                'is_active' => true,
            ]);

            return $systemUser->id ?? null;
        } catch (\Exception $e) {
            Log::warning('Could not determine admin user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get chat history (conversation summary)
     */
    public function getConversationSummary(Request $request)
    {
        try {
            $userId = auth()->id();
            $conversationId = $request->query('conversation_id');

            $messages = ChatMessage::where('user_id', $userId);
            
            if ($conversationId) {
                $messages = $messages->where('conversation_id', $conversationId);
            }

            $summary = $messages->get()->groupBy('conversation_id')->map(function ($msgs) {
                return [
                    'total_messages' => $msgs->count(),
                    'user_messages' => $msgs->where('role', 'user')->count(),
                    'assistant_messages' => $msgs->where('role', 'assistant')->count(),
                    'created_at' => $msgs->first()->created_at,
                    'updated_at' => $msgs->last()->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Get summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of all conversations for the current user
     * Returns conversation ID, title (first message preview), last activity, and message count
     */
    public function getConversations(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'data' => []
                ], 401);
            }

            // Get all conversations that have messages, grouped by conversation_id
            $conversations = ChatMessage::where('user_id', $userId)
                ->select('conversation_id')
                ->distinct()
                ->get()
                ->map(function ($conv) use ($userId) {
                    $messages = ChatMessage::where('user_id', $userId)
                        ->where('conversation_id', $conv->conversation_id)
                        ->orderBy('created_at', 'asc')
                        ->get();

                    // Skip conversations with no messages
                    if ($messages->count() === 0) {
                        return null;
                    }

                    $firstUserMessage = $messages->where('role', 'user')->first();
                    $lastMessage = $messages->last();
                    
                    // Generate title from first user message (truncated)
                    $title = $firstUserMessage 
                        ? Str::limit($firstUserMessage->message, 50) 
                        : 'New Conversation';

                    // Check if this conversation has a record in ChatbotConversation
                    $convRecord = ChatbotConversation::where('conversation_id', $conv->conversation_id)->first();

                    return [
                        'conversation_id' => $conv->conversation_id,
                        'title' => $convRecord?->title ?? $title,
                        'message_count' => $messages->count(),
                        'last_message' => $lastMessage ? Str::limit($lastMessage->message, 100) : null,
                        'last_message_role' => $lastMessage?->role,
                        'created_at' => $messages->first()?->created_at,
                        'updated_at' => $lastMessage?->created_at ?? $convRecord?->last_activity_at,
                        'status' => $convRecord?->status ?? 'active',
                    ];
                })
                ->filter() // Remove null entries (conversations with no messages)
                ->sortByDesc('updated_at')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            Log::error('Get conversations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start a new conversation
     * Generates a new conversation_id and returns it
     * Also properly closes/saves the previous conversation
     */
    public function startNewConversation(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get the previous conversation ID from the request (if provided)
            $previousConversationId = $request->input('previous_conversation_id');
            
            // Properly close/save the previous conversation if it exists
            if ($previousConversationId) {
                try {
                    // Check if there are messages in the previous conversation
                    $previousMessages = ChatMessage::where('user_id', $userId)
                        ->where('conversation_id', $previousConversationId)
                        ->orderBy('created_at', 'asc')
                        ->get();
                    
                    if ($previousMessages->count() > 0) {
                        // Ensure the conversation record exists in chatbot_conversations table
                        $firstUserMessage = $previousMessages->where('role', 'user')->first();
                        $lastMessage = $previousMessages->last();
                        
                        ChatbotConversation::updateOrCreate(
                            ['conversation_id' => $previousConversationId],
                            [
                                'user_id' => $userId,
                                'title' => $firstUserMessage 
                                    ? Str::limit($firstUserMessage->message, 50) 
                                    : 'Conversation',
                                'message_count' => $previousMessages->count(),
                                'user_message_count' => $previousMessages->where('role', 'user')->count(),
                                'bot_message_count' => $previousMessages->where('role', 'assistant')->count(),
                                'status' => 'completed',
                                'last_activity_at' => $lastMessage?->created_at ?? now(),
                            ]
                        );
                        
                        Log::info('Previous conversation saved to history', [
                            'conversation_id' => $previousConversationId,
                            'message_count' => $previousMessages->count(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save previous conversation: ' . $e->getMessage());
                    // Continue anyway - don't block creating new conversation
                }
            }

            // Generate new conversation ID with timestamp for uniqueness
            $conversationId = 'chat_' . $userId . '_' . time() . '_' . Str::random(6);
            
            // Create a new conversation record
            try {
                ChatbotConversation::create([
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'status' => 'active',
                    'last_activity_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::debug('Could not pre-create conversation record: ' . $e->getMessage());
                // Continue anyway - it will be created when first message is sent
            }

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => 'New conversation started',
                'previous_conversation_saved' => !empty($previousConversationId),
            ]);
        } catch (\Exception $e) {
            Log::error('Start new conversation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start new conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation
     */
    public function getConversationMessages(Request $request, $conversationId)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'data' => []
                ], 401);
            }

            $limit = $request->query('limit', 100);

            $messages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
                'conversation_id' => $conversationId
            ]);
        } catch (\Exception $e) {
            Log::error('Get conversation messages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversation messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific conversation
     */
    public function deleteConversation(Request $request, $conversationId)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Delete messages from ChatMessage table
            $deleted = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->delete();
            
            // Also delete from ChatbotConversation table
            ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted',
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            Log::error('Delete conversation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detect language from user message (Filipino/Tagalog vs English)
     * Returns 'filipino' or 'english' based on message content
     * 
     * @param string $message
     * @return string 'filipino' or 'english'
     */
    private function detectLanguage(string $message): string
    {
        $lower = mb_strtolower(trim($message));
        
        // Common Filipino/Tagalog words and patterns
        $filipinoIndicators = [
            // Common words
            'ako', 'ikaw', 'siya', 'kami', 'tayo', 'sila', 'namin', 'nila', 'natin',
            'ang', 'ng', 'sa', 'na', 'pa', 'po', 'opo', 'naman', 'din', 'rin', 'lang', 'lamang',
            'mga', 'yung', 'yun', 'yan', 'eto', 'ito', 'dito', 'doon', 'diyan',
            'ano', 'anong', 'sino', 'saan', 'kailan', 'kelan', 'bakit', 'paano', 'pano', 'magkano',
            'pwede', 'pwedeng', 'puwede', 'gusto', 'gustong', 'kailangan', 'kelangan',
            'hindi', 'wala', 'walang', 'may', 'mayroon', 'meron', 'merong',
            'oo', 'opo', 'sige', 'salamat', 'maraming', 'salamat po',
            'talaga', 'naman', 'nga', 'pala', 'kasi', 'dahil', 'kaya', 'para',
            'muna', 'bago', 'pagkatapos', 'habang', 'kung', 'kapag', 'pag',
            // Verbs/Actions
            'magbook', 'mag-book', 'magpa', 'paki', 'pakiusap', 'patulong',
            'tingnan', 'tignan', 'check', 'icheck', 'i-check',
            'bayad', 'bayaran', 'magbayad', 'babayaran', 'binayad', 'binayaran',
            'cancel', 'icancel', 'i-cancel', 'cancelin',
            'refund', 'irefund', 'i-refund', 'ibalik',
            'kuha', 'kunin', 'makuha', 'nakuha',
            'tulong', 'tulungan', 'patulong',
            // Question patterns
            'kamusta', 'kumusta', 'musta', 'ok ba', 'okay ba', 'oks ba',
            'ano po', 'anong', 'paano po', 'pano po', 'saan po', 'kelan po',
            // Common expressions  
            'salamat po', 'maraming salamat', 'thank you po',
            'sorry po', 'pasensya', 'pasensiya', 'paumanhin',
            'sana', 'baka', 'siguro', 'marahil',
            // Time expressions
            'ngayon', 'mamaya', 'bukas', 'kahapon', 'mamayang', 'kanina',
            'linggo', 'lunes', 'martes', 'miyerkules', 'huwebes', 'biyernes', 'sabado',
        ];
        
        // Count Filipino indicators
        $filipinoCount = 0;
        foreach ($filipinoIndicators as $word) {
            // Use word boundary matching for more accuracy
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $lower)) {
                $filipinoCount++;
            }
        }
        
        // Also check for Tagalog sentence patterns
        $filipinoPatterns = [
            '/\bako\s+(ay|na|pa)/iu',           // "ako ay/na/pa"
            '/\bgusto\s+ko/iu',                  // "gusto ko"
            '/\bpwede\s+(ba|ko|po)/iu',          // "pwede ba/ko/po"
            '/\bpaano\s+(po|ba|ko)/iu',          // "paano po/ba/ko"
            '/\bsaan\s+(po|ba)/iu',              // "saan po/ba"
            '/\bano\s+(po|ba|yung|ang)/iu',      // "ano po/ba/yung/ang"
            '/\bmay\s+(appointment|bayad|refund)/iu', // "may appointment/bayad/refund"
            '/\bwala\s+(pa|na|po)/iu',           // "wala pa/na/po"
            '/po\s*$/iu',                        // ends with "po"
            '/\b(na|pa|din|rin)\s+(po|ba)/iu',   // particle combinations
        ];
        
        foreach ($filipinoPatterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                $filipinoCount += 2; // Patterns are stronger indicators
            }
        }
        
        // Threshold: If 2+ Filipino indicators found, consider it Filipino
        // This handles mixed Taglish (Tagalog-English) as Filipino too
        if ($filipinoCount >= 2) {
            return 'filipino';
        }
        
        // Check if message is predominantly in Filipino characters/patterns
        // even without common words (for misspellings or slang)
        $wordCount = str_word_count($lower);
        if ($wordCount > 0 && $filipinoCount > 0 && ($filipinoCount / $wordCount) >= 0.2) {
            return 'filipino';
        }
        
        return 'english';
    }
}

