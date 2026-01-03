<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * UnifiedChatbotService - LLM-First AI Chatbot Architecture
 * 
 * This service implements a modern RAG (Retrieval-Augmented Generation) pipeline:
 * 
 * 1. EMBED: Convert user message to vector embedding
 * 2. RETRIEVE: Find relevant context from knowledge base via semantic search
 * 3. AUGMENT: Combine user context, conversation history, and retrieved data
 * 4. GENERATE: Send everything to LLM for intelligent response
 * 
 * Key Principles:
 * - LLM is PRIMARY, not fallback
 * - No hardcoded intent patterns (embeddings handle semantic understanding)
 * - Conversation history provides context continuity
 * - All real-time data fed to LLM upfront
 * - Simple, unified response pipeline
 */
class UnifiedChatbotService
{
    private LLMService $llmService;
    private VectorEmbeddingService $embeddingService;
    private ChatbotRealTimeDataService $dataService;
    private ChatbotFeedbackService $feedbackService;
    private ?StreamingLLMService $streamingService;
    
    // Configuration
    private const MAX_CONVERSATION_HISTORY = 10;
    private const SIMILARITY_THRESHOLD = 0.6;
    private const MAX_CONTEXT_DOCS = 5;
    
    public function __construct(
        LLMService $llmService,
        VectorEmbeddingService $embeddingService,
        ChatbotRealTimeDataService $dataService,
        ChatbotFeedbackService $feedbackService,
        ?StreamingLLMService $streamingService = null
    ) {
        $this->llmService = $llmService;
        $this->embeddingService = $embeddingService;
        $this->dataService = $dataService;
        $this->feedbackService = $feedbackService;
        $this->streamingService = $streamingService;
    }
    
