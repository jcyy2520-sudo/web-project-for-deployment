<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatbotRateLimit;
use App\Events\ChatMessageSent;
use App\Events\ChatbotTyping;
use App\Events\ChatStreamToken;
use App\Services\AdvancedLLMService;
use App\Services\ChatbotMemoryService;
use App\Services\ChatbotNLUService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\SemanticEmbeddingsService;
use App\Services\SmartActionSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ChatbotStreamController - Handles streaming and real-time chat features
 * 
 * Features:
 * - Server-Sent Events (SSE) for streaming responses
 * - WebSocket event broadcasting
 * - Real-time token streaming from LLM
 * - Enhanced context management
 */
class ChatbotStreamController extends Controller
{
    private AdvancedLLMService $llmService;
    private ChatbotMemoryService $memoryService;
    private ChatbotNLUService $nluService;
    private ChatbotRoleAwarenessService $roleService;
    private SmartActionSuggestionService $actionService;
    private SemanticEmbeddingsService $embeddingsService;

    public function __construct(
        AdvancedLLMService $llmService,
        ChatbotMemoryService $memoryService,
        ChatbotNLUService $nluService,
        ChatbotRoleAwarenessService $roleService,
        SmartActionSuggestionService $actionService,
        SemanticEmbeddingsService $embeddingsService
    ) {
        $this->llmService = $llmService;
        $this->memoryService = $memoryService;
        $this->nluService = $nluService;
        $this->roleService = $roleService;
        $this->actionService = $actionService;
        $this->embeddingsService = $embeddingsService;
    }

