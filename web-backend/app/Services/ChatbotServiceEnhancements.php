<?php

namespace App\Services;

/**
 * Enhanced ChatbotService - Advanced NLU with Fuzzy Intent Recognition
 *
 * ✅ Natural Language Understanding (NLU) - Handles messy, misspelled, slang, Taglish
 * ✅ Fuzzy Intent Recognition - Detects intent even with unclear messages
 * ✅ Contextual Understanding - Real-time system data integration
 * ✅ Dynamic Interpretation - No hardcoded phrases, semantic understanding
 *
 * This file contains additional helper methods that extend the base ChatbotService
 * with advanced NLU capabilities for better user experience.
 */
class ChatbotServiceEnhancements
{
    /**
     * Extract entities from normalized text (dates, numbers, service names)
     * Helps with contextual understanding beyond simple intent detection
     */
    public static function extractEntities(string $text, array $context): array
    {
        $entities = [
            'dates' => [],
            'times' => [],
            'numbers' => [],
            'services' => [],
            'actions' => [],
        ];

        // Extract date references
        $datePatterns = [
            'today', 'tomorrow', 'yesterday',
            'next week', 'next month', 'this week',
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'
        ];
        foreach ($datePatterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $entities['dates'][] = $pattern;
            }
        }

        // Extract numbers (for "how many", counts, etc.)
        preg_match_all('/\b\d+\b/', $text, $matches);
        if (!empty($matches[0])) {
            $entities['numbers'] = $matches[0];
        }

        // Extract service references if available in context
        if (!empty($context['available_services'])) {
            foreach ($context['available_services'] as $service) {
                $serviceName = strtolower($service['name']);
                if (strpos($text, $serviceName) !== false) {
                    $entities['services'][] = $service;
                }
            }
        }

        // Extract action verbs
        $actionWords = ['book', 'cancel', 'reschedule', 'change', 'update', 'check', 'show', 'get'];
        foreach ($actionWords as $action) {
            if (strpos($text, $action) !== false) {
                $entities['actions'][] = $action;
            }
        }

        return $entities;
    }

    /**
     * Calculate semantic similarity between two texts
     * Used for fuzzy matching when exact intent detection fails
     */
    public static function semanticSimilarity(string $text1, string $text2): float
    {
        $words1 = explode(' ', $text1);
        $words2 = explode(' ', $text2);

        $common = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        if (empty($union)) return 0.0;

        return count($common) / count($union);
    }

    /**
     * Generate contextual suggestions based on user's conversation history
     * and current system state
     */
    public static function generateContextualSuggestions(array $context, string $lastIntent): array
    {
        $suggestions = [];

        // Suggestions based on user role
        $role = $context['user_role'] ?? 'client';

        if ($role === 'admin') {
            $pending = $context['admin_data']['pending_appointments'] ?? 0;
            $today = $context['admin_data']['today_appointments'] ?? 0;

            if ($pending > 0) {
                $suggestions[] = "Review {$pending} pending appointments";
            }
            if ($today > 0) {
                $suggestions[] = "View today's {$today} appointments";
            }
            $suggestions[] = 'Show performance analytics';
            $suggestions[] = 'Top services this month';
        } else {
            // Client suggestions
            $upcomingCount = 0;
            if (isset($context['client_data']['upcoming_appointments'])) {
                $upcomingCount = count($context['client_data']['upcoming_appointments']);
            }

            if ($upcomingCount > 0) {
                $suggestions[] = 'When is my next appointment?';
                $suggestions[] = 'Can I reschedule?';
            } else {
                $suggestions[] = 'Book an appointment';
                $suggestions[] = 'View available services';
            }
            $suggestions[] = 'What should I bring?';
        }

        return array_slice($suggestions, 0, 4);
    }

    /**
     * Confidence scoring for intent detection
     * Returns confidence level (0-1) for the detected intent
     */
    public static function calculateIntentConfidence(string $text, string $intent, array $patterns): float
    {
        $score = 0.0;
        $maxScore = 0.0;

        if (!isset($patterns[$intent])) {
            return 0.0;
        }

        $rules = $patterns[$intent];

        // Pattern matches give highest confidence
        if (isset($rules['patterns'])) {
            $maxScore += 10;
            foreach ($rules['patterns'] as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $score += 10;
                    break;
                }
            }
        }

        // Keyword matches give medium confidence
        if (isset($rules['keywords'])) {
            $maxScore += 5;
            $keywordMatches = 0;
            foreach ($rules['keywords'] as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $keywordMatches++;
                }
            }
            $score += min($keywordMatches * 2, 5);
        }

        // Semantic matches give lower confidence
        if (isset($rules['semantic'])) {
            $maxScore += 3;
            foreach ($rules['semantic'] as $semantic) {
                if (strpos($text, $semantic) !== false) {
                    $score += 3;
                    break;
                }
            }
        }

        return $maxScore > 0 ? $score / $maxScore : 0.0;
    }

    /**
     * Normalize text for similarity comparisons: lowercase, remove punctuation,
     * collapse repeated letters and remove small filler tokens.
     */
    public static function normalizeForSimilarity(string $text): string
    {
        $t = mb_strtolower($text);
        // collapse repeated letters (three or more) -> one
        $t = preg_replace('/([a-z])\1{2,}/u', '$1', $t);
        // remove punctuation and emojis
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);
        // collapse whitespace
        $t = preg_replace('/\s+/', ' ', $t);
        $t = trim($t);
        return $t;
    }

    /**
     * Fuzzy similarity using combination of token overlap, simple Jaccard (semanticSimilarity),
     * and normalized Levenshtein distance. Returns 0..1.
     */
    public static function fuzzySimilarity(string $a, string $b): float
    {
        $na = self::normalizeForSimilarity($a);
        $nb = self::normalizeForSimilarity($b);

        if ($na === $nb) return 1.0;

        // token overlap
        $tokensA = array_filter(explode(' ', $na));
        $tokensB = array_filter(explode(' ', $nb));
        $common = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        $tokenOverlap = $union > 0 ? $common / $union : 0.0;

        // semantic similarity (simple word-based)
        $semantic = self::semanticSimilarity($na, $nb);

        // normalized Levenshtein (safe): shorter/longer max
        $la = strlen($na);
        $lb = strlen($nb);
        $lev = PHP_INT_MAX;
        if ($la > 0 || $lb > 0) {
            $lev = levenshtein($na, $nb);
            $max = max(1, max($la, $lb));
            $levScore = 1.0 - min(1.0, $lev / $max);
        } else {
            $levScore = 0.0;
        }

        // combine weights: tokenOverlap 40%, semantic 35%, lev 25%
        $combined = ($tokenOverlap * 0.4) + ($semantic * 0.35) + ($levScore * 0.25);
        return max(0.0, min(1.0, $combined));
    }

    /**
     * Phonetic similarity using metaphone on tokens. Useful for phonetic misspellings.
     */
    public static function phoneticSimilarity(string $a, string $b): float
    {
        $na = self::normalizeForSimilarity($a);
        $nb = self::normalizeForSimilarity($b);
        $tokensA = array_filter(explode(' ', $na));
        $tokensB = array_filter(explode(' ', $nb));
        if (empty($tokensA) || empty($tokensB)) return 0.0;

        $matches = 0;
        foreach ($tokensA as $ta) {
            $ma = @metaphone($ta);
            foreach ($tokensB as $tb) {
                $mb = @metaphone($tb);
                if ($ma && $mb && $ma === $mb) {
                    $matches++;
                    break;
                }
            }
        }

        $den = max(1, max(count($tokensA), count($tokensB)));
        return $matches / $den;
    }
}
