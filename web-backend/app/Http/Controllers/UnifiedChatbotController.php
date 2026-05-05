<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatbotConversation;
use App\Models\ChatbotRateLimit;
use App\Models\User;
use App\Services\UnifiedChatbotService;
use App\Services\AgentReasoningService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\ChatbotActionService;
use App\Services\ChatbotRealTimeDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UnifiedChatbotController - LLM-First Chatbot API
 * 
 * This controller implements a clean, unified pipeline for chatbot interactions:
 * 
 * NO MORE:
 * - Pattern matching → LLM fallback
 * - 15+ service dependencies
 * - Multiple handlers based on intent
 * - Confidence thresholds
 * 
 * INSTEAD:
 * - LLM is primary (not fallback)
 * - 3 core services: UnifiedChatbot, Feedback, RoleAwareness
 * - One unified response pipeline
 * - Streaming support for better UX
 * 
 * The controller is intentionally simple - all intelligence is in UnifiedChatbotService
 */
class UnifiedChatbotController extends Controller
{
    private UnifiedChatbotService $chatbotService;
    private AgentReasoningService $agentReasoning;
    private ChatbotFeedbackService $feedbackService;
    private ChatbotRoleAwarenessService $roleService;
    private ChatbotRealTimeDataService $dataService;

    public function __construct(
        UnifiedChatbotService $chatbotService,
        AgentReasoningService $agentReasoning,
        ChatbotFeedbackService $feedbackService,
        ChatbotRoleAwarenessService $roleService,
        ChatbotRealTimeDataService $dataService
    ) {
        $this->chatbotService = $chatbotService;
        $this->agentReasoning = $agentReasoning;
        $this->feedbackService = $feedbackService;
        $this->roleService = $roleService;
        $this->dataService = $dataService;
    }
    
