<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * IntelligentFallbackService — Replaces Hardcoded Template Fallbacks
 *
 * When the primary LLM fails, instead of returning generic scripted messages,
 * this service employs a multi-layer recovery strategy:
 *
 * Layer 1: Retry with a different LLM provider
 * Layer 2: Semantic search for relevant knowledge + simple response generation
 * Layer 3: Context-aware clarification questions
 * Layer 4: Intelligent contextual suggestions based on user role and history
 *
 * The chatbot NEVER collapses into "I can't help you right now" style messages.
 */
class IntelligentFallbackService
{
    private LLMService $llmService;
    private VectorEmbeddingService $embeddingService;
    private ChatbotRealTimeDataService $dataService;

    public function __construct(
        LLMService $llmService,
        VectorEmbeddingService $embeddingService,
        ChatbotRealTimeDataService $dataService
    ) {
        $this->llmService = $llmService;
        $this->embeddingService = $embeddingService;
        $this->dataService = $dataService;
    }

    /**
     * Generate an intelligent fallback response when the primary LLM pipeline fails.
     *
     * @param string $userMessage  The user's original message
     * @param string $role         User role (guest, client, admin, cashier)
     * @param int|null $userId     User ID if authenticated
     * @param string $failureReason Why the primary pipeline failed
     * @return array Response array with 'response', 'source', 'fallback_layer'
     */
    public function generateFallback(
        string $userMessage,
        string $role,
        ?int $userId = null,
        string $failureReason = 'llm_unavailable'
    ): array {
        // Layer 1: Retry with minimal prompt on a different provider
        $retryResult = $this->retryWithMinimalPrompt($userMessage, $role);
        if ($retryResult !== null) {
            return [
                'response' => $retryResult,
                'source' => 'fallback_retry',
                'fallback_layer' => 1,
            ];
        }

        // Layer 2: Semantic search + template-free knowledge response
        $knowledgeResult = $this->buildKnowledgeBasedResponse($userMessage, $role);
        if ($knowledgeResult !== null) {
            return [
                'response' => $knowledgeResult,
                'source' => 'fallback_knowledge',
                'fallback_layer' => 2,
            ];
        }

        // Layer 3: Generate a clarification question
        $clarification = $this->generateClarification($userMessage, $role, $userId);
        if ($clarification !== null) {
            return [
                'response' => $clarification,
                'source' => 'fallback_clarification',
                'fallback_layer' => 3,
            ];
        }

        // Layer 4: Contextual suggestions based on role and data
        $suggestion = $this->generateContextualSuggestion($role, $userId);
        return [
            'response' => $suggestion,
            'source' => 'fallback_suggestion',
            'fallback_layer' => 4,
        ];
    }

