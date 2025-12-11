<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            // For guest users, use the service with null userId for dynamic responses
            if ($isGuest) {
                $aiResponse = null;
                $meta = [];
                
                try {
                    $interpreted = $this->chatbotService->interpretAndRespond(null, $userMessage);
                    $aiResponse = $interpreted['reply'] ?? null;
                    $meta = is_array($interpreted) ? $interpreted : [];
                    if (isset($meta['reply'])) {
                        unset($meta['reply']);
                    }
                    $meta['user_role'] = 'guest';
                    $meta['context_refreshed_at'] = now()->toIso8601String();
                } catch (\Exception $e) {
                    Log::warning('Guest interpreter error: ' . $e->getMessage());
                }

                // Fallback if no response
                if (!$aiResponse || !is_string($aiResponse)) {
                    $aiResponse = $this->getGuestResponse($userMessage);
                    $meta = ['source' => 'guest_fallback'];
                }

                return response()->json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'user_message' => $userMessage,
                    'ai_response' => $aiResponse,
                    'meta' => $meta,
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
                $meta = is_array($interpreted) ? $interpreted : [];
                unset($meta['reply']);
                
                // If interpreter provided a high-confidence response, use it
                if ($aiResponse && (!isset($meta['confidence']) || $meta['confidence'] >= 0.7)) {
                    $meta['source'] = 'pattern_match';
                } else if ($aiResponse) {
                    // Low confidence from interpreter - try AI API
                    $context = $this->buildContext($userId, $recentMessages);
                    $aiResult = $this->getAIResponse($userMessage, $context);
                    $aiResponse = $aiResult['response'];
                    $meta = is_array($aiResult) ? $aiResult : [];
                    $meta['fallback_reason'] = 'interpreter_low_confidence';
                } else {
                    // No response from interpreter - try AI API
                    $context = $this->buildContext($userId, $recentMessages);
                    $aiResult = $this->getAIResponse($userMessage, $context);
                    $aiResponse = $aiResult['response'];
                    $meta = is_array($aiResult) ? $aiResult : [];
                }
            } catch (\Exception $interpreterError) {
                Log::error('Interpreter error: ' . $interpreterError->getMessage(), [
                    'user_id' => $userId,
                    'message' => $userMessage
                ]);
                
                // Fallback to AI API
                try {
                    $context = $this->buildContext($userId, $recentMessages);
                    $aiResult = $this->getAIResponse($userMessage, $context);
                    $aiResponse = $aiResult['response'];
                    $meta = is_array($aiResult) ? $aiResult : [];
                    $meta['fallback_reason'] = 'interpreter_exception';
                } catch (\Exception $e) {
                    Log::error('Both interpreter and AI API failed', ['error' => $e->getMessage()]);
                    $aiResponse = "I'm here to help! You can ask me about booking appointments, available services, or checking your appointment status.";
                    $meta = ['source' => 'fallback', 'error' => 'All systems unavailable'];
                }
            }

            // Ensure response is valid and not empty
            if (!$aiResponse || !is_string($aiResponse)) {
                Log::warning('Empty or invalid AI response, using fallback', [
                    'user_id' => $userId,
                    'message' => $userMessage,
                    'response' => $aiResponse
                ]);
                $aiResponse = "I'm here to help! You can ask me about booking appointments, available services, or checking your appointment status.";
                $meta = ['source' => 'fallback', 'reason' => 'empty_response'];
            }

            // Ensure response is not too long for database
            if (strlen($aiResponse) > 5000) {
                $aiResponse = substr($aiResponse, 0, 4997) . '...';
            }

            // Ensure role/context metadata is always present for the frontend
            $meta = is_array($meta) ? $meta : [];
            $meta['user_role'] = $meta['user_role'] ?? ($userId ? (auth()->user()?->getRoleNames()->first() ?? 'user') : 'guest');
            $meta['context_refreshed_at'] = $meta['context_refreshed_at'] ?? now()->toIso8601String();

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
     * Get response from Hugging Face API with improved error handling
     */
    private function getAIResponse($userMessage, $context)
    {
        try {
            $token = env('HUGGINGFACE_API_KEY');
            if (!$token) {
                Log::warning('HUGGINGFACE_API_KEY not configured - using fallback');
                return [
                    'response' => $this->getFallbackResponse($userMessage),
                    'source' => 'pattern_match',
                    'confidence' => 0.3,
                    'api_key_configured' => false,
                    'error' => 'API token not configured'
                ];
            }

            // Build better prompt with system instructions
            $systemInstructions = "You are a helpful assistant for a professional appointment booking system. ";
            $systemInstructions .= "Be concise (2-3 sentences max), professional, and helpful. ";
            $systemInstructions .= "If you don't know something, suggest contacting support.\n\n";
            
            $prompt = $systemInstructions . "Context:\n" . $context . "\n\nCustomer Question: " . $userMessage . "\n\nHelpful Response:";

            Log::debug('HuggingFace API request', [
                'message' => $userMessage,
                'prompt_length' => strlen($prompt)
            ]);

            // Call Hugging Face API with retry logic
            $maxRetries = 2;
            $attempt = 0;
            $lastError = null;

            while ($attempt < $maxRetries) {
                try {
                    $response = Http::withToken($token)
                        ->timeout(15)
                        ->post(self::HF_API_URL, [
                            'inputs' => $prompt,
                            'parameters' => [
                                'max_length' => 150,
                                'min_length' => 20,
                                'do_sample' => false,
                                'early_stopping' => true
                            ]
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        // Validate response structure
                        if (is_array($data) && isset($data[0]['generated_text'])) {
                            $generatedText = $data[0]['generated_text'];
                            
                            // Extract just the assistant's response
                            $assistantResponse = str_replace($prompt, '', $generatedText);
                            $assistantResponse = trim($assistantResponse);
                            $assistantResponse = preg_replace('/^(Response|Assistant):\s*/i', '', $assistantResponse);
                            
                            if (!empty($assistantResponse) && strlen($assistantResponse) > 10) {
                                Log::info('HuggingFace API success', [
                                    'attempt' => $attempt + 1,
                                    'response_length' => strlen($assistantResponse)
                                ]);

                                return [
                                    'response' => $assistantResponse,
                                    'source' => 'huggingface_ai',
                                    'confidence' => 0.95,
                                    'model' => 'flan-t5-small',
                                    'api_key_configured' => true
                                ];
                            }
                        }
                    } else {
                        $lastError = 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 200);
                        Log::warning('HuggingFace API error', [
                            'attempt' => $attempt + 1,
                            'status' => $response->status(),
                            'error' => $lastError
                        ]);
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('HuggingFace API exception on attempt ' . ($attempt + 1) . ': ' . $lastError);
                }

                $attempt++;
                if ($attempt < $maxRetries) {
                    sleep(1); // Brief delay before retry
                }
            }

            // All retries failed - return fallback
            Log::warning('HuggingFace API failed after ' . $maxRetries . ' attempts: ' . $lastError);
            return [
                'response' => $this->getFallbackResponse($userMessage),
                'source' => 'pattern_match',
                'confidence' => 0.4,
                'api_key_configured' => true,
                'error' => 'HuggingFace API unavailable'
            ];

        } catch (\Exception $e) {
            Log::error('HuggingFace API critical error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'response' => $this->getFallbackResponse($userMessage),
                'source' => 'pattern_match',
                'confidence' => 0.3,
                'api_key_configured' => true,
                'error' => $e->getMessage()
            ];
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
}

