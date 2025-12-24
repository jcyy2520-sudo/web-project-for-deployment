<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * PersonalityService - Role-based and sentiment-adaptive responses
 * 
 * Features:
 * - Role-specific personality traits
 * - Tone adaptation based on user sentiment
 * - Response personality injection
 * - Conversation style customization
 */
class PersonalityService
{
    /**
     * Define personality traits for each role
     */
    private const ROLE_PERSONALITIES = [
        'guest' => [
            'tone' => 'professional',
            'formality' => 'formal',
            'examples' => true,
            'encouragement' => false,
            'context_depth' => 'surface',
            'system_prompt_addition' => 'Be professional and informative. Provide guidance on system features and registration.',
        ],
        'client' => [
            'tone' => 'professional',
            'formality' => 'formal',
            'examples' => true,
            'encouragement' => false,
            'context_depth' => 'detailed',
            'system_prompt_addition' => 'Be helpful and professional. Provide accurate information about their appointments and system usage.',
        ],
        'admin' => [
            'tone' => 'professional',
            'formality' => 'formal',
            'examples' => true,
            'encouragement' => false,
            'context_depth' => 'comprehensive',
            'system_prompt_addition' => 'Provide detailed system information and analytics in a neutral, professional manner.',
        ],
        'cashier' => [
            'tone' => 'professional',
            'formality' => 'formal',
            'examples' => false,
            'encouragement' => false,
            'context_depth' => 'detailed',
            'system_prompt_addition' => 'Provide accurate payment and transaction information. Help with shift reporting and verification.',
        ],
    ];

    /**
     * Sentiment-based tone adjustments
     */
    private const SENTIMENT_ADJUSTMENTS = [
        'very_positive' => [
            'enthusiasm' => 'low',
            'emoji_friendly' => false,
            'tone_override' => 'professional',
            'response_length' => 'normal',
        ],
        'positive' => [
            'enthusiasm' => 'low',
            'emoji_friendly' => false,
            'tone_override' => 'professional',
            'response_length' => 'normal',
        ],
        'neutral' => [
            'enthusiasm' => 'low',
            'emoji_friendly' => false,
            'tone_override' => 'professional',
            'response_length' => 'normal',
        ],
        'negative' => [
            'enthusiasm' => 'low',
            'emoji_friendly' => false,
            'tone_override' => 'professional',
            'response_length' => 'normal',
            'extra_support' => true,
        ],
        'very_negative' => [
            'enthusiasm' => 'very_low',
            'emoji_friendly' => false,
            'tone_override' => 'professional',
            'response_length' => 'normal',
            'extra_support' => true,
            'offer_escalation' => true,
        ],
    ];

    /**
     * Get personalized system prompt based on role and sentiment
     */
    public function getPersonalizedSystemPrompt(
        string $role,
        string $sentiment = 'neutral'
    ): string {
        $basePrompt = "You are a smart, helpful AI assistant for a legal appointment booking system.

## Your Responsibilities:
1. Provide accurate information about appointments, services, payments, and refunds
2. Use real-time data provided in context - NEVER fabricate information
3. Be professional but friendly and approachable
4. Address user concerns with empathy, especially if they're frustrated
5. When uncertain, ask clarifying questions rather than guessing
6. Keep responses concise but informative (aim for 50-150 words)
7. Use the user's language (including Taglish/Filipino if they use it)

";

        // Add role-specific personality
        $rolePersonality = self::ROLE_PERSONALITIES[$role] ?? self::ROLE_PERSONALITIES['guest'];
        $basePrompt .= "## Role Personality:\n";
        $basePrompt .= "- Tone: {$rolePersonality['tone']}\n";
        $basePrompt .= "- Formality Level: {$rolePersonality['formality']}\n";
        $basePrompt .= "- Provide Examples: " . ($rolePersonality['examples'] ? 'yes' : 'no') . "\n";
        $basePrompt .= "- Show Encouragement: " . ($rolePersonality['encouragement'] ? 'yes' : 'no') . "\n";
        $basePrompt .= "- Context Depth: {$rolePersonality['context_depth']}\n";
        $basePrompt .= "- Special Instructions: {$rolePersonality['system_prompt_addition']}\n\n";

        // Add sentiment-based adjustments
        $sentimentAdjustments = self::SENTIMENT_ADJUSTMENTS[$sentiment] ?? self::SENTIMENT_ADJUSTMENTS['neutral'];
        $basePrompt .= "## User Sentiment Context:\n";
        $basePrompt .= "- Current Sentiment: " . ucfirst(str_replace('_', ' ', $sentiment)) . "\n";
        $basePrompt .= "- Enthusiasm Level: " . ucfirst(str_replace('_', ' ', $sentimentAdjustments['enthusiasm'])) . "\n";
        
        if ($sentimentAdjustments['tone_override']) {
            $basePrompt .= "- Tone Override: {$sentimentAdjustments['tone_override']}\n";
        }

        if ($sentimentAdjustments['extra_support'] ?? false) {
            $basePrompt .= "- Extra Support: Show extra care and patience\n";
        }

        if ($sentimentAdjustments['offer_escalation'] ?? false) {
            $basePrompt .= "- Offer Escalation: If needed, suggest human support\n";
        }

        $basePrompt .= "\n## Response Format:\n";
        $basePrompt .= "- Response Length: " . ucfirst(str_replace('_', ' ', $sentimentAdjustments['response_length'])) . "\n";
        $basePrompt .= "- Use Friendly Formatting: Use bullet points, numbered lists when appropriate\n";
        $basePrompt .= "- Clear Call-to-Action: Suggest next steps or how you can further assist\n";

        return $basePrompt;
    }

