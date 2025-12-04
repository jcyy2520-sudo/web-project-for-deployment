<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private const HF_API_URL = 'https://api-inference.huggingface.co/models/google/flan-t5-small';
    // Token is loaded from environment variable in sendMessage method
    private const MAX_HISTORY = 10; // Keep last 10 messages for context
    private const SYSTEM_PROMPT = "You are a helpful customer support assistant for our appointment booking system. You help users with questions about scheduling appointments, managing their bookings, and general inquiries. Keep responses concise and professional.";

    /**
     * Get chat history for the current user
     */
    public function getHistory(Request $request)
    {
        try {
            $userId = auth()->id();
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
            $userMessage = $request->input('message');
            $conversationId = $request->input('conversation_id') ?? uniqid('chat_');

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

            // Build context from recent messages
            $context = $this->buildContext($recentMessages);

            // Get AI response from Hugging Face
            $aiResponse = $this->getAIResponse($userMessage, $context);

            if (!$aiResponse) {
                throw new \Exception('Failed to get response from AI service');
            }

            // Save AI message
            ChatMessage::create([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message' => $aiResponse,
                'role' => 'assistant',
                'source' => 'huggingface'
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ChatBot sendMessage error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build context string from previous messages
     */
    private function buildContext($messages)
    {
        $context = self::SYSTEM_PROMPT . "\n\nPrevious conversation:\n";

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
        $keywordResponses = [
            'appointment' => 'I can help you with appointment scheduling. Please provide more details about what you need.',
            'book' => 'To book an appointment, please use the appointment booking system in your dashboard.',
            'cancel' => 'To cancel an appointment, please visit the appointments section in your account.',
            'reschedule' => 'You can reschedule your appointment from the appointments page in your dashboard.',
            'help' => 'I\'m here to help! What would you like to know about our services or appointments?',
            'hello' => 'Hello! How can I assist you today?',
            'hi' => 'Hi there! What can I help you with?'
        ];

        $lowerMessage = strtolower($userMessage);
        
        foreach ($keywordResponses as $keyword => $response) {
            if (strpos($lowerMessage, $keyword) !== false) {
                return $response;
            }
        }

        return "Thank you for your message. I\'m working to provide you with the best response. For immediate assistance, please contact our support team.";
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
     * Get conversation summary (for testing/analytics)
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