    /**
     * Send message with streaming response via Server-Sent Events (SSE)
     */
    public function streamMessage(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string',
            'personality' => 'nullable|string|in:professional,friendly,expert,concise',
            'stream' => 'nullable|boolean',
        ]);

        $userId = auth('sanctum')->id() ?? auth()->id();
        $userMessage = $request->input('message');
        $conversationId = $request->input('conversation_id') ?? uniqid('chat_');
        $personality = $request->input('personality', 'professional');
        $enableStreaming = $request->input('stream', true);

        // For non-authenticated users, return regular response
        if (!$userId) {
            return $this->nonStreamingGuestResponse($userMessage, $conversationId);
        }

        return new StreamedResponse(function () use ($userId, $userMessage, $conversationId, $personality, $enableStreaming) {
            // Disable output buffering
            if (ob_get_level()) ob_end_clean();

            // Set up SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            try {
                // Step 1: Analyze user input
                $this->sendSSE('status', ['message' => 'Analyzing your message...']);

                $roleInfo = $this->roleService->detectUserRole($userId);
                $userRole = $roleInfo['primary_role'] ?? 'client';

                $intentData = $this->nluService->detectIntent($userMessage, [
                    'role' => $userRole,
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                ]);

                $entities = $this->nluService->extractEntities($userMessage);
                $sentimentData = $this->nluService->analyzeSentiment($userMessage);

                // Step 2: Get conversation context
                $this->sendSSE('status', ['message' => 'Loading context...']);

                $conversationContext = $this->memoryService->getConversationContext(
                    $userId,
                    $conversationId,
                    50 // Extended context window
                );

                // Step 3: Get RAG context if available
                $ragContext = '';
                try {
                    $ragContext = $this->embeddingsService->getRAGContext($userMessage);
                } catch (\Exception $e) {
                    Log::debug('RAG context retrieval failed: ' . $e->getMessage());
                }

                // Step 4: Build LLM context
                $systemContext = [
                    'role' => $userRole,
                    'user_id' => $userId,
                    'personality' => $personality,
                    'system_data' => $this->gatherSystemData($userRole),
                    'user_info' => $this->gatherUserData($userId),
                    'knowledge_context' => $ragContext,
                    'memory_context' => $this->memoryService->getConversationContext($userId, $conversationId),
                ];

                // Prepare conversation history
                $conversationHistory = $conversationContext['recent_messages'] ?? [];

                // Step 5: Save user message
                ChatMessage::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'message' => $userMessage,
                    'role' => 'user',
                    'source' => 'user',
                    'metadata' => [
                        'intent' => $intentData['intent'],
                        'intent_confidence' => $intentData['confidence'],
                        'sentiment' => $sentimentData['sentiment'],
                        'entities' => $entities,
                    ],
                ]);

                // Broadcast user message (for real-time sync)
                try {
                    event(new ChatMessageSent($userId, $conversationId, 'user', $userMessage));
                    event(new ChatbotTyping($userId, $conversationId, true));
                } catch (\Exception $e) {
                    // Broadcasting not configured, continue
                }

                // Step 6: Generate streaming response
                $this->sendSSE('status', ['message' => 'Generating response...']);

                if ($enableStreaming) {
                    $fullResponse = $this->streamLLMResponse(
                        $userId,
                        $conversationId,
                        $userMessage,
                        $conversationHistory,
                        $systemContext
                    );
                } else {
                    // Non-streaming fallback
                    $result = $this->llmService->generateResponse(
                        $userMessage,
                        $conversationHistory,
                        $systemContext,
                        false
                    );
                    $fullResponse = $result['response'] ?? 'I apologize, but I encountered an issue generating a response.';
                    $this->sendSSE('token', ['content' => $fullResponse]);
                }

                // Step 7: Save assistant message
                ChatMessage::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'message' => $fullResponse,
                    'role' => 'assistant',
                    'source' => 'llm',
                    'metadata' => [
                        'personality' => $personality,
                        'intent_responded' => $intentData['intent'],
                    ],
                ]);

                // Step 8: Update memory context
                $this->memoryService->updateContext($userId, $conversationId, 'assistant', $fullResponse, [
                    'intent' => $intentData['intent'],
                    'intent_confidence' => $intentData['confidence'],
                ]);

                // Step 9: Get action suggestions
                $suggestions = $this->actionService->getSuggestions(
                    $userId,
                    $userRole,
                    $intentData['intent'],
                    ['entities' => $entities]
                );

                $quickActions = $this->actionService->getQuickActions(
                    $userId,
                    $userRole,
                    $intentData['intent'],
                    $entities
                );

                // Step 10: Send completion event
                $this->sendSSE('done', [
                    'conversation_id' => $conversationId,
                    'intent' => $intentData['intent'],
                    'sentiment' => $sentimentData['sentiment'],
                    'suggestions' => $suggestions,
                    'quick_actions' => $quickActions,
                ]);

                // Broadcast typing stopped
                try {
                    event(new ChatbotTyping($userId, $conversationId, false));
                    event(new ChatMessageSent($userId, $conversationId, 'assistant', $fullResponse, [
                        'suggestions' => $suggestions,
                        'quick_actions' => $quickActions,
                    ]));
                } catch (\Exception $e) {
                    // Broadcasting not configured
                }

            } catch (\Exception $e) {
                Log::error('Stream message error: ' . $e->getMessage());
                $this->sendSSE('error', [
                    'message' => 'An error occurred while generating the response.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream tokens from LLM and send via SSE
     */
    private function streamLLMResponse(
        int $userId,
        string $conversationId,
        string $userMessage,
        array $conversationHistory,
        array $systemContext
    ): string {
        $result = $this->llmService->generateResponse(
            $userMessage,
            $conversationHistory,
            $systemContext,
            true // Enable streaming
        );

        if (!$result['success'] || !$result['stream']) {
            // Fallback to non-streaming
            $nonStreamResult = $this->llmService->generateResponse(
                $userMessage,
                $conversationHistory,
                $systemContext,
                false
            );
            $response = $nonStreamResult['response'] ?? 'I apologize, but I encountered an issue.';
            $this->sendSSE('token', ['content' => $response]);
            return $response;
        }

        // Stream tokens
        $fullResponse = '';
        $generator = $this->llmService->createStreamGenerator($result);

        foreach ($generator as $chunk) {
            if (isset($chunk['error'])) {
                $this->sendSSE('error', ['message' => $chunk['error']]);
                break;
            }

            if (isset($chunk['token'])) {
                $fullResponse .= $chunk['token'];
                $this->sendSSE('token', ['content' => $chunk['token']]);

                // Also broadcast via WebSocket for multi-device sync
                try {
                    event(new ChatStreamToken($userId, $conversationId, $chunk['token']));
                } catch (\Exception $e) {
                    // Broadcasting not configured
                }

                // Flush output
                if (ob_get_level()) ob_flush();
                flush();
            }

            if ($chunk['done'] ?? false) {
                break;
            }
        }

        return $fullResponse;
    }

    /**
     * Send Server-Sent Event
     */
    private function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";

        if (ob_get_level()) ob_flush();
        flush();
    }

    /**
     * Non-streaming response for guests
     */
    private function nonStreamingGuestResponse(string $userMessage, string $conversationId): StreamedResponse
    {
        return new StreamedResponse(function () use ($userMessage, $conversationId) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');

            $response = $this->getGuestResponse($userMessage);

            $this->sendSSE('token', ['content' => $response]);
            $this->sendSSE('done', [
                'conversation_id' => $conversationId,
                'is_guest' => true,
            ]);
        });
    }

    /**
     * Get LLM status and available providers
     */
    public function getStatus(Request $request)
    {
        try {
            $healthCheck = $this->llmService->healthCheck();

            $embeddingStatus = null;
            if ($this->embeddingService) {
                $embeddingStatus = [
                    'available' => $this->embeddingService->isAvailable(),
                    'provider' => $this->embeddingService->getProviderInfo(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'llm' => $healthCheck,
                    'embeddings' => $embeddingStatus,
                    'streaming_supported' => true,
                    'personalities' => $this->llmService->getPersonalities(),
                    'features' => [
                        'streaming' => true,
                        'websocket' => config('broadcasting.default') !== 'log',
                        'memory' => true,
                        'rag' => $embeddingStatus['available'] ?? false,
                        'smart_suggestions' => true,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status',
            ], 500);
        }
    }

    /**
     * Get smart action suggestions
     */
    public function getSuggestions(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $intent = $request->input('intent');

            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'guest';

            $suggestions = $this->actionService->getSuggestions($userId, $role, $intent);
            $questions = $this->actionService->getSuggestedQuestions($role, $intent);

            return response()->json([
                'success' => true,
                'data' => [
                    'action_suggestions' => $suggestions,
                    'suggested_questions' => $questions,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get suggestions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
            ], 500);
        }
    }

    /**
     * Search knowledge base
     */
    public function searchKnowledge(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|max:500',
                'category' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            if (!$this->embeddingService || !$this->embeddingService->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Knowledge search not available',
                ], 503);
            }

            $results = $this->embeddingService->searchKnowledge(
                $request->input('query'),
                $request->input('limit', 5),
                $request->input('category')
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Knowledge search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
            ], 500);
        }
    }

    /**
     * Set user preference
     */
    public function setPreference(Request $request)
    {
        try {
            $request->validate([
                'key' => 'required|string|in:language,personality,communication_style',
                'value' => 'required',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $this->memoryService->storeUserPreference(
                $userId,
                $request->input('key'),
                $request->input('value')
            );

            return response()->json([
                'success' => true,
                'message' => 'Preference saved',
            ]);
        } catch (\Exception $e) {
            Log::error('Set preference error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save preference',
            ], 500);
        }
    }

    /**
     * Gather system data for context
     */
    private function gatherSystemData(string $role): array
    {
        $data = [];

        try {
            if (in_array($role, ['admin', 'cashier'])) {
                $data['pending_appointments'] = \App\Models\Appointment::where('status', 'pending')->count();
                $data['pending_payments'] = \App\Models\Payment::where('status', 'pending')->count();
                $data['pending_refunds'] = \App\Models\Refund::where('status', 'pending')->count();
            }

            $services = \App\Models\Service::where('is_active', true)->pluck('name')->toArray();
            $data['services_available'] = $services;

            $settings = \App\Models\AppointmentSettings::first();
            if ($settings) {
                $data['business_hours'] = $settings->business_hours ?? 'Check booking page';
            }
        } catch (\Exception $e) {
            Log::debug('Failed to gather system data: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Gather user data for context
     */
    private function gatherUserData(int $userId): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return [];
            }

            $data = [
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            $role = $this->roleService->detectUserRole($userId)['primary_role'] ?? 'client';

            if ($role === 'client') {
                $data['appointment_count'] = \App\Models\Appointment::where('user_id', $userId)->count();
                $data['pending_appointments'] = \App\Models\Appointment::where('user_id', $userId)
                    ->where('status', 'pending')->count();
            }

            return $data;
        } catch (\Exception $e) {
            Log::debug('Failed to gather user data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get response for guest users
     */
    private function getGuestResponse(string $userMessage): string
    {
        $lowerMessage = strtolower(trim($userMessage));

        if (preg_match('/(book|appointment|schedule)/i', $lowerMessage)) {
            return "To book an appointment, please register or log in to your account. You'll be able to view available time slots and select your preferred service.";
        }

        if (preg_match('/(service|offer)/i', $lowerMessage)) {
            try {
                $serviceCount = \App\Models\Service::where('is_active', true)->count();
                return "We offer {$serviceCount} professional services. Please register or log in to view our complete catalog with descriptions and pricing.";
            } catch (\Exception $e) {
                return "We offer various professional services. Please register or log in to view our complete service catalog.";
            }
        }

        if (preg_match('/(register|sign up)/i', $lowerMessage)) {
            return "You can register by clicking the 'Register' button. It's quick and free - just provide your email and create a password!";
        }

        return "Thanks for your question! To get personalized assistance and access all features, please register or log in. Our full chatbot capabilities are available to registered users.";
    }
}
