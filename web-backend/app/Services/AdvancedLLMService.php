<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Generator;

/**
 * AdvancedLLMService - Production-Grade AI Backend
 * 
 * Features:
 * - Multiple provider support (Claude, OpenAI, Mistral, Ollama)
 * - Streaming responses for real-time token generation
 * - Extended context window (8K-128K tokens)
 * - Personality/character system
 * - Conversation memory with semantic context
 * - Automatic fallback chain
 * - Rate limiting & cost tracking
 */
class AdvancedLLMService
{
    // Provider API endpoints
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MISTRAL_API_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const OLLAMA_API_URL = 'http://localhost:11434/api';
    
    // Model configurations
    private const MODELS = [
        'claude' => [
            'default' => 'claude-3-5-sonnet-20241022',
            'fast' => 'claude-3-haiku-20240307',
            'smart' => 'claude-3-opus-20240229',
            'context_window' => 200000,
        ],
        'openai' => [
            'default' => 'gpt-4o',
            'fast' => 'gpt-4o-mini',
            'smart' => 'gpt-4-turbo',
            'context_window' => 128000,
        ],
        'mistral' => [
            'default' => 'mistral-large-latest',
            'fast' => 'mistral-small-latest',
            'smart' => 'mistral-large-latest',
            'context_window' => 32000,
        ],
        'ollama' => [
            'default' => 'mistral',
            'fast' => 'mistral',
            'smart' => 'mixtral',
            'context_window' => 32000,
        ],
    ];

    // Personality presets
    private const PERSONALITIES = [
        'professional' => [
            'traits' => ['formal', 'precise', 'helpful'],
            'tone' => 'professional and courteous',
            'style' => 'Use clear, formal language. Be thorough but concise.',
        ],
        'friendly' => [
            'traits' => ['professional', 'approachable', 'helpful'],
            'tone' => 'professional and courteous',
            'style' => 'Use clear, professional language. Be supportive but maintain a neutral tone. NO EMOJIS.',
        ],
        'expert' => [
            'traits' => ['knowledgeable', 'detailed', 'authoritative'],
            'tone' => 'expert and informative',
            'style' => 'Provide detailed explanations with confidence. Reference specifics.',
        ],
        'concise' => [
            'traits' => ['brief', 'direct', 'efficient'],
            'tone' => 'brief and to-the-point',
            'style' => 'Keep responses short. Use bullet points. No unnecessary elaboration.',
        ],
    ];

    private array $config;
    private ?string $preferredProvider = null;

    public function __construct()
    {
        $this->config = [
            'claude_key' => env('ANTHROPIC_API_KEY'),
            'openai_key' => env('OPENAI_API_KEY'),
            'mistral_key' => env('MISTRAL_API_KEY'),
            'use_ollama' => env('USE_OLLAMA_LLM', false) === true || env('USE_OLLAMA_LLM') === 'true',
            'ollama_model' => env('OLLAMA_MODEL', 'mistral'),
            'default_personality' => env('CHATBOT_PERSONALITY', 'professional'),
            'max_tokens' => (int) env('LLM_MAX_TOKENS', 2048),
            'temperature' => (float) env('LLM_TEMPERATURE', 0.7),
            'timeout' => (int) env('LLM_TIMEOUT', 60),
        ];

        // Determine preferred provider based on available keys
        $this->preferredProvider = $this->determineProvider();
    }

