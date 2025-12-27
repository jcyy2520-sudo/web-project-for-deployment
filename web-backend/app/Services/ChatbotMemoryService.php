<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatbotConversation;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotMemoryService - Persistent Memory & Context Management
 * 
 * Features:
 * - Extended context window (last 50 messages, not 10)
 * - Cross-session memory persistence
 * - User preference learning
 * - Conversation summarization
 * - Topic continuity tracking
 * - Semantic context retrieval
 */
class ChatbotMemoryService
{
    // Configurable limits
    private const MAX_CONTEXT_MESSAGES = 50;
    private const MAX_CONTEXT_TOKENS = 8000;
    private const SUMMARY_THRESHOLD = 30; // Summarize after this many messages
    private const PREFERENCE_CACHE_TTL = 3600; // 1 hour

    private ?EmbeddingService $embeddingService = null;

    public function __construct()
    {
        try {
            $this->embeddingService = app(EmbeddingService::class);
        } catch (\Exception $e) {
            Log::debug('EmbeddingService not available: ' . $e->getMessage());
        }
    }

    /**
     * Get comprehensive conversation context for LLM
     * Includes recent messages, user preferences, and relevant history
     */
    public function getConversationContext(
        ?int $userId,
        string $conversationId,
        int $maxMessages = null
    ): array {
        $maxMessages = $maxMessages ?? self::MAX_CONTEXT_MESSAGES;

        $context = [
            'recent_messages' => [],
            'conversation_summary' => null,
            'user_preferences' => [],
            'topics_discussed' => [],
            'pending_context' => null,
            'sentiment_trend' => 'neutral',
        ];

        if (!$userId) {
            // Guest context - limited
            return $context;
        }

        try {
            // Get recent messages for this conversation
            $context['recent_messages'] = $this->getRecentMessages($userId, $conversationId, $maxMessages);

            // Get conversation record
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if ($conversation) {
                $context['conversation_summary'] = $conversation->summary;
                $context['topics_discussed'] = $conversation->context_data['topics'] ?? [];
                $context['sentiment_trend'] = $conversation->overall_sentiment ?? 'neutral';
                $context['pending_context'] = $conversation->context_data['pending'] ?? null;
            }

            // Get user preferences
            $context['user_preferences'] = $this->getUserPreferences($userId);

            // Get related conversations context (for long-term memory)
            $context['related_context'] = $this->getRelatedContext($userId, $conversationId);

        } catch (\Exception $e) {
            Log::warning('Failed to build conversation context: ' . $e->getMessage());
        }

        return $context;
    }

    /**
     * Get recent messages with token limit awareness
     */
    private function getRecentMessages(int $userId, string $conversationId, int $limit): array
    {
        $messages = ChatMessage::where('user_id', $userId)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        // Apply token limit
        $tokenCount = 0;
        $filteredMessages = [];
        
        foreach ($messages as $msg) {
            // Rough token estimate: 4 chars per token
            $msgTokens = ceil(strlen($msg->message) / 4);
            
            if ($tokenCount + $msgTokens > self::MAX_CONTEXT_TOKENS) {
                break;
            }
            
            $tokenCount += $msgTokens;
            $filteredMessages[] = [
                'role' => $msg->role,
                'message' => $msg->message,
                'created_at' => $msg->created_at->toIso8601String(),
                'intent' => $msg->metadata['intent'] ?? null,
            ];
        }

        return $filteredMessages;
    }

    /**
     * Get user preferences and learned patterns
     */
    public function getUserPreferences(int $userId): array
    {
        $cacheKey = "user_preferences_{$userId}";
        
        return Cache::remember($cacheKey, self::PREFERENCE_CACHE_TTL, function() use ($userId) {
            $preferences = [
                'language' => 'en',
                'communication_style' => 'formal',
                'common_topics' => [],
                'preferred_actions' => [],
                'timezone' => null,
                'notification_preference' => 'standard',
            ];

            try {
                // Get from user preferences table if it exists
                $userPref = DB::table('user_preferences')
                    ->where('user_id', $userId)
                    ->first();

                if ($userPref) {
                    $storedPrefs = json_decode($userPref->chatbot_preferences ?? '{}', true);
                    $preferences = array_merge($preferences, $storedPrefs);
                }

                // Learn from conversation history
                $learnedPrefs = $this->learnPreferencesFromHistory($userId);
                $preferences = array_merge($preferences, $learnedPrefs);

            } catch (\Exception $e) {
                Log::debug('Failed to get user preferences: ' . $e->getMessage());
            }

            return $preferences;
        });
    }

