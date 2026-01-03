<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatbotConversation;
use App\Models\ChatbotRateLimit;
use App\Services\UnifiedChatbotService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRoleAwarenessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
    private ChatbotFeedbackService $feedbackService;
    private ChatbotRoleAwarenessService $roleService;
    
    public function __construct(
        UnifiedChatbotService $chatbotService,
        ChatbotFeedbackService $feedbackService,
        ChatbotRoleAwarenessService $roleService
    ) {
        $this->chatbotService = $chatbotService;
        $this->feedbackService = $feedbackService;
        $this->roleService = $roleService;
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
        
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
                'conversation_id' => 'nullable|string|max:100',
            ]);
            
            $userMessage = trim($request->input('message'));
            $conversationId = $request->input('conversation_id') ?? $this->generateConversationId();
            
            // Get user context
            $userId = auth('sanctum')->id() ?? auth()->id();
            $sessionId = $request->header('X-Session-ID') ?? $request->session()?->getId() ?? uniqid('session_');
            $ipAddress = $request->ip();
            
            // Rate limiting check
            try {
                $rateLimitStatus = ChatbotRateLimit::isRateLimited($userId, $sessionId, $ipAddress, $conversationId);
                if ($rateLimitStatus['limited'] ?? false) {
                    return response()->json([
                        'success' => false,
                        'rate_limited' => true,
                        'message' => $rateLimitStatus['message'] ?? 'Rate limit exceeded',
                        'retry_after' => $rateLimitStatus['retry_after'] ?? 60,
                    ], 429);
                }
            } catch (\Exception $e) {
                Log::debug('Rate limit check failed: ' . $e->getMessage());
            }
            
            // Handle guest users with limited response
            if (!$userId) {
                return $this->handleGuestMessage($userMessage, $conversationId, $sessionId);
            }
            
            // Save user message
            $this->saveMessage($userId, $conversationId, $userMessage, 'user');
            
            // Process through unified pipeline
            $result = $this->chatbotService->processMessage(
                $userMessage,
                $userId,
                $conversationId,
                [
                    'language' => $this->detectLanguage($userMessage),
                    'ip_address' => $ipAddress,
                    'user_agent' => $request->userAgent(),
                ]
            );
            
            // Save AI response
            $this->saveMessage($userId, $conversationId, $result['response'], 'assistant', $result['source']);
            
            // Update conversation tracking
            $this->updateConversation($userId, $conversationId, $sessionId);
            
            // Increment rate limit
            try {
                ChatbotRateLimit::incrementCount($userId, $sessionId, $ipAddress, $conversationId);
            } catch (\Exception $e) {
                Log::debug('Rate limit increment failed: ' . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $result['response'],
                'meta' => array_merge($result['meta'] ?? [], [
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]),
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
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
            'conversation_id' => 'nullable|string|max:100',
        ]);
        
        $userMessage = trim($request->input('message'));
        $conversationId = $request->input('conversation_id') ?? $this->generateConversationId();
        $userId = auth('sanctum')->id() ?? auth()->id();
        
        return new StreamedResponse(function () use ($userId, $userMessage, $conversationId) {
            // Disable output buffering for streaming
            if (ob_get_level()) ob_end_clean();
            
            // Set SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            try {
                // Send initial status
                $this->sendSSE('status', ['message' => 'Processing...', 'phase' => 'start']);
                
                // Save user message
                if ($userId) {
                    $this->saveMessage($userId, $conversationId, $userMessage, 'user');
                }
                
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
                    ['language' => $this->detectLanguage($userMessage)]
                );
                
                // If not streaming (fallback), send full response
                if (empty($fullResponse) && !empty($result['response'])) {
                    $fullResponse = $result['response'];
                    $this->sendSSE('token', ['content' => $fullResponse, 'final' => true]);
                }
                
                // Save assistant response
                if ($userId && $fullResponse) {
                    $this->saveMessage($userId, $conversationId, $fullResponse, 'assistant', 'llm');
                }
                
                // Send done signal
                $this->sendSSE('done', [
                    'conversation_id' => $conversationId,
                    'total_length' => strlen($fullResponse),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Streaming error: ' . $e->getMessage());
                $this->sendSSE('error', ['message' => 'An error occurred while generating response']);
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
            
            return response()->json([
                'success' => true,
                'status' => 'operational',
                'services' => $health,
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'degraded',
                'error' => $e->getMessage(),
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
                ]);
            }
            
            $conversationId = $request->query('conversation_id');
            $limit = min($request->query('limit', 50), 100);
            
            $query = ChatMessage::where('user_id', $userId);
            
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
    
    // ==================== PRIVATE HELPERS ====================
    
    private function handleGuestMessage(string $message, string $conversationId, string $sessionId): JsonResponse
    {
        // Simple response for guests encouraging registration
        $guestResponse = "Thank you for your interest in our legal services! To get personalized assistance with appointments, payments, and more, please register or log in. You can also contact us directly at 09765075274 or visit our office at 233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.";
        
        return response()->json([
            'success' => true,
            'conversation_id' => $conversationId,
            'user_message' => $message,
            'ai_response' => $guestResponse,
            'meta' => [
                'source' => 'guest_response',
                'role' => 'guest',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    private function saveMessage(int $userId, string $conversationId, string $message, string $role, string $source = 'user'): void
    {
        try {
            ChatMessage::create([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message' => $message,
                'role' => $role,
                'source' => $source,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save message: ' . $e->getMessage());
        }
    }
    
    private function updateConversation(int $userId, string $conversationId, ?string $sessionId): void
    {
        try {
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
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
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
}