    /**
     * Generate response with full context and streaming support
     */
    public function generateResponse(
        string $userMessage,
        array $conversationHistory = [],
        array $context = [],
        bool $stream = false
    ): array {
        $provider = $context['provider'] ?? $this->preferredProvider;
        $personality = $context['personality'] ?? $this->config['default_personality'];
        $modelTier = $context['model_tier'] ?? 'default';
        
        // Build comprehensive system prompt
        $systemPrompt = $this->buildSystemPrompt($context, $personality);
        
        // Trim conversation history to fit context window
        $trimmedHistory = $this->trimConversationHistory(
            $conversationHistory,
            $provider,
            strlen($systemPrompt) + strlen($userMessage)
        );

        try {
            if ($stream) {
                return $this->streamResponse($provider, $userMessage, $trimmedHistory, $systemPrompt, $modelTier);
            }

            return $this->generateWithProvider(
                $provider,
                $userMessage,
                $trimmedHistory,
                $systemPrompt,
                $modelTier
            );
        } catch (\Exception $e) {
            Log::error("LLM generation failed with {$provider}: " . $e->getMessage());
            
            // Try fallback providers
            $fallbackOrder = $this->getFallbackOrder($provider);
            foreach ($fallbackOrder as $fallback) {
                try {
                    Log::info("Attempting fallback to {$fallback}");
                    return $this->generateWithProvider(
                        $fallback,
                        $userMessage,
                        $trimmedHistory,
                        $systemPrompt,
                        'fast' // Use fast model for fallbacks
                    );
                } catch (\Exception $fallbackEx) {
                    Log::warning("Fallback {$fallback} also failed: " . $fallbackEx->getMessage());
                    continue;
                }
            }

            return [
                'success' => false,
                'error' => 'All LLM providers failed',
                'response' => $this->getSmartFallbackResponse($userMessage, $context),
                'provider' => 'fallback',
            ];
        }
    }

