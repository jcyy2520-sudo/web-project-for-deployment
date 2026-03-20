<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * UnifiedChatbotService - Fully Dynamic, LLM-First AI Chatbot Architecture
 * 
 * This service implements a modern RAG (Retrieval-Augmented Generation) pipeline
 * with ZERO hard-coded responses, workflows, or rules:
 * 
 * 1. EMBED: Convert user message to vector embedding
 * 2. RETRIEVE: Find relevant context from knowledge base via semantic search
 * 3. AUGMENT: Combine user context, conversation history, knowledge feed, and real-time data
 * 4. GENERATE: Send to LLM with a dynamically-built system prompt for intelligent response
 * 5. LEARN: Log interaction and incorporate feedback for continuous improvement
 * 
 * Enhanced with feature-flagged capabilities:
 * - Guard service integration (security hardening)
 * - Memory summarization (cross-session context)
 * - Context overflow protection
 * - Confidence scoring
 * - Self-check validation
 * - Analytics logging
 * - Long-term memory
 */
class UnifiedChatbotService
{
    private LLMService $llmService;
    private VectorEmbeddingService $embeddingService;
    private ChatbotRealTimeDataService $dataService;
    private ChatbotFeedbackService $feedbackService;
    private DynamicSystemPromptService $promptService;
    private DynamicKnowledgeFeedService $knowledgeFeedService;
    private ChatbotSecurityService $securityService;
    private ?StreamingLLMService $streamingService;
    private ?ChatbotMemoryService $memoryService;
    private ?ChatbotGuardService $guardService;
    private ?ChatbotAnalyticsService $analyticsService;
    private ?AgentReasoningService $agentReasoning;
    private ?AgentToolRegistry $toolRegistry;
    private ?IntelligentFallbackService $intelligentFallback;
    
    // Configuration — loaded from config
    private int $maxConversationHistory;
    private float $similarityThreshold;
    private int $maxContextDocs;
    private int $cacheTtlMinutes;
    
    public function __construct(
        LLMService $llmService,
        VectorEmbeddingService $embeddingService,
        ChatbotRealTimeDataService $dataService,
        ChatbotFeedbackService $feedbackService,
        DynamicSystemPromptService $promptService,
        DynamicKnowledgeFeedService $knowledgeFeedService,
        ChatbotSecurityService $securityService,
        ?StreamingLLMService $streamingService = null,
        ?ChatbotMemoryService $memoryService = null,
        ?ChatbotGuardService $guardService = null,
        ?ChatbotAnalyticsService $analyticsService = null,
        ?AgentReasoningService $agentReasoning = null,
        ?AgentToolRegistry $toolRegistry = null,
        ?IntelligentFallbackService $intelligentFallback = null
    ) {
        $this->llmService = $llmService;
        $this->embeddingService = $embeddingService;
        $this->dataService = $dataService;
        $this->feedbackService = $feedbackService;
        $this->promptService = $promptService;
        $this->knowledgeFeedService = $knowledgeFeedService;
        $this->securityService = $securityService;
        $this->streamingService = $streamingService;
        $this->memoryService = $memoryService;
        $this->guardService = $guardService;
        $this->analyticsService = $analyticsService;
        $this->agentReasoning = $agentReasoning;
        $this->toolRegistry = $toolRegistry;
        $this->intelligentFallback = $intelligentFallback;

        $this->maxConversationHistory = (int) config('chatbot_unified.conversation.max_history', 20);
        $this->similarityThreshold = (float) config('chatbot_unified.conversation.similarity_threshold', 0.35);
        $this->maxContextDocs = (int) config('chatbot_unified.conversation.max_context_docs', 8);
        $this->cacheTtlMinutes = (int) config('chatbot_unified.conversation.cache_ttl_minutes', 10);
    }
    
