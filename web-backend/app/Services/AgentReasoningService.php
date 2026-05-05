<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AgentReasoningService — LLM-Based Autonomous Reasoning & Tool Execution
 *
 * Implements a ReAct (Reasoning + Acting) loop where the LLM:
 *   1. Analyzes the user message and available context
 *   2. Decides whether to call a tool or respond directly
 *   3. If a tool is needed, extracts tool name + arguments from LLM output
 *   4. Executes the tool and feeds results back to the LLM
 *   5. LLM generates the final user-facing response incorporating tool results
 *
 * This replaces pattern-matching intent detection with genuine LLM reasoning.
 * The LLM sees all available tools and autonomously decides which to use.
 *
 * Safety:
 *   - Maximum reasoning steps bounded (prevents infinite loops)
 *   - Destructive tool calls require explicit user confirmation
 *   - All tool calls are validated and audited
 *   - Tool results are sanitized before feeding back to LLM
 */
class AgentReasoningService
{
    private LLMService $llmService;
    private AgentToolRegistry $toolRegistry;
    private ChatbotSecurityService $securityService;

    private const TOOL_CALL_PATTERN = '/```tool_call\s*\n?\s*(\{.*?\})\s*\n?\s*```/s';
    private const XML_TOOL_CALL_PATTERN = '/<([a-z_]+)>\s*((?:<parameter=[a-z_]+>.*?<\/parameter>\s*)+)<\/\1>/is';

    public function __construct(
        LLMService $llmService,
        AgentToolRegistry $toolRegistry,
        ChatbotSecurityService $securityService
    ) {
        $this->llmService = $llmService;
        $this->toolRegistry = $toolRegistry;
        $this->securityService = $securityService;
    }

