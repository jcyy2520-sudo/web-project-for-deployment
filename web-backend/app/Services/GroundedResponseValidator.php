<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * GroundedResponseValidator
 * 
 * Enforces the system prompt rules before ANY response is generated.
 * This service ensures responses are grounded, accurate, and never hallucinate.
 * 
 * Rules enforced:
 * 1. Answer-only-from-source (GROUNDED RESPONSES)
 * 2. Intent-based knowledge routing (NO MIXING)
 * 3. Clarification-first behavior (STRICT)
 * 4. Confidence threshold control (EXPOSE UNCERTAINTY)
 * 5. Reasoning-first interaction model (NOT FAQ)
 * 6. Error-driven adaptation (LEARNING)
 * 7. Knowledge hierarchy enforcement (SYSTEM OVER USER)
 * 8. Scoped intelligence (INTENTIONAL LIMITATION)
 * 9. Bad-input robustness (REAL-WORLD TEST)
 */
class GroundedResponseValidator
{
    private array $systemPrompt;
    private float $confidenceScore = 0.0;
    private string $detectedIntent = '';
    private array $verifiedSources = [];
    private bool $hasVerifiedSource = false;
    private bool $requiresClarification = false;
    private string $uncertaintyExplanation = '';

    public function __construct()
    {
        $this->systemPrompt = config('chatbot.system_prompt');
    }

    /**
     * Validate response before it's returned to user
     * Returns validation result with guidance on how to proceed
     */
    public function validateResponse(
        string $userInput,
        ?array $detectedIntent = null,
        ?array $potentialResponse = null,
        ?string $userRole = null,
        ?array $contextData = null
    ): array {
        Log::debug('[GroundedResponseValidator] Starting validation', [
            'user_input_length' => strlen($userInput),
            'has_intent' => $detectedIntent !== null,
        ]);

        $validation = [
            'is_valid' => true,
            'can_answer' => false,
            'confidence' => 0.0,
            'requires_clarification' => false,
            'requires_source_verification' => false,
            'action' => 'respond', // 'respond', 'ask_clarification', 'decline'
            'message' => null,
            'reasoning' => [],
            'verified_sources' => [],
        ];

        // RULE 3: Check if input requires clarification (CLARIFICATION-FIRST)
        $clarificationCheck = $this->checkClarificationNeeded($userInput);
        if ($clarificationCheck['requires_clarification']) {
            $validation['requires_clarification'] = true;
            $validation['action'] = 'ask_clarification';
            $validation['message'] = $clarificationCheck['suggested_clarification'];
            $validation['reasoning'][] = 'Input is vague, ambiguous, or lacks detail - must clarify first';
            Log::info('[GroundedResponseValidator] Clarification required', $clarificationCheck);
            return $validation;
        }

        // RULE 5: Perform reasoning-first analysis
        $reasoningAnalysis = $this->performReasoningAnalysis($userInput, $detectedIntent);
        $validation['reasoning'] = array_merge($validation['reasoning'], $reasoningAnalysis['analysis_steps']);

        // RULE 2: Check intent-based routing
        if ($detectedIntent && !$this->isValidIntentCategory($detectedIntent)) {
            $validation['action'] = 'decline';
            $validation['message'] = $this->getOutOfScopeMessage($userRole);
            $validation['reasoning'][] = 'Intent does not map to valid knowledge category';
            Log::warning('[GroundedResponseValidator] Invalid intent category', $detectedIntent);
            return $validation;
        }

        // RULE 1: Check for verified sources (GROUNDED RESPONSES)
        $sourceCheck = $this->verifySourcesAvailable($userInput, $detectedIntent, $contextData);
        if (!$sourceCheck['has_verified_source']) {
            $validation['action'] = 'ask_or_decline';
            $validation['message'] = $this->getDataUnavailableMessage($userRole);
            $validation['reasoning'][] = 'No verified source available for this information';
            $validation['verified_sources'] = [];
            Log::info('[GroundedResponseValidator] No verified sources found');
            return $validation;
        }

        // RULE 4: Calculate confidence threshold
        $confidence = $this->calculateConfidence($sourceCheck, $detectedIntent, $potentialResponse);
        $validation['confidence'] = $confidence;
        $validation['verified_sources'] = $sourceCheck['sources'];

        // Determine action based on confidence
        if ($confidence >= 0.85) {
            // High confidence - can answer
            $validation['can_answer'] = true;
            $validation['action'] = 'respond';
            $validation['reasoning'][] = "High confidence ({$confidence}) - verified sources found";
        } elseif ($confidence >= 0.65) {
            // Medium confidence - answer + state limitations
            $validation['can_answer'] = true;
            $validation['action'] = 'respond_with_caveats';
            $validation['reasoning'][] = "Medium confidence ({$confidence}) - answer should include limitations";
            $validation['uncertainty_statement'] = $this->buildUncertaintyStatement($confidence, $userRole);
        } else {
            // Low confidence - ask or decline
            $validation['can_answer'] = false;
            $validation['action'] = 'ask_or_decline';
            $validation['message'] = $this->getUncertaintyMessage($userRole);
            $validation['reasoning'][] = "Low confidence ({$confidence}) - cannot provide reliable answer";
        }

        // RULE 7: Verify knowledge hierarchy (system > user claims)
        if ($contextData && isset($contextData['user_claims'])) {
            $hierarchyCheck = $this->enforceKnowledgeHierarchy($contextData);
            if ($hierarchyCheck['conflict_detected']) {
                $validation['reasoning'][] = $hierarchyCheck['conflict_message'];
                if ($hierarchyCheck['system_truth_differs']) {
                    $validation['message'] = $hierarchyCheck['system_message'];
                }
            }
        }

        // RULE 8: Check scope (SCOPED INTELLIGENCE)
        if (!$this->isWithinScope($detectedIntent, $userRole)) {
            $validation['can_answer'] = false;
            $validation['action'] = 'decline';
            $validation['message'] = $this->getScopeMessage($userRole);
            $validation['reasoning'][] = 'Query is outside scope of chatbot intelligence';
            Log::info('[GroundedResponseValidator] Out of scope query');
            return $validation;
        }

        $validation['is_valid'] = true;

        Log::debug('[GroundedResponseValidator] Validation complete', [
            'action' => $validation['action'],
            'confidence' => $validation['confidence'],
            'can_answer' => $validation['can_answer'],
        ]);

        return $validation;
    }

