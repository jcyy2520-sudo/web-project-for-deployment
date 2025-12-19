<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * SmartIntentRecognitionService - Advanced Intent Classification & Disambiguation
 * 
 * Intelligent intent recognition with:
 * - Multi-level intent classification (primary, secondary, context)
 * - Confidence scoring and uncertainty handling
 * - Disambiguation when user intent is ambiguous
 * - Contextual memory for coherent conversations
 * - Fuzzy command matching
 * - Incomplete sentence handling
 * - Intent correction based on context
 * - Multi-language support
 */
class SmartIntentRecognitionService
{
    private const CACHE_PREFIX = 'intent_';
    private const CACHE_TTL = 3600;

    private AdvancedNLPService $nlpService;

    public function __construct(AdvancedNLPService $nlpService)
    {
        $this->nlpService = $nlpService;
    }

    /**
     * Comprehensive intent hierarchy with confidence scoring
     */
    private array $intentHierarchy = [
        // APPOINTMENT MANAGEMENT
        'appointment' => [
            'view' => [
                'keywords' => ['show', 'list', 'view', 'see', 'check', 'display', 'list all'],
                'patterns' => [
                    '/\b(show|list|view|see|check|display)\s+(my\s+)?(appointments?|bookings?|reservations?|schedule)/i',
                    '/\b(what|how many|do i have).{0,10}(appointments?|bookings?)/i',
                    '/\b(my\s+)?(appointments?|bookings?|reservations?|schedule)/i',
                ],
                'language_variations' => [
                    'tl' => ['mga appointment', 'booking ko', 'appointments ko', 'anong appointment ko', 'saan appointments'],
                ],
                'priority' => 1,
            ],
            'book' => [
                'keywords' => ['book', 'booking', 'book', 'reserve', 'make', 'create', 'schedule', 'set up'],
                'patterns' => [
                    '/\b(book|booking|reserve|make|create|schedule|set up).{0,20}(appointment|booking|reservation)/i',
                    '/\b(i want|i\'d like|i need|can i|can you).{0,10}(book|schedule|reserve).{0,10}(appointment|booking)/i',
                    '/\bhow\s+(can i|to)\s+(book|schedule|reserve)/i',
                ],
                'language_variations' => [
                    'tl' => ['magbook', 'gusto ko magbook', 'puwede ba magbook', 'magpareserve', 'magschedule'],
                ],
                'priority' => 1,
            ],
            'cancel' => [
                'keywords' => ['cancel', 'delete', 'remove', 'cancel', 'withdraw'],
                'patterns' => [
                    '/\b(cancel|delete|remove|withdraw).{0,20}(my\s+)?(appointment|booking|reservation)/i',
                    '/\b(i want|i need|can i).{0,10}(cancel|delete|remove)\s+(my\s+)?(appointment|booking)/i',
                ],
                'language_variations' => [
                    'tl' => ['cancel appointment', 'bawiin', 'alisin ang booking', 'i-cancel ang appointment'],
                ],
                'priority' => 1,
            ],
            'reschedule' => [
                'keywords' => ['reschedule', 'change', 'move', 'shift', 'postpone', 'change date', 'change time'],
                'patterns' => [
                    '/\b(reschedule|change|move|shift|postpone|change\s+(date|time)).{0,20}(my\s+)?(appointment|booking)/i',
                    '/\b(can i|can you|i want).{0,10}(reschedule|change|move).{0,10}(my\s+)?(appointment|booking)/i',
                ],
                'language_variations' => [
                    'tl' => ['baguhin ang date', 'i-reschedule', 'gawin ng iba oras', 'lipat ng date'],
                ],
                'priority' => 1,
            ],
            'status' => [
                'keywords' => ['status', 'check', 'pending', 'approved', 'declined', 'completed', 'what is', 'where is'],
                'patterns' => [
                    '/\b(what is|check|where is).{0,10}(my\s+)?(appointment|booking)\s*(status|progress)/i',
                    '/\b(is my|my).{0,10}(appointment|booking).{0,10}(pending|approved|declined|completed|confirmed)/i',
                    '/\b(appointment|booking).{0,20}(status|update|progress)/i',
                ],
                'language_variations' => [
                    'tl' => ['ano status ng appointment', 'approved na ba', 'pending pa ba', 'anong status'],
                ],
                'priority' => 1,
            ],
        ],

        // SERVICE INFORMATION
        'service' => [
            'list' => [
                'keywords' => ['service', 'services', 'offer', 'available', 'what do you offer', 'list'],
                'patterns' => [
                    '/\b(what|which)\s+(services?|offerings?)\s+(do you|are|have)\s+(offer|available)/i',
                    '/\b(list|show|display|tell me about)\s+(your\s+)?(services?|offerings?)/i',
                    '/\b(services?|offerings?)\s+(available|do you offer)/i',
                ],
                'language_variations' => [
                    'tl' => ['anong service', 'ano ang available', 'mga service', 'ano ang offer'],
                ],
                'priority' => 2,
            ],
            'details' => [
                'keywords' => ['service', 'details', 'information', 'about', 'describe', 'tell me'],
                'patterns' => [
                    '/\b(tell me about|what is|details|information|describe)\s+(the\s+)?\w+\s*(service)/i',
                    '/\b(service|services)\s+(details?|information|description)/i',
                ],
                'language_variations' => [
                    'tl' => ['detalye ng service', 'paano ang service', 'ano ang steps'],
                ],
                'priority' => 2,
            ],
            'pricing' => [
                'keywords' => ['price', 'cost', 'how much', 'rate', 'fee', 'charge', 'magkano'],
                'patterns' => [
                    '/\b(how much|what is|what\'s).{0,20}(the\s+)?(price|cost|rate|fee|charge)/i',
                    '/\b(price|cost|rate|fee)\s+(of|for).{0,20}(the\s+)?(service)?/i',
                    '/\b(how much)\s+(is|does).{0,10}(the)?\s*\w+\s*(service|cost)/i',
                    '/\b(magkano|price|cost)\b/i',
                ],
                'language_variations' => [
                    'tl' => ['magkano ang service', 'magkano yan', 'price ng', 'cost ng'],
                ],
                'priority' => 2,
            ],
            'availability' => [
                'keywords' => ['available', 'availability', 'when', 'time', 'slot', 'open', 'hours'],
                'patterns' => [
                    '/\b(when|what time|what day)\s+(is|are|can i book).{0,20}(available|open)/i',
                    '/\b(availability|available\s+time|available\s+slot)/i',
                ],
                'language_variations' => [
                    'tl' => ['kailan available', 'available slots', 'ano oras open'],
                ],
                'priority' => 2,
            ],
        ],

        // PAYMENT & REFUND
        'payment' => [
            'process' => [
                'keywords' => ['payment', 'pay', 'paid', 'charge', 'billing'],
                'patterns' => [
                    '/\b(how|where|can i).{0,10}(pay|process payment|pay for)/i',
                    '/\b(payment|pay|billing|charge|process)\s+(method|options|gateway)/i',
                ],
                'language_variations' => [
                    'tl' => ['paano magbayad', 'saan magbayad', 'payment method'],
                ],
                'priority' => 1,
            ],
            'status' => [
                'keywords' => ['payment status', 'paid', 'unpaid', 'check', 'receipt'],
                'patterns' => [
                    '/\b(check|what is).{0,10}(my\s+)?(payment status|payment\s+status)/i',
                    '/\b(payment|invoice|receipt).{0,10}(status|number|details)/i',
                ],
                'language_variations' => [
                    'tl' => ['payment status', 'bayad na ba', 'saan receipt'],
                ],
                'priority' => 2,
            ],
        ],

        'refund' => [
            'request' => [
                'keywords' => ['refund', 'refund', 'money back', 'return', 'cancel payment'],
                'patterns' => [
                    '/\b(request|how to|can i).{0,10}(refund|money back|return)/i',
                    '/\b(refund|return|money back)\s+(request|policy|process)/i',
                ],
                'language_variations' => [
                    'tl' => ['refund request', 'money back', 'bawiin ang bayad'],
                ],
                'priority' => 1,
            ],
            'status' => [
                'keywords' => ['refund status', 'pending', 'processed', 'check'],
                'patterns' => [
                    '/\b(check|what is).{0,10}(my\s+)?(refund status)/i',
                    '/\b(refund).{0,10}(status|progress|pending)/i',
                ],
                'language_variations' => [
                    'tl' => ['refund status', 'pending pa ba ang refund'],
                ],
                'priority' => 2,
            ],
        ],

        // ACCOUNT & PROFILE
        'account' => [
            'profile' => [
                'keywords' => ['profile', 'my profile', 'account', 'information', 'details'],
                'patterns' => [
                    '/\b(view|check|show|see).{0,10}(my\s+)?(profile|account|information|details)/i',
                    '/\b(my\s+)?(profile|account|information)\s+(details|information)/i',
                ],
                'language_variations' => [
                    'tl' => ['profile ko', 'account ko', 'information ko'],
                ],
                'priority' => 2,
            ],
            'edit' => [
                'keywords' => ['edit', 'update', 'change', 'modify', 'information'],
                'patterns' => [
                    '/\b(edit|update|change|modify).{0,10}(my\s+)?(profile|account|information)/i',
                    '/\b(change|update).{0,10}(name|email|phone|address|password)/i',
                ],
                'language_variations' => [
                    'tl' => ['baguhin ang profile', 'update ng info', 'change password'],
                ],
                'priority' => 2,
            ],
        ],

        // SYSTEM & SUPPORT
        'help' => [
            'general' => [
                'keywords' => ['help', 'support', 'assist', 'question', 'problem', 'issue'],
                'patterns' => [
                    '/\b(help|support|assist|what can you do|how can you help)/i',
                    '/\b(i need help|i have a question|i have a problem)/i',
                ],
                'language_variations' => [
                    'tl' => ['tulong', 'tanong ko', 'problema ko', 'kailangan ko ng help'],
                ],
                'priority' => 3,
            ],
        ],

        // GREETINGS & FAREWELLS
        'greeting' => [
            'hello' => [
                'keywords' => ['hello', 'hi', 'hey', 'greetings', 'welcome'],
                'patterns' => [
                    '/^\b(hello|hi|hey|greetings|welcome)\b/i',
                    '/^(hello|hi|hey|greetings|welcome)\s+there/i',
                ],
                'language_variations' => [
                    'tl' => ['hello', 'hi', 'magandang umaga', 'kumusta'],
                ],
                'priority' => 4,
            ],
        ],

        'farewell' => [
            'goodbye' => [
                'keywords' => ['goodbye', 'bye', 'farewell', 'see you', 'take care'],
                'patterns' => [
                    '/\b(goodbye|bye|bye bye|farewell|see you|take care|later)\b/i',
                ],
                'language_variations' => [
                    'tl' => ['paalam', 'bye', 'kita', 'goodbye', 'salamat'],
                ],
                'priority' => 4,
            ],
        ],
    ];