    /**
     * Send a message and get AI response (non-streaming)
     * 
     * This is the main endpoint for chatbot interactions.
     * For real-time token streaming, use /api/chatbot/v2/stream
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $lock = null;
        $loadReservationToken = null;
        $loadMode = 'normal';
        $loadSnapshot = [];
        
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
                'conversation_id' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            ]);
            
            $userMessage = trim($request->input('message'));

            // Reject empty/whitespace-only messages
            if (mb_strlen($userMessage) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message cannot be empty.',
                ], 422);
            }

            $conversationId = $request->input('conversation_id') ?? $this->generateConversationId();
            
            // Release the session lock EARLY so long-running LLM requests 
            // don't block the user from navigating the rest of the app.
            if ($request->hasSession()) {
                $request->session()->save();
            }
            
            // Ensure PHP doesn't timeout before the LLM (which might take up to 90s)
            set_time_limit(120);

            // Get user context
            $userId = auth('sanctum')->id() ?? auth()->id();
            // Use session ID from middleware (consistent identity) or generate one for fallback
            $sessionId = $request->input('_session_id')
                ?? ($request->hasSession() ? $request->session()->getId() : null)
                ?? uniqid('sess_', true);
            $ipAddress = $request->ip();
            $actorKey = $this->buildChatbotActorKey($userId, $sessionId, $ipAddress);

            // ── CONCURRENT REQUEST PROTECTION ──
            // Prevent the same user from having multiple in-flight chatbot requests.
            // This avoids duplicate processing, wasted LLM calls, and potential race conditions.
            $lockIdentity = "chatbot_lock_{$actorKey}";
            $lock = Cache::lock($lockIdentity, 90); // 90s max (matches LLM timeout)
            if (!$lock->get()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your previous message is still being processed. Please wait for it to complete.',
                    'retry_after' => 5,
                ], 429);
            }

            // ── REQUEST DEDUPLICATION ──
            // Prevent identical messages sent within 3 seconds (double-click, network retry).
            $dedupeKey = 'chatbot_dedup_' . md5($actorKey . $userMessage . $conversationId);
            if (Cache::has($dedupeKey)) {
                $lock->release();
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate message detected. Please wait for the response.',
                    'duplicate' => true,
                ], 429);
            }
            Cache::put($dedupeKey, true, 3); // 3-second dedup window
            
            // ── GUEST MESSAGE LIMIT ──
            // Applies ONLY to unauthenticated (guest) users. Authenticated roles are never affected.
            $guestLimitKey = null;
            $guestLimitMeta = null;
            if (!$userId) {
                $sessionIdHeader = $request->header('X-Session-ID', '');
                $guestLimitKey = $this->buildGuestLimitKey($ipAddress, $sessionIdHeader);
                $guestLimitBlock = $this->resolveGuestLimitBlock($guestLimitKey);

                if ($guestLimitBlock !== null) {
                    if (isset($lock)) {
                        $lock->release();
                    }

                    return response()->json($guestLimitBlock, 429);
                }
            }

            $requestRole = $this->resolveChatRole($userId);
            $loadReservation = $this->admitChatRequest($requestRole, $userId, $userMessage, false);
            $loadReservationToken = $loadReservation['token'] ?? null;
            $loadMode = $loadReservation['mode'] ?? 'normal';
            $loadSnapshot = $loadReservation['snapshot'] ?? [];

            if (!($loadReservation['admitted'] ?? false)) {
                if (isset($lock)) {
                    $lock->release();
                }

                return response()->json(
                    $this->buildBusyChatResponse($conversationId, $userMessage, $requestRole, $loadSnapshot),
                    200
                );
            }

            // Rate limiting is handled by ChatbotRateLimitMiddleware — no duplicate check here.
            
            // ALL users — including guests — go through the unified LLM pipeline.
            // Guest context is handled dynamically by the DynamicSystemPromptService.
            // Role is determined ONLY from server-side auth — NEVER from user input.
            
            // Save user message (for both authenticated and guest users)
            $this->saveMessage($userId, $conversationId, $userMessage, 'user', 'user', $sessionId);
            
            // Process through unified pipeline (handles all roles including guest)
            // Security checks (prompt injection, role escalation) are mandatory inside the service
            $result = $this->chatbotService->processMessage(
                $userMessage,
                $userId,        // null for guests — UnifiedChatbotService auto-detects guest role
                $conversationId,
                [
                    'language' => $this->detectLanguage($userMessage),
                    'ip_address' => $ipAddress,
                    'user_agent' => $request->userAgent(),
                    'session_id' => $sessionId,
                    'actor_key' => $actorKey,
                    'load_shedding_mode' => $loadMode,
                ]
            );
            
            // Save AI response (for both authenticated and guest users)
            $this->saveMessage($userId, $conversationId, $result['response'], 'assistant', $result['source'] ?? 'llm', $sessionId);
            
            // Update conversation tracking
            $this->updateConversation($userId, $conversationId, $sessionId);
            
            // Rate limit increment is handled by ChatbotRateLimitMiddleware after successful response.
            // Do NOT increment here — it was previously causing double-counting (8 msg/min limit hit at 4 msgs).

            // ── INCREMENT GUEST MESSAGE COUNT ──
            if (!$userId && $guestLimitKey !== null) {
                $guestLimitMeta = $this->incrementGuestLimit($guestLimitKey);
            }

            // Role in response metadata is ALWAYS from server-side detection, never from user input
            $serverRole = $result['meta']['role'] ?? $requestRole;

            // Enrich response with role-specific contextual actions and pending items
            $roleDisplay = ucfirst($serverRole);
            $quickActions = [];
            $pendingItems = [];
            try {
                $roleInfo = $this->roleService->detectUserRole($userId);
                $roleDisplay = $roleInfo['display_name'] ?? ucfirst($serverRole);
                // Use contextual suggestions based on conversation topic instead of static role actions
                $quickActions = $this->roleService->getContextualSuggestions(
                    $serverRole,
                    $userMessage,
                    $result['response'] ?? ''
                );
                $pendingItemsRaw = $roleInfo['pending_items'] ?? [];
                // Transform pending items for the frontend
                foreach ($pendingItemsRaw as $key => $count) {
                    if ($count > 0) {
                        $label = ucwords(str_replace('_', ' ', $key));
                        $pendingItems[] = ['label' => $label, 'count' => $count, 'key' => $key];
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Role enrichment failed: ' . $e->getMessage());
            }

            $jsonResponse = response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $result['response'],
                'meta' => array_merge($result['meta'] ?? [], [
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'source' => $result['source'] ?? 'llm',
                    'role' => $serverRole,
                    'role_display' => $roleDisplay,
                    'role_verified' => true, // Indicates role was determined server-side
                    'detected_language' => $result['meta']['detected_language'] ?? $this->detectLanguage($userMessage),
                    'quick_actions' => $quickActions,
                    'pending_items' => $pendingItems,
                    'guest_limit' => $guestLimitMeta, // null for authenticated users
                    'load_state' => $loadSnapshot['state'] ?? 'normal',
                ]),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->releaseChatRequest($loadReservationToken);

            // Release concurrent request lock
            if (isset($lock)) $lock->release();
            
            return $jsonResponse;
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->releaseChatRequest($loadReservationToken);
            if (isset($lock)) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $this->releaseChatRequest($loadReservationToken);
            if (isset($lock)) $lock->release();
            Log::error('Unified chatbot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your message',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    
    /**
     * Send message with streaming response (SSE)
     * 
     * Returns a stream of tokens for real-time display.
     * Better UX - users see response being generated instead of waiting.
     */
    public function streamMessage(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
        ]);
        
        $userMessage = trim($request->input('message'));
        if (mb_strlen($userMessage) === 0) {
            return new StreamedResponse(function () {
                $this->sendSSE('error', ['message' => 'Message cannot be empty.']);
            }, 422, ['Content-Type' => 'text/event-stream']);
        }

        $conversationId = $request->input('conversation_id') ?? $this->generateConversationId();
        $userId = auth('sanctum')->id() ?? auth()->id();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        
        // Release the session lock EARLY so long-running LLM requests 
        // don't block the user from navigating the rest of the app.
        if ($request->hasSession()) {
            $request->session()->save();
        }

        // SECURITY: Generate session ID server-side — never trust client-provided X-Session-ID
        try {
            $sessionId = $request->hasSession() ? $request->session()->getId() : uniqid('sess_', true);
        } catch (\Exception $e) {
            $sessionId = uniqid('sess_', true);
        }

        $actorKey = $this->buildChatbotActorKey($userId, $sessionId, $ipAddress);

        // ── CONCURRENT REQUEST PROTECTION (same as sendMessage) ──
        $lockIdentity = "chatbot_lock_{$actorKey}";
        $lock = Cache::lock($lockIdentity, 90);
        if (!$lock->get()) {
            return new StreamedResponse(function () {
                $this->sendSSE('error', ['message' => 'Your previous message is still being processed. Please wait.']);
            }, 429, ['Content-Type' => 'text/event-stream']);
        }

        // ── REQUEST DEDUPLICATION ──
        $dedupeKey = 'chatbot_dedup_' . md5($actorKey . $userMessage . $conversationId);
        if (Cache::has($dedupeKey)) {
            $lock->release();
            return new StreamedResponse(function () {
                $this->sendSSE('error', ['message' => 'Duplicate message detected. Please wait for the response.']);
            }, 429, ['Content-Type' => 'text/event-stream']);
        }
        Cache::put($dedupeKey, true, 3);
        
        // ── GUEST MESSAGE LIMIT (streaming path) ──
        $guestLimitKey = null;
        if (!$userId) {
            $sessionIdHeader = $request->header('X-Session-ID', '');
            $guestLimitKey = $this->buildGuestLimitKey($ipAddress, $sessionIdHeader);
            $guestLimitBlock = $this->resolveGuestLimitBlock($guestLimitKey);

            if ($guestLimitBlock !== null) {
                $lock->release();
                return new StreamedResponse(function () use ($guestLimitBlock) {
                    $this->sendSSE('error', $guestLimitBlock);
                }, 429, ['Content-Type' => 'text/event-stream']);
            }
        }

        // Ensure PHP doesn't timeout before the LLM
        set_time_limit(120);

        $requestRole = $this->resolveChatRole($userId);
        $loadReservation = $this->admitChatRequest($requestRole, $userId, $userMessage, true);
        $loadReservationToken = $loadReservation['token'] ?? null;
        $loadMode = $loadReservation['mode'] ?? 'normal';
        $loadSnapshot = $loadReservation['snapshot'] ?? [];

        if (!($loadReservation['admitted'] ?? false)) {
            if (isset($lock)) {
                $lock->release();
            }

            return $this->createBusyStreamedResponse($conversationId, $requestRole, $loadSnapshot);
        }

        return new StreamedResponse(function () use ($userId, $userMessage, $conversationId, $sessionId, $actorKey, $ipAddress, $userAgent, $lock, $guestLimitKey, $loadReservationToken, $loadMode, $loadSnapshot) {
            if (!app()->runningUnitTests()) {
                // Disable output buffering for streaming in real HTTP responses.
                if (ob_get_level()) {
                    ob_end_clean();
                }

                // Set SSE headers for direct web streaming.
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
            }
            
            try {
                // Send initial status
                $this->sendSSE('status', ['message' => 'Processing...', 'phase' => 'start']);
                
                // Save user message
                $this->saveMessage($userId, $conversationId, $userMessage, 'user', 'user', $sessionId);
                
                $fullResponse = '';
                
                // Stream response through unified service
                $result = $this->chatbotService->processMessageStreaming(
                    $userMessage,
                    $userId,
                    $conversationId,
                    function ($token, $meta = []) use (&$fullResponse) {
                        $fullResponse .= $token;
                        $this->sendSSE('token', ['content' => $token, 'meta' => $meta]);
                    },
                    function ($finalResult) {
                        $this->sendSSE('complete', $finalResult);
                    },
                    [
                        'language' => $this->detectLanguage($userMessage),
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'session_id' => $sessionId,
                        'actor_key' => $actorKey,
                        'load_shedding_mode' => $loadMode,
                    ]
                );
                
                // If not streaming (fallback), send full response
                if (empty($fullResponse) && !empty($result['response'])) {
                    $fullResponse = $result['response'];
                    $this->sendSSE('token', ['content' => $fullResponse, 'final' => true]);
                }
                
                // Save assistant response
                if ($fullResponse) {
                    $this->saveMessage($userId, $conversationId, $fullResponse, 'assistant', 'llm', $sessionId);
                }

                // ── INCREMENT GUEST MESSAGE COUNT (streaming) ──
                $guestLimitMeta = null;
                if (!$userId && $guestLimitKey !== null) {
                    $guestLimitMeta = $this->incrementGuestLimit($guestLimitKey);
                }
                
                // Build contextual suggestions and role info for the done event
                $serverRole = $userId ? 'client' : 'guest';
                $doneData = [
                    'conversation_id' => $conversationId,
                    'total_length' => strlen($fullResponse),
                ];
                try {
                    $roleInfo = $this->roleService->detectUserRole($userId);
                    $serverRole = $roleInfo['primary_role'] ?? $serverRole;
                    $doneData['meta'] = [
                        'role' => $serverRole,
                        'role_display' => $roleInfo['display_name'] ?? ucfirst($serverRole),
                        'quick_actions' => $this->roleService->getContextualSuggestions(
                            $serverRole, $userMessage, $fullResponse
                        ),
                    ];
                    // Add pending items
                    $pendingItems = [];
                    foreach (($roleInfo['pending_items'] ?? []) as $key => $count) {
                        if ($count > 0) {
                            $pendingItems[] = ['label' => ucwords(str_replace('_', ' ', $key)), 'count' => $count, 'key' => $key];
                        }
                    }
                    if (!empty($pendingItems)) {
                        $doneData['meta']['pending_items'] = $pendingItems;
                    }
                    // Include confirmation info if present
                    if (!empty($result['meta']['requires_confirmation'])) {
                        $doneData['meta']['requires_confirmation'] = true;
                        $doneData['meta']['confirmation_key'] = $result['meta']['confirmation_key'] ?? null;
                    }
                    // Include guest limit info
                    if ($guestLimitMeta !== null) {
                        $doneData['meta']['guest_limit'] = $guestLimitMeta;
                    }
                    $doneData['meta']['load_state'] = $loadSnapshot['state'] ?? 'normal';
                } catch (\Exception $e) {
                    Log::debug('Streaming role enrichment failed: ' . $e->getMessage());
                }

                // Send done signal
                $this->sendSSE('done', $doneData);
                
            } catch (\Exception $e) {
                Log::error('Streaming error: ' . $e->getMessage());
                $this->sendSSE('error', ['message' => 'An error occurred while generating response']);
            } finally {
                $this->releaseChatRequest($loadReservationToken);
                // Release concurrent request lock when streaming completes
                if (isset($lock)) $lock->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
    
    /**
     * Submit feedback for a chatbot interaction
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'interaction_id' => 'required|string',
                'rating' => 'nullable|integer|min:1|max:5',
                'is_helpful' => 'nullable|boolean',
                'is_correct' => 'nullable|boolean',
                'correction' => 'nullable|string|max:2000',
                'expected_response' => 'nullable|string|max:2000',
                'category' => 'nullable|string|max:50',
                'comments' => 'nullable|string|max:1000',
            ]);
            
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            $success = $this->feedbackService->recordFeedback(
                $request->input('interaction_id'),
                array_merge($request->only([
                    'rating', 'is_helpful', 'is_correct', 'correction',
                    'expected_response', 'category', 'comments'
                ]), ['user_id' => $userId])
            );
            
            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to record feedback. Invalid interaction ID.',
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Feedback submission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
            ], 500);
        }
    }
    
    /**
     * Get chatbot health status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $health = $this->chatbotService->getHealthStatus();
            $loadStatus = $this->getPublicLoadStatus();
            $llmAvailable = !empty($health['llm']['available_provider'] ?? null);
            $status = !$llmAvailable
                ? 'degraded'
                : ($loadStatus['status'] === 'busy' ? 'busy' : $loadStatus['status']);
            
            return response()->json([
                'success' => true,
                'status' => $status,
                'data' => [
                    'chatbot' => $status,
                    'load' => $loadStatus['load'],
                    'retry_after_seconds' => $loadStatus['retry_after_seconds'],
                    'streaming_enabled' => (bool) config('chatbot_unified.features.streaming', false),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'degraded',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
            ], 500);
        }
    }
    
    /**
     * Get user's chat history
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'guest_restricted' => true,
                ]);
            }
            try {
                $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            } catch (\Exception $e) {
                $sessionId = null;
            }
            
            // If there's no authenticated user and no session ID, return empty
            if (!$userId && !$sessionId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }
            
            $conversationId = $request->query('conversation_id');
            $limit = min($request->query('limit', 50), 100);
            
            $query = ChatMessage::query();
            
            // Filter by user_id if authenticated, otherwise by session_id for guests
            if ($userId) {
                $query->where('user_id', $userId);
            } elseif ($sessionId) {
                $query->where('session_id', $sessionId);
            }
            
            if ($conversationId) {
                $query->where('conversation_id', $conversationId);
            }
            
            $messages = $query->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $messages,
                'conversation_id' => $conversationId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch history',
            ], 500);
        }
    }
    
    /**
     * Get feedback analytics (admin only)
     */
    public function getFeedbackAnalytics(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $roleInfo = $this->roleService->detectUserRole($userId);
            
            if (($roleInfo['primary_role'] ?? 'client') !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }
            
            $analytics = $this->feedbackService->getAnalytics([
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
            ], 500);
        }
    }
    
    // ==================== CONVERSATION MANAGEMENT ====================

    /**
     * Get list of all conversations for the current user
     */
    public function getConversations(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'guest_restricted' => true,
                ]);
            }
            $sessionId = $request->header('X-Session-ID');

            if (!$userId && !$sessionId) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Single query: load ALL messages for this user, grouped in PHP
            $msgQuery = ChatMessage::query();
            if ($userId) {
                $msgQuery->where('user_id', $userId);
            } elseif ($sessionId) {
                $msgQuery->where('session_id', $sessionId);
            }
            $allMessages = $msgQuery->orderBy('created_at', 'asc')->get()->groupBy('conversation_id');

            if ($allMessages->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Single query: load all conversation records at once
            $convRecords = ChatbotConversation::whereIn('conversation_id', $allMessages->keys())
                ->get()
                ->keyBy('conversation_id');

            $conversationLimit = ChatbotRateLimit::MESSAGES_PER_CONVERSATION;

            $conversations = $allMessages->map(function ($messages, $conversationId) use ($convRecords, $conversationLimit) {
                if ($messages->isEmpty()) {
                    return null;
                }

                $firstUserMessage = $messages->where('role', 'user')->first();
                $lastMessage = $messages->last();
                $title = $firstUserMessage
                    ? Str::limit($firstUserMessage->message, 50)
                    : 'New Conversation';

                $convRecord = $convRecords->get($conversationId);
                $userMessageCount = $messages->where('role', 'user')->count();

                return [
                    'conversation_id' => $conversationId,
                    'title' => $convRecord?->title ?? $title,
                    'message_count' => $messages->count(),
                    'user_message_count' => $userMessageCount,
                    'conversation_limit' => $conversationLimit,
                    'is_at_limit' => $userMessageCount >= $conversationLimit,
                    'last_message' => $lastMessage ? Str::limit($lastMessage->message, 100) : null,
                    'last_message_role' => $lastMessage?->role,
                    'created_at' => $messages->first()?->created_at,
                    'updated_at' => $lastMessage?->created_at ?? $convRecord?->last_activity_at,
                    'status' => $convRecord?->status ?? 'active',
                ];
            })
            ->filter()
            ->sortByDesc('updated_at')
            ->values();

            return response()->json(['success' => true, 'data' => $conversations]);
        } catch (\Exception $e) {
            Log::error('Get conversations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversations',
            ], 500);
        }
    }

    /**
     * Start a new conversation
     */
    public function startNewConversation(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please log in to start a new conversation.',
                ], 403);
            }
            $sessionId = $request->header('X-Session-ID');

            if (!$userId && !$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session or authentication required',
                ], 401);
            }

            $previousConversationId = $request->input('previous_conversation_id');

            // Close/save the previous conversation if provided
            if ($previousConversationId) {
                try {
                    $prevQuery = ChatMessage::where('conversation_id', $previousConversationId);
                    if ($userId) {
                        $prevQuery->where('user_id', $userId);
                    } elseif ($sessionId) {
                        $prevQuery->where('session_id', $sessionId);
                    }
                    $previousMessages = $prevQuery->orderBy('created_at', 'asc')->get();

                    if ($previousMessages->count() > 0) {
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
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save previous conversation: ' . $e->getMessage());
                }
            }

            $identifier = $userId ?? ('guest_' . substr(md5($sessionId), 0, 8));
            $conversationId = 'chat_' . $identifier . '_' . time() . '_' . Str::random(6);

            try {
                ChatbotConversation::create([
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'status' => 'active',
                    'last_activity_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::debug('Could not pre-create conversation record: ' . $e->getMessage());
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
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation
     */
    public function getConversationMessages(Request $request, $conversationId): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'conversation_id' => $conversationId,
                    'guest_restricted' => true,
                    'rate_limit' => [
                        'remaining' => 0,
                        'limit' => 0,
                        'used' => 0,
                        'is_limited' => false,
                        'must_start_new' => false,
                    ],
                ]);
            }
            $sessionId = $request->header('X-Session-ID');

            if (!$userId && !$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'data' => [],
                ], 401);
            }

            $limit = $request->query('limit', 100);
            $query = ChatMessage::where('conversation_id', $conversationId);
            if ($userId) {
                $query->where('user_id', $userId);
            } elseif ($sessionId) {
                $query->where('session_id', $sessionId);
            }

            $messages = $query->orderBy('created_at', 'asc')->limit($limit)->get();

            $userMessageCount = $messages->where('role', 'user')->count();
            $conversationLimit = ChatbotRateLimit::MESSAGES_PER_CONVERSATION;
            $remaining = max(0, $conversationLimit - $userMessageCount);

            return response()->json([
                'success' => true,
                'data' => $messages,
                'conversation_id' => $conversationId,
                'rate_limit' => [
                    'remaining' => $remaining,
                    'limit' => $conversationLimit,
                    'used' => $userMessageCount,
                    'is_limited' => $remaining <= 0,
                    'must_start_new' => $remaining <= 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get conversation messages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversation messages',
            ], 500);
        }
    }

    /**
     * Delete a specific conversation
     */
    public function deleteConversation(Request $request, $conversationId): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please log in to delete conversations.',
                ], 403);
            }
            $sessionId = $request->header('X-Session-ID');

            if (!$userId && !$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session or authentication required',
                ], 401);
            }

            $deleteQuery = ChatMessage::where('conversation_id', $conversationId);
            if ($userId) {
                $deleteQuery->where('user_id', $userId);
            } elseif ($sessionId) {
                $deleteQuery->where('session_id', $sessionId);
            }
            $deleted = $deleteQuery->delete();

            $convDeleteQuery = ChatbotConversation::where('conversation_id', $conversationId);
            if ($userId) {
                $convDeleteQuery->where('user_id', $userId);
            }
            $convDeleteQuery->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted',
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::error('Delete conversation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation',
            ], 500);
        }
    }

    /**
     * Clear chat history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please log in to clear chat history.',
                ], 403);
            }
            $sessionId = $request->header('X-Session-ID');
            $conversationId = $request->input('conversation_id');

            if ($userId) {
                $query = ChatMessage::where('user_id', $userId);
            } elseif ($sessionId) {
                $query = ChatMessage::where('session_id', $sessionId);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot clear history: no user or session identifier',
                ], 400);
            }

            if ($conversationId) {
                $query->where('conversation_id', $conversationId);
            }

            $query->delete();

            return response()->json([
                'success' => true,
                'message' => 'Chat history cleared',
            ]);
        } catch (\Exception $e) {
            Log::error('Clear history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear history',
            ], 500);
        }
    }

    /**
     * Get conversation summary
     */
    public function getConversationSummary(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
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
                    'updated_at' => $msgs->last()->created_at,
                ];
            });

            return response()->json(['success' => true, 'data' => $summary]);
        } catch (\Exception $e) {
            Log::error('Get summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary',
            ], 500);
        }
    }

    // ==================== CAPABILITIES & SUGGESTIONS ====================

    /**
     * Get chatbot capabilities and status based on user role
     */
    public function getCapabilities(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $roleInfo = $this->roleService->detectUserRole($userId);
            $loadStatus = $this->getPublicLoadStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $roleInfo['primary_role'],
                    'display_name' => $roleInfo['display_name'],
                    'capabilities' => array_keys(array_filter($roleInfo['capabilities'] ?? [])),
                    'quick_actions' => $roleInfo['quick_actions'] ?? [],
                    'pending_items' => $roleInfo['pending_items'] ?? [],
                    'greeting' => $this->roleService->getRoleGreeting($userId),
                    'suggested_commands' => $this->roleService->getSuggestedCommands($userId),
                    'llm_available' => true,
                    'llm_status' => [
                        'status' => $loadStatus['status'],
                        'retry_after_seconds' => $loadStatus['retry_after_seconds'],
                    ],
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
     * Get suggested questions based on user role
     */
    public function getSuggestedQuestions(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'What services do you offer and how much do they cost?',
                        'Where is your office located?',
                        'How do I book an appointment?',
                        'What are your business hours?',
                        'What documents do I need to bring?',
                        'How do I register for an account?',
                    ],
                    'dynamic_updates' => [],
                    'role' => 'guest',
                ]);
            }

            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'client';

            $questions = match ($role) {
                'admin', 'administrator' => [
                    'How many pending appointments are there?',
                    'Show me today\'s schedule',
                    'What is the revenue summary?',
                    'Are there any urgent items needing attention?',
                    'Show system analytics',
                    'List recent user registrations',
                ],
                'cashier' => [
                    'Show pending payments',
                    'What is today\'s collection summary?',
                    'What is the revenue summary for this month?',
                    'List approved appointments awaiting payment',
                    'Show approved refunds ready for processing',
                    'Show my shift report',
                    'How many transactions today?',
                ],
                default => [
                    'What is my appointment status?',
                    'How do I book a new appointment?',
                    'What services are available?',
                    'Show my payment history',
                    'How do I request a refund?',
                    'What documents do I need to bring?',
                ],
            };

            $dynamicUpdates = [];
            try {
                if (in_array($role, ['admin', 'administrator'])) {
                    $pending = \App\Models\Appointment::where('status', 'pending')->count();
                    if ($pending > 0) {
                        $dynamicUpdates[] = [
                            'text' => "{$pending} appointment(s) awaiting review",
                            'priority' => $pending >= 5 ? 'high' : 'medium',
                            'route' => '/admin/appointments',
                        ];
                    }
                    $pendingRefunds = \App\Models\Refund::where('status', 'pending')->count();
                    if ($pendingRefunds > 0) {
                        $dynamicUpdates[] = [
                            'text' => "{$pendingRefunds} refund(s) pending approval",
                            'priority' => 'medium',
                            'route' => '/admin/refunds',
                        ];
                    }
                } elseif ($role === 'cashier') {
                    $awaitingPayment = \App\Models\Appointment::where('status', 'approved')
                        ->whereDoesntHave('payments', fn($q) => $q->where('payment_status', 'paid'))
                        ->count();
                    if ($awaitingPayment > 0) {
                        $dynamicUpdates[] = [
                            'text' => "{$awaitingPayment} appointment(s) awaiting payment",
                            'priority' => 'medium',
                        ];
                    }
                } elseif ($role === 'client') {
                    $upcoming = \App\Models\Appointment::where('user_id', $userId)
                        ->where('status', 'approved')
                        ->where('appointment_date', '>=', now())
                        ->count();
                    if ($upcoming > 0) {
                        $dynamicUpdates[] = [
                            'text' => "You have {$upcoming} upcoming appointment(s)",
                            'priority' => 'low',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Dynamic suggestions failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $questions,
                'dynamic_updates' => $dynamicUpdates,
                'role' => $role,
            ]);
        } catch (\Exception $e) {
            Log::error('Get suggested questions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggested questions',
            ], 500);
        }
    }

    /**
     * Get smart action suggestions (from legacy ChatbotStreamController)
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'guest';

            $suggestions = match ($role) {
                'admin', 'administrator' => [
                    ['label' => 'Review Pending', 'message' => 'Show pending appointments'],
                    ['label' => 'Analytics', 'message' => 'Show system analytics'],
                    ['label' => 'User Management', 'message' => 'Show user statistics'],
                ],
                'cashier' => [
                    ['label' => 'Pending Payments', 'message' => 'Show pending payments'],
                    ['label' => 'Revenue Summary', 'message' => 'What is the revenue summary for this month?'],
                    ['label' => 'Shift Report', 'message' => 'Show my shift report'],
                ],
                default => [
                    ['label' => 'My Appointments', 'message' => 'Show my appointments'],
                    ['label' => 'Book Appointment', 'message' => 'How do I book an appointment?'],
                    ['label' => 'Services', 'message' => 'What services are available?'],
                ],
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'action_suggestions' => $suggestions,
                    'suggested_questions' => [],
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
     * Get proactive tips based on user context
     */
    public function getProactiveTips(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $tips = [];

            if ($userId) {
                $roleInfo = $this->roleService->detectUserRole($userId);
                $role = $roleInfo['primary_role'] ?? 'client';

                if ($role === 'client') {
                    $tips = array_merge($tips, $this->getClientTips($userId));
                } elseif ($role === 'admin') {
                    $tips = array_merge($tips, $this->getAdminTips());
                } elseif ($role === 'cashier') {
                    $tips = array_merge($tips, $this->getCashierTips());
                }
            } else {
                $tips[] = [
                    'type' => 'info',
                    'icon' => 'wave',
                    'message' => 'Register for an account to book appointments and access all features.',
                    'action' => ['label' => 'Register', 'route' => '/register'],
                ];
            }

            return response()->json(['success' => true, 'data' => $tips]);
        } catch (\Exception $e) {
            Log::debug('Proactive tips error: ' . $e->getMessage());
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ==================== RATE LIMIT & REAL-TIME DATA ====================

    /**
     * Get rate limit status for the current user/session
     */
    public function getRateLimitStatus(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $sessionId = $request->header('X-Session-ID') ?? $request->session()?->getId();
            $conversationId = $request->input('conversation_id');

            $status = ChatbotRateLimit::getStatus($userId, $sessionId, $conversationId);
            $limitCheck = ChatbotRateLimit::isRateLimited($userId, $sessionId, $request->ip(), $conversationId);

            return response()->json([
                'success' => true,
                'data' => array_merge($status, [
                    'is_limited' => $limitCheck['limited'],
                    'limit_reason' => $limitCheck['reason'] ?? null,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get rate limit status',
            ], 500);
        }
    }

    /**
     * Get real-time data based on data type (for AJAX requests)
     */
    public function getRealTimeData(Request $request): JsonResponse
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

            $data = match ($dataType) {
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
                    ? $this->dataService->getCashierShiftData($userId, $params['date'] ?? $params['start_date'] ?? null)
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

    // ==================== ACTION EXECUTION ====================

    /**
     * Execute a chatbot action directly
     */
    public function executeAction(Request $request): JsonResponse
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

            $result = ChatbotActionService::executeAction(
                $userId,
                $role,
                $request->input('action'),
                $request->input('params') ?? []
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Chatbot executeAction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute action',
            ], 500);
        }
    }

    /**
     * Confirm a pending action
     */
    public function confirmAction(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'confirmation_key' => 'required|string',
                'decision' => 'nullable|in:confirm,deny',
                'conversation_id' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to confirm actions.',
                ], 401);
            }

            $confirmationKey = (string) $request->input('confirmation_key');
            $decision = (string) $request->input('decision', 'confirm');
            $conversationId = $request->input('conversation_id') ?? $this->generateConversationId();
            $sessionId = $request->header('X-Session-ID') ?? $request->session()?->getId();
            $ipAddress = $request->ip();
            $actorKey = $this->buildChatbotActorKey($userId, $sessionId, $ipAddress);

            if (str_starts_with($confirmationKey, 'agent_confirm_')) {
                return $this->confirmAgentAction(
                    $confirmationKey,
                    $decision,
                    $userId,
                    $conversationId,
                    $sessionId,
                    $actorKey
                );
            }

            $pending = ChatbotActionService::getPendingAction($userId, $confirmationKey);

            if (!$pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmation expired or invalid. Please try the action again.',
                ], 400);
            }

            $roleInfo = $this->roleService->detectUserRole($userId);
            $role = $roleInfo['primary_role'] ?? 'client';
            $roleDisplay = $roleInfo['display_name'] ?? ucfirst($role);

            $userMessage = $decision === 'deny' ? 'no' : 'yes';
            $this->saveMessage($userId, $conversationId, $userMessage, 'user', 'user', $sessionId);

            if ($decision === 'deny') {
                $responseText = "Understood, I've cancelled the action. How else can I help you?";
                $this->saveMessage($userId, $conversationId, $responseText, 'assistant', 'action_confirmation_cancel', $sessionId);
                $this->updateConversation($userId, $conversationId, $sessionId);

                return $this->buildConfirmationResponse(
                    $conversationId,
                    $userMessage,
                    $responseText,
                    [
                        'source' => 'action_confirmation_cancel',
                        'role' => $role,
                        'role_display' => $roleDisplay,
                        'role_verified' => true,
                    ]
                );
            }

            $result = ChatbotActionService::executeAction(
                $userId,
                $role,
                $pending['action'],
                $pending['params'],
                true
            );

            $responseText = trim((string) ($result['message'] ?? 'Action processed.'));
            $this->saveMessage($userId, $conversationId, $responseText, 'assistant', 'action_confirmation', $sessionId);
            $this->updateConversation($userId, $conversationId, $sessionId);

            return $this->buildConfirmationResponse(
                $conversationId,
                $userMessage,
                $responseText,
                [
                    'source' => 'action_confirmation',
                    'role' => $role,
                    'role_display' => $roleDisplay,
                    'role_verified' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Chatbot confirmAction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm action',
            ], 500);
        }
    }

    private function confirmAgentAction(
        string $confirmationKey,
        string $decision,
        int $userId,
        string $conversationId,
        ?string $sessionId,
        string $actorKey
    ): JsonResponse {
        $expectedKey = $this->buildAgentConfirmationKey($actorKey);
        if (!hash_equals($expectedKey, $confirmationKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation expired or invalid. Please try the action again.',
            ], 400);
        }

        $pending = AgentReasoningService::getPendingConfirmation($actorKey);
        if (!$pending) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation expired or invalid. Please try the action again.',
            ], 400);
        }

        $roleInfo = $this->roleService->detectUserRole($userId);
        $role = $roleInfo['primary_role'] ?? 'client';
        $roleDisplay = $roleInfo['display_name'] ?? ucfirst($role);
        $userMessage = $decision === 'deny' ? 'no' : 'yes';

        $this->saveMessage($userId, $conversationId, $userMessage, 'user', 'user', $sessionId);

        $agentResult = $this->agentReasoning->reason(
            $userMessage,
            '',
            [],
            $userId,
            $role,
            $pending,
            $actorKey
        );

        $responseText = trim((string) ($agentResult['response'] ?? 'Action processed.'));
        $source = $decision === 'deny' ? 'agent_confirmation_cancel' : 'agent_confirmation';

        $this->saveMessage($userId, $conversationId, $responseText, 'assistant', $source, $sessionId);
        $this->updateConversation($userId, $conversationId, $sessionId);

        $meta = [
            'source' => $source,
            'role' => $role,
            'role_display' => $roleDisplay,
            'role_verified' => true,
        ];

        if (!empty($agentResult['action_buttons'])) {
            $meta['action_buttons'] = $agentResult['action_buttons'];
        }

        if (!empty($agentResult['requires_confirmation'])) {
            $meta['requires_confirmation'] = true;
            $meta['confirmation_key'] = $agentResult['confirmation_key'] ?? null;
            if (!empty($agentResult['pending_tool'])) {
                $meta['pending_tool'] = $agentResult['pending_tool'];
            }
        }

        return $this->buildConfirmationResponse(
            $conversationId,
            $userMessage,
            $responseText,
            $meta
        );
    }

    // ==================== MESSAGE CENTER ====================

    /**
     * Save chatbot message to Messages section
     */
    public function saveMessageToMessageCenter(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string',
                'role' => 'required|in:user,assistant',
                'conversation_id' => 'nullable|string',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            $message = $request->input('message');
            $role = $request->input('role');
            $conversationId = $request->input('conversation_id');

            // For guests, silently skip persistence but return success
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Message not persisted (guest user)',
                    'is_guest' => true,
                ]);
            }

            if ($role === 'user') {
                $senderId = $userId;
                $receiverId = $this->getAdminUserId();
            } else {
                $senderId = $this->getAdminUserId();
                $receiverId = $userId;
            }

            if (empty($senderId) || empty($receiverId)) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No admin found; message not persisted.',
                ]);
            }

            $messageModel = \App\Models\Message::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => $message,
                'conversation_id' => $conversationId,
                'type' => 'chatbot',
                'subject' => 'AI Chat Assistant',
                'read' => false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $messageModel,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Save message to Message Center error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save message',
            ], 500);
        }
    }

    // ==================== KNOWLEDGE SEARCH & PREFERENCES ====================

    /**
     * Search knowledge base (simplified from legacy ChatbotStreamController)
     */
    public function searchKnowledge(Request $request): JsonResponse
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please log in to use chatbot search.',
                ], 403);
            }

            $request->validate([
                'query' => 'required|string|max:500',
                'category' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            // Try to use the vector embedding service if available
            try {
                $embeddingService = app(\App\Services\VectorEmbeddingService::class);
                $results = $embeddingService->searchKnowledge(
                    $request->input('query'),
                    $request->input('limit', 5),
                    $request->input('category')
                );

                return response()->json([
                    'success' => true,
                    'data' => $results,
                ]);
            } catch (\Exception $e) {
                Log::debug('Knowledge search service not available: ' . $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => 'Knowledge search not available',
            ], 503);
        } catch (\Exception $e) {
            Log::error('Knowledge search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
            ], 500);
        }
    }

    /**
     * Set user preference (simplified from legacy ChatbotStreamController)
     */
    public function setPreference(Request $request): JsonResponse
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

            // Store preference in cache for now (lightweight approach)
            $cacheKey = "chatbot_pref_{$userId}_{$request->input('key')}";
            cache()->put($cacheKey, $request->input('value'), now()->addDays(30));

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

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Get chatbot analytics (admin only)
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'day');

            // Try to use the analytics service if available
            try {
                $analyticsService = app(\App\Services\ChatbotAnalyticsService::class);
                $summary = $analyticsService->getDashboardSummary($period);
                $performance = $analyticsService->getPerformanceMetrics($period);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'summary' => $summary,
                        'performance' => $performance,
                        'period' => $period,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);
            } catch (\Exception $e) {
                Log::debug('Analytics service not available: ' . $e->getMessage());
            }

            // Fallback: basic analytics from chat_messages table
            $periodStart = match ($period) {
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                default => now()->subDay(),
            };

            $totalMessages = ChatMessage::where('created_at', '>=', $periodStart)->count();
            $totalConversations = ChatMessage::where('created_at', '>=', $periodStart)
                ->distinct('conversation_id')->count('conversation_id');
            $uniqueUsers = ChatMessage::where('created_at', '>=', $periodStart)
                ->whereNotNull('user_id')
                ->distinct('user_id')->count('user_id');

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_messages' => $totalMessages,
                        'total_conversations' => $totalConversations,
                        'unique_users' => $uniqueUsers,
                    ],
                    'performance' => [],
                    'period' => $period,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
            ], 500);
        }
    }

    /**
     * Get priority conversations needing attention (admin only)
     */
    public function getPriorityConversations(Request $request): JsonResponse
    {
        try {
            try {
                $analyticsService = app(\App\Services\ChatbotAnalyticsService::class);
                $priority = $analyticsService->getPriorityMessages(50);
                $needsAttention = $analyticsService->getConversationsNeedingAttention();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'priority_messages' => $priority,
                        'conversations_needing_attention' => $needsAttention,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::debug('Analytics service not available for priority: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'priority_messages' => [],
                    'conversations_needing_attention' => [],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch priority conversations',
            ], 500);
        }
    }

    /**
     * Get training data (admin only)
     */
    public function getTrainingData(Request $request): JsonResponse
    {
        try {
            $limit = min($request->input('limit', 100), 500);

            try {
                $analyticsService = app(\App\Services\ChatbotAnalyticsService::class);
                $trainingData = $analyticsService->getTrainingData($limit);

                return response()->json([
                    'success' => true,
                    'data' => $trainingData,
                ]);
            } catch (\Exception $e) {
                Log::debug('Analytics service not available for training data: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch training data',
            ], 500);
        }
    }

    /**
     * Get self-improvement report (admin only)
     */
    public function getSelfImprovementReport(Request $request): JsonResponse
    {
        try {
            $period = $request->query('period', 'week');
            $service = app(\App\Services\ChatbotSelfImprovementService::class);
            $report = $service->generateSelfEvaluationReport($period);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Self-improvement report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report.',
            ], 500);
        }
    }

    // ==================== PRIVATE HELPERS ====================
    
    private function handleGuestMessage(string $message, string $conversationId, string $sessionId): JsonResponse
    {
        // This is now only used as a fallback if the LLM pipeline fails for guests.
        // Normally, ALL guest messages go through the unified LLM pipeline via processMessage().
        $phone = config('chatbot_unified.business.phone', '09765075274');
        $address = config('chatbot_unified.business.address', '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro');
        
        $guestResponse = "Thank you for your interest in our legal services! For personalized assistance with appointments, payments, and more, please register or log in. You can also contact us directly at {$phone} or visit our office at {$address}.";
        
        return response()->json([
            'success' => true,
            'conversation_id' => $conversationId,
            'user_message' => $message,
            'ai_response' => $guestResponse,
            'meta' => [
                'source' => 'guest_fallback',
                'role' => 'guest',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function buildGuestLimitKey(string $ipAddress, string $sessionIdHeader): string
    {
        return 'guest_chat_limit_' . md5($ipAddress . ':' . $sessionIdHeader);
    }

    private function getDefaultGuestLimitData(): array
    {
        return ['count' => 0, 'cooldown_until' => null];
    }

    private function normalizeGuestLimitState(string $guestLimitKey): array
    {
        $guestLimitData = Cache::get($guestLimitKey, $this->getDefaultGuestLimitData());
        $cooldownUntilTs = $guestLimitData['cooldown_until'] ?? null;

        if (!empty($cooldownUntilTs) && now()->timestamp >= $cooldownUntilTs) {
            $guestLimitData = $this->getDefaultGuestLimitData();
            Cache::put($guestLimitKey, $guestLimitData, now()->addHours(6));
        }

        return $guestLimitData;
    }

    private function buildGuestLimitExceededPayload(int $cooldownUntilTs): array
    {
        $cooldownUntil = \Carbon\Carbon::createFromTimestamp($cooldownUntilTs);

        return [
            'success' => false,
            'guest_limit_exceeded' => true,
            'message' => 'You\'ve reached the message limit for guest users. You can send messages again on '
                . $cooldownUntil->format('F j, Y') . ' at ' . $cooldownUntil->format('g:i A')
                . '. Register now to continue chatting without limits.',
            'guest_limit' => [
                'count' => 5,
                'remaining' => 0,
                'is_limited' => true,
                'cooldown_until' => $cooldownUntil->toIso8601String(),
            ],
        ];
    }

    private function resolveGuestLimitBlock(string $guestLimitKey): ?array
    {
        $guestLimitData = $this->normalizeGuestLimitState($guestLimitKey);

        if (!empty($guestLimitData['cooldown_until']) && now()->timestamp < $guestLimitData['cooldown_until']) {
            return $this->buildGuestLimitExceededPayload($guestLimitData['cooldown_until']);
        }

        return null;
    }

    private function incrementGuestLimit(string $guestLimitKey): array
    {
        $guestLimitData = $this->normalizeGuestLimitState($guestLimitKey);
        $guestLimitData['count'] = ($guestLimitData['count'] ?? 0) + 1;

        $guestCooldownStr = null;
        if ($guestLimitData['count'] >= 5) {
            $cooldownUntilTs = now()->addHours(5)->timestamp;
            $guestLimitData['cooldown_until'] = $cooldownUntilTs;
            $guestCooldownStr = \Carbon\Carbon::createFromTimestamp($cooldownUntilTs)->toIso8601String();
        } else {
            $guestLimitData['cooldown_until'] = null;
        }

        Cache::put($guestLimitKey, $guestLimitData, now()->addHours(6));

        return [
            'count' => $guestLimitData['count'],
            'remaining' => max(0, 5 - $guestLimitData['count']),
            'is_limited' => $guestLimitData['count'] >= 5,
            'cooldown_until' => $guestCooldownStr,
        ];
    }

    private function buildConfirmationResponse(string $conversationId, string $userMessage, string $responseText, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'conversation_id' => $conversationId,
            'user_message' => $userMessage,
            'ai_response' => $responseText,
            'meta' => $meta,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function buildAgentConfirmationKey(string $actorKey): string
    {
        $normalizedActorKey = preg_replace('/[^A-Za-z0-9:_-]/', '_', $actorKey);

        return 'agent_confirm_' . $normalizedActorKey . '_pending';
    }
    
    private function saveMessage(?int $userId, string $conversationId, string $message, string $role, string $source = 'user', ?string $sessionId = null): void
    {
        try {
            $data = [
                'conversation_id' => $conversationId,
                'message' => $message,
                'role' => $role,
                'source' => $source,
            ];
            
            if ($userId) {
                $data['user_id'] = $userId;
            }
            
            if ($sessionId) {
                $data['session_id'] = $sessionId;
            }
            
            ChatMessage::create($data);
        } catch (\Exception $e) {
            Log::warning('Failed to save message: ' . $e->getMessage());
        }
    }

    private function buildChatbotActorKey(?int $userId, ?string $sessionId, ?string $ipAddress): string
    {
        if ($userId) {
            return "user_{$userId}";
        }

        if (!empty($sessionId)) {
            return 'guest_session_' . sha1($sessionId);
        }

        if (!empty($ipAddress)) {
            return 'guest_ip_' . sha1($ipAddress);
        }

        return 'guest_anonymous';
    }

    private function resolveChatRole(?int $userId): string
    {
        if (!$userId) {
            return 'guest';
        }

        try {
            $user = auth('sanctum')->user() ?? auth()->user();

            if (!$user || (int) $user->id !== (int) $userId) {
                $user = User::find($userId);
            }

            if (!$user) {
                return 'client';
            }

            $dbRole = strtolower(trim((string) ($user->role ?? '')));
            if (in_array($dbRole, ['admin', 'administrator'], true)) {
                return 'admin';
            }

            if ($dbRole === 'staff') {
                return 'staff';
            }

            if ($dbRole === 'cashier') {
                return 'cashier';
            }

            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('admin') || $user->hasRole('administrator')) {
                    return 'admin';
                }

                if ($user->hasRole('staff')) {
                    return 'staff';
                }

                if ($user->hasRole('cashier')) {
                    return 'cashier';
                }
            }

            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return 'admin';
            }

            if (method_exists($user, 'isStaff') && $user->isStaff()) {
                return 'staff';
            }

            if (method_exists($user, 'isCashier') && $user->isCashier()) {
                return 'cashier';
            }

            return 'client';
        } catch (\Exception $e) {
            Log::debug('Failed to resolve request role for load management: ' . $e->getMessage());
            return 'client';
        }
    }

    private function admitChatRequest(string $role, ?int $userId, string $userMessage, bool $streaming = false): array
    {
        return app(\App\Services\ChatbotLoadManagerService::class)->admit([
            'role' => $role,
            'user_id' => $userId,
            'message' => $userMessage,
            'streaming' => $streaming,
        ]);
    }

    private function releaseChatRequest(?string $reservationToken): void
    {
        if (!$reservationToken) {
            return;
        }

        app(\App\Services\ChatbotLoadManagerService::class)->release($reservationToken);
    }

    private function getPublicLoadStatus(): array
    {
        try {
            return app(\App\Services\ChatbotLoadManagerService::class)->publicStatus();
        } catch (\Exception $e) {
            Log::debug('Failed to fetch chatbot public load status: ' . $e->getMessage());

            return [
                'status' => 'operational',
                'load' => 'normal',
                'retry_after_seconds' => 5,
            ];
        }
    }

    private function buildBusyChatResponse(string $conversationId, string $userMessage, string $role, array $snapshot): array
    {
        $retryAfter = max(1, (int) ($snapshot['retry_after_seconds'] ?? 5));
        $message = match ($role) {
            'guest' => 'I\'m helping a high volume of visitors right now, so there may be a slight delay. I can still help with quick public questions about services, office hours, pricing, and location. Please try again in a few seconds for a fuller reply.',
            'client' => 'I\'m handling a high volume of requests right now, so there may be a slight delay. Please try again in a few seconds. For the fastest reply, ask one short direct question about your appointments, payments, or services.',
            default => 'I\'m handling a high volume of requests right now, so there may be a slight delay. Please try again in a few seconds. For the fastest reply, ask one short direct operational question.',
        };

        return [
            'success' => true,
            'conversation_id' => $conversationId,
            'user_message' => $userMessage,
            'ai_response' => $message,
            'meta' => [
                'source' => 'load_shed_busy',
                'role' => $role,
                'role_display' => ucfirst($role),
                'role_verified' => true,
                'degraded' => true,
                'retry_after_seconds' => $retryAfter,
                'load_state' => $snapshot['state'] ?? 'overloaded',
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function createBusyStreamedResponse(string $conversationId, string $role, array $snapshot): StreamedResponse
    {
        $payload = $this->buildBusyChatResponse($conversationId, '', $role, $snapshot);

        return new StreamedResponse(function () use ($payload, $conversationId) {
            $this->sendSSE('status', ['message' => 'High request volume detected.', 'phase' => 'degraded']);
            $this->sendSSE('token', [
                'content' => $payload['ai_response'],
                'meta' => $payload['meta'],
            ]);
            $this->sendSSE('done', [
                'conversation_id' => $conversationId,
                'meta' => $payload['meta'],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
    
    private function updateConversation(?int $userId, string $conversationId, ?string $sessionId): void
    {
        try {
            $data = [
                'session_id' => $sessionId,
                'status' => 'active',
                'last_activity_at' => now(),
            ];
            
            if ($userId) {
                $data['user_id'] = $userId;
            }
            
            ChatbotConversation::updateOrCreate(
                ['conversation_id' => $conversationId],
                $data
            );
        } catch (\Exception $e) {
            Log::debug('Failed to update conversation: ' . $e->getMessage());
        }
    }
    
    private function generateConversationId(): string
    {
        return 'chat_' . time() . '_' . Str::random(8);
    }
    
    private function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";

        if (!app()->runningUnitTests()) {
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
    }
    
    private function detectLanguage(string $message): string
    {
        $lower = mb_strtolower($message);
        
        // Filipino indicators
        $filipinoWords = ['ako', 'ko', 'mo', 'siya', 'po', 'opo', 'ang', 'ng', 'sa', 'na', 'pa',
            'gusto', 'pwede', 'paano', 'ano', 'saan', 'kailan', 'bakit', 'mga', 'yung', 'dito',
            'salamat', 'hindi', 'wala', 'may', 'meron', 'sige', 'oo', 'kamusta', 'kumusta'];
        
        $count = 0;
        foreach ($filipinoWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $lower)) {
                $count++;
            }
        }
        
        return $count >= 2 ? 'filipino' : 'english';
    }

    /**
     * Get the admin user ID (first user with admin role)
     */
    private function getAdminUserId(): ?int
    {
        try {
            $configuredId = (int) env('CHATBOT_ADMIN_USER_ID', 0);
            if ($configuredId > 0) {
                $user = User::find($configuredId);
                if ($user) {
                    return $user->id;
                }
            }

            $adminUser = User::where('role', 'admin')->first();
            if ($adminUser) {
                return $adminUser->id;
            }

            $user = User::where('is_active', true)->orderBy('id', 'asc')->first();
            if ($user) {
                return $user->id;
            }

            $user = User::first();
            return $user?->id;
        } catch (\Exception $e) {
            Log::warning('Could not determine admin user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get context-aware tips for clients
     */
    private function getClientTips(int $userId): array
    {
        $tips = [];

        try {
            $upcoming = \App\Models\Appointment::where('user_id', $userId)
                ->where('status', 'approved')
                ->where('appointment_date', '>=', now())
                ->where('appointment_date', '<=', now()->addDays(3))
                ->first();

            if ($upcoming) {
                $tips[] = [
                    'type' => 'reminder',
                    'icon' => 'calendar',
                    'message' => "You have an upcoming appointment on {$upcoming->appointment_date->format('M j')}. Don't forget to bring your required documents!",
                    'action' => ['label' => 'View Details', 'message' => 'Show my upcoming appointment'],
                ];
            }

            $pending = \App\Models\Appointment::where('user_id', $userId)
                ->where('status', 'pending')
                ->count();

            if ($pending > 0) {
                $tips[] = [
                    'type' => 'info',
                    'icon' => 'clock',
                    'message' => "You have {$pending} pending appointment(s) awaiting approval.",
                    'action' => ['label' => 'Check Status', 'message' => 'What is my appointment status?'],
                ];
            }

            $unpaid = \App\Models\Appointment::where('user_id', $userId)
                ->where('payment_status', 'unpaid')
                ->where('status', 'approved')
                ->count();

            if ($unpaid > 0) {
                $tips[] = [
                    'type' => 'warning',
                    'icon' => 'credit-card',
                    'message' => "You have {$unpaid} approved appointment(s) with pending payment.",
                    'action' => ['label' => 'View Payments', 'message' => 'Show my payment status'],
                ];
            }
        } catch (\Exception $e) {
            Log::debug('Client tips error: ' . $e->getMessage());
        }

        return $tips;
    }

    /**
     * Get context-aware tips for admins
     */
    private function getAdminTips(): array
    {
        $tips = [];

        try {
            $pendingCount = \App\Models\Appointment::where('status', 'pending')->count();
            if ($pendingCount > 0) {
                $tips[] = [
                    'type' => 'action',
                    'icon' => 'clipboard',
                    'message' => "{$pendingCount} appointment(s) need your review.",
                    'action' => ['label' => 'Review', 'message' => 'Show pending appointments'],
                    'priority' => $pendingCount > 5 ? 'high' : 'medium',
                ];
            }
        } catch (\Exception $e) {
            Log::debug('Admin tips error: ' . $e->getMessage());
        }

        return $tips;
    }

    /**
     * Get context-aware tips for cashiers
     */
    private function getCashierTips(): array
    {
        $tips = [];

        try {
            $pendingPayments = \App\Models\Appointment::where('payment_status', 'unpaid')
                ->where('status', 'approved')
                ->count();

            if ($pendingPayments > 0) {
                $tips[] = [
                    'type' => 'action',
                    'icon' => 'dollar-sign',
                    'message' => "{$pendingPayments} payment(s) are waiting to be processed.",
                    'action' => ['label' => 'Process', 'message' => 'Show pending payments'],
                    'priority' => 'medium',
                ];
            }
        } catch (\Exception $e) {
            Log::debug('Cashier tips error: ' . $e->getMessage());
        }

        return $tips;
    }
}