    /**
     * Learn user preferences from their conversation history
     */
    private function learnPreferencesFromHistory(int $userId): array
    {
        $learned = [];

        try {
            // Detect preferred language
            $recentMessages = ChatMessage::where('user_id', $userId)
                ->where('role', 'user')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->pluck('message');

            $tagalogCount = 0;
            $englishCount = 0;

            foreach ($recentMessages as $msg) {
                // Simple language detection
                if (preg_match('/\b(po|ko|mo|na|ba|ang|ng|sa|mga|ako|ikaw)\b/i', $msg)) {
                    $tagalogCount++;
                } else {
                    $englishCount++;
                }
            }

            $learned['language'] = $tagalogCount > $englishCount * 0.3 ? 'tl' : 'en';

            // Detect communication style
            $formalIndicators = preg_match_all('/\b(please|kindly|would|could|may I)\b/i', implode(' ', $recentMessages->toArray()));
            $informalIndicators = preg_match_all('/\b(hey|hi|yo|pls|thx|gonna|wanna)\b/i', implode(' ', $recentMessages->toArray()));

            $learned['communication_style'] = $formalIndicators > $informalIndicators ? 'formal' : 'casual';

            // Find common topics
            $conversations = ChatbotConversation::where('user_id', $userId)
                ->whereNotNull('primary_intent')
                ->groupBy('primary_intent')
                ->selectRaw('primary_intent, count(*) as count')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'primary_intent');

            $learned['common_topics'] = $conversations->keys()->toArray();

        } catch (\Exception $e) {
            Log::debug('Failed to learn preferences: ' . $e->getMessage());
        }