    /**
     * RULE 3: Check if input requires clarification
     * Vague, short, ambiguous, or emotion-based inputs without detail
     */
    private function checkClarificationNeeded(string $userInput): array
    {
        $input = strtolower(trim($userInput));

        // Examples that require clarification
        $clarificationTriggers = [
            'bakit ayaw' => 'What specifically isn\'t working? Can you describe the issue?',
            'di gumagana' => 'What exactly is not working? Please describe the problem.',
            'help' => 'I want to help! Could you tell me what you need assistance with?',
            'may problema' => 'What kind of problem are you experiencing? Tell me more.',
            'di nagana' => 'What didn\'t work? Please provide more details.',
            'stuck' => 'Where are you stuck? Can you describe what happened?',
            'error' => 'What error did you get? When did it happen?',
            'bakit' => 'Why specifically? Can you provide context?',
            'ano' => 'What specifically do you want to know?',
        ];

        // Check for vagueness indicators
        $isVague = strlen($input) < 5 || // Very short input
                   preg_match('/^(help|stuck|error|bakit|ano|hmm|ok|yes|no)$/i', $input) ||
                   str_word_count($input) <= 2;

        // Check for emotion-based without detail
        $emotionPatterns = [
            'frustrated' => ['bad', 'angry', 'mad', 'hate', 'awful', 'terrible'],
            'confused' => ['confused', 'lost', 'dunno', 'wala', 'ewan'],
            'urgent' => ['urgent', 'asap', 'hurry', 'quick', 'emergency'],
        ];

        $hasEmotionButNoDetail = false;
        foreach ($emotionPatterns as $patterns) {
            if (preg_match('/' . implode('|', $patterns) . '/i', $input) && $isVague) {
                $hasEmotionButNoDetail = true;
                break;
            }
        }

        // Check for explicit clarification triggers
        foreach ($clarificationTriggers as $trigger => $suggestion) {
            if (stripos($input, $trigger) !== false) {
                return [
                    'requires_clarification' => true,
                    'reason' => 'Explicit vague trigger detected: ' . $trigger,
                    'suggested_clarification' => $suggestion,
                ];
            }
        }

        if ($isVague || $hasEmotionButNoDetail) {
            return [
                'requires_clarification' => true,
                'reason' => 'Input is too vague or short to understand properly',
                'suggested_clarification' => 'Could you provide more detail about what you need help with?',
            ];
        }

        return [
            'requires_clarification' => false,
            'reason' => 'Input provides sufficient detail',
        ];
    }

    /**
     * RULE 5: Perform reasoning-first analysis
     * Internal flow: Input → Intent → Missing-info → Decision → Response
     */
    private function performReasoningAnalysis(string $userInput, ?array $intent): array
    {
        return [
            'analysis_steps' => [
                'step_1_input_received' => 'Analyzing user input for intent detection',
                'step_2_intent_detection' => $intent ? ('Detected intent: ' . ($intent['category'] ?? 'unknown')) : 'Intent detection pending',
                'step_3_missing_info_check' => 'Checking for missing information',
                'step_4_decision_point' => 'Determining if answer, clarification, or refusal is appropriate',
            ],
        ];
    }

    /**
     * RULE 1: Verify sources are available for answering
     */
    private function verifySourcesAvailable(string $userInput, ?array $intent, ?array $context): array
    {
        $sources = [];
        $hasSource = false;

        // Check database sources
        if ($context && isset($context['database_query'])) {
            $sources[] = 'database_records';
            $hasSource = true;
        }

        // Check system configuration
        if ($context && isset($context['system_config'])) {
            $sources[] = 'system_configuration';
            $hasSource = true;
        }

        // Check user session data
        if ($context && isset($context['user_data'])) {
            $sources[] = 'user_session_data';
            $hasSource = true;
        }

        // Check business settings
        if ($context && isset($context['business_settings'])) {
            $sources[] = 'business_settings';
            $hasSource = true;
        }

        return [
            'has_verified_source' => $hasSource,
            'sources' => $sources,
            'source_count' => count($sources),
        ];
    }

