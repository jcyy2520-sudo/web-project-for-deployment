<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ChatbotContextResolutionService
 * 
 * Handles advanced context resolution for follow-up questions and references.
 * 
 * Features:
 * - Resolve contextual references ("that one", "yung sinabi mo kanina")
 * - Detect if message is a natural follow-up (no need to repeat context)
 * - Build clarification prompts for ambiguous references
 * - Understand incomplete/brief responses in conversational context
 * - Track conversation flow and topic continuity
 */
class ChatbotContextResolutionService
{
    private const CONTEXT_CACHE_PREFIX = 'chatbot_context_resolution_';
    private const CONTEXT_TTL = 1800; // 30 minutes

    /**
     * Resolve contextual references in a user message based on conversation history
     * 
     * @param string $message Current user message
     * @param array $previousMessages Array of recent messages from conversation
     * @param string $language Detected language (en, tl, etc.)
     * @return array Resolution data with context and clarification needs
     */
    public function resolveContext(
        string $message,
        array $previousMessages = [],
        string $language = 'en'
    ): array {
        $resolution = [
            'original_message' => $message,
            'resolved_message' => $message,
            'has_references' => false,
            'references' => [],
            'context_entities' => [],
            'clarification_needed' => false,
            'clarification_prompt' => null,
            'is_follow_up' => false,
            'context_summary' => null,
            'previous_topic' => null,
            'message_type' => 'statement', // statement, question, confirmation, brief_response
        ];

        // Detect message type
        $messageType = $this->detectMessageType($message);
        $resolution['message_type'] = $messageType;

        // Check if this is a brief response that needs context
        if (in_array($messageType, ['brief_response', 'confirmation'])) {
            $resolution['is_follow_up'] = true;
        }

        // Detect references to previous context
        $referencePatterns = $this->getReferencePatterns($language);
        $hasReferences = false;

        foreach ($referencePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message, $matches)) {
                    $hasReferences = true;
                    $resolution['references'][] = [
                        'type' => $type,
                        'matched_text' => $matches[0] ?? null,
                        'position' => strpos($message, $matches[0] ?? ''),
                    ];
                }
            }
        }

        $resolution['has_references'] = $hasReferences;

        // If there are references or brief responses, try to resolve from context
        if (($hasReferences || $messageType === 'brief_response') && !empty($previousMessages)) {
            $contextEntities = $this->extractContextEntities($previousMessages);
            $resolution['context_entities'] = $contextEntities;
            $resolution['previous_topic'] = end($previousMessages)['message'] ?? null;

            // Check if we have enough context to resolve the references
            if (empty($contextEntities) && $hasReferences) {
                $resolution['clarification_needed'] = true;
                $resolution['clarification_prompt'] = $this->buildClarificationPrompt(
                    $resolution['references'],
                    $language
                );
            } else {
                // Try to resolve the references
                $resolution['resolved_message'] = $this->resolveReferences(
                    $message,
                    $contextEntities,
                    $resolution['references']
                );
            }
        }

        // Detect conversation flow continuity
        if (!empty($previousMessages)) {
            $resolution['is_follow_up'] = $this->isNaturalFollowUp($message, $previousMessages);
            $resolution['context_summary'] = $this->buildContextSummary($previousMessages);
        }

        return $resolution;
    }

    /**
     * Get reference patterns for different languages
     */
    private function getReferencePatterns(string $language = 'en'): array
    {
        $patterns = [
            // English patterns
            'english' => [
                'that_one' => '/\b(that one|that|the one|this one|it|them|they)\b/i',
                'previous_mention' => '/\b(what you (mentioned|said)|as you (said|mentioned)|like you said|what we talked about|the thing)\b/i',
                'time_reference' => '/\b(last time|before|earlier|when|a moment ago|just now)\b/i',
                'general_reference' => '/\b(yes|no|okay|ok|sure|absolutely|maybe|not really)\b/i',
            ],
            // Tagalog/Taglish patterns
            'tagalog' => [
                'yung_reference' => '/\byung\s+(sinabi|mentioned|sabi|tinu|nabanggit|nangyari|kasama)\b/i',
                'yan_reference' => '/\b(yan|iyan|yun|iyon|yon|doon|dito|diyan)\b/i',
                'kanina_reference' => '/\b(kanina|awhile ago|kahapon|dati|nakaraang|earlier)\b/i',
                'taglish_yes_no' => '/\b(oo|hindi|oops|wait|actually|cancel|never mind|nope|yup|yep)\b/i',
                'taglish_confirmation' => '/\b(sure|okay|sige|ayos|pwede|gets|naintindihan|nakaintindi)\b/i',
            ],
        ];

        return $patterns[$language] ?? $patterns['english'];
    }

    /**
     * Detect message type
     */
    private function detectMessageType(string $message): string
    {
        $message = trim($message);
        $wordCount = str_word_count($message);

        // Brief response (single word or very short)
        if ($wordCount <= 3 && preg_match('/^(yes|no|yep|nope|oo|hindi|ok|okay|sure|thanks|thank you|salamat|thanks|pls|please)$/i', $message)) {
            return 'brief_response';
        }

        // Confirmation/agreement
        if (preg_match('/^(yes|yeah|yep|sure|absolutely|definitely|oo|sige|okay|sounds good|got it|understood|salamat)\b/i', $message)) {
            return 'confirmation';
        }

        // Question
        if (str_ends_with($message, '?') || preg_match('/^(what|how|why|when|where|who|which|do|can|could|would|should)\s/i', $message)) {
            return 'question';
        }

        // Statement/comment
        return 'statement';
    }

    /**
     * Extract entities and context from recent messages
     */
    private function extractContextEntities(array $recentMessages): array
    {
        $entities = [
            'topics' => [],
            'appointments' => [],
            'services' => [],
            'dates' => [],
            'times' => [],
            'actions' => [],
            'questions_asked' => [],
            'last_mentioned' => null,
        ];

        foreach ($recentMessages as $msg) {
            $text = $msg['message'] ?? '';

            // Extract topics
            if (preg_match('/(appointment|booking|appointment|service|payment|refund|account|profile)/i', $text)) {
                $entities['topics'][] = $text;
            }

            // Extract appointments
            if (preg_match('/appointment|booking|scheduled|booked/i', $text)) {
                $entities['appointments'][] = $text;
            }

            // Extract services
            if (preg_match('/notary|legal|document|attestation|affidavit|consultation/i', $text)) {
                $entities['services'][] = $text;
            }

            // Extract dates
            if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4}|january|february|march|april|may|june|july|august|september|october|november|december|\d{1,2}\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec))/i', $text)) {
                $entities['dates'][] = $text;
            }

            // Extract times
            if (preg_match('/(\d{1,2}:\d{2}\s*(am|pm)?|\d{1,2}\s*(am|pm))/i', $text)) {
                $entities['times'][] = $text;
            }

            // Extract actions/intents
            if (preg_match('/(book|cancel|reschedule|approve|reject|pay|refund|view|show|check|help|question)/i', $text)) {
                $entities['actions'][] = $text;
            }

            // Extract questions
            if (str_ends_with(trim($text), '?')) {
                $entities['questions_asked'][] = $text;
            }
        }

        // Store most recent message
        if (!empty($recentMessages)) {
            $lastMsg = end($recentMessages);
            $entities['last_mentioned'] = $lastMsg['message'] ?? null;
        }

        return $entities;
    }

    /**
     * Resolve contextual references in message
     */
    private function resolveReferences(string $message, array $contextEntities, array $references): string
    {
        $resolved = $message;

        foreach ($references as $ref) {
            $type = $ref['type'];

            // Map reference types to context entities
            if ($type === 'yung_reference' || $type === 'yan_reference') {
                // Replace with most recent mentioned topic
                if (!empty($contextEntities['topics'])) {
                    $lastTopic = end($contextEntities['topics']);
                    // Extract noun from topic
                    if (preg_match('/\b(appointment|booking|service|payment|refund)\b/i', $lastTopic, $matches)) {
                        $noun = $matches[1];
                        $resolved = str_replace($ref['matched_text'], "the {$noun}", $resolved);
                    }
                }
            } elseif ($type === 'that_one') {
                // Replace with relevant entity
                if (!empty($contextEntities['appointments'])) {
                    $resolved = str_replace($ref['matched_text'], 'the appointment', $resolved);
                } elseif (!empty($contextEntities['services'])) {
                    $resolved = str_replace($ref['matched_text'], 'the service', $resolved);
                }
            } elseif ($type === 'kanina_reference' || $type === 'time_reference') {
                // These typically don't need resolution, but could be marked for context
                // The chatbot should use conversation history to understand timing
            }
        }

        return $resolved;
    }

    /**
     * Build a clarification prompt for unresolved references
     */
    private function buildClarificationPrompt(array $references, string $language = 'en'): string
    {
        if (empty($references)) {
            return '';
        }

        $templates = [
            'en' => [
                'default' => "I'm not sure what you're referring to. Could you provide more details?",
                'that_one' => "Which item are you referring to? Could you be more specific?",
                'appointment' => "Which appointment are you asking about?",
                'service' => "Which service are you interested in?",
            ],
            'tl' => [
                'default' => "Hindi ko maintindihan kung ano ang tinutukoy mo. Pwede mo ba i-clarify?",
                'that_one' => "Aling bagay ang tinutukoy mo? Mas specific naman.",
                'appointment' => "Aling appointment ang tinutukoy mo?",
                'service' => "Aling serbisyo ang gusto mo?",
            ],
        ];

        $template = $templates[$language] ?? $templates['en'];
        $refType = $references[0]['type'] ?? 'default';

        // Match reference type to template
        if (strpos($refType, 'appointment') !== false) {
            return $template['appointment'];
        } elseif (strpos($refType, 'service') !== false) {
            return $template['service'];
        } elseif (strpos($refType, 'yan') !== false || strpos($refType, 'that') !== false) {
            return $template['that_one'];
        }

        return $template['default'];
    }

    /**
     * Check if message is a natural follow-up
     */
    private function isNaturalFollowUp(string $currentMessage, array $previousMessages): bool
    {
        if (empty($previousMessages)) {
            return false;
        }

        // Get last assistant message (to see what was asked)
        $lastMessage = end($previousMessages);

        // If previous message was from assistant and contained a question
        if (isset($lastMessage['role']) && $lastMessage['role'] === 'assistant' && str_ends_with(trim($lastMessage['message'] ?? ''), '?')) {
            // Current message is likely a follow-up answer
            $currentWords = array_filter(explode(' ', mb_strtolower($currentMessage)));
            $lastWords = array_filter(explode(' ', mb_strtolower($lastMessage['message'] ?? '')));

            // If minimal word overlap, it's likely answering the question
            $commonWords = count(array_intersect($currentWords, $lastWords));
            $totalWords = count(array_unique(array_merge($currentWords, $lastWords)));

            $similarity = $totalWords > 0 ? $commonWords / $totalWords : 0;

            // Follow-ups typically have lower word similarity
            return $similarity < 0.3 && count($currentWords) > 0;
        }

        return false;
    }

    /**
     * Build a context summary for use in prompts
     */
    private function buildContextSummary(array $recentMessages, int $maxMessages = 5): string
    {
        if (empty($recentMessages)) {
            return '';
        }

        $messages = array_slice($recentMessages, -$maxMessages);
        $summary = "Recent conversation:\n";

        foreach ($messages as $msg) {
            $role = ($msg['role'] ?? 'unknown') === 'assistant' ? 'Assistant' : 'You';
            $text = substr($msg['message'] ?? '', 0, 100);
            $summary .= "- {$role}: {$text}\n";
        }

        return $summary;
    }

    /**
     * Determine if we need additional context before responding
     */
    public function needsAdditionalContext(string $message, array $resolution): bool
    {
        // Need context if:
        // 1. Message has unresolved references
        // 2. Message is a brief response but no previous messages exist
        // 3. Message is a question but missing key entities

        if ($resolution['clarification_needed'] ?? false) {
            return true;
        }

        if ($resolution['message_type'] === 'brief_response' && empty($resolution['context_entities'])) {
            return true;
        }

        // Check if question lacks specificity
        if ($resolution['message_type'] === 'question') {
            $entities = $resolution['context_entities'] ?? [];
            if (empty($entities) || (empty($entities['appointments'] ?? []) && empty($entities['services'] ?? []) && empty($entities['dates'] ?? []))) {
                return true;
            }
        }

        return false;
    }
}
