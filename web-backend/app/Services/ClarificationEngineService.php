<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ClarificationEngineService - Intelligent Question Generation
 * 
 * Responsible for:
 * - Detecting when user input needs clarification
 * - Generating smart, context-aware clarifying questions
 * - Managing follow-up question flows
 * - Supporting multilingual clarification (English, Tagalog, Taglish)
 * 
 * DESIGN PRINCIPLE: Never assume - always ask when unclear
 */
class ClarificationEngineService
{
    private const CACHE_PREFIX = 'clarification_context_';
    private const CACHE_TTL = 1800; // 30 minutes

    /**
     * Ambiguity detection thresholds
     */
    private const MIN_WORD_COUNT_FOR_CLARITY = 3;
    private const MAX_AMBIGUITY_SCORE = 1.0;
    private const CLARIFICATION_THRESHOLD = 0.5;

    /**
     * Clarification templates by category and language
     */
    private array $clarificationTemplates = [
        'appointment' => [
            'which_one' => [
                'en' => 'Which appointment are you referring to? Please provide the appointment ID or the date.',
                'tl' => 'Aling appointment po ang tinutukoy ninyo? Pakibigay po ang appointment ID o petsa.',
            ],
            'action_type' => [
                'en' => "I'd like to help with your appointment. Are you looking to:\n1. Book a new appointment\n2. Check an existing appointment\n3. Reschedule an appointment\n4. Cancel an appointment",
                'tl' => "Gusto ko pong tumulong sa appointment ninyo. Ano po ang gusto ninyo:\n1. Mag-book ng bagong appointment\n2. I-check ang existing appointment\n3. I-reschedule ang appointment\n4. I-cancel ang appointment",
            ],
            'service_type' => [
                'en' => 'What service do you need an appointment for? We offer notary services, legal consultations, document review, and more.',
                'tl' => 'Anong serbisyo po ang kailangan ninyo ng appointment? Nag-aalok kami ng notary services, legal consultations, document review, at iba pa.',
            ],
            'date_preference' => [
                'en' => 'When would you like to schedule your appointment? Please provide your preferred date or dates.',
                'tl' => 'Kailan po ninyo gusto ang appointment? Pakibigay po ang preferred na petsa.',
            ],
            'time_preference' => [
                'en' => 'What time of day works best for you? (Morning, Afternoon, or a specific time)',
                'tl' => 'Anong oras po ang pinaka-convenient para sa inyo? (Umaga, Hapon, o specific na oras)',
            ],
        ],
        'payment' => [
            'which_payment' => [
                'en' => 'Which payment are you inquiring about? Please provide the appointment ID or payment reference number.',
                'tl' => 'Aling bayad po ang tinatanong ninyo? Pakibigay po ang appointment ID o payment reference number.',
            ],
            'payment_action' => [
                'en' => "What would you like to know about payments?\n1. Check payment status\n2. View payment history\n3. Understand payment methods\n4. Check outstanding balance",
                'tl' => "Ano po ang gusto ninyong malaman tungkol sa mga bayad?\n1. I-check ang payment status\n2. Tingnan ang payment history\n3. Malaman ang payment methods\n4. I-check ang outstanding balance",
            ],
            'amount_clarify' => [
                'en' => 'Are you asking about a specific payment amount or your total balance?',
                'tl' => 'Tinatanong po ba ninyo ang isang specific na halaga o ang kabuuang balanse?',
            ],
        ],
        'refund' => [
            'refund_action' => [
                'en' => "How can I help with your refund?\n1. Request a new refund\n2. Check refund status\n3. View refund history\n4. Understand refund policies",
                'tl' => "Paano ko po kayo matutulungan sa refund?\n1. Mag-request ng bagong refund\n2. I-check ang refund status\n3. Tingnan ang refund history\n4. Malaman ang refund policies",
            ],
            'which_refund' => [
                'en' => 'Which refund are you asking about? Please provide the refund ID or related appointment ID.',
                'tl' => 'Aling refund po ang tinatanong ninyo? Pakibigay po ang refund ID o related appointment ID.',
            ],
            'refund_reason' => [
                'en' => 'Could you tell me the reason for your refund request? This helps us process it faster.',
                'tl' => 'Pwede po ba ninyong sabihin ang dahilan ng refund request? Makakatulong ito para mas mabilis ang processing.',
            ],
        ],
        'service' => [
            'service_info' => [
                'en' => "What would you like to know about our services?\n1. List of available services\n2. Service pricing\n3. Service details/requirements\n4. How to book a service",
                'tl' => "Ano po ang gusto ninyong malaman tungkol sa mga serbisyo namin?\n1. Listahan ng available services\n2. Presyo ng mga serbisyo\n3. Detalye/requirements ng serbisyo\n4. Paano mag-book ng serbisyo",
            ],
            'which_service' => [
                'en' => 'Which specific service are you interested in? (Notary, Legal Consultation, Document Review, etc.)',
                'tl' => 'Aling serbisyo po ang interesado kayo? (Notary, Legal Consultation, Document Review, etc.)',
            ],
        ],
        'general' => [
            'vague_request' => [
                'en' => "I'd like to help you better. Could you please provide more details about what you need?",
                'tl' => "Gusto ko pong mas matulungan kayo. Pwede po bang magbigay ng mas detalyadong impormasyon tungkol sa kailangan ninyo?",
            ],
            'multiple_topics' => [
                'en' => "It seems you're asking about multiple things. Which would you like me to address first?",
                'tl' => "Mukhang marami po kayong tinatanong. Alin po ang gusto ninyong unahin kong sagutin?",
            ],
            'unclear_intent' => [
                'en' => "I want to make sure I understand you correctly. Are you asking about appointments, payments, services, or something else?",
                'tl' => "Gusto ko pong siguraduhin na naintindihan ko kayo ng tama. Tungkol po ba ito sa appointments, payments, services, o iba pa?",
            ],
            'pronoun_unclear' => [
                'en' => 'Could you specify what "it" or "that" refers to? I want to give you accurate information.',
                'tl' => 'Pwede po bang i-specify kung ano ang tinutukoy na "ito" o "iyan"? Gusto ko pong magbigay ng tamang impormasyon.',
            ],
        ],
        'status' => [
            'status_of_what' => [
                'en' => "What status would you like to check?\n1. Appointment status\n2. Payment status\n3. Refund status",
                'tl' => "Anong status po ang gusto ninyong i-check?\n1. Appointment status\n2. Payment status\n3. Refund status",
            ],
        ],
        'admin' => [
            'pending_type' => [
                'en' => "What type of pending items would you like to see?\n1. Pending appointments (awaiting approval)\n2. Pending payments (awaiting collection)\n3. Pending refunds (awaiting processing)",
                'tl' => "Anong uri ng pending items ang gusto ninyong makita?\n1. Pending appointments (kailangan i-approve)\n2. Pending payments (kailangan kolektahin)\n3. Pending refunds (kailangan i-process)",
            ],
            'action_target' => [
                'en' => 'Which item would you like to process? Please provide the ID.',
                'tl' => 'Alin po ang gusto ninyong i-process? Pakibigay po ang ID.',
            ],
        ],
    ];