    /**
     * Layer 1: Retry LLM with a stripped-down minimal prompt.
     * Uses less context to reduce token cost and increase success probability.
     */
    private function retryWithMinimalPrompt(string $userMessage, string $role): ?string
    {
        try {
            $minimalPrompt = "You are a helpful assistant for a legal services office. "
                . "The user's role is: {$role}. "
                . "Answer their question concisely and helpfully. "
                . "If you don't know, say so honestly and suggest they contact the office.";

            $result = $this->llmService->generateResponse(
                $userMessage,
                [], // No history — minimal context
                [
                    'system_prompt' => $minimalPrompt,
                    'role' => $role,
                    'skip_internal_prompt' => true,
                ]
            );

            if ($result['success'] && !empty($result['response'])) {
                return $result['response'];
            }
        } catch (\Exception $e) {
            Log::debug('Fallback Layer 1 (retry) failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Layer 2: Use semantic search to find relevant knowledge and build a response.
     * Does NOT require the LLM — constructs a response from retrieved documents.
     */
    private function buildKnowledgeBasedResponse(string $userMessage, string $role): ?string
    {
        try {
            $searchResults = $this->embeddingService->semanticSearch($userMessage, null, 3);

            $relevant = array_filter($searchResults, fn($doc) => ($doc['similarity'] ?? 0) >= 0.4);

            if (empty($relevant)) {
                return null;
            }

            $response = "Based on our knowledge base, here's what I found:\n\n";
            foreach ($relevant as $doc) {
                $title = $doc['title'] ?? 'Information';
                $content = $doc['content'] ?? '';
                // Trim content to reasonable length
                if (strlen($content) > 500) {
                    $content = substr($content, 0, 500) . '...';
                }
                $response .= "**{$title}**\n{$content}\n\n";
            }
            $response .= "If you need more specific help, please ask me a follow-up question.";

            return $response;
        } catch (\Exception $e) {
            Log::debug('Fallback Layer 2 (knowledge) failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Layer 3: Generate a smart clarification question based on detected intent signals.
     */
    private function generateClarification(string $userMessage, string $role, ?int $userId): ?string
    {
        $lower = mb_strtolower($userMessage);

        // Detect broad topic categories from keywords
        $topics = [];
        if (preg_match('/\b(appointment|book|schedule|cancel|reschedule|view|all|list|show|my)\s+(appointment|booking|schedule)?\b/i', $lower)) {
            $topics[] = 'appointments';
        }
        if (preg_match('/\b(pay|payment|billing|receipt|invoice|history|status|view|all|list|show|my)\s+(payment|billing|invoice)?\b/i', $lower)) {
            $topics[] = 'payments';
        }
        if (preg_match('/\b(refund|money back|return|request|status|my)\s+(refund|money back)?\b/i', $lower)) {
            $topics[] = 'refunds';
        }
        if (preg_match('/\b(service|offer|available|price|cost|list|all|what|type|legal)\s+(service|pricing|offer)?\b/i', $lower)) {
            $topics[] = 'services';
        }
        if (preg_match('/\b(office|location|address|hour|time|open|contact|phone|email|where|business)\b/i', $lower)) {
            $topics[] = 'office_info';
        }
        if (preg_match('/\b(system|developer|who\s+developed|about\s+the\s+system|what\s+is\s+this|creator|purpose|features)\b/i', $lower)) {
            $topics[] = 'system_info';
        }

        if (empty($topics)) {
            // If no topic is detected, check if it's a simple greeting or small talk
            if (preg_match('/\b(hi|hello|hey|good\s+(morning|afternoon|evening)|howdy)\b/i', $lower)) {
                return "Hello! I can help you with our legal services, appointments, and payments. What can I do for you today?";
            }

            // Otherwise, treat as out-of-scope per user requirements
            return config('chatbot_unified.safety.refusal_message', "This question is outside the scope of this system. I can only assist with topics related to this system.");
        }

        if (count($topics) === 1) {
            return $this->buildTopicClarification($topics[0], $role);
        }

        // Multiple topics detected — ask which one
        $topicList = implode(', ', array_map(fn($t) => "**{$t}**", $topics));
        return "I noticed your message could be about {$topicList}. Could you clarify which one you need help with?";
    }

    /**
     * Build a topic-specific clarification question.
     */
    private function buildTopicClarification(string $topic, string $role): string
    {
        return match ($topic) {
            'appointments' => $role === 'client'
                ? "I can help with your appointments! Would you like to:\n"
                  . "• View your upcoming appointments\n"
                  . "• Book a new appointment\n"
                  . "• Cancel or reschedule an existing one\n"
                  . "• Check an appointment's status"
                : "What would you like to do with appointments? (view all, approve, decline, etc.)",
            'payments' => "I can help with payments! Are you looking to:\n"
                . "• Check a payment status\n"
                . "• View your payment history\n"
                . "• Understand payment methods",
            'refunds' => "I can help with refunds! Would you like to:\n"
                . "• Request a new refund\n"
                . "• Check an existing refund status",
            'services' => "I can provide service information! Would you like to:\n"
                . "• See all available services\n"
                . "• Get pricing information\n"
                . "• Check availability for a specific service",
            'office_info' => $this->getOfficeInfoResponse(),
            'system_info' => $this->getSystemInfoResponse(),
            default => "Could you provide more details about what you need?",
        };
    }

    /**
     * Get office information response from configuration.
     */
    private function getOfficeInfoResponse(): string
    {
        $name    = config('chatbot_unified.business.name', 'Our Office');
        $address = config('chatbot_unified.business.address', 'Not available');
        $phone   = config('chatbot_unified.business.phone', 'Not available');
        $email   = config('chatbot_unified.business.email', 'Not available');

        return "Here is our office information for **{$name}**:\n\n"
             . "📍 **Address**: {$address}\n"
             . "📞 **Phone**: {$phone}\n"
             . "✉️ **Email**: {$email}\n\n"
             . "📅 **Hours**: 8:00 AM – 11:00 AM, 1:00 PM – 5:00 PM (Monday–Friday). We are closed during lunch (12:00 PM - 1:00 PM) and on weekends.";
    }

    /**
     * Get system and developer information response.
     */
    private function getSystemInfoResponse(): string
    {
        try {
            $sysInfo = app(\App\Services\SystemInfoProvider::class);
            return $sysInfo->getFormattedDescription('conversational', 'standard');
        } catch (\Exception $e) {
            return "This is an Appointment Management & Legal Services system designed to help you book appointments, manage services, and track payments. It was developed to provide efficient digital services for our clients.";
        }
    }

    /**
     * Layer 4: Generate contextual suggestions based on user role and recent data.
     * This is the final fallback — never returns a generic "try again later" message.
     */
    private function generateContextualSuggestion(string $role, ?int $userId): string
    {
        $suggestions = [];

        if ($userId) {
            try {
                // Check for recent appointments
                $appointments = $this->dataService->getUserAppointments($userId, null, 3);
                if (!empty($appointments)) {
                    $upcoming = array_filter($appointments, fn($a) => ($a['is_upcoming'] ?? false));
                    if (!empty($upcoming)) {
                        $next = reset($upcoming);
                        $suggestions[] = "You have an upcoming appointment on {$next['date']} at {$next['time']} ({$next['status']}).";
                    }
                }
            } catch (\Exception $e) {
                // Silently continue
            }
        }

        $intro = match ($role) {
            'admin' => "Here's what I can help you with as an admin:\n"
                . "• Review and manage pending appointments\n"
                . "• View system analytics and statistics\n"
                . "• Process refund requests\n"
                . "• Get AI risk assessments for appointments",
            'cashier' => "Here's what I can help you with:\n"
                . "• View pending payments\n"
                . "• Process payments and refunds\n"
                . "• View daily summary reports",
            'client' => "I can assist you with your appointments, payment history, and refund requests. What specifically do you need help with?",
            default => config('chatbot_unified.safety.refusal_message', "This question is outside the scope of this system. I can only assist with topics related to this system."),
        };

        if (!empty($suggestions)) {
            $intro .= "\n\n📋 Quick update: " . implode(' ', $suggestions);
        }

        $intro .= "\n\nJust type your question and I'll do my best to help!";

        return $intro;
    }
}