    /**
     * Recognize intent with high accuracy and confidence scoring
     * Handles ambiguity and suggests clarifications
     * 
     * @param string $text Normalized user message
     * @param array $conversationContext Recent conversation history
     * @param string $language User's language
     * @return array Intent recognition result with confidence and alternatives
     */
    public function recognizeIntent(string $text, array $conversationContext = [], string $language = 'english'): array
    {
        $text = mb_strtolower(trim($text));
        
        $cacheKey = self::CACHE_PREFIX . md5("intent_{$text}_{$language}");
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Score all intents
        $scores = [];
        $this->scoreAllIntents($text, $language, $scores);

        // Get top 3 matches
        arsort($scores);
        $topMatches = array_slice($scores, 0, 3, true);

        // Determine if we have high confidence
        $topIntent = key($scores);
        $topScore = reset($scores);
        $isHighConfidence = $topScore >= 0.7;
        $needsClarification = (count($topMatches) > 1 && $topMatches[array_key_first($topMatches)] - $topMatches[array_key_last($topMatches)] < 0.15);

        // Use conversation context to disambiguate
        if (!$isHighConfidence && !empty($conversationContext)) {
            $contextResult = $this->disambiguateWithContext($topMatches, $conversationContext);
            if ($contextResult) {
                $topIntent = $contextResult['intent'];
                $topScore = $contextResult['score'];
                $isHighConfidence = true;
            }
        }

        $result = [
            'primary_intent' => $topIntent,
            'primary_confidence' => round($topScore, 3),
            'is_high_confidence' => $isHighConfidence,
            'alternatives' => array_slice(array_keys($topMatches), 1),
            'alternative_scores' => array_slice(array_values($topMatches), 1),
            'needs_clarification' => $needsClarification,
            'suggested_clarification' => $needsClarification ? $this->generateClarificationPrompt(array_slice(array_keys($topMatches), 0, 2), $language) : null,
            'all_scores' => array_map(fn($s) => round($s, 3), array_slice($scores, 0, 10, true)),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Score all intents against the user's text
     */
    private function scoreAllIntents(string $text, string $language, &$scores): void
    {
        $this->traverseIntents($this->intentHierarchy, $text, $language, '', $scores);
    }

    /**
     * Recursively traverse intent hierarchy and score
     */
    private function traverseIntents(array $intents, string $text, string $language, string $prefix, &$scores): void
    {
        foreach ($intents as $key => $value) {
            $currentPath = empty($prefix) ? $key : "{$prefix}.{$key}";

            // Check if this is a terminal intent (has keywords/patterns)
            if (isset($value['keywords']) || isset($value['patterns'])) {
                $score = $this->calculateIntentScore($text, $value, $language);
                $scores[$currentPath] = $score;
            } else {
                // Continue traversing deeper
                $this->traverseIntents($value, $text, $language, $currentPath, $scores);
            }
        }
    }

    /**
     * Calculate score for a specific intent
     */
    private function calculateIntentScore(string $text, array $intentConfig, string $language): float
    {
        $score = 0;
        $maxScore = 0;

        // Score keywords (lower weight)
        if (isset($intentConfig['keywords'])) {
            $keywordScore = $this->scoreKeywords($text, $intentConfig['keywords']);
            $score += $keywordScore * 0.3;
            $maxScore += 0.3;
        }

        // Score patterns (high weight)
        if (isset($intentConfig['patterns'])) {
            $patternScore = $this->scorePatterns($text, $intentConfig['patterns']);
            $score += $patternScore * 0.6;
            $maxScore += 0.6;
        }

        // Score language variations (medium weight)
        if (isset($intentConfig['language_variations'][$language])) {
            $varScore = $this->scoreKeywords($text, $intentConfig['language_variations'][$language]);
            $score += $varScore * 0.4;
            $maxScore += 0.4;
        }

        return $maxScore > 0 ? $score / $maxScore : 0;
    }

    /**
     * Score keywords using fuzzy matching
     */
    private function scoreKeywords(string $text, array $keywords): float
    {
        if (empty($keywords)) return 0;

        $matches = 0;
        foreach ($keywords as $keyword) {
            // Exact word match
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $text)) {
                $matches++;
            } else {
                // Fuzzy match (70% similarity)
                $similarity = $this->nlpService->fuzzyMatch($text, [$keyword], 0.7);
                if (!empty($similarity)) {
                    $matches += 0.5; // Half weight for fuzzy matches
                }
            }
        }

        return min($matches / count($keywords), 1.0);
    }