    /**
     * Analyze message for ambiguity and generate clarification needs
     * 
     * @param string $message User message
     * @param array $context Conversation and user context
     * @return array Analysis result with clarification suggestions
     */
    public function analyze(string $message, array $context = []): array
    {
        $result = [
            'needs_clarification' => false,
            'ambiguity_score' => 0.0,
            'ambiguity_reasons' => [],
            'suggested_clarifications' => [],
            'detected_topics' => [],
            'missing_information' => [],
            'language' => $context['language'] ?? $this->detectLanguage($message),
        ];

        $normalizedMessage = strtolower(trim($message));
        $wordCount = str_word_count($normalizedMessage);

        // Check 1: Too brief
        if ($wordCount < self::MIN_WORD_COUNT_FOR_CLARITY) {
            $result['ambiguity_score'] += 0.3;
            $result['ambiguity_reasons'][] = 'too_brief';
        }

        // Check 2: Vague request patterns
        if ($this->isVagueRequest($normalizedMessage)) {
            $result['ambiguity_score'] += 0.4;
            $result['ambiguity_reasons'][] = 'vague_request';
        }

        // Check 3: Unresolved pronouns without context
        if ($this->hasUnresolvedPronouns($normalizedMessage, $context)) {
            $result['ambiguity_score'] += 0.3;
            $result['ambiguity_reasons'][] = 'unresolved_pronouns';
        }

        // Check 4: Multiple possible intents
        $detectedTopics = $this->detectTopics($normalizedMessage);
        $result['detected_topics'] = $detectedTopics;
        
        if (count($detectedTopics) > 1) {
            $result['ambiguity_score'] += 0.2;
            $result['ambiguity_reasons'][] = 'multiple_topics';
        }

        // Check 5: Missing required information for detected intent
        $missingInfo = $this->checkMissingInformation($normalizedMessage, $detectedTopics, $context);
        $result['missing_information'] = $missingInfo;
        
        if (!empty($missingInfo)) {
            $result['ambiguity_score'] += 0.2 * count($missingInfo);
            $result['ambiguity_reasons'][] = 'missing_information';
        }

        // Determine if clarification is needed
        $result['ambiguity_score'] = min($result['ambiguity_score'], self::MAX_AMBIGUITY_SCORE);
        $result['needs_clarification'] = $result['ambiguity_score'] >= self::CLARIFICATION_THRESHOLD;

        // Generate clarification questions if needed
        if ($result['needs_clarification']) {
            $result['suggested_clarifications'] = $this->generateClarifications(
                $result['ambiguity_reasons'],
                $result['detected_topics'],
                $result['missing_information'],
                $result['language'],
                $context
            );
        }

        return $result;
    }