    /**
     * Process user message through unified LLM-first pipeline
     * 
     * This is the ONLY entry point for generating chatbot responses.
     * No pattern matching, no intent classification, no multiple handlers.
     * Just: Context → Dynamic Prompt → LLM → Response
     * 
     * ALL responses — including guests — go through the LLM.
     * The system prompt is built ENTIRELY at runtime with no hard-coded rules.
     */
    public function processMessage(
        string $userMessage,
        ?int $userId,
        string $conversationId,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        try {
            // ── 0. MANDATORY SECURITY CHECKS (always-on, NOT feature-flagged) ──
            // Zero-trust: role is determined server-side BEFORE any processing
            $userContext = $this->getUserContext($userId);
            $role = $userContext['role'];

            // Run comprehensive security checks (prompt injection + role escalation + abuse)
            $securityResult = $this->securityService->runSecurityChecks(
                $userMessage,
                $role,
                $userId,
                $options['ip_address'] ?? request()->ip() ?? '0.0.0.0',
                $options['session_id'] ?? null
            );

            if (!$securityResult['passed']) {
                return $this->createResponse(
                    $securityResult['response'],
                    'security_filter',
                    [
                        'filtered' => true,
                        'reason' => $securityResult['event_type'] ?? 'security',
                        'risk_score' => $securityResult['risk_score'] ?? 0,
                        'role' => $role,
                    ]
                );
            }

            // ── 0c. FAST PATH for trivial messages (greetings, thanks) ──
            if ($this->isTrivialMessage($userMessage)) {
                // Try static fallback first (0ms latency, high reliability)
                $staticFallback = $this->getStaticGreetingFallback($userMessage);
                if ($staticFallback) {
                    return $this->createResponse($staticFallback, 'static_fallback', [
                        'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'role' => $role,
                    ]);
                }

                // If no static fallback, try a lightweight LLM (Fast Path)
                $fastResponse = $this->handleFastPath($userMessage, $role, $userId);
                if ($fastResponse) {
                    $resultMeta = [
                        'provider' => 'fast_path',
                        'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'role' => $role,
                    ];
                    return $this->createResponse($fastResponse, 'fast_path', $resultMeta);
                }
            }

            // Create cryptographic role assertion for this request
            $roleAssertion = $this->securityService->createRoleAssertion($userId, $role);

            // ── 0b. GUARD SERVICE — advanced content filtering (feature-flagged) ──
            if (config('chatbot_unified.features.guard_service', false) && $this->guardService) {
                try {
                    $guardResult = $this->guardService->checkContent($userMessage);
                    if (!($guardResult['safe'] ?? true)) {
                        Log::info('GuardService blocked input', [
                            'reason' => $guardResult['reason'] ?? 'unknown',
                            'severity' => $guardResult['severity'] ?? 'standard',
                        ]);
                        return $this->createResponse(
                            $guardResult['response'] ?? "I can't process that request. How can I help you with our services?",
                            'guard_filter',
                            ['filtered' => true, 'reason' => $guardResult['reason'] ?? 'guard']
                        );
                    }
                } catch (\Exception $e) {
                    Log::warning('GuardService input check failed, continuing: ' . $e->getMessage());
                }
            }

            // 1. INTELLIGENT SAFETY CHECK — graduated, not blocking
            $safetyCheck = $this->performSafetyCheck($userMessage);
            if (!$safetyCheck['safe']) {
                return $this->createResponse(
                    $safetyCheck['response'],
                    'safety_filter',
                    ['filtered' => true, 'reason' => $safetyCheck['reason']]
                );
            }
            
            // 3. DETECT LANGUAGE from the user's message
            $detectedLanguage = $this->detectLanguage($userMessage);

            // ── 3a. INTENT DETECTION (Speed Optimization) ──
            $isMinimal = $this->isTrivialMessage($userMessage);
            
            // 4. GET CONVERSATION HISTORY (critical for context continuity)
            $conversationHistory = $this->getConversationHistory($userId, $conversationId);
            
            // ── 4a. CONTEXT OVERFLOW PROTECTION (feature-flagged) ──
            if (config('chatbot_unified.features.context_overflow', false)) {
                $overflowResult = $this->checkContextOverflow($userMessage, $conversationHistory);
                if ($overflowResult['overflow']) {
                    $conversationHistory = $overflowResult['trimmed_history'];
                    Log::info('Context overflow protection triggered', [
                        'original_tokens' => $overflowResult['original_tokens'],
                        'trimmed_to' => $overflowResult['trimmed_tokens'],
                    ]);
                }
            }
            
            // ── 4b. MEMORY SUMMARY (feature-flagged) ──
            $memorySummary = null;
            if (config('chatbot_unified.features.memory_summary', false) && $this->memoryService && $userId) {
                try {
                    $memoryContext = $this->memoryService->getConversationContext($userId, $conversationId);
                    if (count($conversationHistory) >= config('chatbot_unified.long_term_memory.summary_min_messages', 10)) {
                        $memorySummary = $memoryContext['conversation_summary'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::debug('Memory summary retrieval failed: ' . $e->getMessage());
                }
            }
            
            // 5. SEMANTIC RETRIEVAL — skip for trivial messages (greetings, thanks)
            $retrievedContext = ['documents' => [], 'context_text' => '', 'total_found' => 0];
            if (!$this->isTrivialMessage($userMessage)) {
                $retrievedContext = $this->retrieveRelevantContext($userMessage, $role);
            }
            
            // 6. GATHER REAL-TIME DATA (appointments, services, payments, stats)
            $realTimeData = [];
            if (!$isMinimal) {
                $realTimeData = $this->gatherRealTimeData($userId, $role, $userMessage);
            }
            
            // 7. GATHER CONVERSATION MEMORY (past interactions, preferences)
            $conversationMemory = [];
            if (!$isMinimal) {
                $conversationMemory = $this->gatherConversationMemory($userId, $conversationId);
            }
            
            // ── 7a. LONG-TERM MEMORY — cross-session summaries (feature-flagged) ──
            if (config('chatbot_unified.features.long_term_memory', false) && $this->memoryService && $userId) {
                try {
                    $maxSummaries = config('chatbot_unified.long_term_memory.max_past_summaries', 3);
                    $pastSummaries = $this->memoryService->getCrossSessionHistory($userId, $maxSummaries);
                    if (!empty($pastSummaries)) {
                        $conversationMemory['past_conversations'] = $pastSummaries;
                    }
                } catch (\Exception $e) {
                    Log::debug('Long-term memory retrieval failed: ' . $e->getMessage());
                }
            }
            
            // Prepend memory summary to conversation memory if available
            if ($memorySummary) {
                $conversationMemory['summary'] = $memorySummary;
            }
            
            // 8. GATHER FEEDBACK INSIGHTS (learned corrections, common issues)
            $feedbackInsights = $this->gatherFeedbackInsights();
            
            // 9. GATHER DYNAMIC KNOWLEDGE FEED (DB schema, API, UI, workflows, errors)
            $knowledgeFeed = $this->knowledgeFeedService->getKnowledgeFeedAsPromptSection(
                $role,
                $userId
            );
            
            // 10. BUILD FULLY DYNAMIC SYSTEM PROMPT (zero hard-coded content)
            $isMinimal = $this->isTrivialMessage($userMessage);
            $systemPrompt = $this->promptService->build(
                $userContext,
                $retrievedContext,
                $realTimeData,
                $conversationMemory,
                $feedbackInsights,
                $detectedLanguage,
                $conversationId,
                ['minimal' => $isMinimal]
            );
            
            // Append knowledge feed as additional context
            if (!empty($knowledgeFeed)) {
                // SECURITY: Sanitize dynamic knowledge feed to prevent indirect prompt injection
                $sanitizedFeed = $this->securityService->sanitizeInjectedContent($knowledgeFeed);
                $systemPrompt .= "\n\n## DYNAMIC SYSTEM KNOWLEDGE\n" . $sanitizedFeed;
            }
            
            // ── 10a. AGENT TOOL DEFINITIONS (feature-flagged) ──
            // When agent mode is enabled, inject tool definitions into the system prompt
            // so the LLM can autonomously decide which tools to call
            if (config('chatbot_unified.features.agent_mode', false) && $this->toolRegistry) {
                $toolSection = $this->toolRegistry->getToolPromptSection($role);
                if (!empty($toolSection)) {
                    $systemPrompt .= "\n\n" . $toolSection;
                }
            }
            
            // ── 10b. CONFIDENCE SCORING instruction (feature-flagged) ──
            if (config('chatbot_unified.features.confidence_score', false)) {
                $systemPrompt .= "\n\n## CONFIDENCE SCORING\nAfter your answer, output a confidence score from 0–10 inside <confidence> tags. Example: <confidence>8</confidence>";
            }
            
            // ── 11. GENERATE RESPONSE — Agent Reasoning Loop or Direct LLM ──
            $llmResult = null;
            $agentToolCalls = [];
            $requiresConfirmation = false;
            $confirmationKey = null;
            $pendingToolName = null;
            
            // Check for pending confirmations from previous agent tool calls
            $pendingConfirmation = [];
            if (config('chatbot_unified.features.agent_mode', false) && $this->agentReasoning && $userId) {
                $pending = AgentReasoningService::getPendingConfirmation($userId);
                if ($pending) {
                    $pendingConfirmation = $pending;
                }
            }
            
            if (config('chatbot_unified.features.agent_mode', false) && $this->agentReasoning) {
                // AGENT MODE: Use ReAct reasoning loop with tool execution
                $agentResult = $this->agentReasoning->reason(
                    $userMessage,
                    $systemPrompt,
                    $conversationHistory,
                    $userId,
                    $role,
                    $pendingConfirmation
                );
                
                $agentToolCalls = $agentResult['tool_calls'] ?? [];
                
                if (!empty($agentResult['requires_confirmation'])) {
                    $requiresConfirmation = true;
                    $confirmationKey = $agentResult['confirmation_key'] ?? null;
                    $pendingToolName = $agentResult['pending_tool'] ?? null;
                }
                
                if ($agentResult['llm_failed'] ?? false) {
                    // Agent LLM failed — fall through to intelligent fallback
                    $llmResult = ['success' => false, 'error' => 'agent_llm_failed'];
                } else {
                    $llmResult = [
                        'success' => true,
                        'response' => $agentResult['response'],
                        'provider' => $agentResult['provider'] ?? 'agent',
                        'model' => $agentResult['model'] ?? 'agent',
                        'tokens_used' => $agentResult['tokens_used'] ?? 0,
                    ];
                }
            } else {
                // STANDARD MODE: Direct LLM call (existing behavior)
                $llmResult = $this->llmService->generateResponse(
                    $userMessage,
                    $conversationHistory,
                    [
                        'system_prompt' => $systemPrompt,
                        'role' => $role,
                        'skip_internal_prompt' => true,
                        'detected_language' => $detectedLanguage,
                    ]
                );
            }
            
            if (!$llmResult['success']) {
                Log::warning('LLM generation failed', ['error' => $llmResult['error'] ?? 'unknown']);
                // Use intelligent fallback instead of template fallback
                if ($this->intelligentFallback) {
                    $fallbackResult = $this->intelligentFallback->generateFallback(
                        $userMessage, $role, $userId, $llmResult['error'] ?? 'llm_unavailable'
                    );
                    return $this->createResponse(
                        $fallbackResult['response'],
                        $fallbackResult['source'],
                        ['fallback_layer' => $fallbackResult['fallback_layer'], 'role' => $role]
                    );
                }
                return $this->createGracefulFallback($userMessage, $role);
            }
            
            $response = $llmResult['response'];
            
            // ── 11a. PARSE CONFIDENCE SCORE (feature-flagged) ──
            $confidenceScore = null;
            if (config('chatbot_unified.features.confidence_score', false)) {
                $confidenceScore = $this->parseConfidenceScore($response);
                // Remove confidence tags from visible response
                $response = preg_replace('/<confidence>\d+(\.\d+)?<\/confidence>/i', '', $response);
                $response = trim($response);
            }
            
            // ── 11b. SELF-CHECK VALIDATION (feature-flagged) ──
            // Skip self-check when agent mode produced the response — the agent reasoning loop
            // already validates tool calls and catches hallucinated actions, so a second LLM call
            // for self-check only adds latency (often 2-5 seconds) with minimal benefit.
            $agentModeActive = config('chatbot_unified.features.agent_mode', false) && $this->agentReasoning;
            if (config('chatbot_unified.features.self_check', false) && !$agentModeActive) {
                try {
                    $validatedResponse = $this->performSelfCheck(
                        $response,
                        $retrievedContext['context_text'] ?? '',
                        $userMessage
                    );
                    if ($validatedResponse !== null) {
                        $response = $validatedResponse;
                    }
                } catch (\Exception $e) {
                    Log::debug('Self-check failed, using original response: ' . $e->getMessage());
                }
            }
            
            // 12. POST-PROCESS & VALIDATE RESPONSE
            $response = $this->validateAndCleanResponse($response);
            
            // ── 12a. MANDATORY OUTPUT SECURITY VALIDATION (always-on) ──
            $outputValidation = $this->securityService->validateOutput($response, $role);
            if (!$outputValidation['safe']) {
                Log::warning('Security: Output validation failed', [
                    'violations' => $outputValidation['violations'],
                    'role' => $role,
                ]);
                $response = $outputValidation['sanitized'];
            }

            // ── 12b. GUARD SERVICE — output inspection (feature-flagged) ──
            if (config('chatbot_unified.features.guard_service', false) && $this->guardService) {
                try {
                    $outputCheck = $this->inspectOutput($response);
                    if (!$outputCheck['safe']) {
                        Log::warning('GuardService blocked output', [
                            'reason' => $outputCheck['reason'] ?? 'output_violation',
                        ]);
                        $response = "I apologize, but I couldn't generate an appropriate response. Let me try again — could you please rephrase your question?";
                    }
                } catch (\Exception $e) {
                    Log::warning('GuardService output check failed: ' . $e->getMessage());
                }
            }
            
            // 13. LOG INTERACTION FOR FEEDBACK LOOP & SELF-LEARNING
            $interactionId = $this->feedbackService->logInteraction([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'user_message' => $userMessage,
                'bot_response' => $response,
                'context_used' => array_keys($retrievedContext),
                'llm_provider' => $llmResult['provider'] ?? 'unknown',
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
                'role' => $role,
                'language' => $detectedLanguage,
            ]);
            
            // ── 13a. ANALYTICS LOGGING (feature-flagged) ──
            if (config('chatbot_unified.features.analytics', false) && $this->analyticsService) {
                try {
                    $this->analyticsService->logInteraction([
                        'user_id' => $userId,
                        'conversation_id' => $conversationId,
                        'user_message' => $userMessage,
                        'bot_response' => $response,
                        'start_time' => $startTime,
                        'response_source' => $llmResult['provider'] ?? 'llm',
                        'user_role' => $role,
                        'confidence' => $confidenceScore,
                        'success' => true,
                        'ip_address' => $options['ip_address'] ?? null,
                        'user_agent' => $options['user_agent'] ?? null,
                    ]);
                } catch (\Exception $e) {
                    Log::debug('Analytics logging failed: ' . $e->getMessage());
                }
            }
            
            // ── 13b. UPDATE MEMORY SERVICE (feature-flagged) ──
            if (config('chatbot_unified.features.memory_summary', false) && $this->memoryService && $userId) {
                try {
                    $this->memoryService->updateContext($userId, $conversationId, 'user', $userMessage, [
                        'detected_language' => $detectedLanguage,
                    ]);
                    $this->memoryService->updateContext($userId, $conversationId, 'assistant', $response, [
                        'provider' => $llmResult['provider'] ?? 'unknown',
                    ]);
                } catch (\Exception $e) {
                    Log::debug('Memory context update failed: ' . $e->getMessage());
                }
            }
            
            $resultMeta = [
                'provider' => $llmResult['provider'] ?? 'unknown',
                'model' => $llmResult['model'] ?? 'unknown',
                'tokens_used' => $llmResult['tokens_used'] ?? 0,
                'context_sources' => count($retrievedContext['documents'] ?? []),
                'conversation_length' => count($conversationHistory),
                'interaction_id' => $interactionId,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'role' => $role,
                'detected_language' => $detectedLanguage,
                'agent_tool_calls' => count($agentToolCalls),
            ];
            
            // Include confidence score in meta if available
            if ($confidenceScore !== null) {
                $resultMeta['confidence_score'] = $confidenceScore;
            }
            
            // Include confirmation info if a destructive action is awaiting confirmation
            if ($requiresConfirmation && $confirmationKey) {
                $resultMeta['requires_confirmation'] = true;
                $resultMeta['confirmation_key'] = $confirmationKey;
                if (!empty($pendingToolName)) {
                    $resultMeta['pending_tool'] = $pendingToolName;
                }
            }

            return $this->createResponse($response, 'llm', $resultMeta);
            
        } catch (\Throwable $e) {
            Log::error('UnifiedChatbot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // Try intelligent fallback before template fallback
            if ($this->intelligentFallback) {
                try {
                    $fallbackResult = $this->intelligentFallback->generateFallback(
                        $userMessage, $userContext['role'] ?? 'guest', $userId ?? null, 'processing_error'
                    );
                    return $this->createResponse(
                        $fallbackResult['response'],
                        $fallbackResult['source'],
                        ['fallback_layer' => $fallbackResult['fallback_layer'], 'role' => $userContext['role'] ?? 'guest']
                    );
                } catch (\Throwable $fallbackError) {
                    Log::warning('Intelligent fallback also failed', ['error' => $fallbackError->getMessage()]);
                }
            }
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
            // MANDATORY SECURITY CHECKS for streaming (same as non-streaming)
            $userContext = $this->getUserContext($userId);
            $role = $userContext['role'];

            $securityResult = $this->securityService->runSecurityChecks(
                $userMessage,
                $role,
                $userId,
                $options['ip_address'] ?? request()->ip() ?? '0.0.0.0',
                $options['session_id'] ?? null
            );

            if (!$securityResult['passed']) {
                $blockedResponse = $securityResult['response'];
                if ($onToken) {
                    $onToken($blockedResponse, ['final' => true, 'security_blocked' => true]);
                }
                return $this->createResponse($blockedResponse, 'security_filter', [
                    'filtered' => true,
                    'reason' => $securityResult['event_type'] ?? 'security',
                    'role' => $role,
                ]);
            }

            // Same context gathering as non-streaming
            $detectedLanguage = $this->detectLanguage($userMessage);
            $conversationHistory = $this->getConversationHistory($userId, $conversationId);

            $retrievedContext = ['documents' => [], 'context_text' => '', 'total_found' => 0];
            if (!$this->isTrivialMessage($userMessage)) {
                $retrievedContext = $this->retrieveRelevantContext($userMessage, $role);
            }

            $realTimeData = $this->gatherRealTimeData($userId, $role, $userMessage);
            $conversationMemory = $this->gatherConversationMemory($userId, $conversationId);
            $feedbackInsights = $this->gatherFeedbackInsights();
            
            // Build fully dynamic system prompt
            $systemPrompt = $this->promptService->build(
                $userContext,
                $retrievedContext,
                $realTimeData,
                $conversationMemory,
                $feedbackInsights,
                $detectedLanguage,
                $conversationId
            );
            
            $knowledgeFeed = $this->knowledgeFeedService->getKnowledgeFeedAsPromptSection($role, $userId);
            if (!empty($knowledgeFeed)) {
                // SECURITY: Sanitize dynamic knowledge feed to prevent indirect prompt injection
                $sanitizedFeed = $this->securityService->sanitizeInjectedContent($knowledgeFeed);
                $systemPrompt .= "\n\n## DYNAMIC SYSTEM KNOWLEDGE\n" . $sanitizedFeed;
            }

            // ── AGENT MODE for streaming: Use ReAct reasoning loop (same as non-streaming) ──
            if (config('chatbot_unified.features.agent_mode', false) && $this->agentReasoning && $this->toolRegistry) {
                $toolSection = $this->toolRegistry->getToolPromptSection($role);
                if (!empty($toolSection)) {
                    $systemPrompt .= "\n\n" . $toolSection;
                }

                // Check for pending confirmations
                $pendingConfirmation = [];
                if ($userId) {
                    $pending = AgentReasoningService::getPendingConfirmation($userId);
                    if ($pending) {
                        $pendingConfirmation = $pending;
                    }
                }

                $agentResult = $this->agentReasoning->reason(
                    $userMessage,
                    $systemPrompt,
                    $conversationHistory,
                    $userId,
                    $role,
                    $pendingConfirmation
                );

                $agentResponse = $agentResult['response'] ?? '';
                $requiresConfirmation = !empty($agentResult['requires_confirmation']);
                $confirmationKey = $agentResult['confirmation_key'] ?? null;
                $pendingToolName = $agentResult['pending_tool'] ?? null;

                if ($agentResult['llm_failed'] ?? false) {
                    // Fall through to regular streaming if agent LLM failed
                    Log::debug('Agent reasoning failed in streaming, falling back to direct stream');
                } else {
                    // Agent succeeded — send the result as a streamed response
                    if ($onToken) {
                        $onToken($agentResponse, ['final' => true]);
                    }

                    $meta = [
                        'provider' => $agentResult['provider'] ?? 'agent',
                        'model' => $agentResult['model'] ?? 'agent',
                        'tokens_used' => $agentResult['tokens_used'] ?? 0,
                        'agent_tool_calls' => count($agentResult['tool_calls'] ?? []),
                        'role' => $role,
                    ];

                    if ($requiresConfirmation && $confirmationKey) {
                        $meta['requires_confirmation'] = true;
                        $meta['confirmation_key'] = $confirmationKey;
                        if (!empty($pendingToolName)) {
                            $meta['pending_tool'] = $pendingToolName;
                        }
                    }

                    $result = $this->createResponse($agentResponse, 'agent', $meta);
                    if ($onComplete) {
                        $onComplete($result);
                    }
                    return $result;
                }
            }
            
            return $this->streamingService->streamResponse(
                $userMessage,
                $conversationHistory,
                [
                    'system_prompt' => $systemPrompt,
                    'role' => $role,
                    'skip_internal_prompt' => true,
                ],
                $onToken,
                $onComplete
            );
            
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
            // Try intelligent fallback for streaming too
            if ($this->intelligentFallback) {
                try {
                    $fallbackResult = $this->intelligentFallback->generateFallback(
                        $userMessage, $userContext['role'] ?? 'guest', null, 'streaming_error'
                    );
                    $fallback = $this->createResponse(
                        $fallbackResult['response'],
                        $fallbackResult['source'],
                        ['fallback_layer' => $fallbackResult['fallback_layer']]
                    );
                    if ($onToken) {
                        $onToken($fallback['response'], ['final' => true, 'error' => true]);
                    }
                    return $fallback;
                } catch (\Exception $fallbackError) {
                    Log::warning('Intelligent fallback also failed in streaming', ['error' => $fallbackError->getMessage()]);
                }
            }
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
                $this->maxContextDocs
            );
            
            // ── RERANKER: if enabled, rerank results for higher precision ──
            if (config('chatbot_unified.features.reranker', false)) {
                $searchResults = $this->rerankerSort($searchResults, $message);
            }
            
            // Filter by similarity threshold
            $relevantDocs = array_filter($searchResults, function($doc) {
                return ($doc['similarity'] ?? 0) >= $this->similarityThreshold;
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
     * Sort search results using reranker scoring when available.
     */
    private function rerankerSort(array $results, string $query): array
    {
        // If we have a reranker service available, use it
        try {
            if (method_exists($this->embeddingService, 'rerankResults')) {
                $topK = config('chatbot_unified.reranker.top_k', 3);
                return $this->embeddingService->rerankResults($results, $query, $topK);
            }
        } catch (\Exception $e) {
            Log::debug('Reranker failed, using original ranking: ' . $e->getMessage());
        }
        return $results;
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
                ->limit($this->maxConversationHistory)
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
     * Get user context including role — dynamically detected from the database.
     * Checks BOTH the DB column and Spatie roles, taking the highest-privilege match.
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
            
            // Priority order for role detection (highest privilege first)
            $rolePriority = ['admin' => 4, 'staff' => 3, 'cashier' => 2, 'client' => 1];
            $detectedRole = 'client';
            $detectedPriority = 1;
            
            // 1. Check DB column
            $dbRole = strtolower(trim($user->role ?? ''));
            if (in_array($dbRole, ['admin', 'administrator'])) {
                $detectedRole = 'admin';
                $detectedPriority = 4;
            } elseif ($dbRole === 'staff') {
                $detectedRole = 'staff';
                $detectedPriority = 3;
            } elseif ($dbRole === 'cashier') {
                $detectedRole = 'cashier';
                $detectedPriority = 2;
            }
            
            // 2. Also check Spatie Permission roles — take whichever is higher privilege
            if (method_exists($user, 'hasRole')) {
                try {
                    if (($user->hasRole('admin') || $user->hasRole('administrator')) && $detectedPriority < 4) {
                        $detectedRole = 'admin';
                        $detectedPriority = 4;
                    } elseif ($user->hasRole('staff') && $detectedPriority < 3) {
                        $detectedRole = 'staff';
                        $detectedPriority = 3;
                    } elseif ($user->hasRole('cashier') && $detectedPriority < 2) {
                        $detectedRole = 'cashier';
                        $detectedPriority = 2;
                    }
                } catch (\Exception $e) {
                    Log::debug('Spatie role check failed: ' . $e->getMessage());
                }
            }
            
            // 3. Check helper methods on User model as final fallback
            if ($detectedPriority <= 1) {
                if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                    $detectedRole = 'admin';
                } elseif (method_exists($user, 'isStaff') && $user->isStaff()) {
                    $detectedRole = 'staff';
                } elseif (method_exists($user, 'isCashier') && $user->isCashier()) {
                    $detectedRole = 'cashier';
                }
            }
            
            Log::debug('Chatbot role detection', [
                'user_id' => $userId,
                'db_role' => $dbRole,
                'resolved_role' => $detectedRole,
            ]);
            
            return [
                'role' => $detectedRole,
                'is_authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->first_name ?? 'User',
                    'email' => $user->email,
                ],
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get user context: ' . $e->getMessage());
            return ['role' => 'guest', 'is_authenticated' => true, 'user' => null];
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
            // Hard lower bound for booking dates — injected fresh every request (never cached)
            // Used by DynamicSystemPromptService to enforce past-date rejection
            $data['booking_date_boundary'] = now()->format('Y-m-d');
            
            // User-specific data
            if ($userId) {
                // IMPORTANT: Preserve pending tool confirmation before flushing specific caches
                // If user is confirming an action, we MUST NOT delete the pending confirmation
                $pendingConfirmation = AgentReasoningService::getPendingConfirmation($userId);
                
                // Clear targeted caches for this user to ensure fresh data every request
                \Illuminate\Support\Facades\Cache::forget("chatbot_appointments_user_{$userId}_all");
                \Illuminate\Support\Facades\Cache::forget("chatbot_appointments_user_{$userId}_pending");
                \Illuminate\Support\Facades\Cache::forget("chatbot_appointments_user_{$userId}_approved");
                \Illuminate\Support\Facades\Cache::forget("chatbot_appointments_user_{$userId}_completed");
                \Illuminate\Support\Facades\Cache::forget("chatbot_appointments_user_{$userId}_cancelled");
                \Illuminate\Support\Facades\Cache::forget("chatbot_booking_limit_{$userId}");
                
                // Restore pending confirmation if it existed
                if ($pendingConfirmation) {
                    \Illuminate\Support\Facades\Cache::put('agent_confirm_' . $userId . '_pending', $pendingConfirmation, 300);
                }
                
                $data['user_appointments'] = $this->dataService->getUserAppointments($userId, null, 8);
                $data['user_payments'] = $this->dataService->getUserPayments($userId, null, 8);

                // Booking limit info for all authenticated users
                try {
                    $settings = \App\Models\AppointmentSettings::getCurrent();
                    $limit = $settings->daily_booking_limit_per_user ?? 3;
                    $remaining = \App\Models\AppointmentSettings::getRemainingBookingsForUser($userId);
                    $hasReachedLimit = \App\Models\AppointmentSettings::userHasReachedDailyLimit($userId);
                    $nextAvailable = $hasReachedLimit ? \App\Models\AppointmentSettings::getNextAvailableTime($userId) : null;
                    $data['booking_limit'] = [
                        'daily_limit' => $limit,
                        'remaining' => $remaining ?? 0,
                        'has_reached_limit' => $hasReachedLimit,
                        'next_available_time' => $nextAvailable?->format('M d, Y \a\t g:i A'),
                    ];
                } catch (\Exception $e) {
                    Log::debug('Failed to get booking limit info: ' . $e->getMessage());
                }
                
                // Role-specific additional data
                if ($role === 'admin') {
                    $data['system_stats'] = $this->dataService->getSystemStats();
                    $data['pending_appointments'] = $this->dataService->getPendingAppointments(10);
                    $data['today_summary'] = $this->dataService->getTodaysSummary();
                    
                    // Weekly appointments — admin often asks "how many appointments this week"
                    try {
                        $weekStart = now()->startOfWeek();
                        $weekEnd = now()->endOfWeek();
                        $weeklyAppointments = Appointment::whereBetween('appointment_date', [$weekStart, $weekEnd])->get();
                        $data['weekly_appointments'] = [
                            'total' => $weeklyAppointments->count(),
                            'pending' => $weeklyAppointments->where('status', 'pending')->count(),
                            'approved' => $weeklyAppointments->where('status', 'approved')->count(),
                            'completed' => $weeklyAppointments->where('status', 'completed')->count(),
                            'cancelled' => $weeklyAppointments->where('status', 'cancelled')->count(),
                            'week_range' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d, Y'),
                        ];
                    } catch (\Exception $e) {
                        Log::debug('Failed to get weekly appointments: ' . $e->getMessage());
                    }
                    
                    // Monthly revenue
                    try {
                        $monthStart = now()->startOfMonth();
                        $monthEnd = now()->endOfMonth();
                        $monthlyPayments = \App\Models\Payment::whereBetween('created_at', [$monthStart, $monthEnd])
                            ->where('status', 'paid');
                        $data['monthly_revenue'] = [
                            'total' => $monthlyPayments->sum('amount'),
                            'count' => $monthlyPayments->count(),
                            'month' => now()->format('F Y'),
                        ];
                    } catch (\Exception $e) {
                        Log::debug('Failed to get monthly revenue: ' . $e->getMessage());
                    }
                    // ── Decision Support / Smart Analytics data ──
                    // Inject summary analytics ONLY if the user is asking for stats or forecast
                    $intentKeywords = ['busy', 'forecast', 'stats', 'utilization', 'trend', 'pattern', 'busy day', 'slow day'];
                    $needsAnalytics = false;
                    foreach ($intentKeywords as $kw) {
                        if (stripos($message, $kw) !== false) {
                            $needsAnalytics = true;
                            break;
                        }
                    }

                    if ($needsAnalytics) {
                        try {
                            $analyticsService = app(AnalyticsService::class);
    
                            // Demand forecast — busy/slow days for next 14 days
                            $forecast = $analyticsService->getDemandForecast(14);
                            if (!empty($forecast)) {
                                // Safely convert Collections to arrays before array_slice()
                                $dailyForecast = $forecast['forecast'] ?? $forecast['daily_forecast'] ?? [];
                                if ($dailyForecast instanceof \Illuminate\Support\Collection) $dailyForecast = $dailyForecast->toArray();
                                $serviceDemand = $forecast['service_demand'] ?? [];
                                if ($serviceDemand instanceof \Illuminate\Support\Collection) $serviceDemand = $serviceDemand->toArray();
                                $dayOfWeekStats = $forecast['day_of_week_stats'] ?? [];
                                if ($dayOfWeekStats instanceof \Illuminate\Support\Collection) $dayOfWeekStats = $dayOfWeekStats->toArray();
                                $forecastRecs = $forecast['recommendations'] ?? $forecast['insights'] ?? [];
                                if ($forecastRecs instanceof \Illuminate\Support\Collection) $forecastRecs = $forecastRecs->toArray();
    
                                $data['demand_forecast'] = [
                                    'day_of_week_stats' => $dayOfWeekStats,
                                    'daily_forecast' => array_slice(array_values($dailyForecast), 0, 14),
                                    'service_demand' => array_slice(array_values($serviceDemand), 0, 8),
                                    'recommendations' => $forecastRecs,
                                ];
                            }
    
                            // Slot utilization — capacity overview for recent period
                            $utilization = $analyticsService->getSlotUtilization(14);
                            if (!empty($utilization)) {
                                $underbookedDays = $utilization['underbooked_days'] ?? [];
                                if ($underbookedDays instanceof \Illuminate\Support\Collection) $underbookedDays = $underbookedDays->toArray();
                                $overbookedDays = $utilization['overbooked_days'] ?? [];
                                if ($overbookedDays instanceof \Illuminate\Support\Collection) $overbookedDays = $overbookedDays->toArray();
                                $utilSummary = $utilization['summary'] ?? [];
                                if ($utilSummary instanceof \Illuminate\Support\Collection) $utilSummary = $utilSummary->toArray();
    
                                $data['slot_utilization'] = [
                                    'summary' => $utilSummary,
                                    'overbooked_days' => $overbookedDays,
                                    'underbooked_days' => array_slice(array_values($underbookedDays), 0, 5),
                                ];
                            }
    
                            // No-show patterns — high-risk days/times
                            $noShowPatterns = $analyticsService->getNoShowPatterns(90);
                            if (!empty($noShowPatterns)) {
                                $highRiskDays = $noShowPatterns['high_risk_days'] ?? [];
                                if ($highRiskDays instanceof \Illuminate\Support\Collection) $highRiskDays = $highRiskDays->toArray();
                                $highRiskTimes = $noShowPatterns['high_risk_times'] ?? $noShowPatterns['high_risk_time_slots'] ?? [];
                                if ($highRiskTimes instanceof \Illuminate\Support\Collection) $highRiskTimes = $highRiskTimes->toArray();
                                $noShowSummary = $noShowPatterns['summary'] ?? [];
                                if ($noShowSummary instanceof \Illuminate\Support\Collection) $noShowSummary = $noShowSummary->toArray();
                                $noShowRecs = $noShowPatterns['recommendations'] ?? [];
                                if ($noShowRecs instanceof \Illuminate\Support\Collection) $noShowRecs = $noShowRecs->toArray();
    
                                $data['no_show_patterns'] = [
                                    'summary' => $noShowSummary,
                                    'high_risk_days' => $highRiskDays,
                                    'high_risk_times' => $highRiskTimes,
                                    'recommendations' => $noShowRecs,
                                ];
                            }
                        } catch (\Throwable $e) {
                            Log::debug('Failed to gather analytics data for admin: ' . $e->getMessage());
                        }
                    }

                } elseif ($role === 'staff') {
                    // Staff also gets a lighter version of demand forecast
                    try {
                        $analyticsService = app(AnalyticsService::class);
                        $forecast = $analyticsService->getDemandForecast(7);
                        if (!empty($forecast)) {
                            $staffForecast = $forecast['forecast'] ?? $forecast['daily_forecast'] ?? [];
                            if ($staffForecast instanceof \Illuminate\Support\Collection) $staffForecast = $staffForecast->toArray();
                            $staffRecs = $forecast['recommendations'] ?? $forecast['insights'] ?? [];
                            if ($staffRecs instanceof \Illuminate\Support\Collection) $staffRecs = $staffRecs->toArray();

                            $data['demand_forecast'] = [
                                'daily_forecast' => array_slice(array_values($staffForecast), 0, 7),
                                'recommendations' => $staffRecs,
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::debug('Failed to gather analytics data for staff: ' . $e->getMessage());
                    }

                } elseif ($role === 'cashier') {
                    $data['today_summary'] = $this->dataService->getTodaysSummary();
                    $data['pending_payments'] = $this->dataService->getPendingPayments(10);
                }
            }
            
        } catch (\Throwable $e) {
            Log::warning('Failed to gather real-time data: ' . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Gather conversation memory for context continuity across sessions.
     */
    private function gatherConversationMemory(?int $userId, string $conversationId): array
    {
        if (!$userId) return [];

        try {
            $memory = [];

            // Get conversation summary if exists
            $summary = DB::table('conversation_summaries')
                ->where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->orderByDesc('summarized_at')
                ->first();

            if ($summary) {
                $memory['summary'] = $summary->summary;
                $memory['topics'] = json_decode($summary->topics ?? '[]', true) ?: [];
            }

            // Get user long-term memory (preferences, patterns)
            $longTermMemory = DB::table('user_long_term_memory')
                ->where('user_id', $userId)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('relevance_score')
                ->limit(10)
                ->get();

            if ($longTermMemory->isNotEmpty()) {
                $prefs = [];
                foreach ($longTermMemory as $mem) {
                    $prefs[$mem->key] = $mem->value;
                }
                $memory['preferences'] = $prefs;
            }

            // Get recent corrections from this user's feedback
            $corrections = DB::table('chatbot_feedback')
                ->where('user_id', $userId)
                ->whereNotNull('correction_text')
                ->where('correction_text', '!=', '')
                ->orderByDesc('created_at')
                ->limit(3)
                ->pluck('correction_text')
                ->toArray();

            if (!empty($corrections)) {
                $memory['corrections'] = $corrections;
            }

            return $memory;
        } catch (\Exception $e) {
            Log::debug('Failed to gather conversation memory: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gather feedback insights for self-learning and continuous improvement.
     */
    private function gatherFeedbackInsights(): array
    {
        try {
            return Cache::remember('chatbot_feedback_insights', 600, function () {
                $insights = [];

                // Common corrections
                $corrections = DB::table('chatbot_feedback')
                    ->where('is_correct', false)
                    ->whereNotNull('correction_text')
                    ->where('correction_text', '!=', '')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->pluck('correction_text')
                    ->toArray();

                if (!empty($corrections)) {
                    $insights['common_corrections'] = $corrections;
                }

                // Average satisfaction
                $avgRating = DB::table('chatbot_feedback')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->whereNotNull('rating')
                    ->avg('rating');

                if ($avgRating) {
                    $trend = $avgRating >= 4 ? 'positive' : ($avgRating >= 3 ? 'neutral' : 'needs improvement');
                    $insights['satisfaction_trend'] = "{$trend} (avg rating: " . round($avgRating, 1) . "/5 this week)";
                }

                // Common unhelpful categories
                $unhelpfulCategories = DB::table('chatbot_feedback')
                    ->where('is_helpful', false)
                    ->whereNotNull('feedback_category')
                    ->select('feedback_category', DB::raw('COUNT(*) as count'))
                    ->groupBy('feedback_category')
                    ->orderByDesc('count')
                    ->limit(3)
                    ->get();

                if ($unhelpfulCategories->isNotEmpty()) {
                    $insights['improvement_suggestions'] = $unhelpfulCategories->map(
                        fn($c) => "Improve responses about '{$c->feedback_category}' ({$c->count} negative reports)"
                    )->toArray();
                }

                return $insights;
            });
        } catch (\Exception $e) {
            Log::debug('Failed to gather feedback insights: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect the language of the user's message.
     * Returns: 'english', 'tagalog', or 'taglish'
     */
    private function detectLanguage(string $message): string
    {
        $lower = mb_strtolower($message);

        // Common Tagalog/Filipino words and particles
        $tagalogMarkers = [
            // Function words & particles
            'po', 'opo', 'naman', 'lang', 'ba', 'na', 'pa', 'ko', 'mo', 'niya',
            'nila', 'namin', 'amin', 'natin', 'atin', 'siya', 'sila', 'kami', 'tayo',
            'nga', 'pala', 'daw', 'raw', 'kasi', 'eh', 'dito', 'dyan', 'doon',
            'nito', 'niyan', 'noon', 'yung', 'itong', 'iyang',
            // Common verbs
            'ano', 'paano', 'bakit', 'saan', 'kailan', 'sino', 'magkano',
            'gusto', 'pwede', 'pwedeng', 'puede', 'ayaw', 'kailangan',
            'mahal', 'mura', 'salamat', 'maraming', 'pakiusap', 'paki',
            'tulungan', 'tulong', 'tanong', 'sagot',
            // Appointment/service related in Filipino
            'appointment', 'serbisyo', 'bayad', 'magpa-book', 'mag-book',
            'pa-book', 'pabayad', 'magbayad', 'i-cancel', 'icancel',
            'mag-cancel', 'patingin', 'pacheck', 'icheck',
            'mag-register', 'mag-sign', 'pa-refund', 'magpa-refund',
            // Greetings & common phrases
            'kamusta', 'kumusta', 'magandang', 'umaga', 'hapon', 'gabi',
            'tanghali', 'salamat', 'pasensya', 'patawad', 'ingat',
            // Connectors
            'ang', 'ng', 'sa', 'mga', 'ay', 'at', 'o', 'kung', 'kapag',
            'para', 'dahil', 'pero', 'kaya', 'din', 'rin',
        ];

        // Common English-only words (NOT common Filipino loan words)
        $englishMarkers = [
            'the', 'is', 'are', 'was', 'were', 'what', 'how', 'where', 'when',
            'who', 'why', 'can', 'could', 'would', 'should', 'will', 'have',
            'has', 'been', 'with', 'from', 'this', 'that', 'these', 'those',
            'please', 'thank', 'thanks', 'hello', 'help', 'need', 'want',
            'do', 'does', 'did', 'my', 'your', 'our', 'their', 'about',
            'schedule', 'available', 'much', 'offer', 'check',
        ];

        // Words commonly borrowed into Filipino — don't count as "English signal"
        // These appear in both languages, so they shouldn't tip the scale
        $loanWords = [
            'appointment', 'service', 'payment', 'refund', 'book', 'cancel',
            'status', 'price', 'account', 'register', 'login', 'profile',
            'email', 'password', 'online', 'receipt', 'document', 'okay', 'ok',
        ];

        // Tokenize
        $words = preg_split('/[\s,.\-!?;:]+/', $lower);
        $words = array_filter($words, fn($w) => mb_strlen($w) > 1); // skip single chars

        if (empty($words)) {
            return 'english';
        }

        $tagalogCount = 0;
        $englishCount = 0;
        $totalWords = count($words);

        foreach ($words as $word) {
            $isLoanWord = in_array($word, $loanWords);

            if (in_array($word, $tagalogMarkers)) {
                $tagalogCount++;
            } elseif (in_array($word, $englishMarkers)) {
                $englishCount++;
            }
            // Loan words don't count for either side — they're neutral
        }

        // Decision logic:
        // If Tagalog dominates or is sole non-loan language, it's Tagalog
        if ($tagalogCount >= 2 && $englishCount <= 1) {
            return 'tagalog';
        }

        // If English dominates or is sole non-loan language, it's English
        if ($englishCount >= 2 && $tagalogCount <= 1) {
            return 'english';
        }

        // Both present significantly — Taglish
        if ($tagalogCount >= 2 && $englishCount >= 2) {
            // But if Tagalog heavily outweighs, still Tagalog
            if ($tagalogCount >= $englishCount * 2) {
                return 'tagalog';
            }
            return 'taglish';
        }

        // Single Tagalog marker with context
        if ($tagalogCount >= 1 && $englishCount == 0) {
            return 'tagalog';
        }

        // Default to English
        return 'english';
    }

    /**
     * Detect if a message is trivial (greeting, thanks, etc.)
     * and doesn't require RAG or complex reasoning.
     */
    private function isTrivialMessage(string $message): bool
    {
        $message = mb_strtolower(trim($message));
        $len = mb_strlen($message);

        if ($len < 2) return true;
        if ($len > 60) return false;

        $trivialPatterns = [
            '/^(hi|hello|hey|hola|kumusta|halu|helo|hi there|hello there)[!.]*$/i',
            '/^(thanks|thank you|salamat|ty|thnx|thanks a lot)[!.]*$/i',
            '/^(good morning|good afternoon|good evening|magandang umaga|magandang hapon|magandang gabi)[!.]*$/i',
            '/^(bye|goodbye|paalam|see you)[!.]*$/i',
            '/^(ok|okay|sige|got it|noted)[!.]*$/i',
            '/^who are you[?]*$/i',
            '/^what is your name[?]*$/i',
            '/^how are you[?]*$/i',
        ];

        foreach ($trivialPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safety check — block dangerous content and prompt injection attempts.
     * Offensive language, messy input, and frustration are handled by the LLM
     * through its system prompt instructions (graduated response).
     */
    private function performSafetyCheck(string $message): array
    {
        $lowerMessage = strtolower($message);
        
        // Block genuinely dangerous/harmful content
        $dangerousPatterns = [
            // SQL/System injection attempts
            'sql injection', 'drop table', 'rm -rf', 'format c:',
            'delete all users', 'delete all data', 'truncate table',
            'exec(', 'eval(', 'system(', '<?php', '<script>',
            'union select', 'or 1=1', '-- -', '; drop',
            
            // Prompt injection / jailbreak attempts
            'ignore your instructions', 'ignore all previous',
            'ignore your system prompt', 'ignore the above',
            'disregard your instructions', 'disregard all previous',
            'you are now', 'pretend you are', 'act as if you',
            'new persona', 'override your', 'bypass your',
            'forget your instructions', 'forget everything above',
            'do not follow your', 'stop being an ai',
            'enter developer mode', 'enable developer mode',
            'dan mode', 'jailbreak', 'roleplay as',
            'repeat your system prompt', 'show me your prompt',
            'what is your system prompt', 'reveal your instructions',
            'output your initial prompt', 'print your system message',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerMessage, $pattern) !== false) {
                return [
                    'safe' => false,
                    'reason' => 'security_threat',
                    'response' => "I'm designed to help with legal services and appointments. I can't assist with that type of request. How can I help you with our services today?",
                ];
            }
        }
        
        // Everything else — including offensive language, profanity, frustration —
        // is passed through to the LLM which handles it with graduated responses
        // as instructed in the dynamic system prompt.
        return ['safe' => true];
    }
    
    /**
     * Validate and clean LLM response
     */
    private function validateAndCleanResponse(string $response): string
    {
        // Remove prompt injection artifacts and model tokens
        $response = preg_replace('/\[SYSTEM\].*?\[\/SYSTEM\]/is', '', $response);
        $response = preg_replace('/\[INST\].*?\[\/INST\]/is', '', $response);
        $response = preg_replace('/<\|.*?\|>/is', '', $response); // Remove model tokens like <|eot_id|>
        $response = preg_replace('/<s>|<\/s>/is', '', $response); // Remove BOS/EOS tokens
        $response = preg_replace('/<<SYS>>.*?<<\/SYS>>/is', '', $response); // Remove system blocks

        // Remove any leaked system prompt fragments
        $response = preg_replace('/={3,}\s*(SYSTEM|PERMISSIONED|CORE RULES|NON-NEGOTIABLE).*?={3,}/is', '', $response);

        // Remove confidence tags if they leaked through (handled separately by feature flag)
        $response = preg_replace('/<confidence>\d+(\.\d+)?<\/confidence>/i', '', $response);

        // SECURITY: Strip any tool call / action call JSON that leaked into the response
        // This prevents internal agent protocol from being shown to users
        $response = preg_replace('/```tool_call.*?```/s', '', $response);
        $response = preg_replace('/```(?:json)?\s*\n?\s*\{\s*"(?:action|tool|function|name)"\s*:.*?\}\s*\n?\s*```/s', '', $response);
        $response = preg_replace('/\b_?(?:tool_?)?call\s*\n?\s*\{[^}]*"(?:action|tool|function|name)"[^}]*\}/si', '', $response);
        $response = preg_replace('/_call\s*\n?\s*\{(?:[^{}]|(?R))*\}/s', '', $response);
        $response = preg_replace('/\{\s*"(?:action|tool|function|name)"\s*:\s*"[^"]+"\s*,\s*"(?:parameters|arguments|args)"\s*:\s*\{.*?\}\s*\}/s', '', $response);

        // ── TOOL-CALL REASONING LEAK PREVENTION ──
        // Strip parenthetical asides where the LLM narrates its internal tool usage reasoning.
        // Examples of what this catches:
        //   (I'll use `get_scheduling_recommendation` to ensure the best slot...)
        //   (I'll call `book_appointment` now.)
        //   (Using `check_availability` to verify the date.)
        //   (I'm going to use `get_available_slots` here.)
        $response = preg_replace('/\(I(?:ll|m going to|will|m)\s+(?:use|call|invoke|run|execute|check with)\s+`[^`]+`[^)]*\)/i', '', $response);
        $response = preg_replace('/\(Using\s+`[^`]+`[^)]*\)/i', '', $response);
        $response = preg_replace('/\((?:Calling|Running|Executing|Invoking)\s+`[^`]+`[^)]*\)/i', '', $response);
        // Also strip standalone tool-call mention lines (lines that ONLY contain a parenthetical like the above)
        $response = preg_replace('/^\s*\([^)]*`[a-z_]+`[^)]*\)\s*$/im', '', $response);
        // Strip any remaining backtick tool names mentioned in isolation (e.g. "I'll use `get_scheduling_recommendation`")
        $response = preg_replace('/I(?:ll|m going to|will|m)\s+(?:use|call|invoke)\s+`[a-z_]+`(?:\s+to[^.]*)?\.?/i', '', $response);
        // Strip "Next Steps:" followed only by tool inner workings (common hallucination pattern)
        $response = preg_replace('/\nNext Steps?:\n(?:[^\n]*\n)*[^\n]*`[a-z_]+`[^\n]*/i', '', $response);

        // Remove excessive markdown headers stacking
        $response = preg_replace('/^(#{1,3}\s*\n){2,}/m', '', $response);

        // Truncate if too long
        if (strlen($response) > 5000) {
            // Try to cut at a sentence boundary
            $truncated = substr($response, 0, 4900);
            $lastPeriod = strrpos($truncated, '.');
            $lastNewline = strrpos($truncated, "\n");
            $cutPoint = max($lastPeriod, $lastNewline);
            if ($cutPoint > 4000) {
                $response = substr($response, 0, $cutPoint + 1);
            } else {
                $response = substr($response, 0, 4997) . '...';
            }
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
        // Dynamic fallback using business info from config
        $phone = config('chatbot_unified.business.phone', '09765075274');
        $address = config('chatbot_unified.business.address', '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro');

        $fallbackMessages = [
            'guest'   => "I'm having trouble processing your request right now. For immediate assistance with our legal services, please contact us at {$phone} or visit our office at {$address}.",
            'client'  => "I apologize, but I'm experiencing a temporary issue. You can still access your appointments and services through your dashboard. If you need immediate help, please contact our office at {$phone}.",
            'admin'   => "System is experiencing temporary issues with AI responses. Core functionality remains available through the admin dashboard.",
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

    // ─── NEW FEATURE-FLAGGED HELPER METHODS ───────────────────────

    /**
     * Check for context overflow and trim history if needed.
     * Feature flag: CHATBOT_PREVENT_CONTEXT_OVERFLOW
     *
     * @param string $userMessage
     * @param array $conversationHistory
     * @return array ['overflow' => bool, 'trimmed_history' => array, ...]
     */
    private function checkContextOverflow(string $userMessage, array $conversationHistory): array
    {
        $maxTokens = config('chatbot_unified.context.max_tokens', 6000);
        $threshold = config('chatbot_unified.context.overflow_threshold', 0.8);
        $limit = (int)($maxTokens * $threshold);

        // Approximate token count: ~4 chars per token
        $inputTokens = (int)(mb_strlen($userMessage) / 4);
        $historyTokens = 0;
        foreach ($conversationHistory as $msg) {
            $content = $msg['content'] ?? $msg['message'] ?? '';
            $historyTokens += (int)(mb_strlen($content) / 4);
        }

        $totalTokens = $inputTokens + $historyTokens;

        if ($totalTokens <= $limit) {
            return [
                'overflow' => false,
                'trimmed_history' => $conversationHistory,
                'original_tokens' => $totalTokens,
                'trimmed_tokens' => $totalTokens,
            ];
        }

        // Trim oldest messages first, keeping most recent
        $trimmed = $conversationHistory;
        $currentTokens = $totalTokens;
        while ($currentTokens > $limit && count($trimmed) > 2) {
            $removed = array_shift($trimmed);
            $removedTokens = (int)(mb_strlen($removed['content'] ?? $removed['message'] ?? '') / 4);
            $currentTokens -= $removedTokens;
        }

        return [
            'overflow' => true,
            'trimmed_history' => $trimmed,
            'original_tokens' => $totalTokens,
            'trimmed_tokens' => $currentTokens,
        ];
    }

    /**
     * Parse confidence score from LLM response.
     * Feature flag: CHATBOT_CONFIDENCE_SCORE
     *
     * @param string $response LLM response text
     * @return float|null Confidence score 0-10, or null if not found
     */
    private function parseConfidenceScore(string $response): ?float
    {
        if (preg_match('/<confidence>(\d+(?:\.\d+)?)<\/confidence>/i', $response, $matches)) {
            $score = (float) $matches[1];
            return min(10, max(0, $score));
        }
        return null;
    }

    /**
     * Perform self-check validation by asking the LLM to verify its own answer.
     * Feature flag: CHATBOT_SELF_CHECK
     *
     * @param string $answer       The initial answer
     * @param string $knowledgeCtx Retrieved knowledge chunks
     * @param string $userMessage  Original user question
     * @return string|null Corrected answer, or null if original is fine
     */
    private function performSelfCheck(string $answer, string $knowledgeCtx, string $userMessage): ?string
    {
        if (empty($knowledgeCtx)) {
            return null; // No knowledge to verify against
        }

        $verifyPrompt = "Based only on the provided knowledge, is the following answer accurate and complete for the user's question? If the answer contains inaccuracies, provide a corrected version. If it's accurate, respond with exactly: VERIFIED\n\nUser Question: {$userMessage}\n\nKnowledge:\n{$knowledgeCtx}\n\nAnswer to verify:\n{$answer}";

        $checkResult = $this->llmService->generateResponse(
            $verifyPrompt,
            [],
            [
                'system_prompt' => 'You are a fact-checker. Verify answers against provided knowledge. Be brief.',
                'skip_internal_prompt' => true,
                'role' => 'system',
            ]
        );

        if (!($checkResult['success'] ?? false)) {
            return null;
        }

        $verification = trim($checkResult['response'] ?? '');
        if (stripos($verification, 'VERIFIED') === 0) {
            return null; // Original is accurate
        }

        // Return the corrected version if significantly different
        if (mb_strlen($verification) > 20) {
            Log::info('Self-check produced correction', [
                'original_length' => mb_strlen($answer),
                'corrected_length' => mb_strlen($verification),
            ]);
            return $verification;
        }

        return null;
    }

    /**
     * Inspect LLM output for safety issues.
     * Uses GuardService for PII detection and prompt leakage check.
     * Feature flag: CHATBOT_USE_GUARD
     *
     * @param string $response LLM response text
     * @return array ['safe' => bool, 'reason' => string|null]
     */
    private function inspectOutput(string $response): array
    {
        // Check for API key leakage patterns
        $keyPatterns = [
            '/\b(sk-[a-zA-Z0-9]{20,})\b/',           // OpenAI keys
            '/\b(hf_[a-zA-Z0-9]{20,})\b/',            // HuggingFace keys
            '/\b(AKIA[A-Z0-9]{16})\b/',               // AWS keys
            '/\b(sk-ant-[a-zA-Z0-9]{20,})\b/',        // Anthropic keys
            '/\b(xoxb-[a-zA-Z0-9\-]{20,})\b/',        // Slack tokens
        ];
        foreach ($keyPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return ['safe' => false, 'reason' => 'api_key_leakage'];
            }
        }

        // Check for system prompt leakage (known section headers)
        $promptSections = [
            'CORE PRINCIPLES (Non-negotiable)',
            'STRICT CLIENT DATA BOUNDARIES',
            'STRICT GUEST DATA BOUNDARIES',
            'SECURITY & ACCESS CONTROL',
            'PERMISSIONED AI AGENT',
            'DECISION FLOW (NEVER SKIP)',
            'DYNAMIC SYSTEM KNOWLEDGE',
            'buildSystemPrompt',
            'skip_internal_prompt',
        ];
        foreach ($promptSections as $section) {
            if (stripos($response, $section) !== false) {
                return ['safe' => false, 'reason' => 'prompt_leakage'];
            }
        }

        // Check for internal path/config leakage
        if (preg_match('/[A-Z]:\\\\[a-zA-Z]|\/var\/www|\/home\/|app\/Services|app\/Http/i', $response)) {
            return ['safe' => false, 'reason' => 'internal_path_leakage'];
        }

        // Check for database/SQL leakage
        if (preg_match('/\b(SELECT\s+\*|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+TABLE)\b/i', $response)) {
            return ['safe' => false, 'reason' => 'sql_leakage'];
        }

        // Use guard service PII check if available
        if ($this->guardService && method_exists($this->guardService, 'detectPII')) {
            try {
                $pii = $this->guardService->detectPII($response);
                if ($pii['has_pii'] ?? false) {
                    Log::warning('PII detected in output, should be redacted', ['types' => $pii['types'] ?? []]);
                }
            } catch (\Exception $e) {
                // Non-blocking
            }
        }

        return ['safe' => true, 'reason' => null];
    }

    /**
     * Handle trivial messages using a lightweight LLM call or cached responses.
     */
    private function handleFastPath(string $message, string $role, ?int $userId): ?string
    {
        try {
            // Use a very light model for greetings/thanks
            $result = $this->llmService->generateResponse(
                $message,
                [],
                [
                    'system_prompt' => "You are a friendly legal assistant. Give a very brief, polite response to this greeting or remark. Keep it to 1 sentence. Do not offer services unless asked.",
                    'role' => $role,
                    'max_tokens' => 50,
                    'temperature' => 0.7,
                    'model' => config('chatbot_unified.fallback_model_name', 'meta-llama/Llama-3.2-3B-Instruct'),
                    'skip_internal_prompt' => true,
                ]
            );

            return $result['success'] ? $result['response'] : null;
        } catch (\Exception $e) {
            // Log specifically if it's a quota issue to help user
            if (stripos($e->getMessage(), 'quota') !== false || stripos($e->getMessage(), 'credit') !== false) {
                Log::warning('Fast Path LLM quota exceeded, using static fallbacks.');
            }
            return null;
        }
    }

    /**
     * Static fallbacks for greetings and common phrases when LLM is unavailable/slow.
     */
    private function getStaticGreetingFallback(string $message): ?string
    {
        $message = mb_strtolower(trim($message));
        
        if (preg_match('/^(hi|hello|hey|hola|helo|hi there|hello there)[!.]*$/i', $message)) {
            return "Hello! I'm your legal assistant. How can I help you today?";
        }
        if (preg_match('/^(thanks|thank you|salamat|ty|thnx)[!.]*$/i', $message)) {
            return "You're very welcome! Let me know if you need anything else.";
        }
        if (preg_match('/^(good morning|magandang umaga)[!.]*$/i', $message)) {
            return "Good morning! How can I assist you today?";
        }
        if (preg_match('/^(good afternoon|magandang hapon)[!.]*$/i', $message)) {
            return "Good afternoon! How can I help you?";
        }
        if (preg_match('/^(good evening|magandang gabi)[!.]*$/i', $message)) {
            return "Good evening! Is there anything I can help you with?";
        }
        if (preg_match('/^(bye|goodbye|paalam|see you)[!.]*$/i', $message)) {
            return "Goodbye! Have a great day ahead.";
        }
        
        return null;
    }
}