        return $learned;
    }

    /**
     * Get context from related conversations (semantic search)
     */
    private function getRelatedContext(int $userId, string $currentConversationId): ?array
    {
        try {
            // Get the current conversation's main topic/intent
            $currentConv = ChatbotConversation::where('conversation_id', $currentConversationId)->first();
            if (!$currentConv || !$currentConv->primary_intent) {
                return null;
            }

            // Find related conversations with the same topic
            $relatedConvs = ChatbotConversation::where('user_id', $userId)
                ->where('conversation_id', '!=', $currentConversationId)
                ->where('primary_intent', $currentConv->primary_intent)
                ->whereNotNull('summary')
                ->orderBy('updated_at', 'desc')
                ->limit(3)
                ->get();

            if ($relatedConvs->isEmpty()) {
                return null;
            }

            $relatedContext = [];
            foreach ($relatedConvs as $conv) {
                $relatedContext[] = [
                    'topic' => $conv->primary_intent,
                    'summary' => $conv->summary,
                    'outcome' => $conv->context_data['outcome'] ?? null,
                    'date' => $conv->updated_at->diffForHumans(),
                ];
            }

            return $relatedContext;

        } catch (\Exception $e) {
            Log::debug('Failed to get related context: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update conversation context after a message
     */
    public function updateContext(
        int $userId,
        string $conversationId,
        string $role,
        string $message,
        array $metadata = []
    ): void {
        try {
            // Get or create conversation record
            $conversation = ChatbotConversation::getOrCreate($conversationId, $userId, null);

            // Update message counts
            $conversation->recordMessage($role, $message, $metadata);

            // Update detected language if provided
            if (!empty($metadata['detected_language'])) {
                $conversation->detected_language = $metadata['detected_language'];
            }

            // Update primary intent if this is a strong intent detection
            if (!empty($metadata['intent']) && ($metadata['intent_confidence'] ?? 0) > 0.7) {
                $conversation->primary_intent = $metadata['intent'];
            }

            // Update topics discussed
            $contextData = $conversation->context_data ?? [];
            $topics = $contextData['topics'] ?? [];
            
            if (!empty($metadata['intent']) && !in_array($metadata['intent'], $topics)) {
                $topics[] = $metadata['intent'];
                $contextData['topics'] = array_slice($topics, -10); // Keep last 10 topics
            }

            // Store pending context (for follow-ups)
            if (!empty($metadata['pending_action'])) {
                $contextData['pending'] = $metadata['pending_action'];
            }

            // Store entities mentioned
            if (!empty($metadata['entities'])) {
                $contextData['last_entities'] = $metadata['entities'];
            }

            $conversation->context_data = $contextData;
            $conversation->last_activity_at = now();
            $conversation->save();

            // Check if we should generate a summary
            if ($conversation->message_count >= self::SUMMARY_THRESHOLD && !$conversation->summary) {
                $this->generateConversationSummary($conversation);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update conversation context: ' . $e->getMessage());
        }
    }

    /**
     * Generate a summary of the conversation (for long-term memory)
     */
    private function generateConversationSummary(ChatbotConversation $conversation): void
    {
        try {
            // Get key messages from the conversation
            $messages = ChatMessage::where('conversation_id', $conversation->conversation_id)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($messages->count() < 5) {
                return;
            }

            // Build a simple summary based on intents and topics
            $intents = $messages->pluck('metadata.intent')->filter()->unique()->values()->toArray();
            $summary = "Conversation about: " . implode(', ', array_slice($intents, 0, 5));

            // Add outcome if there were any actions
            $actions = $messages->where('metadata.action_executed', true);
            if ($actions->isNotEmpty()) {
                $summary .= ". Actions taken: " . $actions->count();
            }

            $conversation->summary = $summary;
            $conversation->save();

            // If embedding service is available, generate embedding for semantic search
            if ($this->embeddingService) {
                $this->embeddingService->storeConversationEmbedding(
                    $conversation->conversation_id,
                    $summary
                );
            }

        } catch (\Exception $e) {
            Log::debug('Failed to generate conversation summary: ' . $e->getMessage());
        }
    }

    /**
     * Store user preference
     */
    public function storeUserPreference(int $userId, string $key, $value): void
    {
        try {
            DB::table('user_preferences')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'chatbot_preferences' => DB::raw(
                        "JSON_SET(COALESCE(chatbot_preferences, '{}'), '$.\"{$key}\"', " . 
                        DB::connection()->getPdo()->quote(json_encode($value)) . ")"
                    ),
                    'updated_at' => now(),
                ]
            );

            // Clear cache
            Cache::forget("user_preferences_{$userId}");

        } catch (\Exception $e) {
            Log::debug('Failed to store user preference: ' . $e->getMessage());
        }
    }

    /**
     * Get pending context (for follow-up handling)
     */
    public function getPendingContext(int $userId, string $conversationId): ?array
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            return $conversation?->context_data['pending'] ?? null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clear pending context after it's been handled
     */
    public function clearPendingContext(int $userId, string $conversationId): void
    {
        try {
            $conversation = ChatbotConversation::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if ($conversation) {
                $contextData = $conversation->context_data ?? [];
                unset($contextData['pending']);
                $conversation->context_data = $contextData;
                $conversation->save();
            }

        } catch (\Exception $e) {
            Log::debug('Failed to clear pending context: ' . $e->getMessage());
        }
    }

    /**
     * Get conversation history across sessions (for continuity)
     */
    public function getCrossSessionHistory(int $userId, int $limit = 5): array
    {
        try {
            return ChatbotConversation::where('user_id', $userId)
                ->whereNotNull('summary')
                ->orderBy('last_activity_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($conv) => [
                    'conversation_id' => $conv->conversation_id,
                    'summary' => $conv->summary,
                    'primary_topic' => $conv->primary_intent,
                    'date' => $conv->last_activity_at->diffForHumans(),
                    'outcome' => $conv->context_data['outcome'] ?? null,
                ])
                ->toArray();

        } catch (\Exception $e) {
            Log::debug('Failed to get cross-session history: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve contextual references in user messages
     * Examples: "that one", "yung sinabi mo kanina", "it", "the appointment", etc.
     * 
     * @param string $message Current user message
     * @param array $recentMessages Previous messages in conversation
     * @return array Resolved context and references
     */
    public function resolveContextualReferences(string $message, array $recentMessages): array
    {
        $resolved = [
            'original_message' => $message,
            'has_references' => false,
            'references' => [],
            'context_entities' => [],
            'clarification_needed' => false,
        ];

        // Patterns for contextual references (English and Tagalog)
        $referencePatterns = [
            // English references
            'that_one' => '/\b(that one|it|that|the one|this one)\b/i',
            'that_appointment' => '/\b(the appointment|my appointment|that appointment|the booking)\b/i',
            'previous_mention' => '/\b(what you mentioned|as you said|like you said|the thing)\b/i',
            'last_discussed' => '/\b(last time|before|earlier)\b/i',
            
            // Tagalog/Taglish references
            'yung_reference' => '/\byung\s+(sinabi|tinu|sabi|mentioned|nabanggit)\b/i',
            'yan_reference' => '/\b(yan|iyan|yun|iyon|yon|doon)\b/i',
            'kanina_reference' => '/\b(kanina|awhile ago|kahapon|earlier)\b/i',
            'ang_reference' => '/\bango|ang\s+\w+\b/i',
            'dito_reference' => '/\b(dito|dyan|doon|here|there)\b/i',
        ];

        // Check for reference patterns
        foreach ($referencePatterns as $type => $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $resolved['has_references'] = true;
                $resolved['references'][] = [
                    'type' => $type,
                    'matched_text' => $matches[0],
                ];
            }
        }

        // If message has references, try to resolve them from recent messages
        if ($resolved['has_references'] && !empty($recentMessages)) {
            $resolved['context_entities'] = $this->extractContextFromRecent($recentMessages);
            
            // Check if we have enough context to resolve references
            if (empty($resolved['context_entities'])) {
                $resolved['clarification_needed'] = true;
            }
        }

        // Handle incomplete messages
        if (preg_match('/^(di|hindi|no|yep|oo|oops|wait|actually|cancel|never mind)$/i', trim($message))) {
            $resolved['is_brief_response'] = true;
            $resolved['expected_context'] = 'previous_question_or_statement';
        }

        return $resolved;
    }

    /**
     * Extract entities and context from recent messages
     */
    private function extractContextFromRecent(array $recentMessages): array
    {
        $entities = [
            'appointments' => [],
            'services' => [],
            'dates' => [],
            'times' => [],
            'people' => [],
            'last_mentioned' => null,
        ];

        foreach ($recentMessages as $msg) {
            $text = $msg['message'] ?? '';
            
            // Extract appointments
            if (preg_match('/appointment|booking|sched/i', $text)) {
                $entities['appointments'][] = $text;
            }
            
            // Extract services
            if (preg_match('/service|notary|legal|consultation/i', $text)) {
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
        }

        // Store the most recent main context
        if (!empty($recentMessages)) {
            $lastMsg = end($recentMessages);
            $entities['last_mentioned'] = $lastMsg['message'] ?? null;
        }

        return $entities;
    }

    /**
     * Build context-aware clarification questions for ambiguous references
     */
    public function buildClarificationForReference(array $resolvedReference, string $language = 'en'): string
    {
        $templates = [
            'en' => [
                'general' => "Could you clarify what you're referring to? Please provide more details.",
                'that_one' => "I noticed you mentioned 'that one' - which item are you referring to? Please be more specific.",
                'appointment' => "Which appointment are you asking about? Please provide the date or service name.",
                'previous' => "I'm not sure what you mean by that. Could you rephrase or give me more context?",
            ],
            'tl' => [
                'general' => "Pwede mo ba i-clarify kung ano ang tinutukoy mo? Bigyan mo ko ng mas maraming detalye.",
                'that_one' => "Nakita ko na sinabi mo 'yun' - aling bagay ang tinutukoy mo? Mas specific naman.",
                'appointment' => "Aling appointment ang tinutukoy mo? Bigyan mo ko ng petsa o serbisyo.",
                'previous' => "Hindi ko maintindihan. Pwede mo ba i-rephrase o bigyan mo ako ng mas maraming context?",
            ],
        ];

        $hasReferences = $resolvedReference['has_references'] ?? false;
        $needsClarification = $resolvedReference['clarification_needed'] ?? false;
        
        if (!$hasReferences || !$needsClarification) {
            return '';
        }

        $template = $templates[$language] ?? $templates['en'];
        $referenceType = $resolvedReference['references'][0]['type'] ?? 'general';
        
        // Match reference type to template
        if (strpos($referenceType, 'appointment') !== false) {
            return $template['appointment'];
        } elseif (strpos($referenceType, 'kanina') !== false || strpos($referenceType, 'last') !== false) {
            return $template['previous'];
        } elseif (strpos($referenceType, 'yan') !== false || strpos($referenceType, 'that') !== false) {
            return $template['that_one'];
        }

        return $template['general'];
    }

    /**
     * Check if user is asking a follow-up without restating context
     * Returns true if message is a natural follow-up (no need to repeat context)
     */
    public function isNaturalFollowUp(string $currentMessage, array $recentMessages): bool
    {
        if (empty($recentMessages)) {
            return false;
        }

        $lastMessage = end($recentMessages);
        
        // Check if current message relates to last message
        $currentWords = array_filter(preg_split('/\W+/', mb_strtolower($currentMessage)));
        $lastWords = array_filter(preg_split('/\W+/', mb_strtolower($lastMessage['message'] ?? '')));
        
        // Calculate similarity (Jaccard similarity)
        $intersection = count(array_intersect($currentWords, $lastWords));
        $union = count(array_unique(array_merge($currentWords, $lastWords)));
        $similarity = $union > 0 ? $intersection / $union : 0;

        // Natural follow-up if:
        // 1. Contains contextual reference words
        // 2. Has reasonable similarity to previous message
        // 3. Is relatively short (follow-ups are typically brief)
        
        $hasContextRef = preg_match('/\b(that|it|so|then|what|why|how|more|please|yes|no|okay|ok|sure|absolutely)\b/i', $currentMessage);
        $isShortMessage = str_word_count($currentMessage) <= 15;
        
        return ($hasContextRef || $similarity > 0.2) && $isShortMessage;
    }

    /**
     * Format context for LLM system prompt
     */
    public function formatContextForPrompt(array $context): string
    {
        $formatted = "";

        // Add user preferences
        if (!empty($context['user_preferences'])) {
            $prefs = $context['user_preferences'];
            $formatted .= "User Preferences:\n";
            $formatted .= "- Language: " . ($prefs['language'] === 'tl' ? 'Filipino/Taglish' : 'English') . "\n";
            $formatted .= "- Style: " . ($prefs['communication_style'] ?? 'standard') . "\n";
            if (!empty($prefs['common_topics'])) {
                $formatted .= "- Common topics: " . implode(', ', array_slice($prefs['common_topics'], 0, 5)) . "\n";
            }
        }

        // Add conversation context
        if (!empty($context['conversation_summary'])) {
            $formatted .= "\nPrevious conversation context:\n{$context['conversation_summary']}\n";
        }

        // Add related history
        if (!empty($context['related_context'])) {
            $formatted .= "\nRelated past conversations:\n";
            foreach ($context['related_context'] as $related) {
                $formatted .= "- {$related['summary']} ({$related['date']})\n";
            }
        }

        // Add pending context
        if (!empty($context['pending_context'])) {
            $formatted .= "\nPending from last message:\n";
            $formatted .= json_encode($context['pending_context'], JSON_PRETTY_PRINT) . "\n";
        }

        return $formatted;
    }
}
