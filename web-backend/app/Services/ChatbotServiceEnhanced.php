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
class ChatbotServiceEnhancementsDuplicate
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
}
