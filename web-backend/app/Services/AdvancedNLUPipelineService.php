<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AdvancedNLUPipelineService - Unified NLU Processing Pipeline
 * 
 * Orchestrates the complete NLU pipeline by integrating:
 * - AdvancedNLPService (text normalization, fuzzy matching)
 * - AdvancedContentModerationService (safety filtering)
 * - SmartIntentRecognitionService (intent classification)
 * 
 * Features:
 * - Real-time, intelligent text processing
 * - Multi-layer safety enforcement
 * - Smart intent classification with confidence scoring
 * - Conversation context awareness
 * - Comprehensive input analysis
 * - Caching for performance
 */
class AdvancedNLUPipelineService
{
    private const CACHE_PREFIX = 'nlu_pipeline_';
    private const CACHE_TTL = 3600;

    private AdvancedNLPService $nlpService;
    private AdvancedContentModerationService $moderationService;
    private SmartIntentRecognitionService $intentService;

    public function __construct(
        AdvancedNLPService $nlpService,
        AdvancedContentModerationService $moderationService,
        SmartIntentRecognitionService $intentService
    ) {
        $this->nlpService = $nlpService;
        $this->moderationService = $moderationService;
        $this->intentService = $intentService;
    }

    /**
     * Process user input through complete NLU pipeline
     * Real-time, intelligent end-to-end processing
     * 
     * @param string $userInput Raw user input
     * @param int|null $userId User ID for context
     * @param array $conversationContext Recent conversation history
     * @param string $userRole User's role
     * @return array Comprehensive NLU analysis result
     */
    public function processInput(
        string $userInput,
        ?int $userId = null,
        array $conversationContext = [],
        string $userRole = 'guest'
    ): array {
        $cacheKey = self::CACHE_PREFIX . md5("{$userInput}_{$userId}_{$userRole}");
        
        // Check cache first (but only for non-personalized generic inputs)
        if (empty($conversationContext) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = [];

        try {
            // ============ STAGE 1: TEXT ANALYSIS & NORMALIZATION ============
            $textAnalysis = $this->nlpService->analyzeText($userInput);
            $result['text_analysis'] = $textAnalysis;
            $normalizedText = $textAnalysis['normalized'];
            $detectedLanguage = $textAnalysis['language'];

            // ============ STAGE 2: CONTENT SAFETY CHECK ============
            $safetyCheck = $this->moderationService->checkContentSafety($userInput, $userId);
            $result['safety'] = $safetyCheck;

            // Early exit if content is unsafe
            if (!$safetyCheck['safe']) {
                $result['is_safe'] = false;
                $result['should_proceed'] = false;
                $result['violation_type'] = $safetyCheck['violation_type'];
                $result['safety_response'] = $this->moderationService->getSafeResponse($safetyCheck['violation_type']);
                
                Log::warning('Unsafe content blocked', [
                    'user_id' => $userId,
                    'violation' => $safetyCheck['violation_type'],
                    'confidence' => $safetyCheck['confidence'],
                ]);

                return $result;
            }

            $result['is_safe'] = true;

            // ============ STAGE 3: INTENT RECOGNITION ============
            $intentResult = $this->intentService->recognizeIntent(
                $normalizedText,
                $conversationContext,
                $detectedLanguage
            );
            $result['intent'] = $intentResult['primary_intent'];
            $result['intent_confidence'] = $intentResult['primary_confidence'];
            $result['intent_alternatives'] = $intentResult['alternatives'];
            $result['needs_clarification'] = $intentResult['needs_clarification'];
            $result['suggested_clarification'] = $intentResult['suggested_clarification'];

            // ============ STAGE 4: ENTITY EXTRACTION ============
            $entities = $this->intentService->extractIntentEntities($normalizedText, $result['intent']);
            $result['entities'] = $entities;

            // ============ STAGE 5: COMPILATION & CONTEXT ENHANCEMENT ============
            $result['raw_input'] = $userInput;
            $result['normalized_input'] = $normalizedText;
            $result['language_detected'] = $detectedLanguage;
            $result['user_id'] = $userId;
            $result['user_role'] = $userRole;
            $result['timestamp'] = now();
            $result['should_proceed'] = true;

            // Additional metadata
            $result['metadata'] = [
                'processing_quality' => $this->calculateProcessingQuality($result),
                'confidence_score' => round(
                    (($result['intent_confidence'] ?? 0) + ($safetyCheck['confidence'] ?? 0)) / 2,
                    3
                ),
                'requires_human_review' => $this->shouldEscalateToHuman($result),
                'suggested_response_type' => $this->suggestResponseType($result),
            ];

            // Cache result
            Cache::put($cacheKey, $result, self::CACHE_TTL);

            Log::info('NLU processing complete', [
                'user_id' => $userId,
                'intent' => $result['intent'],
                'confidence' => $result['intent_confidence'],
                'safety_ok' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('NLU pipeline error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'input' => mb_substr($userInput, 0, 50),
            ]);

            $result['error'] = $e->getMessage();
            $result['should_proceed'] = false;
            $result['intent'] = 'error';
        }

        return $result;
    }

    /**
     * Classify user intent with advanced multi-level analysis
     * 
     * @param string $userInput User message
     * @param string|null $language Language hint
     * @return array Intent classification with confidence and alternatives
     */
    public function classifyIntent(string $userInput, ?string $language = null): array
    {
        $textAnalysis = $this->nlpService->analyzeText($userInput);
        $normalizedText = $textAnalysis['normalized'];
        $detectedLanguage = $language ?? $textAnalysis['language'];

        $intentResult = $this->intentService->recognizeIntent($normalizedText, [], $detectedLanguage);

        return [
            'primary_intent' => $intentResult['primary_intent'],
            'primary_confidence' => $intentResult['primary_confidence'],
            'alternatives' => $intentResult['alternatives'],
            'alternative_scores' => $intentResult['alternative_scores'],
            'is_high_confidence' => $intentResult['is_high_confidence'],
            'needs_clarification' => $intentResult['needs_clarification'],
            'clarification_prompt' => $intentResult['suggested_clarification'],
            'all_scores' => $intentResult['all_scores'] ?? [],
        ];
    }

    /**
     * Safety check with detailed analysis
     * 
     * @param string $userInput User message
     * @return array Safety assessment result
     */
    public function checkSafety(string $userInput): array
    {
        return $this->moderationService->checkContentSafety($userInput);
    }

    /**
     * Normalize and analyze text
     * 
     * @param string $userInput User message
     * @return array Text analysis result
     */
    public function analyzeText(string $userInput): array
    {
        return $this->nlpService->analyzeText($userInput);
    }

    /**
     * Calculate overall processing quality
     */
    private function calculateProcessingQuality(array $result): float
    {
        $factors = [];

        // Intent confidence factor
        $factors['intent'] = $result['intent_confidence'] ?? 0;

        // Safety factor
        $factors['safety'] = $result['is_safe'] ? 1.0 : 0;

        // Language detection confidence
        $factors['language'] = $result['text_analysis']['language_confidence'] ?? 0.5;

        // Entity extraction factor
        $entityCount = count(array_filter($result['entities'] ?? []));
        $factors['entities'] = min($entityCount / 5, 1.0); // Max 5 entities for 1.0

        // Average all factors
        $quality = array_sum($factors) / count($factors);
        
        return round($quality, 3);
    }

    /**
     * Determine if response should be escalated to human
     */
    private function shouldEscalateToHuman(array $result): bool
    {
        // Always escalate if unsafe
        if (!$result['is_safe']) {
            return true;
        }

        // Escalate if intent confidence is very low
        if (($result['intent_confidence'] ?? 0) < 0.3) {
            return true;
        }

        // Escalate if there's a need for clarification and multiple alternatives
        if ($result['needs_clarification'] && count($result['intent_alternatives'] ?? []) >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Suggest response type based on analysis
     */
    private function suggestResponseType(array $result): string
    {
        if (!$result['is_safe']) {
            return 'safety_violation';
        }

        if ($result['intent_confidence'] >= 0.85) {
            return 'deterministic'; // Use specific action/answer
        }

        if ($result['intent_confidence'] >= 0.6 && !$result['needs_clarification']) {
            return 'confident'; // Use LLM with context
        }

        if ($result['needs_clarification']) {
            return 'clarify'; // Ask for clarification
        }

        return 'general'; // Use general LLM response
    }

    /**
     * Batch process multiple inputs
     * Useful for analyzing conversation history or bulk operations
     */
    public function processBatch(array $inputs, ?int $userId = null): array
    {
        $results = [];

        foreach ($inputs as $index => $input) {
            $results[$index] = $this->processInput($input, $userId);
        }

        return $results;
    }

    /**
     * Get detailed diagnostics for a user input
     * Useful for debugging and understanding what the NLU is doing
     */
    public function getDiagnostics(string $userInput, ?int $userId = null, string $userRole = 'guest'): array
    {
        $result = $this->processInput($userInput, $userId, [], $userRole);

        return [
            'raw_input' => $userInput,
            'processing_result' => $result,
            'diagnostics' => [
                'text_normalization' => [
                    'original' => $userInput,
                    'normalized' => $result['normalized_input'] ?? 'N/A',
                    'language' => $result['language_detected'] ?? 'unknown',
                    'confidence' => $result['text_analysis']['language_confidence'] ?? 0,
                ],
                'safety_analysis' => [
                    'is_safe' => $result['is_safe'],
                    'violation_type' => $result['violation_type'] ?? null,
                    'confidence' => $result['safety']['confidence'] ?? 0,
                    'violations' => array_keys($result['safety']['violation_details'] ?? []),
                ],
                'intent_analysis' => [
                    'primary_intent' => $result['intent'],
                    'confidence' => $result['intent_confidence'],
                    'alternatives' => $result['intent_alternatives'],
                    'alternative_scores' => $result['intent_alternatives'] 
                        ? $result['intent_confidence'] . ' vs ' . implode(', ', $result['intent_alternatives'])
                        : 'N/A',
                    'needs_clarification' => $result['needs_clarification'],
                ],
                'entity_extraction' => [
                    'entities_found' => count(array_filter($result['entities'] ?? [])),
                    'entity_types' => array_keys($result['entities'] ?? []),
                    'entities' => $result['entities'] ?? [],
                ],
                'recommendations' => [
                    'response_type' => $result['metadata']['suggested_response_type'],
                    'requires_escalation' => $result['metadata']['requires_human_review'],
                    'processing_quality' => $result['metadata']['processing_quality'],
                    'overall_confidence' => $result['metadata']['confidence_score'],
                ],
            ],
        ];
    }

    /**
     * Get system statistics about NLU processing
     */
    public function getSystemStats(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::CACHE_TTL,
            'services_loaded' => [
                'nlp_service' => $this->nlpService !== null,
                'moderation_service' => $this->moderationService !== null,
                'intent_service' => $this->intentService !== null,
            ],
            'timestamp' => now(),
        ];
    }
}