    /**
     * Process user message through unified LLM-first pipeline
     * 
     * This is the ONLY entry point for generating chatbot responses.
     * No pattern matching, no intent classification, no multiple handlers.
     * Just: Embed → Retrieve → Augment → Generate
     */
    public function processMessage(
        string $userMessage,
        ?int $userId,
        string $conversationId,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        try {
            // 1. BASIC VALIDATION & SAFETY CHECK
            $safetyCheck = $this->performSafetyCheck($userMessage);
            if (!$safetyCheck['safe']) {
                return $this->createResponse(
                    $safetyCheck['response'],
                    'safety_filter',
                    ['filtered' => true, 'reason' => $safetyCheck['reason']]
                );
            }
            
            // 2. GET USER CONTEXT
            $userContext = $this->getUserContext($userId);
            $role = $userContext['role'];
            
            // 3. GET CONVERSATION HISTORY (Critical for context continuity)
            $conversationHistory = $this->getConversationHistory($userId, $conversationId);
            
            // 4. SEMANTIC RETRIEVAL - Find relevant knowledge
            $retrievedContext = $this->retrieveRelevantContext($userMessage, $role);
            
            // 5. GATHER REAL-TIME DATA (appointments, services, etc.)
            $realTimeData = $this->gatherRealTimeData($userId, $role, $userMessage);
            
            // 6. BUILD UNIFIED PROMPT WITH ALL CONTEXT
            $systemPrompt = $this->buildSystemPrompt(
                $userContext,
                $retrievedContext,
                $realTimeData,
                $options['language'] ?? 'english'
            );
            
            // 7. GENERATE RESPONSE VIA LLM
            $llmResult = $this->llmService->generateResponse(
                $userMessage,
                $conversationHistory,
                ['system_prompt' => $systemPrompt] + $realTimeData
            );
            
            if (!$llmResult['success']) {
                // LLM failed - use graceful fallback
                Log::warning('LLM generation failed', ['error' => $llmResult['error'] ?? 'unknown']);
                return $this->createGracefulFallback($userMessage, $role);
            }
            
            $response = $llmResult['response'];
            
            // 8. POST-PROCESS & VALIDATE RESPONSE
            $response = $this->validateAndCleanResponse($response);
            
            // 9. LOG INTERACTION FOR FEEDBACK LOOP
            $interactionId = $this->feedbackService->logInteraction([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'bot_response' => $response,
                'context_used' => array_keys($retrievedContext),
                'llm_provider' => $llmResult['provider'] ?? 'unknown',
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);
            
            return $this->createResponse($response, 'llm', [
                'provider' => $llmResult['provider'] ?? 'unknown',
                'model' => $llmResult['model'] ?? 'unknown',
                'tokens_used' => $llmResult['tokens_used'] ?? 0,
                'context_sources' => count($retrievedContext['documents'] ?? []),
                'conversation_length' => count($conversationHistory),
                'interaction_id' => $interactionId,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            
        } catch (\Exception $e) {
            Log::error('UnifiedChatbot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->createGracefulFallback($userMessage, $userContext['role'] ?? 'guest');
        }
    }
    
    /**
     * Process message with streaming (for real-time token display)
     */
    public function processMessageStreaming(
        string $userMessage,
        ?int $userId,
        string $conversationId,
        callable $onToken,
        callable $onComplete = null,
        array $options = []
    ): array {
        if (!$this->streamingService) {
            // Fall back to non-streaming if service not available
            $result = $this->processMessage($userMessage, $userId, $conversationId, $options);
            if ($onToken) {
                $onToken($result['response'], ['final' => true]);
            }
            if ($onComplete) {
                $onComplete($result);
            }
            return $result;
        }
        
        try {
            // Same context gathering as non-streaming
            $userContext = $this->getUserContext($userId);
            $conversationHistory = $this->getConversationHistory($userId, $conversationId);
            $retrievedContext = $this->retrieveRelevantContext($userMessage, $userContext['role']);
            $realTimeData = $this->gatherRealTimeData($userId, $userContext['role'], $userMessage);
            
            $systemPrompt = $this->buildSystemPrompt(
                $userContext,
                $retrievedContext,
                $realTimeData,
                $options['language'] ?? 'english'
            );
            
            return $this->streamingService->streamResponse(
                $userMessage,
                $conversationHistory,
                ['system_prompt' => $systemPrompt] + $realTimeData,
                $onToken,
                $onComplete
            );
            
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
            $fallback = $this->createGracefulFallback($userMessage, $userContext['role'] ?? 'guest');
            if ($onToken) {
                $onToken($fallback['response'], ['final' => true, 'error' => true]);
            }
            return $fallback;
        }
    }
    
    /**
     * Retrieve relevant context using vector embeddings
     * This replaces hardcoded intent patterns with semantic understanding
     */
    private function retrieveRelevantContext(string $message, string $role): array
    {
        try {
            // Get semantic search results from knowledge base
            $searchResults = $this->embeddingService->semanticSearch(
                $message,
                null, // Search all categories
                self::MAX_CONTEXT_DOCS
            );
            
            // Filter by similarity threshold
            $relevantDocs = array_filter($searchResults, function($doc) {
                return ($doc['similarity'] ?? 0) >= self::SIMILARITY_THRESHOLD;
            });
            
            // Build context string for LLM
            $contextText = '';
            if (!empty($relevantDocs)) {
                $contextText = "## Relevant Information from Knowledge Base:\n\n";
                foreach ($relevantDocs as $doc) {
                    $similarity = round(($doc['similarity'] ?? 0) * 100, 1);
                    $contextText .= "### {$doc['title']} (Relevance: {$similarity}%)\n";
                    $contextText .= "{$doc['content']}\n\n";
                }
            }
            
            return [
                'documents' => $relevantDocs,
                'context_text' => $contextText,
                'total_found' => count($relevantDocs),
            ];
            
        } catch (\Exception $e) {
            Log::warning('Context retrieval failed: ' . $e->getMessage());
            return ['documents' => [], 'context_text' => '', 'total_found' => 0];
        }
    }
    
    /**
     * Get conversation history for context continuity
     */
    private function getConversationHistory(?int $userId, string $conversationId): array
    {
        if (!$userId) {
            return [];
        }
        
        try {
            $messages = ChatMessage::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit(self::MAX_CONVERSATION_HISTORY)
                ->get()
                ->reverse()
                ->values();
            
            return $messages->map(fn($msg) => [
                'role' => $msg->role === 'user' ? 'user' : 'assistant',
                'content' => $msg->message,
            ])->toArray();
            
        } catch (\Exception $e) {
            Log::debug('Failed to get conversation history: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gather real-time system data based on user role and detected intent
     */
    private function gatherRealTimeData(?int $userId, string $role, string $message): array
    {
        $data = [];
        
        try {
            // Business info - always included
            $data['business_info'] = $this->dataService->getBusinessInfo();
            $data['services'] = $this->dataService->getAvailableServices();
            $data['business_hours'] = $this->dataService->getBusinessHours();
            $data['current_datetime'] = [
                'date' => now()->format('F j, Y'),
                'day' => now()->format('l'),
                'time' => now()->format('g:i A'),
            ];
            
            // User-specific data
            if ($userId) {
                $data['user_appointments'] = $this->dataService->getUserAppointments($userId, 5);
                $data['user_payments'] = $this->dataService->getUserPayments($userId, 5);
                
                // Role-specific additional data
                if ($role === 'admin') {
                    $data['system_stats'] = $this->dataService->getSystemStats();
                    $data['pending_appointments'] = $this->dataService->getPendingAppointments(10);
                } elseif ($role === 'cashier') {
                    $data['today_summary'] = $this->dataService->getTodaysSummary();
                    $data['pending_payments'] = $this->dataService->getPendingPayments(10);
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to gather real-time data: ' . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Get user context including role
     */
    private function getUserContext(?int $userId): array
    {
        if (!$userId) {
            return [
                'role' => 'guest',
                'is_authenticated' => false,
                'user' => null,
            ];
        }
        
        try {
            $user = User::find($userId);
            if (!$user) {
                return ['role' => 'guest', 'is_authenticated' => false, 'user' => null];
            }
            
            // Get role from user model
            $role = 'client'; // Default
            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('admin')) {
                    $role = 'admin';
                } elseif ($user->hasRole('cashier')) {
                    $role = 'cashier';
                }
            } elseif (isset($user->role)) {
                $role = strtolower($user->role);
            }
            
            return [
                'role' => $role,
                'is_authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->first_name ?? 'User',
                    'email' => $user->email,
                ],
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get user context: ' . $e->getMessage());
            return ['role' => 'client', 'is_authenticated' => true, 'user' => null];
        }
    }
    
    /**
     * Build the unified system prompt
     * SIMPLIFIED: Focus on accuracy, not verbosity
     */
    private function buildSystemPrompt(
        array $userContext,
        array $retrievedContext,
        array $realTimeData,
        string $language
    ): string {
        $role = $userContext['role'];
        $userName = $userContext['user']['name'] ?? null;
        
        // Core prompt - concise and effective
        $prompt = <<<PROMPT
You are a helpful assistant for Peejayy De Guzman Legal, a notary and legal services office.

CORE RULES:
1. Answer ONLY what you know from the provided data
2. If uncertain, ask for clarification
3. Never guess, assume, or fabricate information
4. Be concise and professional
5. If asked to perform actions (book, cancel, approve), explain HOW to do it - don't pretend to do it

USER CONTEXT:
- Role: {$role}
- Language: {$language}
PROMPT;

        if ($userName) {
            $prompt .= "\n- Name: {$userName}";
        }
        
        // Add retrieved knowledge base context
        if (!empty($retrievedContext['context_text'])) {
            $prompt .= "\n\n" . $retrievedContext['context_text'];
        }
        
        // Add real-time data context
        if (!empty($realTimeData)) {
            $prompt .= "\n\n## Current System Data:\n";
            $prompt .= $this->formatRealTimeDataForPrompt($realTimeData);
        }
        
        $prompt .= "\n\nRemember: You are an ASSISTANT. Guide users to perform actions themselves through the system interface.";
        
        return $prompt;
    }
    
    /**
     * Format real-time data as readable context for LLM
     */
    private function formatRealTimeDataForPrompt(array $data): string
    {
        $output = '';
        
        if (!empty($data['business_info'])) {
            $info = $data['business_info'];
            $output .= "Business: {$info['company_name']}\n";
            $output .= "Address: {$info['address']}\n";
            $output .= "Phone: {$info['phone']}\n";
            $output .= "Email: {$info['email']}\n";
        }
        
        if (!empty($data['current_datetime'])) {
            $dt = $data['current_datetime'];
            $output .= "Current: {$dt['day']}, {$dt['date']} at {$dt['time']}\n";
        }
        
        if (!empty($data['services'])) {
            $output .= "\nAvailable Services:\n";
            foreach (array_slice($data['services'], 0, 10) as $service) {
                $price = isset($service['price']) ? " - ₱" . number_format($service['price'], 2) : '';
                $output .= "- {$service['name']}{$price}\n";
            }
        }
        
        if (!empty($data['user_appointments'])) {
            $output .= "\nUser's Recent Appointments:\n";
            foreach (array_slice($data['user_appointments'], 0, 5) as $apt) {
                $output .= "- {$apt['service_name']} on {$apt['date']} ({$apt['status']})\n";
            }
        }
        
        if (!empty($data['business_hours'])) {
            $output .= "\nBusiness Hours:\n";
            foreach ($data['business_hours'] as $day => $hours) {
                if (is_array($hours)) {
                    $output .= "- {$day}: {$hours['open']} - {$hours['close']}\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Basic safety check for inappropriate content
     */
    private function performSafetyCheck(string $message): array
    {
        $lowerMessage = strtolower($message);
        
        // Check for harmful content patterns
        $harmfulPatterns = [
            'hack', 'exploit', 'vulnerability', 'inject', 'sql injection',
            'delete all', 'drop table', 'rm -rf', 'format c:',
        ];
        
        foreach ($harmfulPatterns as $pattern) {
            if (strpos($lowerMessage, $pattern) !== false) {
                return [
                    'safe' => false,
                    'reason' => 'potential_harmful_content',
                    'response' => "I'm designed to help with legal services and appointments. I can't assist with that type of request. How else can I help you today?",
                ];
            }
        }
        
        // Check for explicit/offensive content
        $offensivePatterns = [
            '/\b(fuck|shit|ass|damn|bitch|bastard)\b/i',
        ];
        
        foreach ($offensivePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return [
                    'safe' => false,
                    'reason' => 'offensive_language',
                    'response' => "I understand you may be frustrated. I'm here to help with your legal service needs. Could you please rephrase your question?",
                ];
            }
        }
        
        return ['safe' => true];
    }
    
    /**
     * Validate and clean LLM response
     */
    private function validateAndCleanResponse(string $response): string
    {
        // Remove any potential prompt injection attempts in response
        $response = preg_replace('/\[SYSTEM\].*?\[\/SYSTEM\]/is', '', $response);
        $response = preg_replace('/\[INST\].*?\[\/INST\]/is', '', $response);
        
        // Truncate if too long
        if (strlen($response) > 4000) {
            $response = substr($response, 0, 3997) . '...';
        }
        
        // Ensure not empty
        if (empty(trim($response))) {
            $response = "I apologize, but I couldn't generate a proper response. Could you please rephrase your question?";
        }
        
        return trim($response);
    }
    
    /**
     * Create standardized response array
     */
    private function createResponse(string $response, string $source, array $meta = []): array
    {
        return [
            'success' => true,
            'response' => $response,
            'source' => $source,
            'meta' => $meta,
            'timestamp' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Create graceful fallback when LLM fails
     */
    private function createGracefulFallback(string $message, string $role): array
    {
        $fallbackMessages = [
            'guest' => "I'm having trouble processing your request right now. For immediate assistance with our legal services, please contact us at 09765075274 or visit our office at 233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro.",
            'client' => "I apologize, but I'm experiencing a temporary issue. You can still access your appointments and services through your dashboard. If you need immediate help, please contact our office at 09765075274.",
            'admin' => "System is experiencing temporary issues with AI responses. Core functionality remains available through the admin dashboard.",
            'cashier' => "I'm having trouble responding right now. Please use the cashier dashboard for payment processing and other tasks.",
        ];
        
        $response = $fallbackMessages[$role] ?? $fallbackMessages['guest'];
        
        return $this->createResponse($response, 'fallback', [
            'fallback_reason' => 'llm_unavailable',
            'role' => $role,
        ]);
    }
    
    /**
     * Check if LLM service is available
     */
    public function isAvailable(): bool
    {
        try {
            $health = $this->llmService->healthCheck();
            return ($health['available_provider'] ?? null) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get service health status
     */
    public function getHealthStatus(): array
    {
        return [
            'llm' => $this->llmService->healthCheck(),
            'embeddings' => $this->embeddingService->isAvailable(),
            'knowledge_base_indexed' => $this->embeddingService->getIndexedDocumentCount(),
        ];
    }
}