    /**
     * Stream response tokens in real-time (returns generator)
     */
    public function streamResponse(
        string $provider,
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        string $modelTier = 'default'
    ): array {
        // Return stream configuration for the controller to handle
        return [
            'success' => true,
            'stream' => true,
            'provider' => $provider,
            'model' => self::MODELS[$provider][$modelTier] ?? self::MODELS[$provider]['default'],
            'config' => [
                'system_prompt' => $systemPrompt,
                'messages' => $this->formatMessages($provider, $userMessage, $conversationHistory),
                'max_tokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature'],
            ],
        ];
    }

    /**
     * Generator function for streaming tokens (used by controller)
     */
    public function createStreamGenerator(array $streamConfig): Generator
    {
        $provider = $streamConfig['provider'];
        $model = $streamConfig['model'];
        $config = $streamConfig['config'];

        switch ($provider) {
            case 'claude':
                yield from $this->streamClaude($model, $config);
                break;
            case 'openai':
                yield from $this->streamOpenAI($model, $config);
                break;
            case 'mistral':
                yield from $this->streamMistral($model, $config);
                break;
            case 'ollama':
                yield from $this->streamOllama($model, $config);
                break;
            default:
                yield ['error' => 'Streaming not supported for provider: ' . $provider];
        }
    }

    /**
     * Stream from Claude API
     */
    private function streamClaude(string $model, array $config): Generator
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->post(self::CLAUDE_API_URL, [
                'headers' => [
                    'x-api-key' => $this->config['claude_key'],
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => $config['max_tokens'],
                    'system' => $config['system_prompt'],
                    'messages' => $config['messages'],
                    'stream' => true,
                ],
                'stream' => true,
                'timeout' => $this->config['timeout'],
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                // Parse SSE events
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    if (strpos($event, 'data: ') === 0) {
                        $data = substr($event, 6);
                        if ($data === '[DONE]') {
                            yield ['done' => true];
                            return;
                        }

                        $json = json_decode($data, true);
                        if ($json && isset($json['delta']['text'])) {
                            yield ['token' => $json['delta']['text']];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            yield ['error' => $e->getMessage()];
        }
    }

    /**
     * Stream from OpenAI API
     */
    private function streamOpenAI(string $model, array $config): Generator
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $messages = array_merge(
                [['role' => 'system', 'content' => $config['system_prompt']]],
                $config['messages']
            );

            $response = $client->post(self::OPENAI_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['openai_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => $config['max_tokens'],
                    'messages' => $messages,
                    'stream' => true,
                    'temperature' => $config['temperature'],
                ],
                'stream' => true,
                'timeout' => $this->config['timeout'],
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (strpos($line, 'data: ') === 0) {
                        $data = trim(substr($line, 6));
                        if ($data === '[DONE]') {
                            yield ['done' => true];
                            return;
                        }

                        $json = json_decode($data, true);
                        if ($json && isset($json['choices'][0]['delta']['content'])) {
                            yield ['token' => $json['choices'][0]['delta']['content']];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            yield ['error' => $e->getMessage()];
        }
    }

    /**
     * Stream from Mistral API
     */
    private function streamMistral(string $model, array $config): Generator
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $messages = array_merge(
                [['role' => 'system', 'content' => $config['system_prompt']]],
                $config['messages']
            );

            $response = $client->post(self::MISTRAL_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['mistral_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => $config['max_tokens'],
                    'messages' => $messages,
                    'stream' => true,
                    'temperature' => $config['temperature'],
                ],
                'stream' => true,
                'timeout' => $this->config['timeout'],
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (strpos($line, 'data: ') === 0) {
                        $data = trim(substr($line, 6));
                        if ($data === '[DONE]') {
                            yield ['done' => true];
                            return;
                        }

                        $json = json_decode($data, true);
                        if ($json && isset($json['choices'][0]['delta']['content'])) {
                            yield ['token' => $json['choices'][0]['delta']['content']];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            yield ['error' => $e->getMessage()];
        }
    }

    /**
     * Stream from Ollama API
     */
    private function streamOllama(string $model, array $config): Generator
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $prompt = $this->buildOllamaPrompt($config['system_prompt'], $config['messages']);

            $response = $client->post(self::OLLAMA_API_URL . '/generate', [
                'json' => [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => true,
                ],
                'stream' => true,
                'timeout' => $this->config['timeout'] * 2, // Ollama can be slower
            ]);

            $body = $response->getBody();
            
            while (!$body->eof()) {
                $line = trim($body->read(4096));
                if (empty($line)) continue;

                // Ollama returns newline-delimited JSON
                foreach (explode("\n", $line) as $jsonLine) {
                    if (empty($jsonLine)) continue;
                    
                    $json = json_decode($jsonLine, true);
                    if ($json) {
                        if (isset($json['response'])) {
                            yield ['token' => $json['response']];
                        }
                        if ($json['done'] ?? false) {
                            yield ['done' => true];
                            return;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            yield ['error' => $e->getMessage()];
        }
    }

    /**
     * Generate response with specific provider (non-streaming)
     */
    private function generateWithProvider(
        string $provider,
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        string $modelTier
    ): array {
        $model = self::MODELS[$provider][$modelTier] ?? self::MODELS[$provider]['default'];
        $messages = $this->formatMessages($provider, $userMessage, $conversationHistory);

        switch ($provider) {
            case 'claude':
                return $this->generateViaClaude($model, $systemPrompt, $messages);
            case 'openai':
                return $this->generateViaOpenAI($model, $systemPrompt, $messages);
            case 'mistral':
                return $this->generateViaMistral($model, $systemPrompt, $messages);
            case 'ollama':
                return $this->generateViaOllama($model, $systemPrompt, $messages);
            default:
                throw new \Exception("Unknown provider: {$provider}");
        }
    }

    /**
     * Generate via Claude API
     */
    private function generateViaClaude(string $model, string $systemPrompt, array $messages): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->config['claude_key'],
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout($this->config['timeout'])
        ->post(self::CLAUDE_API_URL, [
            'model' => $model,
            'max_tokens' => $this->config['max_tokens'],
            'system' => $systemPrompt,
            'messages' => $messages,
            'temperature' => $this->config['temperature'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'success' => true,
            'response' => $this->cleanResponse($data['content'][0]['text'] ?? ''),
            'provider' => 'claude',
            'model' => $model,
            'tokens_used' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            'finish_reason' => $data['stop_reason'] ?? 'end_turn',
        ];
    }

    /**
     * Generate via OpenAI API
     */
    private function generateViaOpenAI(string $model, string $systemPrompt, array $messages): array
    {
        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['openai_key'],
        ])
        ->timeout($this->config['timeout'])
        ->post(self::OPENAI_API_URL, [
            'model' => $model,
            'max_tokens' => $this->config['max_tokens'],
            'messages' => $allMessages,
            'temperature' => $this->config['temperature'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'success' => true,
            'response' => $this->cleanResponse($data['choices'][0]['message']['content'] ?? ''),
            'provider' => 'openai',
            'model' => $model,
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
        ];
    }

    /**
     * Generate via Mistral API
     */
    private function generateViaMistral(string $model, string $systemPrompt, array $messages): array
    {
        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['mistral_key'],
        ])
        ->timeout($this->config['timeout'])
        ->post(self::MISTRAL_API_URL, [
            'model' => $model,
            'max_tokens' => $this->config['max_tokens'],
            'messages' => $allMessages,
            'temperature' => $this->config['temperature'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Mistral API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'success' => true,
            'response' => $this->cleanResponse($data['choices'][0]['message']['content'] ?? ''),
            'provider' => 'mistral',
            'model' => $model,
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
        ];
    }

    /**
     * Generate via Ollama (self-hosted)
     */
    private function generateViaOllama(string $model, string $systemPrompt, array $messages): array
    {
        $prompt = $this->buildOllamaPrompt($systemPrompt, $messages);

        $response = Http::timeout($this->config['timeout'] * 2)
            ->post(self::OLLAMA_API_URL . '/generate', [
                'model' => $this->config['ollama_model'] ?? $model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'num_predict' => $this->config['max_tokens'],
                    'temperature' => $this->config['temperature'],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ollama API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'success' => true,
            'response' => $this->cleanResponse($data['response'] ?? ''),
            'provider' => 'ollama',
            'model' => $model,
            'tokens_used' => $data['eval_count'] ?? 0,
            'finish_reason' => $data['done'] ? 'stop' : 'length',
        ];
    }

    /**
     * Build comprehensive system prompt with personality and context
     */
    private function buildSystemPrompt(array $context, string $personality): string
    {
        $role = $context['role'] ?? 'guest';
        $systemData = $context['system_data'] ?? [];
        $userInfo = $context['user_info'] ?? [];
        $personalityConfig = self::PERSONALITIES[$personality] ?? self::PERSONALITIES['professional'];

        $prompt = "You are an intelligent AI assistant for a legal services appointment booking system.

## Your Personality
- Traits: " . implode(', ', $personalityConfig['traits']) . "
- Tone: {$personalityConfig['tone']}
- Style: {$personalityConfig['style']}

## Core Responsibilities
1. Provide accurate, helpful information about appointments, services, payments, and refunds
2. Use ONLY the real-time data provided - never fabricate information
3. Be empathetic and supportive, especially with frustrated users
4. Ask clarifying questions when uncertain rather than assuming
5. Suggest relevant actions the user can take
6. Remember context from earlier in the conversation
7. Adapt language to match the user (English or Taglish/Filipino)

## Current User Context
- Role: {$role}
- Authenticated: " . ($context['user_id'] ? 'Yes' : 'No (Guest)') . "
";

        // Add role-specific instructions
        $roleInstructions = [
            'guest' => "
- This is an unauthenticated visitor
- Provide general information about services and booking process
- Encourage them to register/login for personalized assistance
- Cannot access personal appointment or payment data",
            
            'client' => "
- This is a registered client
- Can view and manage their appointments
- Can request refunds and check payment status
- Provide personalized assistance with their bookings",
            
            'admin' => "
- This is a system administrator
- Full access to system data and operations
- Can approve/decline appointments and refunds
- Provide detailed analytics and operational insights",
            
            'cashier' => "
- This is a cashier/payment processor
- Can process payments and refunds
- Focus on transaction-related assistance
- Provide shift reports and payment summaries",
        ];

        $prompt .= $roleInstructions[$role] ?? $roleInstructions['guest'];

        // Add real-time system data
        if (!empty($systemData)) {
            $prompt .= "\n\n## Real-Time System Data\n";
            foreach ($systemData as $key => $value) {
                if (is_array($value)) {
                    $prompt .= "- {$key}: " . implode(', ', array_slice($value, 0, 10)) . "\n";
                } else {
                    $prompt .= "- {$key}: {$value}\n";
                }
            }
        }

        // Add user-specific context
        if (!empty($userInfo) && $role !== 'guest') {
            $prompt .= "\n## User Profile\n";
            foreach ($userInfo as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        // Add knowledge base context if available
        if (!empty($context['knowledge_context'])) {
            $prompt .= "\n## Relevant Knowledge Base Information\n";
            $prompt .= $context['knowledge_context'];
        }

        $prompt .= "\n\n## Response Guidelines
- Keep responses concise but helpful (50-200 words ideal)
- Use formatting (bullets, bold) for clarity when listing items
- Always be accurate - say \"I don't have that information\" if uncertain
- Suggest next steps or related actions when appropriate
- Match the user's language and communication style";

        return $prompt;
    }

    /**
     * Format messages for API calls
     */
    private function formatMessages(string $provider, string $userMessage, array $history): array
    {
        $messages = [];

        foreach ($history as $msg) {
            $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
            $content = $msg['message'] ?? $msg['content'] ?? '';
            
            if (!empty($content)) {
                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Build prompt string for Ollama (Mistral/Llama format)
     */
    private function buildOllamaPrompt(string $systemPrompt, array $messages): string
    {
        $prompt = "[INST] <<SYS>>\n{$systemPrompt}\n<</SYS>>\n\n";

        foreach ($messages as $msg) {
            if ($msg['role'] === 'assistant') {
                $prompt .= "[/INST] " . $msg['content'] . " [INST] ";
            } else {
                $prompt .= $msg['content'] . " ";
            }
        }

        if (!str_ends_with($prompt, '[INST] ')) {
            $prompt .= "[/INST]";
        } else {
            $prompt = substr($prompt, 0, -7) . "[/INST]";
        }

        return $prompt;
    }

    /**
     * Trim conversation history to fit context window
     */
    private function trimConversationHistory(array $history, string $provider, int $reservedTokens): array
    {
        $maxTokens = self::MODELS[$provider]['context_window'] ?? 8000;
        $availableTokens = $maxTokens - $reservedTokens - $this->config['max_tokens'];
        
        // Rough estimate: 4 chars per token
        $maxChars = $availableTokens * 4;
        $currentChars = 0;
        $trimmedHistory = [];

        // Process from newest to oldest
        $reversedHistory = array_reverse($history);
        
        foreach ($reversedHistory as $msg) {
            $content = $msg['message'] ?? $msg['content'] ?? '';
            $msgChars = strlen($content) + 50; // Add overhead for formatting
            
            if ($currentChars + $msgChars > $maxChars) {
                break;
            }
            
            $currentChars += $msgChars;
            array_unshift($trimmedHistory, $msg);
        }

        return $trimmedHistory;
    }

    /**
     * Clean response from artifacts
     */
    private function cleanResponse(string $response): string
    {
        // Remove markdown code blocks
        $response = preg_replace('/```[a-z]*\n?/i', '', $response);
        
        // Remove excessive whitespace
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        
        // Trim
        $response = trim($response);
        
        // Safety truncation
        if (strlen($response) > 5000) {
            $response = substr($response, 0, 4997) . '...';
        }

        return $response;
    }

    /**
     * Determine best available provider
     */
    private function determineProvider(): ?string
    {
        if ($this->config['use_ollama']) {
            return 'ollama';
        }
        
        if (!empty($this->config['claude_key'])) {
            return 'claude';
        }
        
        if (!empty($this->config['openai_key'])) {
            return 'openai';
        }
        
        if (!empty($this->config['mistral_key'])) {
            return 'mistral';
        }
        
        // Check if Ollama is available locally
        try {
            $response = Http::timeout(2)->get(self::OLLAMA_API_URL . '/tags');
            if ($response->successful()) {
                return 'ollama';
            }
        } catch (\Exception $e) {
            // Ollama not available
        }

        return null;
    }

    /**
     * Get fallback provider order
     */
    private function getFallbackOrder(string $currentProvider): array
    {
        $order = ['claude', 'openai', 'mistral', 'ollama'];
        return array_filter($order, function($p) use ($currentProvider) {
            if ($p === $currentProvider) return false;
            return $this->isProviderAvailable($p);
        });
    }

    /**
     * Check if provider is available
     */
    private function isProviderAvailable(string $provider): bool
    {
        switch ($provider) {
            case 'claude':
                return !empty($this->config['claude_key']);
            case 'openai':
                return !empty($this->config['openai_key']);
            case 'mistral':
                return !empty($this->config['mistral_key']);
            case 'ollama':
                return $this->config['use_ollama'] || $this->checkOllamaAvailable();
            default:
                return false;
        }
    }

    /**
     * Check if Ollama is available
     */
    private function checkOllamaAvailable(): bool
    {
        return Cache::remember('ollama_available', 60, function() {
            try {
                $response = Http::timeout(2)->get(self::OLLAMA_API_URL . '/tags');
                return $response->successful();
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Get smart fallback response when all providers fail
     */
    private function getSmartFallbackResponse(string $userMessage, array $context): string
    {
        $role = $context['role'] ?? 'guest';
        $lowerMessage = strtolower($userMessage);

        // Context-aware fallback responses
        if (str_contains($lowerMessage, 'appointment') || str_contains($lowerMessage, 'book')) {
            return "I can help you with appointments! To book a new appointment, please visit the booking page. For existing appointments, check your dashboard. Is there something specific I can help you with?";
        }

        if (str_contains($lowerMessage, 'payment') || str_contains($lowerMessage, 'pay')) {
            return "For payment inquiries, you can check your payment status in your dashboard. If you need to make a payment, our cashier can assist you. Would you like more details?";
        }

        if (str_contains($lowerMessage, 'refund')) {
            return "Refund requests can be submitted through your appointment details. The process typically takes 3-5 business days after approval. Do you need help with a specific refund?";
        }

        if (str_contains($lowerMessage, 'service')) {
            return "We offer various legal consultation services. You can browse all available services on our booking page. Would you like me to describe any specific service?";
        }

        // Role-specific generic response
        $genericResponses = [
            'guest' => "I'm here to help! As a guest, I can provide general information about our services and booking process. For personalized assistance, please log in to your account.",
            'client' => "I'm here to assist you with your appointments, payments, and any questions. How can I help you today?",
            'admin' => "I can help you with system administration, approvals, and analytics. What would you like to manage?",
            'cashier' => "I can assist with payments, refunds, and transaction management. What do you need help with?",
        ];

        return $genericResponses[$role] ?? $genericResponses['guest'];
    }

    /**
     * Health check for all providers
     */
    public function healthCheck(): array
    {
        $status = [
            'claude' => false,
            'openai' => false,
            'mistral' => false,
            'ollama' => false,
            'available_provider' => null,
            'preferred_provider' => $this->preferredProvider,
        ];

        // Check Claude
        if (!empty($this->config['claude_key'])) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->config['claude_key'],
                    'anthropic-version' => '2023-06-01',
                ])->timeout(5)->get('https://api.anthropic.com/v1/models');
                $status['claude'] = $response->successful();
            } catch (\Exception $e) {
                Log::debug('Claude health check failed: ' . $e->getMessage());
            }
        }

        // Check OpenAI
        if (!empty($this->config['openai_key'])) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->config['openai_key'],
                ])->timeout(5)->get(self::OPENAI_API_URL . '/models');
                $status['openai'] = $response->successful();
            } catch (\Exception $e) {
                Log::debug('OpenAI health check failed: ' . $e->getMessage());
            }
        }

        // Check Mistral
        if (!empty($this->config['mistral_key'])) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->config['mistral_key'],
                ])->timeout(5)->get('https://api.mistral.ai/v1/models');
                $status['mistral'] = $response->successful();
            } catch (\Exception $e) {
                Log::debug('Mistral health check failed: ' . $e->getMessage());
            }
        }

        // Check Ollama
        try {
            $response = Http::timeout(3)->get(self::OLLAMA_API_URL . '/tags');
            $status['ollama'] = $response->successful();
        } catch (\Exception $e) {
            Log::debug('Ollama health check failed: ' . $e->getMessage());
        }

        // Determine available provider
        foreach (['claude', 'openai', 'mistral', 'ollama'] as $provider) {
            if ($status[$provider]) {
                $status['available_provider'] = $provider;
                break;
            }
        }

        return $status;
    }

    /**
     * Get available personalities
     */
    public function getPersonalities(): array
    {
        return array_keys(self::PERSONALITIES);
    }

    /**
     * Get available models for a provider
     */
    public function getModels(string $provider): array
    {
        return self::MODELS[$provider] ?? [];
    }
}
