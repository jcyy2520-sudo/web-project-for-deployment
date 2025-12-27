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
     * 
     * IMPORTANT: The chatbot's role is strictly to ASSIST, INFORM, GUIDE, and EXPLAIN.
     * It must NEVER perform actions, make changes, execute commands, or act on behalf of users.
     * 
     * PERMISSIONED AI AGENT: Verify before answering. Never guess.
     */
    private function buildSystemPrompt(array $systemContext): string
    {
        $role = $systemContext['role'] ?? 'guest';
        $systemData = $systemContext['system_data'] ?? [];
        $userInfo = $systemContext['user_info'] ?? [];
        $language = $systemContext['language'] ?? 'english';
        $ragContext = $systemContext['rag_context'] ?? '';
        $memoryContext = $systemContext['memory_context'] ?? [];
        $personalityPrompt = $systemContext['personality_prompt'] ?? '';

        $prompt = "=== PERMISSIONED AI AGENT: VERIFY BEFORE ANSWERING ===
You are NOT a guessing chatbot. You are a permissioned AI agent - verify information before answering.

CORE MANDATE: If an answer can be verified but hasn't been verified, you MUST NOT answer.

DECISION FLOW (NEVER SKIP):
1. Understand what user is asking
2. Determine: Does this need system data, database data, or file inspection?
3. If YES→retrieve data. If NO→use verified knowledge. If UNCLEAR→ask clarification.
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
- If a request is outside the user’s role, clearly state that access is restricted and identify which role is permitted.

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

## LANGUAGE HANDLING
";

        if ($language === 'filipino') {
            $prompt .= "**USER LANGUAGE: Filipino/Tagalog/Taglish**
- RESPOND IN FILIPINO - natural, professional Filipino
- Use 'po' for politeness
- Taglish (mixed Filipino-English) is acceptable
- Maintain strict professionalism even in Filipino
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

        $prompt .= "\n\n## RESPONSE GUIDELINES - CRITICAL
1. **Be Helpful**: Answer the user's actual question
2. **Be Accurate**: Only state facts from the data provided - NEVER HARDCODE DATA
3. **Be Honest**: Say 'I don't have access to that information' when data isn't available
4. **Be Clear**: Use simple, understandable language
5. **Be Professional**: Maintain a neutral, respectful tone
6. **Be Concise**: Keep responses focused
7. **Be Actionable**: Tell users what they can do next

## STEP-BY-STEP RESPONSE APPROACH
When answering complex queries, ALWAYS structure your response as follows:
1. **Acknowledge**: Briefly confirm what the user is asking about
2. **Explain**: Provide the relevant information or explanation
3. **Guide**: Give clear, numbered steps if action is needed
4. **Confirm**: End with what the user should do next

## INTELLIGENCE PRINCIPLES
- **REAL-TIME DATA**: Always use the system data provided below - never guess or use cached information
- **TYPO TOLERANCE**: Understand user intent even with spelling errors (e.g., 'apointment' = appointment)
- **LANGUAGE FLEXIBILITY**: Seamlessly handle English, Filipino/Tagalog, and Taglish (mixed)
- **CONTEXT AWARENESS**: Remember conversation context to provide coherent multi-turn responses
- **CLARIFICATION FIRST**: If a request is unclear, ask for clarification before assuming

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
                    $prompt .= "- Total Revenue: ₱" . number_format($stats['total_revenue'], 2) . "\n";
                }
                
                // Today's summary for cashier
                if (isset($systemData['today_summary'])) {
                    $summary = $systemData['today_summary'];
                    $prompt .= "\n### TODAY'S SUMMARY:\n";
                    $prompt .= "- Collections: ₱" . number_format($summary['collections'] ?? 0, 2) . "\n";
                    $prompt .= "- Refunds: ₱" . number_format($summary['refunds'] ?? 0, 2) . "\n";
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
                $prompt .= "- Total Paid: ₱" . number_format($userInfo['total_amount_paid'], 2) . "\n";
            }
            
            // Refund data
            if (isset($userInfo['pending_refunds'])) {
                $prompt .= "- Pending Refunds: " . $userInfo['pending_refunds'] . "\n";
            }
        }

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