    /**
     * Score patterns (regex matching)
     */
    private function scorePatterns(string $text, array $patterns): float
    {
        if (empty($patterns)) return 0;

        $matches = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }

        return min($matches / count($patterns), 1.0);
    }

    /**
     * Disambiguate using conversation context
     */
    private function disambiguateWithContext(array $topMatches, array $conversationContext): ?array
    {
        if (empty($conversationContext)) return null;

        // Get last few messages
        $recentMessages = array_slice($conversationContext, -5);

        // Look for topic consistency
        foreach ($topMatches as $intent => $score) {
            $topicCategory = explode('.', $intent)[0] ?? null;
            
            // Check if this topic appears in recent context
            foreach ($recentMessages as $msg) {
                if (stripos($msg, $topicCategory) !== false) {
                    return ['intent' => $intent, 'score' => $score + 0.1];
                }
            }
        }

        return null;
    }

    /**
     * Generate clarification prompt when intent is ambiguous
     */
    private function generateClarificationPrompt(array $ambiguousIntents, string $language = 'english'): string
    {
        if (count($ambiguousIntents) < 2) {
            return '';
        }

        $intent1 = explode('.', $ambiguousIntents[0])[0] ?? 'service';
        $intent2 = explode('.', $ambiguousIntents[1])[0] ?? 'appointment';

        if ($language === 'tl' || $language === 'mixed') {
            return "Sorrypo, ang inyong mensahe ay maaaring tumukoy sa {$intent1} o {$intent2}. Maaring mapaklarangi kung alin po ang inyong intention?";
        }

        return "I want to make sure I understand correctly. Are you asking about {$intent1} or {$intent2}?";
    }

    /**
     * Extract entities based on recognized intent
     */
    public function extractIntentEntities(string $text, string $intent): array
    {
        $entities = [];

        // Extract based on intent type
        if (strpos($intent, 'appointment') !== false) {
            $entities['appointment_entities'] = $this->extractAppointmentEntities($text);
        } elseif (strpos($intent, 'payment') !== false) {
            $entities['payment_entities'] = $this->extractPaymentEntities($text);
        } elseif (strpos($intent, 'service') !== false) {
            $entities['service_entities'] = $this->extractServiceEntities($text);
        }

        return $entities;
    }

    private function extractAppointmentEntities(string $text): array
    {
        $entities = [];
        
        if (preg_match('/\b(tomorrow|today|next\s+\w+|monday|tuesday|wednesday|thursday|friday|saturday|sunday|bukas|ngayon)\b/i', $text, $m)) {
            $entities['date'] = $m[0];
        }

        if (preg_match('/\b([0-9]{1,2}:[0-9]{2}\s*(?:am|pm)?)\b/i', $text, $m)) {
            $entities['time'] = $m[0];
        }

        return $entities;
    }

    private function extractPaymentEntities(string $text): array
    {
        $entities = [];

        if (preg_match('/(?:₱|php\s*)?([0-9,]+\.?[0-9]*)/i', $text, $m)) {
            $entities['amount'] = $m[1];
        }

        return $entities;
    }

    private function extractServiceEntities(string $text): array
    {
        $entities = [];

        // Extract service names based on patterns
        if (preg_match('/(notary|massage|consulting|haircut|cleaning)/i', $text, $m)) {
            $entities['service'] = $m[0];
        }

        return $entities;
    }
}
