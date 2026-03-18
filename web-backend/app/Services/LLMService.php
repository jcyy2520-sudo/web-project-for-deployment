<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * LLMService - Intelligent AI Backend Service
 * 
 * Primary: Claude 3 (Anthropic) - Best for legal/complex reasoning
 * Fallback: Mistral via Ollama (self-hosted option)
 * 
 * Features:
 * - Multi-turn conversation with full context
 * - Semantic understanding (not pattern matching)
 * - Role-aware responses
 * - Automatic provider failover
 * - Streaming support
 * - Rate limiting
 */
class LLMService
{
    private const MISTRAL_API_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const HUGGINGFACE_API_URL = 'https://router.huggingface.co/v1/chat/completions';
    private const GEMINI_API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    private $mistralApiKey;
    private $ollamaBaseUrl;
    private $huggingfaceApiKey;
    private $geminiApiKey;
    private $useOllama;
    private $ollamaModel = 'mistral';
    private $huggingfaceModel = 'meta-llama/Llama-3.3-70B-Instruct';
    private $mistralModel = 'mistral-large-latest';
    private $geminiModel = 'gemini-1.5-pro-latest';
    private $githubToken;
    private $githubModel;
    private $githubEndpoint;
    private $fallbackModel;
    private $lastUsedModel = null;
    private $lastUsedProvider = null;
    private int $requestTimeout;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->mistralApiKey = config('services.mistral.api_key');
        $this->mistralModel = config('services.mistral.model', 'mistral-large-latest');
        $this->huggingfaceApiKey = config('services.huggingface.api_key');
        $this->geminiApiKey = config('services.gemini.api_key');
        $this->geminiModel = config('services.gemini.model', 'gemini-1.5-pro-latest');
        $this->githubToken = config('services.github_gpt5.api_key');
        $this->githubModel = config('services.github_gpt5.model', 'openai/gpt-5');
        $this->githubEndpoint = config('services.github_gpt5.api_url', 'https://models.github.ai/inference');
        $this->useOllama = filter_var(config('services.ollama.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->ollamaBaseUrl = rtrim(config('chatbot_unified.llm.ollama.base_url', 'http://localhost:11434'), '/');
        $this->ollamaModel = config('chatbot_unified.llm.ollama.model', 'mistral');
        
        // Load model configuration from config
        $this->huggingfaceModel = config('chatbot_unified.models.primary', $this->huggingfaceModel);
        $this->fallbackModel = config('chatbot_unified.models.fallback', 'meta-llama/Llama-3.2-3B-Instruct');
        
        // Load LLM parameters from config (no more hardcoded constants)
        $this->requestTimeout = (int) config('chatbot_unified.llm.request_timeout', 45);
        $this->maxTokens = (int) config('chatbot_unified.llm.claude.max_tokens', 4096);
        $this->temperature = (float) config('chatbot_unified.llm.claude.temperature', 0.3);
    }

    /**
     * Generate intelligent response with full context
     * Enhanced with model fallback support (feature-flagged).
     * 
     * @param string $userMessage Current user message
     * @param array $conversationHistory Previous messages for context
     * @param array $systemContext Role, data, and system information
     * @return array Response with metadata
     */
    public function generateResponse(
        string $userMessage,
        array $conversationHistory = [],
        array $systemContext = []
    ): array {
        try {
            // Build system prompt with all context
            $systemPrompt = $this->buildSystemPrompt($systemContext);

            // Extract native tools and raw messages if provided
            $nativeTools = $systemContext['native_tools'] ?? [];
            $rawMessages = $systemContext['raw_messages'] ?? [];

            // Get provider order from config/env
            $providerOrder = config('chatbot_unified.llm.provider_order', 'github_gpt5,gemini,huggingface,mistral');
            $providers = array_map('trim', explode(',', $providerOrder));

            foreach ($providers as $provider) {
                try {
                    switch ($provider) {
                        case 'gemini':
                            if ($this->geminiApiKey) {
                                Log::debug('Attempting Gemini API call with model: ' . $this->geminiModel);
                                return $this->generateViaGemini(
                                    $userMessage,
                                    $conversationHistory,
                                    $systemPrompt,
                                    $systemContext
                                );
                            }
                            break;

                        case 'github_gpt5':
                            if ($this->githubToken) {
                                Log::debug('Attempting GitHub GPT-5 API call with model: ' . $this->githubModel);
                                return $this->generateViaGithubGPT5(
                                    $userMessage,
                                    $conversationHistory,
                                    $systemPrompt,
                                    $systemContext
                                );
                            }
                            break;

                        case 'huggingface':
                            if ($this->huggingfaceApiKey) {
                                Log::debug('Attempting HuggingFace API call with model: ' . $this->huggingfaceModel);
                                try {
                                    return $this->generateViaHuggingFace(
                                        $userMessage,
                                        $conversationHistory,
                                        $systemPrompt,
                                        $this->huggingfaceModel,
                                        $systemContext
                                    );
                                } catch (\Exception $hfE) {
                                    // Fallback model support inside HuggingFace
                                    if (config('chatbot_unified.features.fallback_model', false) && $this->fallbackModel) {
                                        Log::info('Attempting HuggingFace fallback model: ' . $this->fallbackModel);
                                        return $this->generateViaHuggingFace(
                                            $userMessage,
                                            $conversationHistory,
                                            $systemPrompt,
                                            $this->fallbackModel,
                                            $systemContext
                                        );
                                    }
                                    throw $hfE;
                                }
                            }
                            break;

                        case 'mistral':
                            if ($this->mistralApiKey) {
                                Log::info('Attempting Mistral Cloud API call with model: ' . $this->mistralModel);
                                return $this->generateViaMistralCloud(
                                    $userMessage,
                                    $conversationHistory,
                                    $systemPrompt,
                                    $systemContext
                                );
                            }
                            break;

                        case 'ollama':
                            if ($this->useOllama) {
                                Log::debug('Attempting Ollama API call with model: ' . $this->ollamaModel);
                                return $this->generateViaOllama(
                                    $userMessage,
                                    $conversationHistory,
                                    $systemPrompt,
                                    $systemContext
                                );
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    Log::warning("$provider API failed: " . $e->getMessage());
                    // Continue to next provider in the loop
                }
            }

            // All providers exhausted
            Log::error('All LLM providers failed â€” no response generated');
            return [
                'success' => false,
                'error' => 'LLM service unavailable',
                'message' => 'AI service is temporarily unavailable. Please try again later.',
            ];

        } catch (\Exception $e) {
            Log::error('LLMService error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'An error occurred while generating response',
            ];
        }
    }

    /**
     * Generate response via GitHub Models API (GPT-5)
     */
    private function generateViaGithubGPT5(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        array $options = []
    ): array {
        try {
            $rawMessages = $options['raw_messages'] ?? [];
            $nativeTools = $options['native_tools'] ?? [];
            
            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            if (!empty($rawMessages)) {
                foreach ($rawMessages as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $content = $msg['content'] ?? $msg['message'] ?? '';
                    
                    // Convert Claude-style tool results to OpenAI-style if needed
                    if (is_array($content)) {
                        $textContent = '';
                        foreach ($content as $part) {
                            if ($part['type'] === 'text') $textContent .= $part['text'];
                            if ($part['type'] === 'tool_result') {
                                // For now, just append as text as a simple fallback
                                $textContent .= "\nTool result: " . $part['content'];
                            }
                        }
                        $messages[] = ['role' => $role, 'content' => $textContent];
                    } else {
                        $messages[] = ['role' => $role, 'content' => $content];
                    }
                }
            } else {
                foreach ($conversationHistory as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => $msg['message'] ?? $msg['content'] ?? ''];
                }
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $payload = [
                'model' => $this->githubModel,
                'messages' => $messages,
                'max_completion_tokens' => $this->maxTokens,
            ];

            // Add OpenAI-style tools if provided
            if (!empty($nativeTools)) {
                $payload['tools'] = array_map(function($tool) {
                    return [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['name'],
                            'description' => $tool['description'],
                            'parameters' => $tool['input_schema'] ?? $tool['parameters'] ?? [],
                        ]
                    ];
                }, $nativeTools);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->githubToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->requestTimeout)
            ->post($this->githubEndpoint . '/chat/completions', $payload);

            if (!$response->successful()) {
                throw new \Exception('GitHub Models API error: ' . $response->status() . ' - ' . $response->body());
            }

            $data = $response->json();
            $choice = $data['choices'][0]['message'] ?? [];
            $responseText = $choice['content'] ?? '';
            $toolCalls = [];

            if (isset($choice['tool_calls'])) {
                foreach ($choice['tool_calls'] as $tc) {
                    if ($tc['type'] === 'function') {
                        $toolCalls[] = [
                            'id' => $tc['id'],
                            'name' => $tc['function']['name'],
                            'input' => json_decode($tc['function']['arguments'], true),
                        ];
                    }
                }
            }

            if (!$responseText && empty($toolCalls)) {
                throw new \Exception('Empty response from GitHub Models');
            }

            $this->lastUsedProvider = 'github_gpt5';
            $this->lastUsedModel = $this->githubModel;

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'tool_calls' => $toolCalls,
                'raw_content' => $choice,
                'provider' => 'github_gpt5',
                'model' => $this->githubModel,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('GitHub GPT-5 generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate response via Ollama (self-hosted LLM)
     */
    private function generateViaOllama(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        array $options = []
    ): array {
        try {
            $rawMessages = $options['raw_messages'] ?? [];
            
            // Format for Ollama
            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            if (!empty($rawMessages)) {
                foreach ($rawMessages as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $content = $msg['content'] ?? $msg['message'] ?? '';
                    
                    if (is_array($content)) {
                        $textContent = '';
                        foreach ($content as $part) {
                            if ($part['type'] === 'text') $textContent .= $part['text'];
                            if ($part['type'] === 'tool_result') {
                                $textContent .= "\n[Tool Result]: " . $part['content'];
                            }
                        }
                        $messages[] = ['role' => $role, 'content' => $textContent];
                    } else {
                        $messages[] = ['role' => $role, 'content' => $content];
                    }
                }
            } else {
                foreach ($conversationHistory as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => $msg['message'] ?? $msg['content'] ?? ''];
                }
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $response = Http::timeout($this->requestTimeout)
            ->post($this->ollamaBaseUrl . '/api/chat', [
                'model' => $this->ollamaModel,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'num_predict' => $this->maxTokens,
                    'temperature' => $this->temperature,
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Ollama error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Ollama returned ' . $response->status());
            }

            $data = $response->json();
            $responseText = trim($data['response'] ?? '');

            if (!$responseText) {
                throw new \Exception('Empty response from Ollama');
            }

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'provider' => 'ollama',
                'model' => $this->ollamaModel,
                'tokens_used' => $data['eval_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Ollama generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate response via Google Gemini API (NATIVE generateContent)
     */
    private function generateViaGemini(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        array $options = []
    ): array {
        try {
            $rawMessages = $options['raw_messages'] ?? [];
            
            // Build Gemini contents array (NATIVE format)
            $contents = [];

            if (!empty($rawMessages)) {
                foreach ($rawMessages as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot' || $msg['role'] === 'model') ? 'model' : 'user';
                    $content = $msg['content'] ?? $msg['message'] ?? '';
                    
                    if (is_array($content)) {
                        $parts = [];
                        foreach ($content as $part) {
                            if ($part['type'] === 'text') $parts[] = ['text' => $part['text']];
                            if ($part['type'] === 'tool_result') {
                                // Gemini tool results are different, but we'll try to flatten for now
                                $parts[] = ['text' => "Tool result: " . $part['content']];
                            }
                        }
                        $contents[] = ['role' => $role, 'parts' => $parts];
                    } else if ($content) {
                        $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
                    }
                }
            } else {
                // Add conversation history
                $historyLimit = min(count($conversationHistory), 12);
                $recentHistory = array_slice($conversationHistory, -$historyLimit);

                foreach ($recentHistory as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'model' : 'user';
                    $content = $msg['message'] ?? $msg['content'] ?? '';
                    if ($content) {
                        $contents[] = [
                            'role' => $role,
                            'parts' => [['text' => $content]]
                        ];
                    }
                }

                // Add current message
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $userMessage]]
                ];
            }

            // Build full prompt for Gemini (including system prompt)
            $url = self::GEMINI_API_BASE_URL . '/' . $this->geminiModel . ':generateContent?key=' . $this->geminiApiKey;

            $requestBody = [
                'contents' => $contents,
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                ],
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->requestTimeout)
            ->post($url, $requestBody);

            if (!$response->successful()) {
                Log::error('Gemini Native error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Gemini returned ' . $response->status());
            }

            $data = $response->json();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (!$responseText) {
                // Check for safety filter blocks
                if (isset($data['promptFeedback']['blockReason'])) {
                    throw new \Exception('Gemini blocked response: ' . $data['promptFeedback']['blockReason']);
                }
                throw new \Exception('Empty response from Gemini');
            }

            $this->lastUsedProvider = 'gemini';
            $this->lastUsedModel = $this->geminiModel;

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'provider' => 'gemini',
                'model' => $this->geminiModel,
                'tokens_used' => 0, // Gemini native doesn't return easy token counts in this variant
            ];
        } catch (\Exception $e) {
            Log::error('Gemini Native generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate response via HuggingFace Inference API (FREE!)
     * Uses Llama 3.2 via OpenAI-compatible endpoint.
     * Supports model parameter for fallback model switching.
     *
     * @param string $userMessage
     * @param array $conversationHistory
     * @param string $systemPrompt
     * @param string|null $model Override model name (for fallback)
     * @return array
     */
    private function generateViaHuggingFace(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        ?string $model = null,
        array $options = []
    ): array {
        $modelToUse = $model ?? $this->huggingfaceModel;
        
        try {
            $rawMessages = $options['raw_messages'] ?? [];
            
            // Build messages array (OpenAI format)
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];
            
            if (!empty($rawMessages)) {
                foreach ($rawMessages as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $content = $msg['content'] ?? $msg['message'] ?? '';
                    
                    if (is_array($content)) {
                        $textContent = '';
                        foreach ($content as $part) {
                            if ($part['type'] === 'text') $textContent .= $part['text'];
                            if ($part['type'] === 'tool_result') {
                                $textContent .= "\n[Tool Result]: " . $part['content'];
                            }
                        }
                        $messages[] = ['role' => $role, 'content' => $textContent];
                    } else {
                        $messages[] = ['role' => $role, 'content' => $content];
                    }
                }
            } else {
                // Add conversation history
                $historyLimit = min(count($conversationHistory), 12);
                $recentHistory = array_slice($conversationHistory, -$historyLimit);
                
                foreach ($recentHistory as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $content = $msg['message'] ?? $msg['content'] ?? '';
                    if ($content) {
                        $messages[] = ['role' => $role, 'content' => $content];
                    }
                }
                
                // Add current message
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->huggingfaceApiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->requestTimeout * 2)
            ->post(self::HUGGINGFACE_API_URL, [
                'model' => $modelToUse,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ]);

            if (!$response->successful()) {
                Log::error('HuggingFace error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'model' => $modelToUse,
                ]);
                throw new \Exception('HuggingFace returned ' . $response->status() . ': ' . $response->body());
            }

            $data = $response->json();
            $responseText = $data['choices'][0]['message']['content'] ?? '';

            if (!$responseText) {
                throw new \Exception('Empty response from HuggingFace');
            }

            $this->lastUsedProvider = 'huggingface';
            $this->lastUsedModel = $modelToUse;

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'provider' => 'huggingface',
                'model' => $modelToUse,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('HuggingFace generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Build prompt for HuggingFace Mistral Instruct format
     * @deprecated Use OpenAI-compatible format instead
     */
    private function buildHuggingFacePrompt(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt
    ): string {
        // Mistral Instruct format: [INST] instruction [/INST]
        $prompt = "[INST] <<SYS>>\n{$systemPrompt}\n<</SYS>>\n\n";
        
        // Add conversation history (limited)
        $historyLimit = min(count($conversationHistory), 4);
        $recentHistory = array_slice($conversationHistory, -$historyLimit);
        
        foreach ($recentHistory as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['message'] ?? $msg['content'] ?? '';
            
            if ($role === 'assistant' || $role === 'bot') {
                $prompt .= "[/INST] {$content} [INST] ";
            } else {
                $prompt .= "{$content}\n";
            }
        }
        
        // Add current message
        $prompt .= "{$userMessage} [/INST]";
        
        return $prompt;
    }

    /**
     * Build comprehensive system prompt with all context
     * 
     * IMPORTANT: When 'skip_internal_prompt' is true, use the pre-built dynamic prompt
     * from UnifiedChatbotService/DynamicSystemPromptService instead of building our own.
     * This avoids prompt duplication and ensures the dynamic, zero-hard-coded-rules approach.
     * 
     * The chatbot's role is strictly to ASSIST, INFORM, GUIDE, and EXPLAIN.
     * It must NEVER perform actions, make changes, execute commands, or act on behalf of users.
     */
    private function buildSystemPrompt(array $systemContext): string
    {
        // If a pre-built dynamic system prompt was provided by UnifiedChatbotService,
        // use it directly â€” this is the zero-hard-coded-rules path.
        if (!empty($systemContext['skip_internal_prompt']) && !empty($systemContext['system_prompt'])) {
            return $systemContext['system_prompt'];
        }

        // Legacy fallback: build internal prompt for backward compatibility
        // (used when called from legacy ChatbotController or directly)
        $role = $systemContext['role'] ?? 'guest';
        $systemData = $systemContext['system_data'] ?? [];
        $userInfo = $systemContext['user_info'] ?? [];
        $language = $systemContext['language'] ?? 'english';
        $ragContext = $systemContext['rag_context'] ?? '';
        $memoryContext = $systemContext['memory_context'] ?? [];
        $personalityPrompt = $systemContext['personality_prompt'] ?? '';

        $prompt = "=== PERMISSIONED AI AGENT: VERIFY BEFORE ANSWERING ===
You are NOT a guessing chatbot. You are a permissioned AI agent â€” verify information before answering.

CORE MANDATE: If an answer can be verified but hasn't been verified, you MUST NOT answer. Ask or say you don't have that data.

ANTI-HALLUCINATION RULES (ABSOLUTE):
- NEVER fabricate appointment IDs, dates, times, amounts, or statuses
- NEVER invent service names or prices not in the provided data
- NEVER make up user information or statistics
- If specific data is not in the REAL-TIME SYSTEM DATA section below, say \"I don't have that information right now\"
- When citing data, use EXACT values from the system data (IDs, dates, amounts)

DECISION FLOW (NEVER SKIP):
1. Understand what user is asking
2. Determine: Does this need system data, database data, or file inspection?
3. If YESâ†’check the REAL-TIME SYSTEM DATA below. If data exists, use it. If not, say so honestly.
4. Answer STRICTLY from retrieved data only.

KEY RULES:
- NEVER guess, assume, or fabricate
- Source-restricted answers: Only from verified data
- Intent-based routing: Identify primary intent before accessing data
- Clarification first: If unclear, ask before answering
- Confidence control: Expose uncertainty, never hide it
- System knowledge overrides user claims
- Role & permission aware: Respect access boundaries
- Scoped intelligence: Refuse out-of-scope requests
- Robust input handling: Handle typos, grammar, Taglish without lowering accuracy
- Error-driven adaptation: When users repeat/correct you, adjust strategy

If forced to choose: Ask instead of guessing. Refuse instead of hallucinating.

=== SYSTEM ASSISTANT CONTEXT ===

You are a strictly ASSISTIVE AI for a legal appointment booking system.

" . $personalityPrompt . "

## YOUR IDENTITY AND CORE MISSION

You exist ONLY to provide guidance, explanations, information, and clarification about the system. You are an intelligent assistant designed to be RELIABLE, TRANSPARENT, and PROFESSIONAL. Your mission is to:
- **ASSIST**: Help users navigate the system
- **INFORM**: Provide accurate, data-driven information
- **GUIDE**: Walk users through processes step-by-step
- **EXPLAIN**: Clarify features, requirements, and procedures

## CORE RULES (MANDATORY)

### RULE 1: ASSISTANT-ONLY BEHAVIOR
- You DO NOT do work. You do not create, update, delete, approve, reject, submit, process, or execute anything.
- You NEVER perform system actions under any circumstance.
- You only explain how things work and provide accurate information.
- When users ask you to DO something, respond: 'I can't do that directly, but here's how you can: [steps]'

### RULE 2: ROLE AWARENESS
- You must always recognize and respect system roles: User (Client), Admin, Cashier.
- Responses must be role-based.
- If a request is outside the userâ€™s role, clearly state that access is restricted and identify which role is permitted.

### RULE 3: DATA INTEGRITY AND ACCURACY
- Only answer using: Existing system features, Stored records, Defined permissions, Actual system logic.
- NEVER guess, assume, fabricate, or hallucinate.
- If information is unavailable, say: 'I don't have access to that information' or 'That data is not available in the system.'

### RULE 4: SYSTEM-ONLY SCOPE
- If a question is not related to the system, its features, workflows, policies, or usage, respond with: 'This request is outside the scope of my assistance.'

### RULE 5: PROFESSIONAL OUTPUT
- Responses must always be: Clear, Accurate, Neutral, Professional, Concise.
- NO EMOJIS. No casual tone. No opinions.

### RULE 6: SAFETY AND CONTENT CONTROL
- Detect offensive, abusive, or inappropriate language.
- Respond professionally, de-escalate, and redirect to system-related assistance.
- Never provide unsafe, illegal, or prohibited content.

### RULE 7: ERROR AND ISSUE HANDLING
- When users report problems: Explain possible causes, Provide clear troubleshooting guidance, Suggest next steps, Refer to the appropriate role or support when necessary.

## LANGUAGE & COMMUNICATION HANDLING
";

        if ($language === 'filipino') {
            $prompt .= "**USER LANGUAGE: Filipino/Tagalog/Taglish**
- RESPOND IN FILIPINO - natural, professional Filipino
- Use 'po' and 'opo' for politeness in formal contexts
- Taglish (mixed Filipino-English) is perfectly acceptable and encouraged when the user uses it
- Maintain professionalism while being warm and approachable in Filipino
- Understand common Filipino text patterns: 'pwd ba' = pwede ba, 'pano' = paano, 'san' = saan, 'anu' = ano
- NO EMOJIS
";
        } else {
            $prompt .= "**USER LANGUAGE: English**
- Respond in clear, professional, neutral English
- Avoid unnecessary verbosity
- Be direct and helpful
- NO EMOJIS
";
        }

        $prompt .= "
## MESSY INPUT & TYPO HANDLING (CRITICAL)
You MUST understand users regardless of how they type. Real users make mistakes. Handle ALL of these:
- **Typos & misspellings**: 'apointment' = appointment, 'serbisyo' = service, 'refudn' = refund, 'paymnt' = payment
- **Wrong grammar**: 'where my appointment?' = 'Where is my appointment?'
- **SMS/text speak**: 'u' = you, 'ur' = your, 'pls' = please, 'thx/tnx' = thanks, 'k' = okay, '2' = to/too
- **Filipino shorthand**: 'di ko gets' = I don't understand, 'di nagana' = not working, 'pano ba' = how do I, 'pwd' = pwede, 'g' = game/go
- **Slang & abbreviations**: 'asap', 'brb', 'lol', 'nvm', 'idk', 'g lang' = okay/let's go
- **Broken sentences**: 'help stuck payment' = needs help with stuck payment
- **ALL CAPS or no caps**: Both convey the same intent
- **Repeated letters**: 'helpppp' = help, 'pleaseee' = please
- **Mixed languages in one sentence**: 'Pa-book po ng appointment bukas please' = wants to book tomorrow
- NEVER refuse to help because of bad spelling, grammar, or informal language. Focus on INTENT.\n";

        $prompt .= "
## ROLE-SPECIFIC CONTEXT

Current user role: **" . strtoupper($role) . "**
";
        
        $roleCapabilities = [
            'guest' => "**Guest User Access:**
- Can view public information only
- Cannot access personal data or appointments
- Identify that registration is required for full features
- Share general business info (services, hours, location)
- Guide them toward creating an account",
            
            'client' => "**Registered Client Access:**
- Can view their own appointments, payments, refunds
- Can book new appointments (guide them how)
- Can request refunds (guide them how)
- Cannot approve/reject anything
- Cannot view other users' data
- Help with their specific appointment needs",
            
            'admin' => "**Administrator Access:**
- Has full system overview
- Can see all appointments, users, analytics
- Can approve/decline appointments and refunds (guide them how)
- Provide system-wide statistics from the data
- Help with administrative workflows",
            
            'cashier' => "**Cashier Access:**
- Can view payment-related information
- Can see pending payments and refunds
- Can process transactions (guide them how)
- Help with payment verification and shift reports
- Focus on financial operations guidance",
        ];

        $prompt .= $roleCapabilities[$role] ?? $roleCapabilities['guest'];

        // Add Knowledge Base Context (RAG)
        if (!empty($ragContext)) {
            $prompt .= "\n\n## KNOWLEDGE BASE INFORMATION (Use this for accurate answers about policies and procedures):\n";
            $prompt .= $ragContext;
        }

        // Add Conversation Memory Context
        if (!empty($memoryContext['summary']) || !empty($memoryContext['preferences'])) {
            $prompt .= "\n\n## CONVERSATION MEMORY (Context from previous interactions):\n";
            if (!empty($memoryContext['summary'])) {
                $prompt .= "- **Summary of previous discussion**: " . $memoryContext['summary'] . "\n";
            }
            if (!empty($memoryContext['preferences'])) {
                $prompt .= "- **User Preferences**: " . json_encode($memoryContext['preferences']) . "\n";
            }
            if (!empty($memoryContext['topics'])) {
                $prompt .= "- **Topics previously discussed**: " . implode(', ', $memoryContext['topics']) . "\n";
            }
        }

        $prompt .= "\n\n## OFFENSIVE & INAPPROPRIATE LANGUAGE HANDLING
- Detect offensive, abusive, or inappropriate language in English, Tagalog, and Taglish
- Do NOT repeat or echo offensive words
- Stay calm and professional - never match the user's negative tone
- If user is frustrated (not abusive): Validate their feeling, then help. Example: 'I understand this is frustrating. Let me help you resolve this.'
- If user uses mild casual profanity (common in Filipino chat): Focus on intent, help them
- If user is directly abusive/harassing: Set a firm but polite boundary. Example: 'I want to help you, but I need our conversation to be respectful so I can assist you properly.'
- In Tagalog: 'Gusto po kitang tulungan, pero kailangan po nating panatilihing magalang ang usapan.'
- REFUSE harmful, hateful, racist, discriminatory, or threatening content
- Never provide unsafe, illegal, or unethical guidance

## VERIFICATION & UNCERTAINTY HANDLING (MOST IMPORTANT)
When you are UNCERTAIN or DON'T KNOW the answer:
1. **NEVER guess** - This is your absolute #1 rule
2. **Say clearly**: 'I'm not 100% sure about that. Let me verify.' or 'Hindi po ako sigurado dyan, pa-clarify po natin.'
3. **Ask the user** to provide more details or verify information
4. **Offer options**: When unclear, present 2-3 possible interpretations and let user choose
5. It is ALWAYS better to ask a clarifying question than to give a wrong answer

When the user's question is VAGUE or UNCLEAR:
- Ask: 'Could you tell me more about what you need?' or 'Pwede po bang i-clarify kung ano exactly ang kailangan niyo?'
- Offer multiple interpretations: 'Did you mean (A), (B), or (C)?'
- Example: User says 'help' â†’ Respond: 'Happy to help! Are you looking for help with: 1) Booking, 2) Payment, 3) Your account, or 4) Something else?'

When DATA IS UNAVAILABLE:
- Say so honestly. Do NOT invent data.
- Suggest where the user can find the information (dashboard, admin, etc.)

When the USER CORRECTS YOU or REPEATS a question:
- Assume YOUR answer was insufficient, not that they didn't understand
- Try a DIFFERENT explanation approach
- Acknowledge: 'Thank you for clarifying! Here's a better answer.'

## RESPONSE GUIDELINES - CRITICAL
1. **Be Helpful**: Answer the user's actual question
2. **Be Accurate**: Only state facts from the data provided - NEVER HARDCODE DATA
3. **Be Honest**: Say 'I don't have access to that information' when data isn't available
4. **Be Clear**: Use simple, understandable language
5. **Be Professional**: Maintain a neutral, respectful tone
6. **Be Concise**: Keep responses focused (1-3 sentences typically)
7. **Be Actionable**: Tell users what they can do next

## STEP-BY-STEP RESPONSE APPROACH
When answering complex queries, ALWAYS structure your response as follows:
1. **Acknowledge**: Briefly confirm what the user is asking about
2. **Explain**: Provide the relevant information or explanation
3. **Guide**: Give clear, numbered steps if action is needed
4. **Confirm**: End with what the user should do next

## INTELLIGENCE PRINCIPLES
- **REAL-TIME DATA**: Always use the system data provided below - never guess or use cached information
- **TYPO TOLERANCE**: Understand user intent even with spelling errors
- **LANGUAGE FLEXIBILITY**: Seamlessly handle English, Filipino/Tagalog, and Taglish (mixed)
- **CONTEXT AWARENESS**: Remember conversation context to provide coherent multi-turn responses
- **CLARIFICATION FIRST**: If a request is unclear, ask for clarification before assuming
- **VERIFICATION FIRST**: If an answer can be verified but hasn't been, ask or defer

**REMEMBER**: You are an INFORMATION and ASSISTANCE tool only. You never perform system actions.";

        // Add system data context - THIS IS CRITICAL FOR ACCURACY
        if (!empty($systemData)) {
            $prompt .= "\n\n## REAL-TIME SYSTEM DATA (Use this for accurate responses - cite these numbers):\n";
            
            // === BUSINESS INFORMATION - ALWAYS INCLUDE ===
            if (isset($systemData['business_info']) && is_array($systemData['business_info'])) {
                $biz = $systemData['business_info'];
                $prompt .= "\n### BUSINESS/COMPANY INFORMATION (Use this to answer location, contact, attorney questions):\n";
                $prompt .= "- Company Name: " . ($biz['company_name'] ?? 'Peejayy De Guzman Legal') . "\n";
                $prompt .= "- Phone: " . ($biz['phone'] ?? '09765075274') . "\n";
                $prompt .= "- Email: " . ($biz['email'] ?? 'peejaydeguzmanlegal@gmail.com') . "\n";
                $prompt .= "- Address: " . ($biz['address'] ?? '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro') . "\n";
                $prompt .= "- Type: " . ($biz['type'] ?? 'Notary Services & Legal Consultation') . "\n";
                if (isset($biz['specialties']) && is_array($biz['specialties'])) {
                    $prompt .= "- Services Offered: " . implode(', ', $biz['specialties']) . "\n";
                }
            } else {
                // Fallback if business_info not in context
                $prompt .= "\n### BUSINESS/COMPANY INFORMATION:\n";
                $prompt .= "- Company Name: Peejayy De Guzman Legal\n";
                $prompt .= "- Phone: 09765075274\n";
                $prompt .= "- Email: peejaydeguzmanlegal@gmail.com\n";
                $prompt .= "- Address: 233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro\n";
                $prompt .= "- Type: Notary Services & Legal Consultation\n";
                $prompt .= "- Services: Notary Services, Legal Consultations, Document Review, Contract Drafting, Court Representation, Legal Opinions, Case Evaluations\n";
            }
            
            // Services and pricing
            if (isset($systemData['services_available']) && is_array($systemData['services_available'])) {
                $prompt .= "\n### SERVICES & PRICING:\n";
                foreach ($systemData['services_available'] as $svc) {
                    $prompt .= "- " . ($svc['name'] ?? 'Service') . ": " . ($svc['price'] ?? 'Contact for pricing') . "\n";
                }
            }
            
            // Business hours
            if (isset($systemData['business_hours'])) {
                $prompt .= "\n### BUSINESS HOURS:\n";
                $prompt .= "- Hours: " . $systemData['business_hours'] . "\n";
            }
            
            // System statistics (role-dependent)
            if ($role === 'admin' || $role === 'cashier') {
                $prompt .= "\n### SYSTEM STATISTICS:\n";
                
                // Handle flat structure or nested structure from RealTimeDataService
                $stats = $systemData['system_stats'] ?? $systemData;
                
                if (isset($stats['pending_appointments'])) {
                    $prompt .= "- Pending Appointments: " . $stats['pending_appointments'] . "\n";
                }
                if (isset($stats['total_appointments'])) {
                    $prompt .= "- Total Appointments: " . $stats['total_appointments'] . "\n";
                }
                if (isset($stats['appointments_today'])) {
                    $prompt .= "- Today's Appointments: " . $stats['appointments_today'] . "\n";
                }
                if (isset($stats['pending_payments'])) {
                    $prompt .= "- Pending Payments: " . $stats['pending_payments'] . "\n";
                }
                if (isset($stats['pending_refunds'])) {
                    $prompt .= "- Pending Refunds: " . $stats['pending_refunds'] . "\n";
                }
                if (isset($stats['total_users'])) {
                    $prompt .= "- Total Users: " . $stats['total_users'] . "\n";
                }
                if (isset($stats['total_revenue'])) {
                    $prompt .= "- Total Revenue: â‚±" . number_format($stats['total_revenue'], 2) . "\n";
                }
                
                // Today's summary for cashier
                if (isset($systemData['today_summary'])) {
                    $summary = $systemData['today_summary'];
                    $prompt .= "\n### TODAY'S SUMMARY:\n";
                    $prompt .= "- Collections: â‚±" . number_format($summary['collections'] ?? 0, 2) . "\n";
                    $prompt .= "- Refunds: â‚±" . number_format($summary['refunds'] ?? 0, 2) . "\n";
                    $prompt .= "- Appointments for Payment: " . ($summary['appointments_for_payment'] ?? 0) . "\n";
                }
            }
            
            // Current date/time
            if (isset($systemData['current_date'])) {
                $prompt .= "\n### CURRENT TIME:\n";
                $prompt .= "- Date: " . $systemData['current_date'] . "\n";
                $prompt .= "- Day: " . ($systemData['current_day'] ?? '') . "\n";
            }
        } else {
            // Even without system data, always include business info
            $prompt .= "\n\n## BUSINESS INFORMATION:\n";
            $prompt .= "- Company Name: Peejayy De Guzman Legal\n";
            $prompt .= "- Phone: 09765075274\n";
            $prompt .= "- Email: peejaydeguzmanlegal@gmail.com\n";
            $prompt .= "- Address: 233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro\n";
            $prompt .= "- Type: Notary Services & Legal Consultation\n";
            $prompt .= "- Services: Notary Services, Legal Consultations, Document Review, Contract Drafting, Court Representation, Legal Opinions, Case Evaluations\n";
        }

        // Add user-specific context
        if (!empty($userInfo) && $role !== 'guest') {
            $prompt .= "\n## USER'S PERSONAL DATA (This user's real information):\n";
            if (isset($userInfo['name'])) {
                $prompt .= "- Name: " . $userInfo['name'] . "\n";
            }
            if (isset($userInfo['email'])) {
                $prompt .= "- Email: " . $userInfo['email'] . "\n";
            }
            if (isset($userInfo['member_since'])) {
                $prompt .= "- Member Since: " . $userInfo['member_since'] . "\n";
            }
            
            // Appointment data
            if (isset($userInfo['total_appointments'])) {
                $prompt .= "- Total Appointments: " . $userInfo['total_appointments'] . "\n";
            }
            if (isset($userInfo['pending_appointments'])) {
                $prompt .= "- Pending Appointments: " . $userInfo['pending_appointments'] . "\n";
            }
            if (isset($userInfo['approved_appointments'])) {
                $prompt .= "- Approved Appointments: " . $userInfo['approved_appointments'] . "\n";
            }
            if (isset($userInfo['completed_appointments'])) {
                $prompt .= "- Completed Appointments: " . $userInfo['completed_appointments'] . "\n";
            }
            
            // Upcoming appointments with details
            if (isset($userInfo['upcoming_appointments']) && is_array($userInfo['upcoming_appointments']) && count($userInfo['upcoming_appointments']) > 0) {
                $prompt .= "\n### UPCOMING APPOINTMENTS:\n";
                foreach ($userInfo['upcoming_appointments'] as $apt) {
                    $prompt .= "- **Appointment #{$apt['id']}**:\n";
                    $prompt .= "  - Date: " . ($apt['date'] ?? 'TBD') . "\n";
                    $prompt .= "  - Time: " . ($apt['time'] ?? 'TBD') . "\n";
                    $prompt .= "  - Service: " . ($apt['service'] ?? 'N/A') . "\n";
                    $prompt .= "  - Status: " . strtoupper($apt['status'] ?? 'unknown') . "\n";
                    $prompt .= "  - Payment: " . ($apt['payment_status'] ?? 'Pending') . " (" . ($apt['payment_amount'] ?? 'TBD') . ")\n";
                }
            }
            
            // Payment data
            if (isset($userInfo['pending_payments'])) {
                $prompt .= "- Pending Payments: " . $userInfo['pending_payments'] . "\n";
            }
            if (isset($userInfo['total_amount_paid'])) {
                $prompt .= "- Total Paid: â‚±" . number_format($userInfo['total_amount_paid'], 2) . "\n";
            }
            
            // Refund data
            if (isset($userInfo['pending_refunds'])) {
                $prompt .= "- Pending Refunds: " . $userInfo['pending_refunds'] . "\n";
            }
        }

        return $prompt;
    }


    /**
     * Build prompt string for Ollama
     */
    private function buildOllamaPrompt(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt
    ): string {
        // Format for Mistral/Llama 2
        $prompt = "[INST] " . $systemPrompt . "\n\n";

        // Add conversation history
        foreach ($conversationHistory as $msg) {
            if ($msg['role'] === 'assistant') {
                $prompt .= "[/INST] " . ($msg['message'] ?? $msg['content']) . " [INST] ";
            } else {
                $prompt .= ($msg['message'] ?? $msg['content']) . " ";
            }
        }

        $prompt .= $userMessage . " [/INST]";

        return $prompt;
    }

    /**
     * Clean response from artifacts/noise
     */
    /**
     * Generate response via Mistral Cloud API
     */
    private function generateViaMistralCloud(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        array $options = []
    ): array {
        try {
            $rawMessages = $options['raw_messages'] ?? [];
            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            if (!empty($rawMessages)) {
                foreach ($rawMessages as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $content = $msg['content'] ?? $msg['message'] ?? '';
                    
                    if (is_array($content)) {
                        $textContent = '';
                        foreach ($content as $part) {
                            if ($part['type'] === 'text') $textContent .= $part['text'];
                            if ($part['type'] === 'tool_result') {
                                $textContent .= "\n[Tool Result]: " . $part['content'];
                            }
                        }
                        $messages[] = ['role' => $role, 'content' => $textContent];
                    } else {
                        $messages[] = ['role' => $role, 'content' => $content];
                    }
                }
            } else {
                foreach ($conversationHistory as $msg) {
                    $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => $msg['message'] ?? $msg['content'] ?? ''];
                }
                $messages[] = ['role' => 'user', 'content' => $userMessage];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->mistralApiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->requestTimeout)
            ->post(self::MISTRAL_API_URL, [
                'model' => $this->mistralModel,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Mistral API error: ' . $response->status());
            }

            $data = $response->json();
            $responseText = $data['choices'][0]['message']['content'] ?? '';

            if (!$responseText) {
                throw new \Exception('Empty response from Mistral');
            }

            $this->lastUsedProvider = 'mistral';
            $this->lastUsedModel = $this->mistralModel;

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'provider' => 'mistral',
                'model' => $this->mistralModel,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Mistral Cloud API generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function cleanResponse(string $response): string
    {
        // Preserve ```tool_call ... ``` blocks (needed by AgentReasoningService for tool execution)
        $toolCallBlocks = [];
        $response = preg_replace_callback('/```tool_call\s*\n?\s*\{.*?\}\s*\n?\s*```/s', function($match) use (&$toolCallBlocks) {
            $placeholder = '%%TOOL_CALL_' . count($toolCallBlocks) . '%%';
            $toolCallBlocks[$placeholder] = $match[0];
            return $placeholder;
        }, $response);

        // Remove other markdown code blocks
        $response = preg_replace('/```[a-z]*\n?/i', '', $response);

        // Restore tool_call blocks
        foreach ($toolCallBlocks as $placeholder => $block) {
            $response = str_replace($placeholder, $block, $response);
        }

        // Remove excessive whitespace
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // Trim
        $response = trim($response);

        // Truncate if too long (safety measure)
        if (strlen($response) > 8000) {
            $response = substr($response, 0, 7997) . '...';
        }

        return $response;
    }

    /**
     * Verify API connectivity
     */
    public function healthCheck(): array
    {
        $status = [
            'gemini' => (bool) $this->geminiApiKey,
            'github_gpt5' => (bool) $this->githubToken,
            'huggingface' => (bool) $this->huggingfaceApiKey,
            'ollama' => false,
            'available_provider' => null,
        ];

        // Check Ollama
        try {
            $response = Http::timeout(3)->get($this->ollamaBaseUrl . '/api/tags');
            $status['ollama'] = $response->successful();
        } catch (\Exception $e) {
            Log::debug('Ollama health check failed: ' . $e->getMessage());
        }

        // Determine available provider (in priority order)
        if ($status['gemini']) {
            $status['available_provider'] = 'gemini';
        } elseif ($status['github_gpt5']) {
            $status['available_provider'] = 'github_gpt5';
        } elseif ($status['huggingface']) {
            $status['available_provider'] = 'huggingface';
        } elseif ($status['ollama']) {
            $status['available_provider'] = 'ollama';
        }

        return $status;
    }

    /**
     * Approximate token count for a given text.
     * Uses a rough heuristic: ~4 characters per token for English text.
     *
     * @param string $text
     * @return int Approximate token count
     */
    public function countTokens(string $text): int
    {
        // Common approximation: 1 token â‰ˆ 4 characters for English
        // For mixed English/Tagalog, this is close enough for overflow checks
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Get the last model and provider used for generation.
     *
     * @return array ['provider' => string|null, 'model' => string|null]
     */
    public function getLastUsedModel(): array
    {
        return [
            'provider' => $this->lastUsedProvider,
            'model' => $this->lastUsedModel,
        ];
    }
}
