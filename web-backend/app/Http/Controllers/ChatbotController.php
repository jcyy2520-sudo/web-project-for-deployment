<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private const HF_API_URL = 'https://api-inference.huggingface.co/models/google/flan-t5-small';
    // Token is loaded from environment variable in sendMessage method
    private const MAX_HISTORY = 10; // Keep last 10 messages for context
    
    private $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Get chat history for the current user
     */
    public function getHistory(Request $request)
    {
        try {
            $userId = auth()->id();
            
            // Return empty history for guests
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $limit = $request->query('limit', 20);

            $messages = ChatMessage::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $messages
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
     * Send a message and get AI response
     */
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
                'conversation_id' => 'nullable|string'
            ]);

            $userId = auth()->id();
            $isGuest = !$userId;
            $userMessage = $request->input('message');
            $conversationId = $request->input('conversation_id') ?? uniqid('chat_');

            // For guest users, provide simple responses without database access
            if ($isGuest) {
                $aiResponse = $this->getGuestResponse($userMessage);
                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $aiResponse,
                    'meta' => ['source' => 'guest', 'requires_auth' => true],
                    'timestamp' => now()->toIso8601String()
                ]);
            }

            // Save user message
            ChatMessage::create([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message' => $userMessage,
                'role' => 'user',
                'source' => 'user'
            ]);

            // Get conversation context (last messages for context)
            $recentMessages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit(self::MAX_HISTORY)
                ->get()
                ->reverse()
                ->values();

            // Use real-time interpreter for fuzzy understanding and role-based response
            try {
                $interpreted = $this->chatbotService->interpretAndRespond($userId, $userMessage);
                $aiResponse = $interpreted['reply'] ?? null;
                $meta = $interpreted;
                unset($meta['reply']);
                
                if (!$aiResponse) {
                    // Fallback to previous AI pipeline only if interpreter returned nothing
                    $context = $this->buildContext($userId, $recentMessages);
                    $aiResponse = $this->getAIResponse($userMessage, $context);
                    $meta = ['source' => 'huggingface'];
                }
            } catch (\Exception $interpreterError) {
                Log::error('Interpreter error: ' . $interpreterError->getMessage(), [
                    'user_id' => $userId,
                    'message' => $userMessage,
                    'trace' => $interpreterError->getTraceAsString()
                ]);
                
                // Fallback to simple response
                $aiResponse = "I'm here to help! You can ask me about booking appointments, available services, or checking your appointment status.";
                $meta = ['source' => 'fallback', 'error' => 'Interpreter unavailable'];
            }

            // Ensure response is not too long for database
            if (strlen($aiResponse) > 5000) {
                $aiResponse = substr($aiResponse, 0, 4997) . '...';
            }

            // Save AI message
            ChatMessage::create([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message' => $aiResponse,
                'role' => 'assistant',
                'source' => 'interpreter'
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'meta' => $meta,
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
                'user_id' => $userId ?? null,
                'message' => $userMessage ?? null,
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
     * Build context string from previous messages
     */
    private function buildContext($userId, $messages)
    {
        // Get enhanced system prompt with real-time system data
        $systemPrompt = $this->chatbotService->buildEnhancedSystemPrompt($userId);
        
        $context = $systemPrompt . "\n\nPrevious conversation:\n";

        foreach ($messages as $msg) {
            $role = $msg->role === 'user' ? 'Customer' : 'Assistant';
            $context .= "$role: {$msg->message}\n";
        }

        return $context;
    }

    /**
     * Get response from Hugging Face API
     */
    private function getAIResponse($userMessage, $context)
    {
        try {
            $prompt = $context . "\nCustomer: " . $userMessage . "\nAssistant:";

            // Get token from environment variable
            $token = env('HUGGINGFACE_API_KEY');
            if (!$token) {
                Log::warning('HUGGINGFACE_API_KEY not configured');
                return $this->getFallbackResponse($userMessage);
            }

            // Call Hugging Face API
            $response = Http::withToken($token)
                ->timeout(30)
                ->post(self::HF_API_URL, [
                    'inputs' => $prompt,
                    'parameters' => [
                        'max_length' => 200,
                        'do_sample' => true,
                        'temperature' => 0.7
                    ]
                ]);

            if ($response->failed()) {
                Log::warning('Hugging Face API error: ' . $response->body());
                
                // Return a fallback response
                return $this->getFallbackResponse($userMessage);
            }

            $data = $response->json();

            // Extract the generated text
            if (isset($data[0]['generated_text'])) {
                $generatedText = $data[0]['generated_text'];
                
                // Extract just the assistant's response (after the prompt)
                $assistantResponse = str_replace($prompt, '', $generatedText);
                $assistantResponse = trim($assistantResponse);
                
                // Clean up the response
                $assistantResponse = preg_replace('/^Assistant:\s*/i', '', $assistantResponse);
                
                return $assistantResponse ?: $this->getFallbackResponse($userMessage);
            }

            return $this->getFallbackResponse($userMessage);
        } catch (\Exception $e) {
            Log::error('Hugging Face API exception: ' . $e->getMessage());
            return $this->getFallbackResponse($userMessage);
        }
    }

    /**
     * Get a fallback response when API fails
     */
    private function getFallbackResponse($userMessage)
    {
        // No hardcoded responses: rely on interpreter, else minimal generic notice
        return "I can provide a real-time answer once I am connected to the system's database and context.";
    }

    /**
     * Get response for guest users (not authenticated)
     */
    private function getGuestResponse($userMessage)
    {
        $lowerMessage = strtolower(trim($userMessage));
        
        // Simple pattern matching for common questions
        if (preg_match('/(book|appointment|schedule|reserve)/i', $lowerMessage)) {
            return "To book an appointment, please register or log in to your account. You'll be able to view available time slots and choose a convenient appointment time.";
        }
        
        if (preg_match('/(service|offer|what do you)/i', $lowerMessage)) {
            return "We offer various professional services. Please register or log in to view our complete service catalog with detailed descriptions and pricing.";
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
            
            // Return generic questions for guests
            if (!$userId) {
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

            // Create message in Message model
            $messageModel = \App\Models\Message::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => $message,
                'conversation_id' => $conversationId,
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
            Log::error('Save message to Message Center error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the admin user ID (first user with admin role)
     */
    private function getAdminUserId()
    {
        try {
            // Try to find a user with admin role
            $adminUser = \App\Models\User::role('admin')->first();
            
            if ($adminUser) {
                return $adminUser->id;
            }

            // Fallback: get user with ID 1 (usually the first/admin user)
            $user = \App\Models\User::find(1);
            if ($user) {
                return $user->id;
            }

            // If no admin found, return null (will be handled by client)
            return null;
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
}