    /**
     * Run the ReAct reasoning loop.
     *
     * @param string $userMessage     The user's message
     * @param string $systemPrompt    The full system prompt (including tool definitions)
     * @param array $conversationHistory Previous messages
     * @param int|null $userId        Authenticated user ID
     * @param string $role            User role
     * @param array $pendingConfirmation Any pending destructive action awaiting confirmation
     * @param string|null $actorKey   Session-scoped actor key for guest confirmation isolation
     * @return array ['response' => string, 'tool_calls' => array, 'reasoning_steps' => int, ...]
     */
    public function reason(
        string $userMessage,
        string $systemPrompt,
        array $conversationHistory,
        ?int $userId,
        string $role,
        array $pendingConfirmation = [],
        ?string $actorKey = null
    ): array {
        $toolCalls = [];
        $step = 0;
        $toolResultContext = '';
        $pendingActorKey = $this->resolvePendingActorKey($userId, $actorKey);

        // Check for confirmation of a pending destructive action
        if (!empty($pendingConfirmation)) {
            Log::info('AgentReasoning: Found pending confirmation for action', [
                'user_id' => $userId,
                'pending_tool' => $pendingConfirmation['tool'] ?? 'unknown',
            ]);
            $confirmResult = $this->handleConfirmation($userMessage, $pendingConfirmation, $userId, $role);
            if ($confirmResult !== null) {
                Log::info('AgentReasoning: Confirmation was handled', [
                    'user_id' => $userId,
                    'was_confirmed' => $confirmResult['confirmed_action'] ?? false,
                    'was_cancelled' => $confirmResult['cancelled'] ?? false,
                ]);
                return $confirmResult;
            }
            // User didn't say yes or no — re-store the pending confirmation so it isn't lost.
            // The cache was already consumed by getPendingConfirmation(), so we must put it back.
            Log::debug('AgentReasoning: No clear confirmation/denial detected, re-storing pending confirmation', [
                'user_id' => $userId,
                'pending_tool' => $pendingConfirmation['tool'] ?? 'unknown',
            ]);
            $this->storePendingToolCall($userId, $pendingConfirmation['tool'], $pendingConfirmation['arguments'], $pendingActorKey);
        }

        // SECURITY: Neutralize any tool_call blocks injected in the user message
        $sanitizedMessage = $this->neutralizeToolCallInjection($userMessage);

        // Get native tool definitions for the user's role
        $nativeTools = $this->toolRegistry->getNativeToolDefinitions($role);

        Log::info('AgentReasoning: Starting reasoning loop', [
            'user_id' => $userId,
            'role' => $role,
            'native_tools_count' => count($nativeTools),
            'native_tool_names' => array_map(fn($t) => $t['name'], $nativeTools),
        ]);

        // Build raw-format messages from conversation history
        $rawMessages = $this->buildRawMessages($conversationHistory, $sanitizedMessage);

        $maxSteps = (int) config('chatbot_unified.agent.max_reasoning_steps', 5);

        while ($step < $maxSteps) {
            $step++;

            Log::debug('AgentReasoning: Step ' . $step, [
                'message_count' => count($rawMessages),
                'has_native_tools' => !empty($nativeTools),
            ]);

            // Generate LLM response with native tool definitions
            $llmResult = $this->llmService->generateResponse(
                $sanitizedMessage, // user message (used by non-native providers as fallback)
                [], // conversation history handled by raw_messages
                [
                    'system_prompt' => $systemPrompt,
                    'role' => $role,
                    'skip_internal_prompt' => true,
                    'native_tools' => $nativeTools,
                    'raw_messages' => $rawMessages,
                ]
            );

            if (!$llmResult['success']) {
                return [
                    'response' => null,
                    'tool_calls' => $toolCalls,
                    'reasoning_steps' => $step,
                    'llm_failed' => true,
                    'provider' => $llmResult['provider'] ?? 'unknown',
                ];
            }

            $llmResponse = $llmResult['response'] ?? '';
            $nativeToolCalls = $llmResult['tool_calls'] ?? [];
            $rawContent = $llmResult['raw_content'] ?? [];

            Log::info('AgentReasoning: LLM response received', [
                'step' => $step,
                'provider' => $llmResult['provider'] ?? 'unknown',
                'has_native_tools' => !empty($nativeToolCalls),
                'native_tool_count' => count($nativeToolCalls),
                'native_tool_names' => array_map(fn($t) => $t['name'] ?? 'unknown', $nativeToolCalls),
                'response_length' => strlen($llmResponse),
            ]);

            // ── NATIVE TOOL-USE PATH (Claude) ──
            if (!empty($nativeToolCalls)) {
                Log::info('AgentReasoning: Native tool call detected', [
                    'step' => $step,
                    'tools' => array_map(fn($t) => $t['name'], $nativeToolCalls),
                ]);

                // Process the first tool call (one at a time for safety)
                $nativeTool = $nativeToolCalls[0];
                $toolName = $nativeTool['name'];
                $toolArgs = $nativeTool['input'] ?? [];
                $toolUseId = $nativeTool['id'];

                // Coerce integer-typed args that LLMs sometimes send as strings (e.g. "1" → 1)
                $toolArgs = $this->coerceToolArgTypes($toolName, $toolArgs);

                // Security: validate tool name
                if (!preg_match('/^[a-z_]+$/', $toolName) || !$this->toolRegistry->toolExists($toolName)) {
                    Log::warning('AgentReasoning: Invalid native tool name', ['tool' => $toolName]);
                    $assistantText = !empty(trim($llmResponse)) ? $llmResponse : "Attempting tool: {$toolName}";
                    $rawMessages[] = ['role' => 'assistant', 'content' => $assistantText];
                    $rawMessages[] = [
                        'role' => 'user',
                        'content' => "Error: Unknown tool '{$toolName}'. Use only the tools provided.",
                    ];
                    continue;
                }

                // SECURITY: Permission check — validate role can use this tool before ANY action.
                // Prevents privilege escalation even if the LLM is tricked into calling a forbidden tool.
                if (!$this->toolRegistry->canRoleUseTool($role, $toolName)) {
                    Log::warning('AgentReasoning: Permission denied on native tool call', [
                        'tool' => $toolName,
                        'role' => $role,
                        'user_id' => $userId,
                    ]);
                    $assistantText = !empty(trim($llmResponse)) ? $llmResponse : "Attempting tool: {$toolName}";
                    $rawMessages[] = ['role' => 'assistant', 'content' => $assistantText];
                    $rawMessages[] = [
                        'role' => 'user',
                        'content' => "PERMISSION DENIED: The role '{$role}' cannot use tool '{$toolName}'. " .
                            "For guests: inform the user they must log in or register to perform this action. " .
                            "Do NOT attempt this tool again.",
                    ];
                    continue;
                }

                // Check if destructive — pause for confirmation
                if ($this->toolRegistry->isDestructiveTool($toolName)) {

                    // PRE-VALIDATE booking before showing confirmation
                    // This prevents showing a broken confirmation (₱0.00, no services) that will fail anyway
                    if ($toolName === 'book_appointment') {
                        $preValidation = $this->toolRegistry->validateBookingSlot($toolArgs, $userId ?? 0);
                        if (!$preValidation['valid']) {
                            $bookingDecision = $this->analyzeBookingDecision(
                                $userMessage,
                                $toolArgs,
                                $userId,
                                $role,
                                $preValidation['error'] ?? 'Validation failed.'
                            );
                            $this->logBookingDecision($userMessage, $toolArgs, $userId, $role, $bookingDecision);

                            Log::info('AgentReasoning: Booking pre-validation failed, feeding error back to LLM', [
                                'user_id' => $userId,
                                'error' => $preValidation['error'],
                                'tool_args' => $toolArgs,
                            ]);
                            // Feed the validation error back to the LLM so it can ask for missing info
                            $assistantText = !empty(trim($llmResponse)) ? $llmResponse : "Attempting to book appointment...";
                            $rawMessages[] = ['role' => 'assistant', 'content' => $assistantText];
                            $rawMessages[] = [
                                'role' => 'user',
                                'content' => $this->buildBookingClarificationInstruction($preValidation['error']),
                            ];
                            continue; // Let LLM retry with corrected params
                        }

                        $bookingDecision = $this->analyzeBookingDecision($userMessage, $toolArgs, $userId, $role);
                        $this->logBookingDecision($userMessage, $toolArgs, $userId, $role, $bookingDecision);

                        if ($bookingDecision['execute_immediately']) {
                            return $this->executeToolImmediately(
                                $toolName,
                                $toolArgs,
                                $userId,
                                $role,
                                $toolCalls,
                                $step,
                                $llmResult
                            );
                        }
                    }

                    $confirmKey = $this->storePendingToolCall($userId, $toolName, $toolArgs, $pendingActorKey);

                    Log::info('AgentReasoning: Destructive tool requires confirmation', [
                        'user_id' => $userId,
                        'tool_name' => $toolName,
                        'confirm_key' => $confirmKey,
                        'step' => $step,
                    ]);

                    // For booking, generate a rich confirmation with price details
                    if ($toolName === 'book_appointment') {
                        $explanation = $this->buildBookingConfirmation($toolArgs, $llmResponse);
                    } else {
                        $explanation = !empty(trim($llmResponse))
                            ? trim($llmResponse)
                            : "I'd like to perform this action for you. Please confirm to proceed.";
                    }

                    return [
                        'response' => $explanation,
                        'requires_confirmation' => true,
                        'confirmation_key' => $confirmKey,
                        'pending_tool' => $toolName,
                        'pending_args' => $toolArgs,
                        'tool_calls' => $toolCalls,
                        'reasoning_steps' => $step,
                        'provider' => $llmResult['provider'] ?? 'unknown',
                    ];
                }

                // Execute non-destructive tool
                $toolResult = $this->toolRegistry->executeTool($toolName, $toolArgs, $userId ?? 0, $role);
                $toolCalls[] = [
                    'tool' => $toolName,
                    'arguments' => $toolArgs,
                    'result' => $toolResult,
                ];

                $toolResultJson = json_encode($toolResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (strlen($toolResultJson) > 4000) {
                    $toolResultJson = substr($toolResultJson, 0, 4000) . "\n... (truncated)";
                }

                // Feed tool result back as plain text messages (works across all providers)
                $assistantText = !empty(trim($llmResponse)) ? $llmResponse : "Calling tool: {$toolName}";
                $rawMessages[] = ['role' => 'assistant', 'content' => $assistantText];
                $rawMessages[] = [
                    'role' => 'user',
                    'content' => "Tool `{$toolName}` executed. Result:\n```json\n{$toolResultJson}\n```\nRespond to the user with the specific data from this result. Do NOT call another tool unless needed.",
                ];

                continue; // Let the LLM process the tool result
            }

            // ── TEXT-BASED FALLBACK PATH (non-Claude providers) ──
            // Check for text-based tool_call blocks (used by OpenAI, Llama, etc.)
            $parsedToolCall = $this->parseToolCall($llmResponse);

            if ($parsedToolCall === null) {
                // No tool call — check if the LLM hallucinated an action
                if ($step < $maxSteps && $this->detectsHallucinatedAction($llmResponse, $toolCalls)) {
                    Log::warning('AgentReasoning: Detected hallucinated action without tool_call', [
                        'step' => $step,
                        'response_snippet' => mb_substr($llmResponse, 0, 200),
                    ]);
                    $rawMessages[] = ['role' => 'assistant', 'content' => $llmResponse];
                    $rawMessages[] = ['role' => 'user', 'content' =>
                        "SYSTEM OVERRIDE: You described performing an action but did NOT actually call a tool. " .
                        "The action was NOT performed — the database was NOT changed. " .
                        "You MUST use the tool to actually execute the action. Call the appropriate tool now."
                    ];
                    continue;
                }

                // LLM is responding directly to the user
                $cleanResponse = $this->cleanResponse($llmResponse);
                
                // Extract action_buttons from the last successful tool result
                $actionButtons = $this->extractActionButtonsFromToolCalls($toolCalls);
                
                return [
                    'response' => $cleanResponse,
                    'tool_calls' => $toolCalls,
                    'action_buttons' => $actionButtons,
                    'reasoning_steps' => $step,
                    'llm_failed' => false,
                    'provider' => $llmResult['provider'] ?? 'unknown',
                    'model' => $llmResult['model'] ?? 'unknown',
                    'tokens_used' => $llmResult['tokens_used'] ?? 0,
                ];
            }

            // Text-based tool call detected — validate and execute
            $toolName = $parsedToolCall['tool'] ?? '';
            $toolArgs = $parsedToolCall['arguments'] ?? [];

            if (!preg_match('/^[a-z_]+$/', $toolName)) {
                Log::warning('AgentReasoning: Invalid tool name attempted', ['tool' => $toolName]);
                $rawMessages[] = ['role' => 'assistant', 'content' => $llmResponse];
                $rawMessages[] = ['role' => 'user', 'content' => "Tool error: Invalid tool name '{$toolName}'."];
                continue;
            }

            // SECURITY: Permission check on text-based path — prevents guests from
            // triggering destructive tools via text-based tool_call blocks.
            if (!$this->toolRegistry->canRoleUseTool($role, $toolName)) {
                Log::warning('AgentReasoning: Permission denied on text-based tool call', [
                    'tool' => $toolName,
                    'role' => $role,
                    'user_id' => $userId,
                ]);
                $rawMessages[] = ['role' => 'assistant', 'content' => $llmResponse];
                $rawMessages[] = ['role' => 'user', 'content' =>
                    "PERMISSION DENIED: The role '{$role}' cannot use tool '{$toolName}'. " .
                    "For guests: inform the user they must log in or register to perform this action. " .
                    "Do NOT attempt this tool again."
                ];
                continue;
            }

            if ($this->toolRegistry->isDestructiveTool($toolName)) {
                if ($toolName === 'book_appointment') {
                    $preValidation = $this->toolRegistry->validateBookingSlot($toolArgs, $userId ?? 0);
                    if (!$preValidation['valid']) {
                        $bookingDecision = $this->analyzeBookingDecision(
                            $userMessage,
                            $toolArgs,
                            $userId,
                            $role,
                            $preValidation['error'] ?? 'Validation failed.'
                        );
                        $this->logBookingDecision($userMessage, $toolArgs, $userId, $role, $bookingDecision);

                        Log::info('AgentReasoning: Booking pre-validation failed on text-based path, feeding error back to LLM', [
                            'user_id' => $userId,
                            'error' => $preValidation['error'],
                            'tool_args' => $toolArgs,
                        ]);
                        $assistantText = $this->extractPreToolText($llmResponse);
                        $rawMessages[] = [
                            'role' => 'assistant',
                            'content' => !empty(trim($assistantText)) ? $assistantText : 'Attempting to book appointment...',
                        ];
                        $rawMessages[] = [
                            'role' => 'user',
                            'content' => $this->buildBookingClarificationInstruction($preValidation['error']),
                        ];
                        continue;
                    }

                    $bookingDecision = $this->analyzeBookingDecision($userMessage, $toolArgs, $userId, $role);
                    $this->logBookingDecision($userMessage, $toolArgs, $userId, $role, $bookingDecision);

                    if ($bookingDecision['execute_immediately']) {
                        return $this->executeToolImmediately(
                            $toolName,
                            $toolArgs,
                            $userId,
                            $role,
                            $toolCalls,
                            $step,
                            $llmResult
                        );
                    }
                }

                $confirmKey = $this->storePendingToolCall($userId, $toolName, $toolArgs, $pendingActorKey);

                Log::info('AgentReasoning: Destructive tool detected (text-based path), requires confirmation', [
                    'user_id' => $userId,
                    'tool_name' => $toolName,
                    'confirm_key' => $confirmKey,
                    'step' => $step,
                ]);

                if ($toolName === 'book_appointment') {
                    $explanation = $this->buildBookingConfirmation($toolArgs, $this->extractPreToolText($llmResponse));
                } else {
                    $explanation = $this->extractPreToolText($llmResponse);
                }

                return [
                    'response' => $explanation,
                    'requires_confirmation' => true,
                    'confirmation_key' => $confirmKey,
                    'pending_tool' => $toolName,
                    'pending_args' => $toolArgs,
                    'tool_calls' => $toolCalls,
                    'reasoning_steps' => $step,
                    'provider' => $llmResult['provider'] ?? 'unknown',
                ];
            }

            // Execute non-destructive tool (text-based path)
            $toolResult = $this->toolRegistry->executeTool($toolName, $toolArgs, $userId ?? 0, $role);
            $toolCalls[] = [
                'tool' => $toolName,
                'arguments' => $toolArgs,
                'result' => $toolResult,
            ];

            $toolResultContext = json_encode($toolResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (strlen($toolResultContext) > 4000) {
                $toolResultContext = substr($toolResultContext, 0, 4000) . "\n... (truncated)";
            }

            $rawMessages[] = ['role' => 'assistant', 'content' => $llmResponse];
            $rawMessages[] = ['role' => 'user', 'content' => "Tool `{$toolName}` executed successfully. Result:\n```json\n{$toolResultContext}\n```\nIMPORTANT: Now respond to the user with the results. Present SPECIFIC data from the tool output (appointment IDs, dates, times, names, amounts, statuses). Do NOT call another tool unless the user's original question requires additional data."];
        }

        // Max steps reached — return what we have
        Log::warning('AgentReasoning: Max reasoning steps reached', ['steps' => $step]);
        return [
            'response' => "I found some information but couldn't fully complete my reasoning. Here's what I have so far based on the tools I called.",
            'tool_calls' => $toolCalls,
            'reasoning_steps' => $step,
            'max_steps_reached' => true,
        ];
    }

    /**
     * Build raw-format messages array from conversation history.
     * Handles the proper alternation required by many AI APIs.
     *
     * IMPORTANT: The controller saves the user message to DB BEFORE calling
     * processMessage(), so the conversation history may already contain the
     * current user message as the last entry. We detect and skip it to avoid
     * duplication. The current user message is ALWAYS added as a separate,
     * distinct entry so the LLM clearly identifies it as the latest question.
     */
    private function buildRawMessages(array $conversationHistory, string $currentUserMessage): array
    {
        $messages = [];
        $lastRole = null;

        // Check if the last message in history is the current user message (duplicate detection)
        $historyCount = count($conversationHistory);
        $skipLastIfDuplicate = false;
        if ($historyCount > 0) {
            $lastMsg = $conversationHistory[$historyCount - 1];
            $lastContent = trim($lastMsg['message'] ?? $lastMsg['content'] ?? '');
            $lastMsgRole = ($lastMsg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            if ($lastMsgRole === 'user' && $lastContent === trim($currentUserMessage)) {
                $skipLastIfDuplicate = true;
            }
        }

        foreach ($conversationHistory as $idx => $msg) {
            // Skip the last message if it's a duplicate of the current user message
            if ($skipLastIfDuplicate && $idx === $historyCount - 1) {
                continue;
            }

            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $content = $msg['message'] ?? $msg['content'] ?? '';

            if (empty($content)) continue;

            // Claude requires strict alternation — merge consecutive same-role messages
            if ($role === $lastRole && !empty($messages)) {
                $lastIdx = count($messages) - 1;
                if (is_string($messages[$lastIdx]['content'])) {
                    $messages[$lastIdx]['content'] .= "\n" . $content;
                }
            } else {
                $messages[] = ['role' => $role, 'content' => $content];
                $lastRole = $role;
            }
        }

        // ALWAYS add the current user message as a separate entry.
        // If the last history message was also from a user (rare, but possible after
        // deduplication), insert a brief assistant placeholder to maintain alternation.
        if ($lastRole === 'user' && !empty($messages)) {
            $messages[] = ['role' => 'assistant', 'content' => 'Understood. How can I help?'];
            $lastRole = 'assistant';
        }
        $messages[] = ['role' => 'user', 'content' => $currentUserMessage];

        return $messages;
    }

    /**
     * Parse a tool call from the LLM response.
     * Handles multiple formats LLMs may produce:
     *   1. ```tool_call\n{"tool": "...", "arguments": {...}}\n```  (standard)
     *   2. tool_call\n{"tool": "...", "arguments": {...}}           (fences stripped)
     *   3. _call\n{"name": "...", "parameters": {...}}              (LLM variant)
     *   4. Bare JSON {"tool"/"name": "...", "arguments"/"parameters": {...}}
        *   5. <tool_name><parameter=key>value</parameter>...</tool_name>     (XML-like variant)
     * Returns null if no tool call is found.
     */
    private function parseToolCall(string $response): ?array
    {
        // Pattern 1: Standard ```tool_call { ... } ``` format (primary expected format)
        if (preg_match(self::TOOL_CALL_PATTERN, $response, $matches)) {
            $result = $this->validateAndNormalizeToolCallJson($matches[1]);
            if ($result) return $result;
        }

        // Pattern 2: tool_call without code fences (when LLMService::cleanResponse stripped them)
        if (preg_match('/\btool_call\s*\n\s*(\{.*?\})\s*$/ms', $response, $matches)) {
            $result = $this->validateAndNormalizeToolCallJson($matches[1]);
            if ($result) return $result;
        }

        // Pattern 3: _call format some LLMs produce (with "name"/"parameters" keys)
        if (preg_match('/_call\s*\n\s*(\{.*\})\s*$/ms', $response, $matches)) {
            $result = $this->validateAndNormalizeToolCallJson($matches[1]);
            if ($result) return $result;
        }

        // Pattern 4: JSON block inside code fences with "tool"/"name" and "arguments"/"parameters"
        if (preg_match('/```(?:json)?\s*\n?\s*(\{\s*"(?:tool|name)"\s*:.*?\})\s*\n?\s*```/s', $response, $matches)) {
            $result = $this->validateAndNormalizeToolCallJson($matches[1]);
            if ($result) return $result;
        }

        // Pattern 5: Bare JSON with recognizable tool-call structure at end of response
        if (preg_match('/(\{\s*"(?:tool|name)"\s*:\s*"[^"]+"\s*,\s*"(?:arguments|parameters)"\s*:\s*\{.*?\}\s*\})\s*$/s', $response, $matches)) {
            $result = $this->validateAndNormalizeToolCallJson($matches[1]);
            if ($result) return $result;
        }

        // Pattern 6: XML-like tool wrapper with parameter tags
        $result = $this->parseXmlLikeToolCall($response);
        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Parse XML-like tool call blocks such as:
     * <get_available_slots>
     *   <parameter=service_id>Document Review</parameter>
     *   <parameter=date>2026-05-04</parameter>
     * </get_available_slots>
     */
    private function parseXmlLikeToolCall(string $response): ?array
    {
        if (!preg_match(self::XML_TOOL_CALL_PATTERN, $response, $matches)) {
            return null;
        }

        $toolName = $matches[1] ?? null;
        $parameterBlock = $matches[2] ?? '';

        if (!is_string($toolName) || $toolName === '') {
            return null;
        }

        if (!$this->toolRegistry->toolExists($toolName)) {
            Log::warning('AgentReasoning: LLM requested unknown XML-like tool', ['tool' => $toolName]);
            return null;
        }

        $arguments = [];
        if (preg_match_all('/<parameter=([a-z_]+)>(.*?)<\/parameter>/is', $parameterBlock, $parameterMatches, PREG_SET_ORDER)) {
            foreach ($parameterMatches as $parameterMatch) {
                $key = trim((string) ($parameterMatch[1] ?? ''));
                $value = trim(html_entity_decode(strip_tags((string) ($parameterMatch[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($key === '') {
                    continue;
                }

                $arguments[$key] = $value;
            }
        }

        return ['tool' => $toolName, 'arguments' => $arguments];
    }

    /**
     * Validate and normalize a JSON string into a standardized tool call array.
     * Accepts both {"tool": ..., "arguments": ...} and {"name": ..., "parameters": ...}.
     */
    private function validateAndNormalizeToolCallJson(string $jsonStr): ?array
    {
        try {
            // Clean potential LLM artifacts: trailing commas, comments
            $cleanedJson = preg_replace('/,\s*}/', '}', $jsonStr);
            $cleanedJson = preg_replace('/,\s*]/', ']', $cleanedJson);
            $parsed = json_decode($cleanedJson, true, 10, JSON_THROW_ON_ERROR);

            // Normalize: accept both "tool"/"name" and "arguments"/"parameters"
            $toolName = $parsed['tool'] ?? $parsed['name'] ?? null;
            $arguments = $parsed['arguments'] ?? $parsed['parameters'] ?? [];

            if (!$toolName || !is_string($toolName)) {
                return null;
            }

            if (!is_array($arguments)) {
                $arguments = [];
            }

            // SECURITY: Validate tool name is in the known registry
            if (!$this->toolRegistry->toolExists($toolName)) {
                Log::warning('AgentReasoning: LLM requested unknown tool', ['tool' => $toolName]);
                return null;
            }

            // SECURITY: Validate argument values — allow scalars and flat arrays, reject nested objects
            foreach ($arguments as $key => $value) {
                if (is_scalar($value) || is_null($value)) {
                    continue; // OK: string, int, float, bool, null
                }
                if (is_array($value)) {
                    // Allow flat arrays (e.g., ["a", "b"]) but reject nested objects/arrays
                    foreach ($value as $innerVal) {
                        if (!is_scalar($innerVal) && !is_null($innerVal)) {
                            Log::warning('AgentReasoning: Nested object in tool argument rejected', ['tool' => $toolName, 'key' => $key]);
                            return null;
                        }
                    }
                    // Flatten single-element arrays that should be scalar (e.g., service_id: [1] → 1)
                    if (count($value) === 1 && array_is_list($value)) {
                        $arguments[$key] = $value[0];
                    }
                    continue;
                }
                Log::warning('AgentReasoning: Invalid tool argument type rejected', ['tool' => $toolName, 'key' => $key]);
                return null;
            }

            return ['tool' => $toolName, 'arguments' => $arguments];
        } catch (\JsonException $e) {
            Log::debug('AgentReasoning: Failed to parse tool call JSON', ['raw' => $jsonStr]);
        }
        return null;
    }

    /**
     * Extract the text before a tool_call block (the LLM's explanation).
     */
    private function extractPreToolText(string $response): string
    {
        // Try standard pattern first
        $parts = preg_split(self::TOOL_CALL_PATTERN, $response, 2);
        if (count($parts) > 1 && !empty(trim($parts[0]))) {
            return trim($parts[0]);
        }

        // Try XML-like tool wrapper
        $parts = preg_split(self::XML_TOOL_CALL_PATTERN, $response, 2);
        if (count($parts) > 1 && !empty(trim($parts[0]))) {
            return trim($parts[0]);
        }

        // Try _call and other variant patterns
        $parts = preg_split('/(?:_call|tool_call)\s*\n\s*\{/s', $response, 2);
        $text = trim($parts[0] ?? '');
        return !empty($text) ? $text : "I'd like to perform this action for you. Please confirm to proceed.";
    }

    /**
     * Build a rich confirmation message for appointment bookings showing full breakdown.
     */
    private function buildBookingConfirmation(array $toolArgs, string $llmText = ''): string
    {
        try {
            $serviceIds = $this->toolRegistry->resolveServiceIdsPublic(
                $toolArgs['service_ids'] ?? $toolArgs['service_id'] ?? []
            );
            $services = \App\Models\Service::whereIn('id', $serviceIds)->get();
            $date = $toolArgs['date'] ?? '';
            $time = $toolArgs['time'] ?? '';

            $dateFormatted = $date;
            $dayOfWeek = '';
            try {
                $parsedDate = \Carbon\Carbon::parse($date);
                $dateFormatted = $parsedDate->format('F j, Y');
                $dayOfWeek = $parsedDate->format('l');
            } catch (\Exception $e) {}

            $timeFormatted = $time;
            try {
                $timeFormatted = \Carbon\Carbon::parse($time)->format('g:i A');
            } catch (\Exception $e) {}

            $lines = ["**Please confirm your appointment:**\n"];
            $lines[] = "**Date:** {$dateFormatted}";
            $lines[] = "**Time:** {$timeFormatted}";

            $totalPrice = 0;
            if ($services->count() === 1) {
                $service = $services->first();
                $price = number_format($service->price, 2);
                $lines[] = "**Service:** {$service->name}";
                $lines[] = "**Price:** ₱{$price}";
                $totalPrice = $service->price;
            } else {
                $lines[] = "\n**Services:**";
                foreach ($services as $service) {
                    $price = number_format($service->price, 2);
                    $lines[] = "- {$service->name} — ₱{$price}";
                    $totalPrice += $service->price;
                }
            }

            $totalFormatted = number_format($totalPrice, 2);
            $lines[] = "\n**Total: ₱{$totalFormatted}**";

            return implode("\n", $lines);
        } catch (\Exception $e) {
            Log::debug('buildBookingConfirmation failed: ' . $e->getMessage());
            return !empty(trim($llmText))
                ? trim($llmText)
                : "I'd like to book this appointment for you. Please confirm to proceed.";
        }
    }

    /**
     * Extract action_buttons from executed tool results for the frontend.
     * Generates context-appropriate navigation buttons based on which tools were called.
     */
    private function extractActionButtonsFromToolCalls(array $toolCalls): array
    {
        $buttons = [];
        foreach ($toolCalls as $call) {
            $toolName = $call['tool'] ?? '';
            $result = $call['result'] ?? [];
            $success = $result['success'] ?? false;

            // Return any action_buttons explicitly set in the tool result
            if (!empty($result['action_buttons'])) {
                $buttons = array_merge($buttons, $result['action_buttons']);
                continue;
            }

            // Generate contextual buttons based on the tool that was called
            switch ($toolName) {
                case 'book_appointment':
                    if ($success) {
                        $buttons[] = ['label' => 'View My Appointments', 'route' => '/appointments', 'icon' => '📅', 'type' => 'primary'];
                    }
                    break;
                case 'cancel_appointment':
                    if ($success) {
                        $buttons[] = ['label' => 'View My Appointments', 'route' => '/appointments', 'icon' => '📅', 'type' => 'primary'];
                        $buttons[] = ['label' => 'Book New Appointment', 'message' => 'I want to book an appointment', 'icon' => '➕', 'type' => 'secondary'];
                    }
                    break;
                case 'reschedule_appointment':
                    if ($success) {
                        $buttons[] = ['label' => 'View My Appointments', 'route' => '/appointments', 'icon' => '📅', 'type' => 'primary'];
                    }
                    break;
                case 'get_my_appointments':
                    $buttons[] = ['label' => 'View Appointments', 'route' => '/appointments', 'icon' => '📅', 'type' => 'primary'];
                    $buttons[] = ['label' => 'Book New', 'message' => 'I want to book an appointment', 'icon' => '➕', 'type' => 'secondary'];
                    break;
                case 'get_available_services':
                case 'get_available_slots':
                    $buttons[] = ['label' => 'Book Appointment', 'message' => 'I want to book an appointment', 'icon' => '📅', 'type' => 'primary'];
                    break;
                case 'get_my_payments':
                case 'check_payment_status':
                    $buttons[] = ['label' => 'View Payments', 'route' => '/payments', 'icon' => '💳', 'type' => 'primary'];
                    break;
            }
        }

        // Deduplicate by label
        $seen = [];
        $unique = [];
        foreach ($buttons as $btn) {
            if (!in_array($btn['label'], $seen)) {
                $seen[] = $btn['label'];
                $unique[] = $btn;
            }
        }

        return array_slice($unique, 0, 3); // Max 3 buttons
    }

    /**
     * Clean the LLM response by removing internal reasoning artifacts.
     */
    private function cleanResponse(string $response): string
    {
        // Remove any stray tool_call blocks that might have been partial
        $response = preg_replace('/```tool_call.*?```/s', '', $response);
        // Remove XML-like tool call blocks that some providers emit
        $response = preg_replace(self::XML_TOOL_CALL_PATTERN, '', $response);
        // Remove malformed tool call variants the LLM might generate
        // e.g., "_call { ... }", "```json\n{\"action\":...}\n```", "tool_call\n{...}", etc.
        $response = preg_replace('/\b_?(?:tool_?)?call\s*\n?\s*\{[^}]*"(?:action|tool|function|name)"[^}]*\}/si', '', $response);
        // Also catch `_call` even if it doesn't have recognized keys: match any well-formed JSON after _call
        $response = preg_replace('/_call\s*\n?\s*\{(?:[^{}]|(?R))*\}/s', '', $response);
        // Remove any JSON blocks that look like tool/action calls (with or without code fences)
        $response = preg_replace('/```(?:json)?\s*\n?\s*\{\s*"(?:action|tool|function|name)"\s*:.*?\}\s*\n?\s*```/s', '', $response);
        // Remove standalone JSON objects that look like API calls (including "name"/"parameters" variant)
        $response = preg_replace('/\{\s*"(?:action|tool|function|name)"\s*:\s*"[^"]+"\s*,\s*"(?:parameters|arguments|args)"\s*:\s*\{.*?\}\s*\}/s', '', $response);
        // Remove confidence tags (handled separately)
        $response = preg_replace('/<confidence>\d+(\.\d+)?<\/confidence>/i', '', $response);
        return trim($response);
    }

    /**
     * Handle confirmation flow for destructive actions.
     */
    private function handleConfirmation(string $userMessage, array $pending, ?int $userId, string $role): ?array
    {
        $intent = self::detectConfirmationIntent($userMessage);
        $lower = $intent['normalized'];
        $isConfirm = $intent['is_confirm'];
        $isDeny = $intent['is_deny'];

        if (!$isConfirm && !$isDeny) {
            Log::debug('AgentReasoning: Confirmation not detected in message', [
                'message' => $lower,
                'pending_tool' => $pending['tool'] ?? 'unknown',
            ]);
            return null; // Not a confirmation response — proceed with normal reasoning
        }

        if ($isDeny) {
            Log::info('AgentReasoning: User denied confirmation', [
                'user_id' => $userId,
                'pending_tool' => $pending['tool'] ?? 'unknown',
            ]);
            return [
                'response' => "Understood, I've cancelled the action. How else can I help you?",
                'tool_calls' => [],
                'reasoning_steps' => 0,
                'cancelled' => true,
            ];
        }

        // Execute the confirmed tool
        $toolName = $pending['tool'] ?? '';
        $toolArgs = $pending['arguments'] ?? [];

        Log::info('AgentReasoning: User confirmed action, executing tool', [
            'user_id' => $userId,
            'tool_name' => $toolName,
            'tool_args_keys' => array_keys($toolArgs),
        ]);

        $toolResult = $this->toolRegistry->executeTool($toolName, $toolArgs, $userId ?? 0, $role);

        Log::info('AgentReasoning: Tool executed after confirmation', [
            'user_id' => $userId,
            'tool_name' => $toolName,
            'tool_success' => $toolResult['success'] ?? false,
            'tool_error' => $toolResult['error'] ?? null,
        ]);

        // Generate a natural response from the tool result — direct formatting (no LLM call)
        // This saves 2-5 seconds vs making another LLM API call just to format a result.
        $confirmResponse = $this->formatToolResultDirectly($toolName, $toolResult, $toolArgs);

        $confirmedToolCalls = [['tool' => $toolName, 'arguments' => $toolArgs, 'result' => $toolResult]];
        $actionButtons = $this->extractActionButtonsFromToolCalls($confirmedToolCalls);

        return [
            'response' => $confirmResponse,
            'tool_calls' => $confirmedToolCalls,
            'action_buttons' => $actionButtons,
            'reasoning_steps' => 1,
            'confirmed_action' => true,
        ];
    }

    public static function detectConfirmationIntent(string $userMessage): array
    {
        $lower = mb_strtolower(trim($userMessage));
        $affirmatives = ['yes', 'confirm', 'ok', 'proceed', 'go ahead', 'do it', 'sure', 'yep', 'yeah', 'yup', 'absolutely', 'please', 'correct', 'oo', 'sige', 'opo', 'oo po', 'g', 'y'];
        $negatives = ['no', 'cancel', 'stop', 'never mind', 'nevermind', 'nope', 'nah', 'dont', 'don\'t', 'abort', 'wait', 'hindi', 'huwag', 'ayaw', 'wag', 'hindi po', 'ayoko'];

        return [
            'normalized' => $lower,
            'is_confirm' => in_array($lower, $affirmatives) || str_starts_with($lower, 'yes') || str_starts_with($lower, 'confirm') || str_starts_with($lower, 'go ahead') || str_starts_with($lower, 'proceed'),
            'is_deny' => in_array($lower, $negatives) || str_starts_with($lower, 'no ') || str_starts_with($lower, 'cancel') || str_starts_with($lower, 'stop'),
        ];
    }

    /**
     * Detect if the LLM response claims to have performed an action without a tool_call block,
     * OR describes tool parameters / asks for permission instead of outputting the tool_call.
     */
    private function detectsHallucinatedAction(string $response, array $toolCalls = []): bool
    {
        $lower = mb_strtolower($response);

        // Patterns that indicate the LLM claims an action was performed
        $actionClaimPatterns = [
            // Booking claims (English)
            '/(?:appointment|booking|reservation)\s+(?:has been|is|was)\s+(?:successfully\s+)?(?:booked|scheduled|reserved|created|confirmed)/i',
            '/(?:i\'ve|i have)\s+(?:successfully\s+)?(?:booked|scheduled|reserved|created)\s+(?:your|the|an?)\s+appointment/i',
            '/(?:your|the)\s+appointment\s+(?:has been|is now|was)\s+(?:booked|confirmed|scheduled|set)/i',
            '/successfully\s+(?:booked|scheduled|reserved|created)\s+(?:your|the|an?)\s+appointment/i',
            // Booking claims (Filipino/Tagalog)
            '/(?:nakareserba|na-?book|naka-?book|nakatakda|nai-?schedule|nakapag-?book|na-?reserve)\s+(?:na|ang)/i',
            '/appointment\s+.*?(?:#\d+|number\s+\d+).*?(?:pending|approved|confirmed|nakareserba)/i',
            '/(?:ang\s+)?(?:iyong|inyong)\s+appointment\s+.*?(?:nakareserba|naka-?book|nakatakda|na-?schedule)/i',
            '/bilang\s+appointment\s*#?\d+/i',
            // Cancellation claims (English)
            '/(?:appointment|booking)\s+(?:has been|is|was)\s+(?:successfully\s+)?cancelled/i',
            '/(?:i\'ve|i have)\s+(?:successfully\s+)?cancelled\s+(?:your|the|an?)\s+appointment/i',
            '/(?:your|the)\s+appointment\s+(?:has been|is now|was)\s+cancelled/i',
            '/successfully\s+cancelled\s+(?:your|the|an?)\s+appointment/i',
            // Cancellation claims (Filipino/Tagalog)
            '/(?:na-?cancel|nakansela|na-?kansela)\s+(?:na|ang)/i',
            '/appointment\s+.*?(?:nakansela|na-?cancel)/i',
            // Rescheduling claims (English)
            '/(?:appointment|booking)\s+(?:has been|is|was)\s+(?:successfully\s+)?rescheduled/i',
            '/(?:i\'ve|i have)\s+(?:successfully\s+)?rescheduled\s+(?:your|the|an?)\s+appointment/i',
            // Rescheduling claims (Filipino/Tagalog)
            '/(?:na-?reschedule|nai-?lipat|nailipat)\s+(?:na|ang)/i',
            // LLM describes parameters and asks to proceed instead of calling the tool
            '/(?:shall|should|would you like me to|do you want me to|let me)\s+(?:proceed|book|schedule|cancel|go ahead)/i',
            '/(?:here\s+are|here\'s)\s+(?:the|your)\s+(?:details|booking details|appointment details|summary)\s*:/i',
            '/(?:i\'ll|let me)\s+(?:book|schedule|reserve)\s+(?:this|that|the|an?|your)\s+(?:appointment|booking)/i',
            // Filipino: LLM describes the action without doing it
            '/(?:ibo-?book|ika-?cancel|ire-?reschedule|ipa-?pag-?book)\s+(?:ko|natin)/i',
        ];

        foreach ($actionClaimPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                // If the response also contains a tool_call block, it's not a hallucination
                if (preg_match('/```tool_call/i', $response)) {
                    return false;
                }

                // If a relevant tool was already called in this reasoning loop, it's a summary, not a hallucination
                // Determine which category of action this pattern relates to
                $relevantTools = [];
                if (preg_match('/book|schedul|reserv|creat|nakareserba|naka-?book|nakatakda|nai-?schedule|bilang/i', $pattern)) {
                    $relevantTools = ['book_appointment', 'admin_approve_appointment'];
                } elseif (preg_match('/cancel|kansela/i', $pattern)) {
                    $relevantTools = ['cancel_appointment', 'admin_decline_appointment', 'admin_bulk_cancel_appointments'];
                } elseif (preg_match('/reschedul|lipat/i', $pattern)) {
                    $relevantTools = ['reschedule_appointment'];
                }

                foreach ($toolCalls as $call) {
                    if (in_array($call['tool'], $relevantTools) && ($call['result']['success'] ?? false)) {
                        return false; // Successful action summary
                    }
                }

                return true;
            }
        }


        return false;
    }

    /**
     * Coerce tool argument types to match their schema definitions.
     * LLMs sometimes send integers as strings (e.g. "1" instead of 1),
     * which causes strict validation failures on providers like Groq.
     */
    private function coerceToolArgTypes(string $toolName, array $args): array
    {
        // Known integer params across tools — cast string→int to prevent schema validation errors
        $integerParams = ['appointment_id', 'limit', 'payment_id'];
        foreach ($integerParams as $param) {
            if (isset($args[$param]) && is_string($args[$param]) && is_numeric($args[$param])) {
                $args[$param] = (int) $args[$param];
            }
        }

        // Coerce service_ids array elements: string numbers → integers
        if (isset($args['service_ids']) && is_array($args['service_ids'])) {
            $args['service_ids'] = array_map(function ($v) {
                return is_string($v) && is_numeric($v) ? (int) $v : $v;
            }, $args['service_ids']);
        }

        return $args;
    }

    /**
     * Neutralize any tool_call injection attempts in user messages.
     * Replaces tool_call code blocks with harmless text so the LLM cannot be tricked into executing them.
     */
    private function neutralizeToolCallInjection(string $message): string
    {
        // Strip any ```tool_call ... ``` blocks entirely
        $cleaned = preg_replace('/```\s*tool_call\s*\n?\s*\{.*?\}\s*\n?\s*```/si', '[removed: invalid tool_call block]', $message);
        // Also strip partial/malformed attempts (e.g., backtick variations, HTML-encoded)
        $cleaned = preg_replace('/`{1,3}\s*tool_call/i', '[removed]', $cleaned);
        // Strip JSON blocks that look like tool calls ({"tool": "...", "arguments": ...})
        $cleaned = preg_replace('/\{\s*"tool"\s*:\s*"[^"]+"\s*,\s*"arguments"\s*:/i', '[removed: suspicious JSON]', $cleaned);
        return $cleaned;
    }

    /**
     * Store a pending destructive tool call awaiting user confirmation.
     */
    private function storePendingToolCall(?int $userId, string $toolName, array $args, ?string $actorKey = null): string
    {
        $key = self::buildPendingConfirmationCacheKey($this->resolvePendingActorKey($userId, $actorKey));
        Cache::put($key, [
            'tool' => $toolName,
            'arguments' => $args,
            'created_at' => now()->toIso8601String(),
        ], 300); // 5-minute expiry

        return $key;
    }

    private function analyzeBookingDecision(
        string $userMessage,
        array $toolArgs,
        ?int $userId,
        string $role,
        ?string $validationError = null
    ): array
    {
        $missingFields = $this->getMissingBookingFields($toolArgs);
        $ambiguityAnalysis = $this->detectBookingAmbiguity($userMessage);

        if ($validationError !== null) {
            return [
                'decision' => 'clarify',
                'reason_code' => $this->classifyBookingValidationError($validationError, $missingFields),
                'execute_immediately' => false,
                'ambiguous' => $ambiguityAnalysis['ambiguous'],
                'ambiguity_rule' => $ambiguityAnalysis['reason_code'],
                'missing_fields' => $missingFields,
                'validation_error' => $validationError,
            ];
        }

        if ($role !== 'client' || empty($userId)) {
            return [
                'decision' => 'confirm',
                'reason_code' => 'non_client_or_missing_user',
                'execute_immediately' => false,
                'ambiguous' => $ambiguityAnalysis['ambiguous'],
                'ambiguity_rule' => $ambiguityAnalysis['reason_code'],
                'missing_fields' => $missingFields,
                'validation_error' => null,
            ];
        }

        if (!empty($missingFields)) {
            return [
                'decision' => 'clarify',
                'reason_code' => 'missing_required_fields',
                'execute_immediately' => false,
                'ambiguous' => $ambiguityAnalysis['ambiguous'],
                'ambiguity_rule' => $ambiguityAnalysis['reason_code'],
                'missing_fields' => $missingFields,
                'validation_error' => null,
            ];
        }

        if ($ambiguityAnalysis['ambiguous']) {
            return [
                'decision' => 'confirm',
                'reason_code' => $ambiguityAnalysis['reason_code'] ?? 'ambiguous_intent',
                'execute_immediately' => false,
                'ambiguous' => true,
                'ambiguity_rule' => $ambiguityAnalysis['reason_code'],
                'missing_fields' => [],
                'validation_error' => null,
            ];
        }

        return [
            'decision' => 'confirm',
            'reason_code' => 'complete_clear_request',
            'execute_immediately' => false,
            'ambiguous' => false,
            'ambiguity_rule' => null,
            'missing_fields' => [],
            'validation_error' => null,
        ];
    }

    private function detectBookingAmbiguity(string $userMessage): array
    {
        $patterns = [
            'uncertain_intent' => '/\b(maybe|not sure|unsure|if possible|if available|availability|available slots?|either|any slot)\b/i',
            'availability_lookup' => '/\b(check|show|list|tell me)\b.*\b(availability|available|slots?|services?)\b/i',
            'question_about_field' => '/\b(which|what)\b.*\b(service|slot|time|date)\b/i',
            'multiple_time_options' => '/\b(?:8|9|10|11|12|1|2|3|4|5)(?:am|pm)?\s+or\s+(?:8|9|10|11|12|1|2|3|4|5)(?:am|pm)?\b/i',
        ];

        foreach ($patterns as $reasonCode => $pattern) {
            if (preg_match($pattern, $userMessage)) {
                return [
                    'ambiguous' => true,
                    'reason_code' => $reasonCode,
                ];
            }
        }

        return [
            'ambiguous' => false,
            'reason_code' => null,
        ];
    }

    private function getMissingBookingFields(array $toolArgs): array
    {
        $missingFields = [];

        if (empty($toolArgs['service_id']) && empty($toolArgs['service_ids'])) {
            $missingFields[] = 'service';
        }
        if (empty($toolArgs['date'])) {
            $missingFields[] = 'date';
        }
        if (empty($toolArgs['time'])) {
            $missingFields[] = 'time';
        }

        return $missingFields;
    }

    private function classifyBookingValidationError(string $validationError, array $missingFields): string
    {
        if (!empty($missingFields)) {
            return 'missing_required_fields';
        }

        $lower = mb_strtolower($validationError);

        if (str_contains($lower, 'no valid services')) {
            return 'unresolved_service';
        }
        if (str_contains($lower, 'invalid date')) {
            return 'invalid_date';
        }
        if (str_contains($lower, 'invalid time')) {
            return 'invalid_time';
        }
        if (str_contains($lower, 'weekend')) {
            return 'unavailable_weekend';
        }
        if (str_contains($lower, 'lunch break')) {
            return 'unavailable_lunch_break';
        }
        if (str_contains($lower, 'daily booking limit')) {
            return 'daily_limit_reached';
        }
        if (str_contains($lower, 'fully booked')) {
            return 'slot_fully_booked';
        }
        if (str_contains($lower, 'already have a booking')) {
            return 'duplicate_booking';
        }

        return 'validation_failed';
    }

    private function logBookingDecision(
        string $userMessage,
        array $toolArgs,
        ?int $userId,
        string $role,
        array $decision
    ): void {
        Log::channel('chatbot_booking_decisions')->info('AgentReasoning: Booking decision analyzed', [
            'user_id' => $userId,
            'role' => $role,
            'decision' => $decision['decision'] ?? 'unknown',
            'reason_code' => $decision['reason_code'] ?? 'unknown',
            'ambiguous' => $decision['ambiguous'] ?? false,
            'ambiguity_rule' => $decision['ambiguity_rule'] ?? null,
            'missing_fields' => $decision['missing_fields'] ?? [],
            'validation_error' => $decision['validation_error'] ?? null,
            'message_signature' => $this->buildBookingMessageSignature($userMessage),
            'message_length' => mb_strlen(trim($userMessage)),
            'normalized_booking_fields' => $this->extractBookingFieldsForLog($toolArgs),
        ]);
    }

    private function buildBookingMessageSignature(string $userMessage): string
    {
        $normalizedMessage = preg_replace('/\s+/', ' ', mb_strtolower(trim($userMessage)));

        return substr(hash('sha256', $normalizedMessage), 0, 16);
    }

    private function extractBookingFieldsForLog(array $toolArgs): array
    {
        $serviceInput = $toolArgs['service_ids'] ?? $toolArgs['service_id'] ?? null;

        return [
            'service' => $serviceInput,
            'date' => $toolArgs['date'] ?? null,
            'time' => $toolArgs['time'] ?? null,
            'has_notes' => !empty($toolArgs['notes']),
        ];
    }

    private function buildBookingClarificationInstruction(string $error): string
    {
        return "Tool `book_appointment` validation failed: {$error}\n"
            . 'Ask the user for the missing, unclear, or invalid booking detail in one concise message. '
            . 'If the service is unclear, call get_available_services first and present the exact returned service list instead of relying on memory or a partial summary. '
            . 'If the date or time is unclear, ask only for that missing detail.';
    }

    private function executeToolImmediately(
        string $toolName,
        array $toolArgs,
        ?int $userId,
        string $role,
        array $existingToolCalls,
        int $step,
        array $llmResult
    ): array {
        Log::info('AgentReasoning: Executing validated booking immediately', [
            'user_id' => $userId,
            'tool_name' => $toolName,
            'tool_args_keys' => array_keys($toolArgs),
            'step' => $step,
        ]);

        $toolResult = $this->toolRegistry->executeTool($toolName, $toolArgs, $userId ?? 0, $role);
        $executedToolCalls = array_merge($existingToolCalls, [[
            'tool' => $toolName,
            'arguments' => $toolArgs,
            'result' => $toolResult,
        ]]);

        return [
            'response' => $this->formatToolResultDirectly($toolName, $toolResult, $toolArgs),
            'tool_calls' => $executedToolCalls,
            'action_buttons' => $this->extractActionButtonsFromToolCalls($executedToolCalls),
            'reasoning_steps' => $step,
            'llm_failed' => false,
            'provider' => $llmResult['provider'] ?? 'unknown',
            'model' => $llmResult['model'] ?? 'unknown',
            'tokens_used' => $llmResult['tokens_used'] ?? 0,
        ];
    }

    /**
     * Format a tool result directly without an LLM call.
     * Saves 2-5 seconds per confirmation by avoiding a round-trip to the LLM API.
     */
    private function formatToolResultDirectly(string $toolName, array $result, array $args): string
    {
        $success = $result['success'] ?? false;

        if ($toolName === 'book_appointment') {
            if ($success) {
                $data = $result['data'] ?? [];
                $lines = ["**Appointment booked successfully!**\n"];
                if (!empty($data['date_formatted'])) {
                    $lines[] = "**Date:** {$data['date_formatted']}" . (!empty($data['day']) ? " ({$data['day']})" : '');
                }
                if (!empty($data['time_formatted'])) {
                    $lines[] = "**Time:** {$data['time_formatted']}";
                }
                if (!empty($data['services']) && is_array($data['services'])) {
                    if (count($data['services']) === 1) {
                        $srv = $data['services'][0];
                        $lines[] = "**Service:** " . ($srv['name'] ?? 'N/A');
                        if (isset($srv['price_formatted'])) {
                            $lines[] = "**Price:** {$srv['price_formatted']}";
                        }
                    } else {
                        $lines[] = "\n**Services:**";
                        foreach ($data['services'] as $srv) {
                            $pf = $srv['price_formatted'] ?? '';
                            $lines[] = "- " . ($srv['name'] ?? 'N/A') . ($pf ? " — {$pf}" : '');
                        }
                    }
                } elseif (!empty($data['service'])) {
                    $lines[] = "**Service:** {$data['service']}";
                }
                if (!empty($data['total_price_formatted'])) {
                    $lines[] = "**Total:** {$data['total_price_formatted']}";
                }
                $lines[] = "**Status:** Pending approval";
                if (isset($data['remaining_bookings_today']) && isset($data['daily_limit'])) {
                    $used = $data['daily_limit'] - $data['remaining_bookings_today'];
                    $lines[] = "\nDaily slots: {$used}/{$data['daily_limit']} used";
                }
                return implode("\n", $lines);
            } else {
                $error = $result['error'] ?? 'Unknown error';
                return "**Booking failed:** {$error}";
            }
        }

        if ($toolName === 'cancel_appointment') {
            if ($success) {
                return "**Appointment cancelled successfully.** " . ($result['message'] ?? '');
            } else {
                return "**Cancellation failed:** " . ($result['error'] ?? 'Unknown error');
            }
        }

        if ($toolName === 'reschedule_appointment') {
            if ($success) {
                return "**Appointment rescheduled successfully.** " . ($result['message'] ?? '');
            } else {
                return "**Rescheduling failed:** " . ($result['error'] ?? 'Unknown error');
            }
        }

        // Generic fallback for other tools
        if ($success) {
            return $result['message'] ?? "Action completed successfully.";
        } else {
            return "Action failed: " . ($result['error'] ?? 'Unknown error');
        }
    }

    /**
     * Retrieve a pending confirmation for a user.
     * Uses a single deterministic key instead of scanning 300 time-based keys.
     */
    public static function getPendingConfirmation($actorKey): ?array
    {
        $key = self::buildPendingConfirmationCacheKey($actorKey);
        $pending = Cache::get($key);
        if ($pending) {
            Cache::forget($key);
            return $pending;
        }
        return null;
    }

    public static function hasPendingConfirmation($actorKey): bool
    {
        return Cache::has(self::buildPendingConfirmationCacheKey($actorKey));
    }

    private function resolvePendingActorKey(?int $userId, ?string $actorKey = null): string
    {
        if ($actorKey !== null && $actorKey !== '') {
            return $actorKey;
        }

        if ($userId !== null) {
            return (string) $userId;
        }

        return 'guest';
    }

    private static function buildPendingConfirmationCacheKey($actorKey): string
    {
        $normalizedActorKey = preg_replace('/[^A-Za-z0-9:_-]/', '_', (string) ($actorKey ?? 'guest'));

        return 'agent_confirm_' . $normalizedActorKey . '_pending';
    }
}
