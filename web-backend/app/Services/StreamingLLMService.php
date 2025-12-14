<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * StreamingLLMService - Streaming AI Response Generator
 * 
 * Provides streaming responses for real-time token-by-token generation
 * Supports both Claude and Ollama with streaming
 * 
 * Features:
 * - Token-by-token streaming
 * - Server-Sent Events (SSE) support
 * - Graceful error handling
 * - Progress tracking
 */
class StreamingLLMService
{
    private const CLAUDE_STREAM_URL = 'https://api.anthropic.com/v1/messages';
    private const OLLAMA_STREAM_URL = 'http://localhost:11434/api/generate';
    private const REQUEST_TIMEOUT = 300; // 5 minutes for streaming
    private const MAX_TOKENS = 2048;
    
    private $claudeApiKey;
    private $useOllama;
    private $ollamaModel = 'mistral';

    public function __construct()
    {
        $this->claudeApiKey = env('ANTHROPIC_API_KEY');
        $this->useOllama = env('USE_OLLAMA_LLM', false) === true || env('USE_OLLAMA_LLM', 'false') === 'true';
    }

    /**
     * Stream response via Server-Sent Events (SSE)
     * 
     * Usage in controller:
     * ```php
     * return $this->streamingLLMService->streamResponse(
     *     $userMessage,
     *     $conversationHistory,
     *     $systemContext,
     *     function($token, $metadata) {
     *         echo "data: " . json_encode(['token' => $token, 'meta' => $metadata]) . "\n\n";
     *         ob_flush();
     *         flush();
     *     }
     * );
     * ```
     */
    public function streamResponse(
        string $userMessage,
        array $conversationHistory = [],
        array $systemContext = [],
        callable $onToken = null,
        callable $onComplete = null
    ): array {
        try {
            $systemPrompt = $this->buildSystemPrompt($systemContext);
            
            // Try Claude first if API key exists
            if ($this->claudeApiKey && !$this->useOllama) {
                try {
                    return $this->streamViaClaudeAPI(
                        $userMessage,
                        $conversationHistory,
                        $systemPrompt,
                        $onToken,
                        $onComplete
                    );
                } catch (\Exception $e) {
                    Log::warning('Claude streaming failed: ' . $e->getMessage());
                    if (!$this->useOllama) {
                        throw $e;
                    }
                }
            }

            // Try Ollama (self-hosted)
            try {
                return $this->streamViaOllama(
                    $userMessage,
                    $conversationHistory,
                    $systemPrompt,
                    $onToken,
                    $onComplete
                );
            } catch (\Exception $e) {
                Log::error('Ollama streaming failed: ' . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stream response from Claude API
     */
    private function streamViaClaudeAPI(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        callable $onToken = null,
        callable $onComplete = null
    ): array {
        try {
            $messages = $this->buildClaudeMessages($userMessage, $conversationHistory);

            $client = Http::withHeaders([
                'x-api-key' => $this->claudeApiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->timeout(self::REQUEST_TIMEOUT);

            $fullResponse = '';
            $totalInputTokens = 0;
            $totalOutputTokens = 0;

            // Create request body with streaming enabled
            $requestBody = [
                'model' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
                'max_tokens' => self::MAX_TOKENS,
                'system' => $systemPrompt,
                'messages' => $messages,
                'stream' => true,
            ];

            $response = $client->post(self::CLAUDE_STREAM_URL, $requestBody);

            if (!$response->successful()) {
                throw new \Exception('Claude API returned ' . $response->status());
            }

            // Parse streaming response (Server-Sent Events format)
            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                if (empty($line) || $line === ':OPEN_FILE') continue;

                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    $event = json_decode($json, true);

                    if (!$event) continue;

                    // Extract token from different event types
                    if ($event['type'] === 'content_block_delta' && isset($event['delta']['text'])) {
                        $token = $event['delta']['text'];
                        $fullResponse .= $token;

                        if ($onToken) {
                            $onToken($token, [
                                'provider' => 'claude',
                                'type' => 'content_block_delta',
                            ]);
                        }
                    } elseif ($event['type'] === 'message_start' && isset($event['message']['usage'])) {
                        $totalInputTokens = $event['message']['usage']['input_tokens'] ?? 0;
                    } elseif ($event['type'] === 'message_delta' && isset($event['usage'])) {
                        $totalOutputTokens = $event['usage']['output_tokens'] ?? 0;
                    }
                }
            }

            if ($onComplete) {
                $onComplete([
                    'success' => true,
                    'provider' => 'claude',
                    'tokens_input' => $totalInputTokens,
                    'tokens_output' => $totalOutputTokens,
                ]);
            }

            return [
                'success' => true,
                'response' => $this->cleanResponse($fullResponse),
                'provider' => 'claude',
                'model' => 'claude-3-sonnet-20240229',
                'tokens_input' => $totalInputTokens,
                'tokens_output' => $totalOutputTokens,
                'tokens_total' => $totalInputTokens + $totalOutputTokens,
            ];
        } catch (\Exception $e) {
            Log::error('Claude streaming failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stream response from Ollama
     */
    private function streamViaOllama(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        callable $onToken = null,
        callable $onComplete = null
    ): array {
        try {
            $prompt = $this->buildOllamaPrompt($userMessage, $conversationHistory, $systemPrompt);

            $client = Http::timeout(self::REQUEST_TIMEOUT);

            $fullResponse = '';
            $totalTokens = 0;

            $response = $client->post(self::OLLAMA_STREAM_URL, [
                'model' => $this->ollamaModel,
                'prompt' => $prompt,
                'stream' => true,
                'num_predict' => self::MAX_TOKENS,
                'temperature' => 0.7,
                'top_p' => 0.9,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ollama returned ' . $response->status());
            }

            // Parse Ollama streaming response (NDJSON format)
            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                if (empty($line)) continue;

                $data = json_decode($line, true);
                if (!$data) continue;

                if (isset($data['response'])) {
                    $token = $data['response'];
                    $fullResponse .= $token;
                    $totalTokens = $data['eval_count'] ?? $totalTokens;

                    if ($onToken) {
                        $onToken($token, [
                            'provider' => 'ollama',
                            'eval_count' => $data['eval_count'] ?? 0,
                            'done' => $data['done'] ?? false,
                        ]);
                    }
                }
            }

            if ($onComplete) {
                $onComplete([
                    'success' => true,
                    'provider' => 'ollama',
                    'tokens' => $totalTokens,
                ]);
            }

            return [
                'success' => true,
                'response' => $this->cleanResponse($fullResponse),
                'provider' => 'ollama',
                'model' => $this->ollamaModel,
                'tokens_used' => $totalTokens,
            ];
        } catch (\Exception $e) {
            Log::error('Ollama streaming failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Build system prompt
     */
    private function buildSystemPrompt(array $systemContext): string
    {
        $role = $systemContext['role'] ?? 'guest';
        $systemData = $systemContext['system_data'] ?? [];
        $userInfo = $systemContext['user_info'] ?? [];

        $prompt = "You are a smart, helpful AI assistant for a legal appointment booking system.

## Your Responsibilities:
1. Provide accurate information about appointments, services, payments, and refunds
2. Use real-time data provided in context - NEVER fabricate information
3. Be professional but friendly and approachable
4. Address user concerns with empathy, especially if they're frustrated
5. When uncertain, ask clarifying questions rather than guessing
6. Keep responses concise but informative (aim for 50-150 words)
7. Use the user's language (including Taglish/Filipino if they use it)

## User Role & Capabilities:
";
        
        $roleCapabilities = [
            'guest' => "You're helping a guest visitor.\n- Only provide public information\n- Encourage registration/login for personalized help\n- Share general business info (hours, services, booking process)",
            'client' => "You're helping a registered client.\n- Provide personalized appointment details\n- Help with booking, rescheduling, cancellations\n- Explain payment and refund processes\n- Be helpful with their specific concerns",
            'admin' => "You're helping a system administrator.\n- Provide system-wide information\n- Help with approval workflows\n- Discuss analytics and reports\n- Support administrative tasks",
            'cashier' => "You're helping a cashier/payment processor.\n- Provide payment and refund information\n- Help with transaction verification\n- Support shift reporting\n- Help with payment processing",
        ];

        $prompt .= $roleCapabilities[$role] ?? $roleCapabilities['guest'];

        if (!empty($systemData)) {
            $prompt .= "\n\n## Current System Data:\n";
            
            foreach ($systemData as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_slice($value, 0, 5));
                }
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
            }
        }

        if (!empty($userInfo) && $role !== 'guest') {
            $prompt .= "\n## User Profile:\n";
            foreach ($userInfo as $key => $value) {
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
            }
        }

        $prompt .= "\n## Instructions:
- Respond directly to the user's question
- Reference real data when available
- Be specific, not vague
- Suggest next steps when appropriate
- Keep a professional but warm tone";

        return $prompt;
    }

    /**
     * Build Claude messages
     */
    private function buildClaudeMessages(string $userMessage, array $conversationHistory): array
    {
        $messages = [];
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['message'] ?? $msg['content'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    /**
     * Build Ollama prompt
     */
    private function buildOllamaPrompt(string $userMessage, array $conversationHistory, string $systemPrompt): string
    {
        $prompt = "[INST] " . $systemPrompt . "\n\n";
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
     * Clean response
     */
    private function cleanResponse(string $response): string
    {
        $response = preg_replace('/```[a-z]*\n?/i', '', $response);
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = trim($response);
        if (strlen($response) > 5000) {
            $response = substr($response, 0, 4997) . '...';
        }
        return $response;
    }
}
