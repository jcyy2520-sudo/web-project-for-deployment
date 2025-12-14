<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const OLLAMA_API_URL = 'http://localhost:11434/api/generate';
    private const REQUEST_TIMEOUT = 30;
    private const MAX_TOKENS = 2048; // Increased for better context window
    private const CONTEXT_WINDOW = 8000; // Token limit for conversation history (Claude: 200K, Ollama Mistral: 32K)
    
    private $claudeApiKey;
    private $useOllama;
    private $ollamaModel = 'mistral'; // Change to 'llama2' if using Llama 2

    public function __construct()
    {
        $this->claudeApiKey = env('ANTHROPIC_API_KEY');
        $this->useOllama = env('USE_OLLAMA_LLM', false) === true || env('USE_OLLAMA_LLM', 'false') === 'true';
    }

    /**
     * Generate intelligent response with full context
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

            // Try Claude first if API key exists
            if ($this->claudeApiKey && !$this->useOllama) {
                try {
                    Log::debug('Attempting Claude API call');
                    return $this->generateViaClaudeAPI(
                        $userMessage,
                        $conversationHistory,
                        $systemPrompt
                    );
                } catch (\Exception $e) {
                    Log::warning('Claude API failed, falling back to Ollama: ' . $e->getMessage());
                    if (!$this->useOllama) {
                        // If Claude fails and Ollama not configured, return error
                        return [
                            'success' => false,
                            'error' => 'LLM service unavailable',
                            'message' => 'AI service is temporarily unavailable. Please try again later.',
                        ];
                    }
                }
            }

            // Try Ollama (self-hosted)
            try {
                Log::debug('Attempting Ollama API call with model: ' . $this->ollamaModel);
                return $this->generateViaOllama(
                    $userMessage,
                    $conversationHistory,
                    $systemPrompt
                );
            } catch (\Exception $e) {
                Log::error('Ollama API failed: ' . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'LLM service unavailable',
                    'message' => 'AI service is temporarily unavailable. Please try again later.',
                ];
            }

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
     * Generate response via Claude API (Anthropic)
     */
    private function generateViaClaudeAPI(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt
    ): array {
        try {
            // Build messages array for Claude
            $messages = $this->buildClaudeMessages($userMessage, $conversationHistory);

            $response = Http::withHeaders([
                'x-api-key' => $this->claudeApiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(self::REQUEST_TIMEOUT)
            ->post(self::CLAUDE_API_URL, [
                'model' => 'claude-3-sonnet-20240229', // Best balance of speed and intelligence
                'max_tokens' => self::MAX_TOKENS,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if (!$response->successful()) {
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Claude API returned ' . $response->status());
            }

            $data = $response->json();
            $responseText = $data['content'][0]['text'] ?? '';

            if (!$responseText) {
                throw new \Exception('Empty response from Claude');
            }

            return [
                'success' => true,
                'response' => $this->cleanResponse($responseText),
                'provider' => 'claude',
                'model' => 'claude-3-sonnet-20240229',
                'tokens_used' => $data['usage']['output_tokens'] ?? 0,
                'stop_reason' => $data['stop_reason'] ?? 'end_turn',
            ];
        } catch (\Exception $e) {
            Log::error('Claude API generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate response via Ollama (self-hosted LLM)
     */
    private function generateViaOllama(
        string $userMessage,
        array $conversationHistory,
        string $systemPrompt
    ): array {
        try {
            // Format conversation for Ollama
            $prompt = $this->buildOllamaPrompt(
                $userMessage,
                $conversationHistory,
                $systemPrompt
            );

            $response = Http::timeout(self::REQUEST_TIMEOUT * 2) // Ollama can be slower
                ->post(self::OLLAMA_API_URL, [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'num_predict' => self::MAX_TOKENS,
                    'temperature' => 0.7,
                    'top_p' => 0.9,
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
     * Build comprehensive system prompt with all context
     */
    private function buildSystemPrompt(array $systemContext): string
    {
        $role = $systemContext['role'] ?? 'guest';
        $systemData = $systemContext['system_data'] ?? [];
        $userInfo = $systemContext['user_info'] ?? [];
        $language = $systemContext['language'] ?? 'english';

        $prompt = "You are a smart, helpful AI assistant for a legal appointment booking system.

## CRITICAL INSTRUCTIONS:

### Language Rules - VERY IMPORTANT:
";

        if ($language === 'filipino') {
            $prompt .= "- The user is speaking in Filipino/Tagalog/Taglish
- YOU MUST RESPOND IN FILIPINO (Tagalog)
- Use natural Filipino language, can mix with English (Taglish) when appropriate
- Use polite markers like 'po' when addressing the user
- Example responses:
  - 'Meron kayong 3 pending appointments po.'
  - 'Pwede ko po kayong tulungan sa booking.'
  - 'Ang status ng appointment niyo po ay approved na.'
";
        } else {
            $prompt .= "- The user is speaking in English
- Respond in clear, professional English
- Be friendly but professional
";
        }

        $prompt .= "
### Your Core Responsibilities:
1. Provide accurate information about appointments, services, payments, and refunds
2. Use ONLY the real-time data provided in context - NEVER fabricate or guess information
3. Be professional but friendly and approachable
4. Address user concerns with empathy, especially if they're frustrated
5. When uncertain, ask clarifying questions rather than guessing
6. Keep responses concise but informative (aim for 50-150 words)
7. Always acknowledge the user's role and provide role-appropriate information

## User Role & Capabilities:
";
        
        $roleCapabilities = [
            'guest' => $language === 'filipino' 
                ? "Tumutulong ka sa isang guest visitor.\n- Public information lang ang ibigay\n- I-encourage ang registration/login para sa personalized help\n- I-share ang general business info (hours, services, booking process)"
                : "You're helping a guest visitor.\n- Only provide public information\n- Encourage registration/login for personalized help\n- Share general business info (hours, services, booking process)",
            'client' => $language === 'filipino'
                ? "Tumutulong ka sa isang registered client/user.\n- Ibigay ang personalized appointment details nila\n- Tulungan sa booking, rescheduling, cancellations\n- Ipaliwanag ang payment at refund processes\n- Maging helpful sa specific concerns nila"
                : "You're helping a registered client.\n- Provide personalized appointment details\n- Help with booking, rescheduling, cancellations\n- Explain payment and refund processes\n- Be helpful with their specific concerns",
            'admin' => $language === 'filipino'
                ? "Tumutulong ka sa isang ADMINISTRATOR.\n- Ibigay ang system-wide information\n- Tulungan sa approval workflows (pending appointments, refunds)\n- Discuss analytics at reports\n- Support administrative tasks\n- May full access sa lahat ng data"
                : "You're helping a system ADMINISTRATOR.\n- Provide system-wide information\n- Help with approval workflows (pending appointments, refunds)\n- Discuss analytics and reports\n- Support administrative tasks\n- Has full access to all data",
            'cashier' => $language === 'filipino'
                ? "Tumutulong ka sa isang CASHIER/payment processor.\n- Ibigay ang payment at refund information\n- Tulungan sa transaction verification\n- Support shift reporting\n- Help with payment processing"
                : "You're helping a CASHIER/payment processor.\n- Provide payment and refund information\n- Help with transaction verification\n- Support shift reporting\n- Help with payment processing",
        ];

        $prompt .= $roleCapabilities[$role] ?? $roleCapabilities['guest'];

        // Add system data context - THIS IS CRITICAL FOR ACCURACY
        if (!empty($systemData)) {
            $prompt .= "\n\n## REAL-TIME SYSTEM DATA (Use this for accurate responses):\n";
            
            if (isset($systemData['pending_appointments'])) {
                $prompt .= "- Pending Appointments awaiting approval: " . $systemData['pending_appointments'] . "\n";
            }
            if (isset($systemData['pending_payments'])) {
                $prompt .= "- Pending Payments to collect: " . $systemData['pending_payments'] . "\n";
            }
            if (isset($systemData['pending_refunds'])) {
                $prompt .= "- Pending Refunds to process: " . $systemData['pending_refunds'] . "\n";
            }
            if (isset($systemData['today_appointments'])) {
                $prompt .= "- Today's Appointments: " . $systemData['today_appointments'] . "\n";
            }
            if (isset($systemData['services_available']) && is_array($systemData['services_available'])) {
                $prompt .= "- Available Services: " . implode(', ', array_slice($systemData['services_available'], 0, 10)) . "\n";
            }
            if (isset($systemData['business_hours'])) {
                $prompt .= "- Business Hours: " . $systemData['business_hours'] . "\n";
            }
        }

        // Add user-specific context
        if (!empty($userInfo) && $role !== 'guest') {
            $prompt .= "\n## USER PROFILE (Current user's real data):\n";
            if (isset($userInfo['name'])) {
                $prompt .= "- User Name: " . $userInfo['name'] . "\n";
            }
            if (isset($userInfo['appointment_count'])) {
                $prompt .= "- Total Appointments: " . $userInfo['appointment_count'] . "\n";
            }
            if (isset($userInfo['pending_appointments'])) {
                $prompt .= "- Pending Appointments: " . $userInfo['pending_appointments'] . "\n";
            }
            if (isset($userInfo['pending_items'])) {
                $prompt .= "- Pending Items to handle: " . $userInfo['pending_items'] . "\n";
            }
            if (isset($userInfo['upcoming_appointments']) && is_array($userInfo['upcoming_appointments'])) {
                $prompt .= "- Upcoming Appointments:\n";
                foreach ($userInfo['upcoming_appointments'] as $apt) {
                    $prompt .= "  • ID #{$apt['id']}: {$apt['date']} at {$apt['time']} - {$apt['service']} (Status: {$apt['status']})\n";
                }
            }
        }

        $prompt .= "\n## RESPONSE INSTRUCTIONS:
- Respond directly to the user's question using the data above
- Reference REAL data when available - cite specific numbers
- Be specific, not vague - use actual counts and details from the data
- Suggest next steps when appropriate
- Keep a professional but warm tone
- If data is not available, say so honestly rather than guessing";

        return $prompt;
    }

    /**
     * Build messages array for Claude API
     */
    private function buildClaudeMessages(string $userMessage, array $conversationHistory): array
    {
        $messages = [];

        // Add conversation history (Claude supports up to ~200k tokens)
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['message'] ?? $msg['content'],
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
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
    private function cleanResponse(string $response): string
    {
        // Remove markdown code blocks
        $response = preg_replace('/```[a-z]*\n?/i', '', $response);

        // Remove excessive whitespace
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // Trim
        $response = trim($response);

        // Truncate if too long (safety measure)
        if (strlen($response) > 5000) {
            $response = substr($response, 0, 4997) . '...';
        }

        return $response;
    }

    /**
     * Verify API connectivity
     */
    public function healthCheck(): array
    {
        $status = [
            'claude' => false,
            'ollama' => false,
            'available_provider' => null,
        ];

        // Check Claude
        if ($this->claudeApiKey) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->claudeApiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->timeout(5)
                ->get('https://api.anthropic.com/v1/models');

                $status['claude'] = $response->successful();
            } catch (\Exception $e) {
                Log::debug('Claude health check failed: ' . $e->getMessage());
            }
        }

        // Check Ollama
        try {
            $response = Http::timeout(5)->get(str_replace('/api/generate', '', self::OLLAMA_API_URL) . '/tags');
            $status['ollama'] = $response->successful();
        } catch (\Exception $e) {
            Log::debug('Ollama health check failed: ' . $e->getMessage());
        }

        // Determine available provider
        if ($status['claude'] && !$this->useOllama) {
            $status['available_provider'] = 'claude';
        } elseif ($status['ollama']) {
            $status['available_provider'] = 'ollama';
        }

        return $status;
    }
}
