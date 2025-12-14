<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
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
use App\Services\LLMService;
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
    private ?ChatbotAnalyticsService $analyticsService = null;

    private const MAX_HISTORY = 10;

    public function __construct(
        ChatbotService $chatbotService,
        ChatbotRoleAwarenessService $roleService,
        ChatbotNLUService $nluService,
        ChatbotRealTimeDataService $dataService,
        ChatbotSmartResponseBuilder $responseBuilder,
        ChatbotLLMIntegration $llmIntegration
    ) {
        $this->chatbotService = $chatbotService;
        $this->roleService = $roleService;
        $this->nluService = $nluService;
        $this->dataService = $dataService;
        $this->responseBuilder = $responseBuilder;
        $this->llmIntegration = $llmIntegration;
        
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
                'confirmed' => 'nullable|boolean',
                'confirmation_key' => 'nullable|string',
            ]);
            
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to execute actions.',
                ], 401);
            }
            
            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'];
            $action = $request->input('action');
            $params = $request->input('params', []);
            $confirmed = $request->input('confirmed', false);
            $confirmationKey = $request->input('confirmation_key');
            
            // Handle confirmation for pending action
            if ($confirmationKey && $confirmed) {
                $pendingAction = ChatbotActionService::getPendingAction($userId, $confirmationKey);
                if ($pendingAction) {
                    $action = $pendingAction['action'];
                    $params = $pendingAction['params'];
                    $confirmed = true;
                }
            }
            
            // Check permission
            $permissionCheck = $this->roleService->canPerformIntent($userId, $action);
            if (!$permissionCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $this->roleService->getPermissionDeniedMessage($action, $userId),
                    'permission_denied' => true,
                ], 403);
            }
            
            // Execute the action
            $result = ChatbotActionService::executeAction($userId, $role, $action, $params, $confirmed);
            
            // Log the action
            Log::info('Chatbot action executed', [
                'user_id' => $userId,
                'role' => $role,
                'action' => $action,
                'success' => $result['success'],
            ]);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'requires_confirmation' => $result['requires_confirmation'] ?? false,
                'confirmation_key' => $result['confirmation_key'] ?? null,
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Execute action error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute action',
                'error' => config('app.debug') ? $e->getMessage() : null,
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
                'confirm' => 'required|boolean',
            ]);
            
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }
            
            $confirmationKey = $request->input('confirmation_key');
            $confirm = $request->input('confirm');
            
            if (!$confirm) {
                // User cancelled
                return response()->json([
                    'success' => true,
                    'message' => 'Action cancelled.',
                    'cancelled' => true,
                ]);
            }
            
            $pendingAction = ChatbotActionService::getPendingAction($userId, $confirmationKey);
            
            if (!$pendingAction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmation expired or invalid. Please try again.',
                ], 400);
            }
            
            $roleInfo = $this->roleService->detectUserRole($userId);
            $result = ChatbotActionService::executeAction(
                $userId,
                $roleInfo['primary_role'],
                $pendingAction['action'],
                $pendingAction['params'],
                true // confirmed
            );
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Confirm action error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm action',
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

            // Step 2: Analyze User Input (NLU)
            $intentData = [];
            $entities = [];
            $sentiment = 'neutral';
            $sentimentScore = 3;
            $isFollowUp = false;
            
            try {
                // Detect language for bilingual support (Filipino/English)
                $detectedLanguage = $this->detectLanguage($userMessage);
                
                // Pass role and user context to NLU for better intent detection
                $nluContext = [
                    'role' => $userRole,
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'language' => $detectedLanguage,
                ];
                
                $intentData = $this->nluService->detectIntent($userMessage, $nluContext);
                $entities = $this->nluService->extractEntities($userMessage);
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
            
            // Check role permission for the detected intent
            $permissionCheck = $this->roleService->canPerformIntent($userId, $intentData['intent'] ?? 'general_question');
            if (!$permissionCheck['allowed'] && ($intentData['confidence'] ?? 0) > 0.7) {
                // User is trying to do something they don't have permission for
                Log::info('Permission denied for intent', [
                    'user_id' => $userId,
                    'role' => $userRole,
                    'intent' => $intentData['intent'],
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
            $detectedLanguage = $this->detectLanguage($userMessage);
            
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
                
                // If SmartResponseBuilder returned a generic response, try LLM for better intelligence
                if (($responseData['meta']['source'] ?? null) === 'fallback' && $this->llmIntegration->isAvailable()) {
                    Log::debug('Attempting LLM enhancement for low-confidence intent', [
                        'intent' => $intentData['intent'],
                        'confidence' => $intentData['confidence'],
                        'language' => $detectedLanguage,
                    ]);
                    
                    $llmResult = $this->llmIntegration->shouldUseLLMAndRespond(
                        $userId,
                        $userMessage,
                        $conversationId,
                        $intentData,
                        $detectedLanguage
                    );
                    
                    if ($llmResult) {
                        $aiResponse = $llmResult['response'];
                        $meta = array_merge($meta, $llmResult['meta']);
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

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
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
        $lower = mb_strtolower(trim($userMessage));
        
        try {
            // Try to fetch real data for responses
            if (preg_match('/(book|appointment|schedule|reserve)/i', $lower)) {
                $appointmentCount = \App\Models\Appointment::count();
                return "You can book an appointment through your dashboard. We have received $appointmentCount appointments so far. Select your preferred date, time, and service. Your appointment will be pending approval.";
            }
            
            if (preg_match('/(service|what.*offer|available)/i', $lower)) {
                $services = \App\Models\Service::where('is_active', true)->get();
                if ($services->count() > 0) {
                    $serviceList = $services->pluck('name')->implode(', ');
                    return "We offer the following services: $serviceList. Log in to view detailed descriptions, pricing, and availability.";
                }
                return "We offer professional services. Log in to view our complete service catalog with detailed descriptions and pricing.";
            }
            
            if (preg_match('/(hour|time|when|open|business)/i', $lower)) {
                $settings = \App\Models\AppointmentSettings::first();
                if ($settings && $settings->business_hours) {
                    return "Our business hours are: " . $settings->business_hours . ". You can book appointments during these times.";
                }
                return "Our services are available during business hours. For specific hours, please log in to your account or contact us.";
            }
            
            if (preg_match('/(price|cost|fee|how much|charge)/i', $lower)) {
                $priceRange = \App\Models\Service::where('is_active', true)
                    ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                    ->first();
                if ($priceRange && $priceRange->min_price) {
                    return "Service pricing ranges from \$" . number_format($priceRange->min_price, 2) . " to \$" . number_format($priceRange->max_price, 2) . ". Log in to view prices for specific services.";
                }
                return "Service pricing varies based on the type. Please log in to view current rates.";
            }
            
            if (preg_match('/(cancel|reschedule|change|modify)/i', $lower)) {
                return "You can manage your appointments from your dashboard. To cancel or reschedule, visit the Appointments section and select the appointment you want to modify.";
            }
            
            if (preg_match('/(status|pending|approved|completed)/i', $lower)) {
                return "Check your appointment status in your dashboard. Pending appointments are awaiting approval, approved ones are confirmed, and completed ones are in your history.";
            }
        } catch (\Exception $e) {
            Log::debug('Error fetching real data for fallback response: ' . $e->getMessage());
            // Continue to hardcoded fallback
        }
        
        // Fallback for queries we couldn't match
        return "I can help you with appointments, services, pricing, and more. Please feel free to ask specific questions, and I'll provide detailed assistance.";
    }

    /**
     * Get response for guest users (not authenticated)
     * Uses dynamic data when available
     */
    private function getGuestResponse($userMessage)
    {
        $lowerMessage = strtolower(trim($userMessage));
        
        try {
            // Pattern matching with dynamic data fallback
            if (preg_match('/(book|appointment|schedule|reserve)/i', $lowerMessage)) {
                return "To book an appointment, please register or log in to your account. You'll be able to view available time slots and choose a convenient appointment time.";
            }
            
            if (preg_match('/(service|offer|what do you)/i', $lowerMessage)) {
                $serviceCount = \App\Models\Service::where('is_active', true)->count();
                if ($serviceCount > 0) {
                    return "We offer $serviceCount professional services. Please register or log in to view our complete service catalog with detailed descriptions and pricing.";
                }
                return "We offer various professional services. Please register or log in to view our complete service catalog.";
            }
            
            if (preg_match('/(hour|time|when|open)/i', $lowerMessage)) {
                return "Our business hours and availability can be viewed after you register or log in. This ensures you get the most up-to-date information.";
            }
            
            if (preg_match('/(price|cost|fee|how much)/i', $lowerMessage)) {
                return "For pricing information, please register or log in to access our full service catalog with current rates.";
            }
            
            if (preg_match('/(register|sign up|create account)/i', $lowerMessage)) {
                return "You can register by clicking the 'Register' button. Registration is quick and easy - just provide your email and create a password to get started!";
            }
            
            if (preg_match('/(login|log in|sign in)/i', $lowerMessage)) {
                return "Please click the 'Login' button at the top right to access your account. If you don't have an account yet, you can register for free!";
            }
        } catch (\Exception $e) {
            Log::debug('Error building guest response: ' . $e->getMessage());
        }
        
        // Default guest response
        return "Thanks for your question! To get personalized assistance and access all features, please register or log in. Our full chatbot capabilities are available to registered users.";
    }

    /**
     * Get suggested questions based on user role and system state
     */
    public function getSuggestedQuestions(Request $request)
    {
        try {
            $userId = auth()->id();

            // For guests, try service first, fallback to hardcoded
            if (!$userId) {
                try {
                    $questions = $this->chatbotService->getSuggestedQuestions(null);
                    if (!empty($questions)) {
                        return response()->json([
                            'success' => true,
                            'data' => $questions
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
                    ]
                ]);
            }

            $questions = $this->chatbotService->getSuggestedQuestions($userId);

            return response()->json([
                'success' => true,
                'data' => $questions
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
                $adminUser = User::role('admin')->first();
                if ($adminUser) {
                    return $adminUser->id;
                }
            } catch (\Exception $roleError) {
                Log::debug('Admin role not found, trying alternative methods: ' . $roleError->getMessage());
            }

            // Fallback: Try to get the most privileged user (usually first user or creator)
            // Look for users with is_active status (more likely to be admin)
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
                'username' => 'chatbot-admin',
                'email' => 'chatbot-admin@system.local',
                'password' => Hash::make(Str::random(32)),
                'role' => 'admin',
                'first_name' => 'Chatbot',
                'last_name' => 'Admin',
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

            // Get all conversations grouped by conversation_id with summary info
            $conversations = ChatMessage::where('user_id', $userId)
                ->select('conversation_id')
                ->distinct()
                ->get()
                ->map(function ($conv) use ($userId) {
                    $messages = ChatMessage::where('user_id', $userId)
                        ->where('conversation_id', $conv->conversation_id)
                        ->orderBy('created_at', 'asc')
                        ->get();

                    $firstUserMessage = $messages->where('role', 'user')->first();
                    $lastMessage = $messages->last();
                    
                    // Generate title from first user message (truncated)
                    $title = $firstUserMessage 
                        ? Str::limit($firstUserMessage->message, 50) 
                        : 'New Conversation';

                    return [
                        'conversation_id' => $conv->conversation_id,
                        'title' => $title,
                        'message_count' => $messages->count(),
                        'last_message' => $lastMessage ? Str::limit($lastMessage->message, 100) : null,
                        'last_message_role' => $lastMessage?->role,
                        'created_at' => $messages->first()?->created_at,
                        'updated_at' => $lastMessage?->created_at,
                    ];
                })
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

            // Generate new conversation ID with timestamp for uniqueness
            $conversationId = 'chat_' . $userId . '_' . time() . '_' . Str::random(6);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => 'New conversation started'
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

            $deleted = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
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