    /**
     * Check if message is a vague request
     */
    private function isVagueRequest(string $message): bool
    {
        $vaguePatterns = [
            '/^(help|tulong|pano|paano|how)$/i',
            '/^(help me|tulong naman|tulungan mo ako)$/i',
            '/^(i need|kailangan ko|gusto ko)$/i',
            '/^(what|ano|which|alin)\??$/i',
            '/^(can you|pwede ba|pede ba|pwede mo ba)$/i',
            '/^(tell me|sabihin mo)$/i',
            '/^(show|ipakita|pakita)$/i',
            '/^(check|tingnan|i-check)$/i',
            '/^please$/i',
            '/^(yes|no|oo|hindi|opo|sige)$/i',
        ];

        foreach ($vaguePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for unresolved pronouns that need context
     */
    private function hasUnresolvedPronouns(string $message, array $context): bool
    {
        $pronounPatterns = [
            '/\b(it|this|that|these|those)\b/i',
            '/\b(ito|iyan|iyon|yun|yan)\b/i',
            '/\b(them|they|sila|nila)\b/i',
        ];

        foreach ($pronounPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                // Check if we have context to resolve this
                if (empty($context['last_topic']) && empty($context['last_entity'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect topics mentioned in the message
     */
    private function detectTopics(string $message): array
    {
        $topics = [];

        $topicPatterns = [
            'appointment' => '/\b(appointment|appt|apt|book|booking|schedule|sched|resched|reschedule|cancel|reserve)\b/i',
            'payment' => '/\b(pay|payment|bayad|singil|fee|price|cost|amount|balance|receipt|paid|unpaid)\b/i',
            'refund' => '/\b(refund|return|ibalik|money back|reimburse|refunded)\b/i',
            'service' => '/\b(service|serbisyo|notary|legal|document|consultation|offer|available)\b/i',
            'status' => '/\b(status|pending|approved|completed|cancelled|declined|track|where|check)\b/i',
            'time' => '/\b(time|date|when|kelan|oras|petsa|schedule|bukas|today|tomorrow|mamaya)\b/i',
            'location' => '/\b(where|saan|location|address|office|lugar|nasaan)\b/i',
            'contact' => '/\b(contact|phone|email|call|number|tawag|tumawag)\b/i',
            'help' => '/\b(help|tulong|assist|how|paano|pano|guide)\b/i',
            'profile' => '/\b(account|profile|info|details|password|email|personal)\b/i',
        ];

        foreach ($topicPatterns as $topic => $pattern) {
            if (preg_match($pattern, $message)) {
                $topics[] = $topic;
            }
        }

        return $topics;
    }

    /**
     * Check what required information is missing for the detected intent
     */
    private function checkMissingInformation(string $message, array $topics, array $context): array
    {
        $missing = [];

        // Appointment-related missing info
        if (in_array('appointment', $topics)) {
            // If canceling/rescheduling, need appointment identifier
            if (preg_match('/\b(cancel|reschedule|resched|change|move)\b/i', $message)) {
                if (!preg_match('/\b(id|#|\d{3,})\b/i', $message) && empty($context['last_appointment_id'])) {
                    $missing[] = 'appointment_id';
                }
            }
            
            // If booking, might need service and date
            if (preg_match('/\b(book|new|create|schedule|reserve)\b/i', $message)) {
                if (!preg_match('/\b(notary|legal|document|consultation)\b/i', $message)) {
                    $missing[] = 'service_type';
                }
            }
        }

        // Payment-related missing info
        if (in_array('payment', $topics)) {
            if (!preg_match('/\b(id|#|all|total|\d{3,})\b/i', $message)) {
                $missing[] = 'payment_reference';
            }
        }

        // Refund-related missing info
        if (in_array('refund', $topics)) {
            if (preg_match('/\b(request|want|need|gusto)\b/i', $message)) {
                if (!preg_match('/\b(id|#|\d{3,})\b/i', $message)) {
                    $missing[] = 'appointment_for_refund';
                }
            }
        }

        // Status without specifying what
        if (in_array('status', $topics) && count($topics) === 1) {
            $missing[] = 'status_type';
        }

        return $missing;
    }

    /**
     * Generate appropriate clarification questions
     */
    private function generateClarifications(
        array $reasons,
        array $topics,
        array $missingInfo,
        string $language,
        array $context
    ): array {
        $clarifications = [];
        $lang = $language === 'tl' || $language === 'filipino' || $language === 'tagalog' ? 'tl' : 'en';

        // Handle by primary reason
        if (in_array('vague_request', $reasons) && empty($topics)) {
            $clarifications[] = $this->clarificationTemplates['general']['unclear_intent'][$lang];
        }

        if (in_array('multiple_topics', $reasons)) {
            $clarifications[] = $this->clarificationTemplates['general']['multiple_topics'][$lang];
        }

        if (in_array('unresolved_pronouns', $reasons)) {
            $clarifications[] = $this->clarificationTemplates['general']['pronoun_unclear'][$lang];
        }

        // Handle by topic
        foreach ($topics as $topic) {
            if (isset($this->clarificationTemplates[$topic])) {
                // Check missing info specific to topic
                if ($topic === 'appointment') {
                    if (in_array('appointment_id', $missingInfo)) {
                        $clarifications[] = $this->clarificationTemplates['appointment']['which_one'][$lang];
                    } elseif (in_array('service_type', $missingInfo)) {
                        $clarifications[] = $this->clarificationTemplates['appointment']['service_type'][$lang];
                    } elseif (in_array('vague_request', $reasons)) {
                        $clarifications[] = $this->clarificationTemplates['appointment']['action_type'][$lang];
                    }
                }

                if ($topic === 'payment') {
                    if (in_array('payment_reference', $missingInfo)) {
                        $clarifications[] = $this->clarificationTemplates['payment']['which_payment'][$lang];
                    } elseif (in_array('vague_request', $reasons)) {
                        $clarifications[] = $this->clarificationTemplates['payment']['payment_action'][$lang];
                    }
                }

                if ($topic === 'refund') {
                    if (in_array('appointment_for_refund', $missingInfo)) {
                        $clarifications[] = $this->clarificationTemplates['refund']['which_refund'][$lang];
                    } elseif (in_array('vague_request', $reasons)) {
                        $clarifications[] = $this->clarificationTemplates['refund']['refund_action'][$lang];
                    }
                }

                if ($topic === 'service' && in_array('vague_request', $reasons)) {
                    $clarifications[] = $this->clarificationTemplates['service']['service_info'][$lang];
                }

                if ($topic === 'status' && in_array('status_type', $missingInfo)) {
                    $clarifications[] = $this->clarificationTemplates['status']['status_of_what'][$lang];
                }
            }
        }

        // Fallback if no specific clarifications
        if (empty($clarifications)) {
            $clarifications[] = $this->clarificationTemplates['general']['vague_request'][$lang];
        }

        // Limit to top 2 most relevant clarifications
        return array_slice(array_unique($clarifications), 0, 2);
    }

    /**
     * Detect language of the message
     */
    private function detectLanguage(string $message): string
    {
        $tagalogIndicators = [
            'po', 'opo', 'ko', 'mo', 'ka', 'ako', 'ikaw', 'siya', 'kami', 'kayo', 'sila',
            'ang', 'ng', 'sa', 'na', 'pa', 'ba', 'nga', 'naman', 'lang', 'din', 'rin',
            'ano', 'sino', 'saan', 'kailan', 'bakit', 'paano', 'magkano',
            'gusto', 'kailangan', 'pwede', 'ayaw', 'meron', 'wala',
            'ito', 'iyan', 'iyon', 'dito', 'doon', 'yung', 'yun',
            'salamat', 'sige', 'oo', 'hindi',
        ];

        $words = preg_split('/\s+/', strtolower($message));
        $tagalogCount = 0;

        foreach ($words as $word) {
            if (in_array($word, $tagalogIndicators)) {
                $tagalogCount++;
            }
        }

        $tagalogRatio = count($words) > 0 ? $tagalogCount / count($words) : 0;

        return $tagalogRatio > 0.2 ? 'tl' : 'en';
    }

    /**
     * Format clarification response for chat output
     */
    public function formatClarificationResponse(array $clarifications, string $language = 'en'): string
    {
        if (empty($clarifications)) {
            return '';
        }

        $intro = $language === 'tl' 
            ? "Para mas matulungan kita, kailangan ko pong linawin ang ilang bagay:\n\n"
            : "To better assist you, I need to clarify a few things:\n\n";

        return $intro . implode("\n\n", $clarifications);
    }

    /**
     * Store clarification context for follow-up handling
     */
    public function storeClarificationContext(int $userId, string $conversationId, array $context): void
    {
        $key = self::CACHE_PREFIX . $userId . '_' . $conversationId;
        
        Cache::put($key, [
            'awaiting_clarification' => true,
            'clarification_type' => $context['type'] ?? 'general',
            'original_message' => $context['original_message'] ?? '',
            'detected_topics' => $context['detected_topics'] ?? [],
            'missing_info' => $context['missing_info'] ?? [],
            'timestamp' => now()->toDateTimeString(),
        ], self::CACHE_TTL);
    }

    /**
     * Get pending clarification context
     */
    public function getClarificationContext(int $userId, string $conversationId): ?array
    {
        $key = self::CACHE_PREFIX . $userId . '_' . $conversationId;
        return Cache::get($key);
    }

    /**
     * Clear clarification context after resolution
     */
    public function clearClarificationContext(int $userId, string $conversationId): void
    {
        $key = self::CACHE_PREFIX . $userId . '_' . $conversationId;
        Cache::forget($key);
    }

    /**
     * Check if a response is a clarification to previous question
     */
    public function isClarificationResponse(string $message, array $pendingContext): bool
    {
        if (empty($pendingContext) || !($pendingContext['awaiting_clarification'] ?? false)) {
            return false;
        }

        // Check if message provides requested information
        $missingInfo = $pendingContext['missing_info'] ?? [];
        
        foreach ($missingInfo as $info) {
            switch ($info) {
                case 'appointment_id':
                    if (preg_match('/\b(#?\d{3,})\b/', $message)) {
                        return true;
                    }
                    break;
                case 'service_type':
                    if (preg_match('/\b(notary|legal|document|consultation|1|2|3|4)\b/i', $message)) {
                        return true;
                    }
                    break;
                case 'status_type':
                    if (preg_match('/\b(appointment|payment|refund|1|2|3)\b/i', $message)) {
                        return true;
                    }
                    break;
            }
        }

        // Check if user selected an option (numbers)
        if (preg_match('/^\s*[1-4]\s*$/', $message)) {
            return true;
        }

        return false;
    }
}
