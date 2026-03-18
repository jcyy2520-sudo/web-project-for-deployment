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
    private ?string $ollamaStreamUrl = null;
    private ?string $githubStreamUrl = null;
    private ?string $githubToken = null;
    private string $githubModel;
    private ?string $geminiApiKey = null;
    private string $geminiModel;
    private int $requestTimeout;
    private int $maxTokens;
    
    private $useOllama;
    private string $ollamaModel;

    public function __construct()
    {
        $this->useOllama = filter_var(config('services.ollama.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->ollamaStreamUrl = config('services.ollama.stream_url') ?: config('services.ollama.url') ?: 'http://localhost:11434/api/generate';
        $this->githubToken = config('services.github_gpt5.api_key') ?: config('chatbot_unified.llm.github_gpt5.api_key');
        $this->githubModel = config('services.github_gpt5.model', config('chatbot_unified.llm.github_gpt5.model', 'openai/gpt-5'));
        $this->githubStreamUrl = (config('services.github_gpt5.api_url') ?: config('chatbot_unified.llm.github_gpt5.base_url', 'https://models.github.ai/inference')) . '/chat/completions';
        $this->geminiApiKey = config('services.gemini.api_key') ?: config('chatbot_unified.llm.gemini.api_key');
        $this->geminiModel = config('services.gemini.model', config('chatbot_unified.llm.gemini.model', 'gemini-1.5-pro-latest'));
        $this->requestTimeout = (int) config('chatbot_unified.llm.streaming_timeout', 300);
        $this->maxTokens = (int) config('chatbot_unified.llm.streaming_max_tokens', 4096);
        $this->ollamaModel = config('services.ollama.model', config('chatbot_unified.llm.ollama.model', 'mistral'));
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

            // Try Gemini first (Primary)
            if ($this->geminiApiKey) {
                try {
                    return $this->streamViaGemini(
                        $userMessage,
                        $conversationHistory,
                        $systemPrompt,
                        $onToken,
                        $onComplete,
                        $systemContext
                    );
                } catch (\Exception $e) {
                    Log::warning('Gemini streaming failed, falling back: ' . $e->getMessage());
                }
            }

            // Try GitHub GPT-5 secondary
            if ($this->githubToken) {
                try {
                    return $this->streamViaGithubGPT5(
                        $userMessage,
                        $conversationHistory,
                        $systemPrompt,
                        $onToken,
                        $onComplete,
                        $systemContext
                    );
                } catch (\Exception $e) {
                    Log::warning('GitHub GPT-5 streaming failed, falling back: ' . $e->getMessage());
                }
            }
            
            // Try Ollama (self-hosted)
            if ($this->useOllama) {
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
            }

            throw new \Exception('No streaming providers available');
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
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
        callable $onComplete = null,
        array $options = []
    ): array {
        $rawMessages = $options['raw_messages'] ?? [];
        
        $messages = [];
        
        if (!empty($rawMessages)) {
            foreach ($rawMessages as $msg) {
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                $content = $msg['content'] ?? '';
                if (is_array($content)) {
                    $text = '';
                    foreach ($content as $part) if ($part['type'] === 'text') $text .= $part['text'];
                    $content = $text;
                }
                $messages[] = ['role' => $role, 'content' => $content];
            }
        } else {
            foreach ($conversationHistory as $msg) {
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                $messages[] = ['role' => $role, 'content' => $msg['message'] ?? $msg['content'] ?? ''];
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }

        try {
            $fullResponse = '';
            $totalTokens = 0;
            
            // Ollama chat API accepts messages
            $response = Http::timeout($this->requestTimeout)
            ->withOptions(['stream' => true])
            ->post($this->ollamaBaseUrl . '/api/chat', [
                'model' => $this->ollamaModel,
                'messages' => $messages, // messages built from rawMessages or history
                'stream' => true,
                'options' => [
                    'num_predict' => $this->maxTokens,
                    'temperature' => (float) config('chatbot_unified.llm.temperature', 0.7),
                    'top_p' => (float) config('chatbot_unified.llm.top_p', 0.9),
                ]
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ollama returned ' . $response->status() . ': ' . $response->body());
            }

            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                $line = $this->readLine($body);
                if (empty($line)) continue;

                $data = json_decode($line, true);
                if (!$data) continue;

                $token = $data['message']['content'] ?? $data['response'] ?? '';
                if ($token) {
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
     * Stream response via Google Gemini API
     */
    private function streamViaGemini(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        callable $onToken,
        callable $onComplete = null,
        array $options = []
    ): array {
        $rawMessages = $options['raw_messages'] ?? [];
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
                            $parts[] = ['text' => "Tool result: " . $part['content']];
                        }
                    }
                    $contents[] = ['role' => $role, 'parts' => $parts];
                } else if ($content) {
                    $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
                }
            }
        } else {
            foreach ($conversationHistory as $msg) {
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'model' : 'user';
                $contents[] = ['role' => $role, 'parts' => [['text' => $msg['message'] ?? $msg['content'] ?? '']]];
            }
            $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->geminiModel}:streamGenerateContent?key={$this->geminiApiKey}";
        
        $payload = [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => 0.7,
            ]
        ];

        $fullResponse = '';
        $stream = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout($this->requestTimeout)
            ->withOptions(['stream' => true])
            ->post($url, $payload);

        $body = $stream->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;
            
            // Gemini returns a JSON array of objects [...] for streaming
            // This is slightly tricky to parse incrementally with simple regex,
            // but for a robust implementation we'll try to find full objects
            while (($start = strpos($buffer, '{')) !== false) {
                // Find matching closing brace (very naive but often works for this specific API)
                $depth = 0;
                $end = -1;
                for ($i = $start; $i < strlen($buffer); $i++) {
                    if ($buffer[$i] === '{') $depth++;
                    if ($buffer[$i] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $end = $i;
                            break;
                        }
                    }
                }

                if ($end !== -1) {
                    $json = substr($buffer, $start, $end - $start + 1);
                    $buffer = substr($buffer, $end + 1);
                    
                    $data = json_decode($json, true);
                    $token = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($token) {
                        $fullResponse .= $token;
                        if ($onToken) $onToken($token, ['provider' => 'gemini', 'model' => $this->geminiModel]);
                    }
                } else {
                    break; // Wait for more data
                }
            }
        }

        if ($onComplete) $onComplete($fullResponse);

        return [
            'success' => true,
            'response' => $fullResponse,
            'provider' => 'gemini',
            'model' => $this->geminiModel
        ];
    }

    /**
     * Stream response from GitHub GPT-5 (OpenAI-compatible)
     */
    private function streamViaGithubGPT5(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt,
        callable $onToken = null,
        callable $onComplete = null,
        array $options = []
    ): array {
        $rawMessages = $options['raw_messages'] ?? [];
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if (!empty($rawMessages)) {
            foreach ($rawMessages as $msg) {
                $role = ($msg['role'] === 'assistant' || $msg['role'] === 'bot') ? 'assistant' : 'user';
                $content = $msg['content'] ?? '';
                if (is_array($content)) {
                    $text = '';
                    foreach ($content as $part) {
                        if ($part['type'] === 'text') $text .= $part['text'];
                    }
                    $messages[] = ['role' => $role, 'content' => $text];
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

        try {
            $fullResponse = '';
            $totalTokens = 0;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->githubToken,
                'Accept' => 'text/event-stream',
                'Content-Type' => 'application/json',
            ])
            ->withOptions([
                'stream' => true,
            ])
            ->timeout($this->requestTimeout)
            ->post($this->githubStreamUrl, [
                'model' => $this->githubModel,
                'messages' => $messages,
                'stream' => true,
                'max_completion_tokens' => $this->maxTokens,
            ]);

            if (!$response->successful()) {
                throw new \Exception('GitHub Multi-Model API error: ' . $response->status());
            }

            // Simple SSE parser
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                $line = $this->readLine($body);
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') break;

                    $data = json_decode($json, true);
                    if ($data && isset($data['choices'][0]['delta']['content'])) {
                        $token = $data['choices'][0]['delta']['content'];
                        $fullResponse .= $token;
                        if ($onToken) {
                            $onToken($token, ['provider' => 'github_gpt5']);
                        }
                    }
                }
            }

            if ($onComplete) {
                $onComplete([
                    'success' => true,
                    'provider' => 'github_gpt5',
                    'response' => $fullResponse
                ]);
            }

            return [
                'success' => true,
                'response' => $this->cleanResponse($fullResponse),
                'provider' => 'github_gpt5',
                'model' => $this->githubModel,
            ];
        } catch (\Exception $e) {
            Log::error('GitHub GPT-5 streaming failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Read a line from a stream
     */
    private function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") break;
            $buffer .= $char;
        }
        return trim($buffer);
    }
    /**
     * Build system prompt
     */
    private function buildSystemPrompt(array $systemContext): string
    {
        if (!empty($systemContext['skip_internal_prompt']) && !empty($systemContext['system_prompt'])) {
            return $systemContext['system_prompt'];
        }

        $role = $systemContext['role'] ?? 'guest';
        $systemData = $systemContext['system_data'] ?? [];
        $userInfo = $systemContext['user_info'] ?? [];

        $prompt = "=== PERMISSIONED AI AGENT: VERIFY BEFORE ANSWERING ===
You are a smart, accurate AI assistant - NOT a guessing chatbot.

CORE MANDATE: Verify information before answering. If answer can be verified but hasn't been, do NOT answer.

DECISION FLOW:
1. Understand the question
2. Determine if system data/verification is needed
3. If YES→retrieve. If NO→use verified knowledge. If UNCLEAR→ask.
4. Answer ONLY from retrieved data.

KEY RULES:
- Never guess, assume, or fabricate
- Source-restricted: Only from verified data
- Clarification first: If unclear, ask before answering  
- Confidence control: Expose uncertainty
- Role aware: Respect access boundaries
- Scope limited: Refuse out-of-scope requests
- Handle input robustly: Typos, grammar, Taglish OK - don't lower accuracy
- Error-adaptive: When users correct/repeat, adjust strategy

If forced to choose: Ask instead of guessing. Refuse instead of hallucinating.

=== SYSTEM ASSISTANT ===

You are a smart, helpful AI assistant for a legal appointment booking system.

## Your Responsibilities:
1. Provide accurate information from verified data sources
2. Use real-time data provided in context - NEVER fabricate
3. Be professional, calm, and reliable
4. Address concerns with clarity, especially if user is frustrated
5. When uncertain, ask clarifying questions - do NOT guess
6. Keep responses concise but informative (aim for 50-150 words)
7. Use the user's language (including Taglish/Filipino if they use it)

## User Role & Permissions (Respect These Boundaries):
";
        
        $roleCapabilities = [
            'guest' => "You're helping a guest visitor.\n- ALLOWED: Provide only public information\n- ALLOWED: Encourage registration/login\n- ALLOWED: Share general business info\n- RESTRICTED: Cannot access personal appointment data",
            'client' => "You're helping a registered client.\n- ALLOWED: Provide their own appointment details\n- ALLOWED: Help with booking, rescheduling, cancellations\n- ALLOWED: Explain payment and refund processes\n- RESTRICTED: Cannot access other users' data",
            'admin' => "You're helping a system administrator.\n- ALLOWED: Provide system-wide information\n- ALLOWED: Help with approval workflows  \n- ALLOWED: Discuss analytics from verified data\n- RESTRICTED: Still verify data before sharing",
            'cashier' => "You're helping a payment processor.\n- ALLOWED: Provide payment/refund information\n- ALLOWED: Help with transaction verification\n- ALLOWED: Support shift reporting\n- RESTRICTED: Cannot access user personal data",
        ];

        $prompt .= $roleCapabilities[$role] ?? $roleCapabilities['guest'];

        if (!empty($systemData)) {
            $prompt .= "\n\n## Verified System Data (from database):\n";
            
            foreach ($systemData as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_slice($value, 0, 5));
                }
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
            }
        }

        if (!empty($userInfo) && $role !== 'guest') {
            $prompt .= "\n## Verified User Profile:\n";
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
        // Preserve ```tool_call ... ``` blocks
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
        $response = trim($response);

        if (strlen($response) > 8000) {
            $response = substr($response, 0, 7997) . '...';
        }
        return $response;
    }
}