    /**
     * Detect sentiment from user message
     * 
     * Returns: very_positive, positive, neutral, negative, very_negative
     */
    public function detectSentiment(string $message): string
    {
        $messageLower = strtolower($message);

        // Very positive indicators
        $veryPositiveKeywords = ['thank you', 'thanks', 'amazing', 'awesome', 'excellent', 'perfect', '❤️', '😍', '🎉'];
        foreach ($veryPositiveKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'very_positive';
            }
        }

        // Positive indicators
        $positiveKeywords = ['good', 'great', 'nice', 'helpful', 'appreciate', 'love', '😊', '👍'];
        foreach ($positiveKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'positive';
            }
        }

        // Negative indicators
        $negativeKeywords = ['bad', 'wrong', 'issue', 'problem', 'not working', 'frustrated', '😕', '😞'];
        foreach ($negativeKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'negative';
            }
        }

        // Very negative indicators
        $veryNegativeKeywords = ['terrible', 'awful', 'useless', 'waste', 'angry', 'furious', 'never', '😠', '🤬'];
        foreach ($veryNegativeKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'very_negative';
            }
        }

        return 'neutral';
    }

    /**
     * Adjust response based on sentiment
     * 
     * Enhances bot response to match user's emotional state
     */
    public function adjustResponseForSentiment(
        string $response,
        string $sentiment
    ): string {
        $adjustments = self::SENTIMENT_ADJUSTMENTS[$sentiment] ?? self::SENTIMENT_ADJUSTMENTS['neutral'];

        // Add empathy for negative sentiments
        if (in_array($sentiment, ['negative', 'very_negative'])) {
            $empathyPrefix = match($sentiment) {
                'negative' => "I understand this might be frustrating. ",
                'very_negative' => "I sincerely apologize for the frustration you're experiencing. ",
                default => ""
            };

            $response = $empathyPrefix . $response;
        }

        // Add encouragement for positive sentiments
        if ($adjustments['enthusiasm'] === 'high') {
            $response .= " We're here to help whenever you need us!";
        }

        // Suggest escalation for very negative
        if ($adjustments['offer_escalation'] ?? false) {
            $response .= "\n\nIf you'd like to speak with someone directly, I can connect you with our team. Would that help?";
        }

        return $response;
    }

    /**
     * Get role capabilities for response formatting
     */
    public function getRoleCapabilities(string $role): array
    {
        $capabilities = [
            'guest' => [
                'can_view_services' => true,
                'can_book_appointment' => true,
                'can_view_own_appointments' => false,
                'can_process_payments' => false,
                'can_access_analytics' => false,
                'can_approve_items' => false,
            ],
            'client' => [
                'can_view_services' => true,
                'can_book_appointment' => true,
                'can_view_own_appointments' => true,
                'can_process_payments' => true,
                'can_access_analytics' => false,
                'can_approve_items' => false,
            ],
            'admin' => [
                'can_view_services' => true,
                'can_book_appointment' => true,
                'can_view_own_appointments' => true,
                'can_process_payments' => true,
                'can_access_analytics' => true,
                'can_approve_items' => true,
            ],
            'cashier' => [
                'can_view_services' => true,
                'can_book_appointment' => false,
                'can_view_own_appointments' => false,
                'can_process_payments' => true,
                'can_access_analytics' => true,
                'can_approve_items' => false,
            ],
        ];

        return $capabilities[$role] ?? $capabilities['guest'];
    }

    /**
     * Create response suggestions based on role and sentiment
     */
    public function getResponseSuggestions(
        string $userMessage,
        string $role,
        string $sentiment
    ): array {
        $suggestions = [];

        // Role-based suggestions
        if ($role === 'guest' && strpos(strtolower($userMessage), 'book') !== false) {
            $suggestions[] = [
                'action' => 'encourage_registration',
                'text' => 'Consider mentioning registration benefits'
            ];
        }

        // Sentiment-based suggestions
        if ($sentiment === 'very_negative') {
            $suggestions[] = [
                'action' => 'offer_support',
                'text' => 'Offer to connect with human support'
            ];
        }

        if ($sentiment === 'negative') {
            $suggestions[] = [
                'action' => 'show_empathy',
                'text' => 'Express understanding of their situation'
            ];
        }

        return $suggestions;
    }
}
