<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ChatbotRateLimit;
use App\Models\ChatbotConversation;
use Symfony\Component\HttpFoundation\Response;

class ChatbotRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * Implements:
     * - 20 messages per conversation limit (must start new conversation)
     * - 5 messages per minute rate limit
     * - Spam detection (3 messages in 10 seconds = block)
     * - Abuse pattern detection
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth()->id();
        $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId() ?? null;
        $ipAddress = $request->ip();
        $conversationId = $request->input('conversation_id');

        // Check rate limit status
        $rateLimitStatus = ChatbotRateLimit::isRateLimited($userId, $sessionId, $ipAddress, $conversationId);

        if ($rateLimitStatus['limited']) {
            // Log the rate limit event
            \Illuminate\Support\Facades\Log::info('chatbot_rate_limited', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip' => $ipAddress,
                'conversation_id' => $conversationId,
                'reason' => $rateLimitStatus['reason'],
            ]);

            // Update conversation status if rate limited
            if ($conversationId && $rateLimitStatus['reason'] === 'conversation_limit') {
                $conversation = ChatbotConversation::where('conversation_id', $conversationId)->first();
                if ($conversation) {
                    $conversation->markRateLimited();
                }
            }

            return response()->json([
                'success' => false,
                'rate_limited' => true,
                'rate_limit_info' => $rateLimitStatus,
                'message' => $rateLimitStatus['message'] ?? 'Rate limit exceeded. Please try again later.',
                'must_start_new_conversation' => $rateLimitStatus['must_start_new'] ?? false,
                'remaining_messages' => $rateLimitStatus['remaining'] ?? 0,
            ], 429);
        }

        // Add rate limit info to request for controller use
        $request->merge([
            '_rate_limit_status' => $rateLimitStatus,
            '_session_id' => $sessionId,
        ]);

        // Process the request
        $response = $next($request);

        // Increment count after successful message processing
        if ($response->getStatusCode() === 200) {
            $responseData = json_decode($response->getContent(), true);
            
            // Only increment for actual message sends, not other chatbot endpoints
            if ($request->is('*/chatbot/send-message') && ($responseData['success'] ?? false)) {
                ChatbotRateLimit::incrementCount($userId, $sessionId, $ipAddress, $conversationId);
            }
        }

        // Add rate limit headers to response
        $currentStatus = ChatbotRateLimit::getStatus($userId, $sessionId, $conversationId);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => ChatbotRateLimit::MESSAGES_PER_CONVERSATION,
            'X-RateLimit-Remaining' => $currentStatus['conversation_remaining'],
            'X-RateLimit-Conversation-Limit' => ChatbotRateLimit::MESSAGES_PER_CONVERSATION,
            'X-RateLimit-Should-Start-New' => $currentStatus['should_start_new'] ? 'true' : 'false',
        ]);
    }
}