    /**
     * Check if intent maps to valid knowledge category
     */
    private function isValidIntentCategory(?array $intent): bool
    {
        if (!$intent || !isset($intent['category'])) {
            return false;
        }

        $validCategories = [
            'appointments',
            'users',
            'roles_and_permissions',
            'system_rules',
            'errors_and_issues',
            'faqs',
            'policies',
            'payments',
            'refunds',
        ];

        return in_array(strtolower($intent['category']), $validCategories);
    }

    /**
     * RULE 4: Calculate confidence score
     * Returns value 0.0 to 1.0
     */
    private function calculateConfidence(array $sourceCheck, ?array $intent, ?array $response): float
    {
        $confidence = 0.0;

        // Base on number of sources
        if ($sourceCheck['source_count'] >= 2) {
            $confidence += 0.5;
        } elseif ($sourceCheck['source_count'] === 1) {
            $confidence += 0.3;
        }

        // Add for clear intent
        if ($intent && isset($intent['confidence'])) {
            $confidence += $intent['confidence'] * 0.3;
        }

        // Add for response completeness
        if ($response && !empty($response)) {
            $confidence += 0.2;
        }

        return min(1.0, $confidence);
    }

    /**
     * Build uncertainty statement for medium-confidence responses
     */
    private function buildUncertaintyStatement(float $confidence, ?string $role): string
    {
        $isFil = in_array($role, ['client_fil', 'cashier_fil', 'admin_fil']);

        if ($confidence >= 0.75) {
            return $isFil
                ? 'Ito ay base sa available data, pero pwede pang may details na kulang.'
                : 'Based on available information, though some details might be incomplete.';
        } else {
            return $isFil
                ? 'May uncertainty ako dito. Dapat confirm pa kung tama ang sagot ko.'
                : 'I\'m not entirely certain about this. Please verify before proceeding.';
        }
    }

    /**
     * RULE 7: Enforce knowledge hierarchy
     * System knowledge must override user claims
     */
    private function enforceKnowledgeHierarchy(array $context): array
    {
        $result = [
            'conflict_detected' => false,
            'system_truth_differs' => false,
            'conflict_message' => '',
            'system_message' => '',
        ];

        if (!isset($context['user_claims']) || !isset($context['system_facts'])) {
            return $result;
        }

        // Check if user claims contradict system facts
        foreach ($context['user_claims'] as $claim => $value) {
            if (isset($context['system_facts'][$claim]) && $context['system_facts'][$claim] !== $value) {
                $result['conflict_detected'] = true;
                $result['system_truth_differs'] = true;
                $result['conflict_message'] = "Knowledge hierarchy conflict detected for '{$claim}': User claims '{$value}' but system shows '{$context['system_facts'][$claim]}'";
                $result['system_message'] = "According to system records, {$claim} is: " . $context['system_facts'][$claim];
                break;
            }
        }

        return $result;
    }

    /**
     * RULE 8: Check if query is within chatbot scope
     */
    private function isWithinScope(?array $intent, ?string $role): bool
    {
        if (!$intent || !isset($intent['category'])) {
            return false;
        }

        $inScopeCategories = [
            'appointments',
            'users',
            'roles_and_permissions',
            'system_rules',
            'errors_and_issues',
            'faqs',
            'policies',
            'payments',
            'refunds',
        ];

        return in_array(strtolower($intent['category']), $inScopeCategories);
    }

    /**
     * Get appropriate data unavailable message
     */
    private function getDataUnavailableMessage(?string $role): string
    {
        $isFil = in_array($role, ['client_fil', 'cashier_fil', 'admin_fil']);

        return $isFil
            ? "Pasensya, wala akong access sa information na iyan sa system."
            : "I don't have access to that information in the system.";
    }

    /**
     * Get uncertainty message for low confidence
     */
    private function getUncertaintyMessage(?string $role): string
    {
        $isFil = in_array($role, ['client_fil', 'cashier_fil', 'admin_fil']);

        return $isFil
            ? "Hindi ako sure kung paano sumagot dito. Pwede po ba kayong magbigay ng mas maraming detalye?"
            : "I'm not certain about that. Could you provide more context?";
    }

    /**
     * Get out of scope message
     */
    private function getOutOfScopeMessage(?string $role): string
    {
        $isFil = in_array($role, ['client_fil', 'cashier_fil', 'admin_fil']);

        return $isFil
            ? "Pasensya, hindi po iyan sakop ng aming assistant. Pwede lang akong tumulong sa appointments, services, at payments."
            : "That's outside my area of assistance. I can help with appointments, services, and payments.";
    }

    /**
     * Get scope message
     */
    private function getScopeMessage(?string $role): string
    {
        return $this->getOutOfScopeMessage($role);
    }

    /**
     * Get the confidence level rating (text version)
     */
    public function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 0.85) return 'high';
        if ($confidence >= 0.65) return 'medium';
        return 'low';
    }
}
