<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ChatbotGuardService - Safety, Content Filtering, and Scope Enforcement
 * 
 * This service ensures the chatbot operates within safe boundaries:
 * - Content filtering (profanity, offensive language, harmful content)
 * - Scope enforcement (system-only questions)
 * - Role-based access restrictions
 * - Safety checks before response generation
 * - Transparency enforcement
 * 
 * The chatbot's role is strictly to ASSIST, INFORM, GUIDE, and EXPLAIN.
 * It must NEVER perform actions, make changes, execute commands, or act on behalf of users.
 */
class ChatbotGuardService
{
    /**
     * Offensive/inappropriate words and patterns (Filipino + English)
     * This list is intentionally comprehensive to catch variations
     */
    private array $blockedPatterns = [
        // English profanity patterns (regex)
        '/\bf+u+c+k+\w*/i',
        '/\bs+h+i+t+\w*/i',
        '/\ba+s+s+h+o+l+e+/i',
        '/\bb+i+t+c+h+\w*/i',
        '/\bd+a+m+n+\w*/i',
        '/\bc+u+n+t+/i',
        '/\bd+i+c+k+\w*/i',
        '/\bp+u+s+s+y+/i',
        '/\bn+i+g+g+\w*/i',
        '/\bf+a+g+\w*/i',
        '/\br+e+t+a+r+d+\w*/i',
        '/\bw+h+o+r+e+/i',
        '/\bs+l+u+t+/i',
        '/\bh+e+l+l+\b/i',
        '/\bcrap\b/i',
        '/\bstfu\b/i',
        '/\bwtf\b/i',
        '/\bstupid\s*(bot|ai|assistant|chatbot)/i',
        '/\bidiot\s*(bot|ai|assistant|chatbot)/i',
        '/\bdumb\s*(bot|ai|assistant|chatbot)/i',
        '/\buseless\s*(bot|ai|assistant|chatbot)/i',
        
        // Filipino/Tagalog profanity patterns
        '/\bp+u+t+a+n*g*\s*i+n+a+/i',
        '/\bg+a+g+o+/i',
        '/\bt+a+n+g+i+n+a+/i',
        '/\bu+l+o+l+/i',
        '/\bb+o+b+o+/i',
        '/\bt+a+r+a+n+t+a+d+o+/i',
        '/\bl+i+n+t+i+k+/i',
        '/\bp+u+n+y+e+t+a+/i',
        '/\bl+e+c+h+e+/i',
        '/\bp+a+k+y+u+/i',
        '/\bp+a+k+s+h+e+t+/i',
        '/\bg+a+y+a+t+/i',
        '/\bt+i+t+i+/i',
        '/\bp+e+k+p+e+k+/i',
        '/\bb+e+t+l+o+g+/i',
        '/\bk+a+n+t+o+t+/i',
        '/\bs+u+p+o+t+/i',
        '/\bb+a+y+a+g+/i',
        
        // Harassment patterns
        '/\b(kill|hurt|harm|attack)\s*(yourself|urself|me|you)/i',
        '/\b(go|jump)\s*(die|dead|suicide)/i',
        '/\bkys\b/i',
        '/\bi\s*(hate|despise|loathe)\s*(you|this|bot)/i',
    ];

    /**
     * Harmful intent patterns that should be flagged
     */
    private array $harmfulIntentPatterns = [
        // Violence
        '/\b(how|can|help|teach)\s*(me|i)?\s*(to)?\s*(kill|murder|hurt|harm|attack)/i',
        '/\b(weapon|gun|bomb|explosive|poison)\s*(make|create|build|get)/i',
        
        // Self-harm
        '/\b(how|ways?)\s*(to)?\s*(commit)?\s*(suicide|kill\s*myself|end\s*(my)?\s*life)/i',
        '/\b(want|going)\s*to\s*(die|end\s*it|kill\s*myself)/i',
        
        // Illegal activities
        '/\b(how|help)\s*(to)?\s*(hack|steal|scam|fraud|illegal)/i',
        '/\b(bypass|circumvent|break)\s*(security|system|law)/i',
        
        // Exploitation
        '/\b(exploit|cheat|manipulate)\s*(the)?\s*(system|bot|ai)/i',
    ];

    /**
     * Out-of-scope topic patterns
     * Enhanced with more comprehensive detection
     */
    private array $outOfScopePatterns = [
        // General knowledge
        '/\b(what|who)\s*(is|was|are|were)\s*(the)?\s*(president|capital|weather|news|sports|movie|celebrity|singer|actor)/i',
        '/\b(tell|explain)\s*(me)?\s*(about)?\s*(history|science|math|politics|religion|philosophy)/i',
        '/\b(when\s+did|who\s+invented|how\s+old\s+is)/i',
        '/\b(how\s+tall|how\s+many|population\s+of|distance\s+to)/i',
        
        // Entertainment and creative requests
        '/\b(write|compose|create)\s*(me)?\s*(a)?\s*(poem|song|story|joke|essay|speech|letter)/i',
        '/\b(sing|dance|play|game|riddle|trivia|quiz)/i',
        '/\b(recommend|suggest)\s*(a)?\s*(movie|book|music|song|show|restaurant|game)/i',
        '/\b(draw|paint|sketch|illustrate)/i',
        
        // Personal opinions and emotions
        '/\b(what|do)\s*(you)?\s*(think|feel|believe|opinion)\s*(about)?/i',
        '/\b(are\s*you)\s*(happy|sad|alive|real|human|conscious|sentient)/i',
        '/\b(do\s*you)\s*(like|love|hate|prefer|enjoy)/i',
        '/\b(what\'?s?\s+your)\s*(favorite|opinion|thought|feeling)/i',
        
        // Unrelated services and commerce
        '/\b(order|deliver|food|pizza|restaurant|shop|buy|purchase|amazon|ebay|grab|lazada)\b/i',
        '/\b(translate|translation|language\s*learning|duolingo)/i',
        '/\b(flight|hotel|travel|vacation|booking\.com|airbnb)/i',
        '/\b(uber|taxi|ride|transport|delivery)/i',
        
        // Medical and health (outside system scope)
        '/\b(medical|health|symptom|diagnosis|treatment|medicine|drug|prescription)\s*(advice)?/i',
        '/\b(am\s*i\s*(sick|healthy|okay|pregnant))/i',
        '/\b(should\s*i\s*(take|see|visit)\s*(medicine|doctor|hospital))/i',
        
        // Legal advice (outside legal appointment system scope)
        '/\b(legal\s*advice|lawsuit|sue|court\s*case|my\s*rights)\b/i',
        '/\b(is\s*it\s*legal|can\s*i\s*sue|lawyer\s*for)/i',
        
        // Financial advice
        '/\b(financial|investment|stock|crypto|bitcoin|forex)\s*(advice|tips|recommendation)/i',
        '/\b(should\s*i\s*(invest|buy|sell)\s*(stock|crypto|bitcoin))/i',
        '/\b(how\s*to\s*(make|earn)\s*money\s*(online|fast|quick))/i',
        
        // Technical unrelated
        '/\b(code|program|develop|build)\s*(me|a)?\s*(website|app|software|game|bot)/i',
        '/\b(fix|repair|troubleshoot)\s*(my)?\s*(computer|phone|device|laptop|pc)/i',
        '/\b(how\s*to\s*(hack|code|program|develop|build))/i',
        '/\b(debug|compile|runtime\s*error|syntax\s*error)/i',
        
        // Relationship and personal advice
        '/\b(relationship|dating|love\s*life|break\s*up|divorce)/i',
        '/\b(how\s*to\s*(get|find|meet)\s*(girlfriend|boyfriend|partner|date))/i',
        '/\b(should\s*i\s*(break\s*up|divorce|marry))/i',
        
        // Random/irrelevant questions
        '/\b(meaning\s*of\s*life|why\s*do\s*we\s*exist|what\s*is\s*reality)/i',
        '/\b(tell\s*me\s*a\s*(fact|secret|truth))/i',
        '/\b(how\s*does\s*(gravity|electricity|universe|time)\s*work)/i',
    ];

    /**
     * Action request patterns - things the bot should NOT do
     * Enhanced with more comprehensive action detection
     */
    private array $actionRequestPatterns = [
        // Direct action requests
        '/\b(please|can\s*you|could\s*you|will\s*you|would\s*you)\s*(delete|remove|modify|change|update|edit|create|add|approve|reject|cancel)\s*(my|the|this|a)/i',
        '/\b(make|do|perform|execute|run|process|complete)\s*(the)?\s*(action|task|change|update|modification|booking|cancellation|approval)/i',
        '/\b(approve|reject|decline|cancel|complete|confirm)\s*(this|the|my)?\s*(appointment|booking|refund|payment)?\s*(for\s*me|on\s*my\s*behalf|automatically)?/i',
        '/\b(send|submit|post|upload|forward)\s*(this|the|my)?\s*(for\s*me|automatically|to)/i',
        '/\b(book|schedule|reserve)\s*(me|an|a)?\s*(appointment|slot|time)/i',
        '/\b(process|handle|manage)\s*(my|this|the)\s*(request|transaction|application)/i',
        
        // Impersonation requests
        '/\b(pretend|act\s*like|be)\s*(you\'?re?|as\s*if)\s*(a|an|the)?\s*(admin|user|cashier|human|person|staff)/i',
        '/\b(log\s*in|login|sign\s*in|access)\s*(as|for|to)\s*(me|someone|my\s*account|another)/i',
        '/\b(use\s*my|access\s*my|get\s*into\s*my)\s*(account|credentials|password|profile)/i',
        '/\b(act|behave)\s*(on\s*my\s*behalf|for\s*me)/i',
        
        // System manipulation requests
        '/\b(bypass|skip|ignore|override)\s*(the)?\s*(verification|authentication|security|rules|system|checks)/i',
        '/\b(give\s*me|grant\s*me|make\s*me)\s*(admin|access|permission|authority)/i',
        '/\b(change|update|modify)\s*(my|the)?\s*(role|permissions|access\s*level)/i',
        '/\b(reset|recover|change)\s*(someone\'?s?|another|other)\s*(password|account)/i',
        
        // Data manipulation requests  
        '/\b(delete|remove|erase)\s*(my|the|all|this)\s*(data|records|history|account|information)/i',
        '/\b(show|display|reveal|expose)\s*(other|someone|another)\s*(user\'?s?|person\'?s?|client\'?s?)\s*(data|info|account|appointments?)/i',
        '/\b(access|view|see)\s*(private|confidential|sensitive|hidden)\s*(data|info|records)/i',
        
        // Filipino action requests
        '/\b(paki|pakiusap)\s*(i?-?)?(approve|cancel|delete|book|update|change)/i',
        '/\b(pwede\s*(mo|ba)|paki)\s*(gawa|gawin|baguhin|i-?cancel|i-?approve)/i',
        '/\b(i-?(approve|reject|cancel|book|delete|remove|change))\s*(mo|na|po)/i',
    ];

    /**
     * System-related topics (in scope)
     */
    private array $systemTopicPatterns = [
        // Appointments
        '/\b(appointment|booking|schedule|reservation|slot)/i',
        '/\b(book|reserve|reschedule|cancel)\b/i',
        '/\b(date|time|available|availability)/i',
        
        // Services
        '/\b(service|notary|legal|document|attestation|affidavit)/i',
        '/\b(price|cost|fee|rate|charge|payment)/i',
        
        // Users and accounts
        '/\b(account|profile|password|login|register|sign\s*up)/i',
        '/\b(user|client|admin|cashier)/i',
        
        // Payments and refunds
        '/\b(pay|payment|paid|refund|receipt|transaction)/i',
        '/\b(balance|owe|due|outstanding)/i',
        
        // Business
        '/\b(hour|open|close|business|office|location|address)/i',
        '/\b(contact|email|phone|support)/i',
        
        // Status and information
        '/\b(status|pending|approved|declined|completed|cancelled)/i',
        '/\b(how|what|where|when|why|can\s*i|do\s*i)/i',
        
        // Help
        '/\b(help|assist|support|guide|explain|clarify)/i',
    ];

    /**
     * Check if a message contains inappropriate content
     * 
     * @param string $message User message
     * @return array ['safe' => bool, 'reason' => string|null, 'type' => string|null]
     */
    public function checkContent(string $message): array
    {
        $message = $this->normalizeMessage($message);

        // Check for blocked patterns (profanity)
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                Log::warning('Chatbot: Blocked content detected', [
                    'pattern' => $pattern,
                    'message_snippet' => substr($message, 0, 100),
                ]);
                return [
                    'safe' => false,
                    'reason' => 'inappropriate_language',
                    'type' => 'profanity',
                    'response' => $this->getInappropriateContentResponse(),
                ];
            }
        }

        // Check for harmful intent
        foreach ($this->harmfulIntentPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                Log::warning('Chatbot: Harmful intent detected', [
                    'pattern' => $pattern,
                    'message_snippet' => substr($message, 0, 100),
                ]);
                return [
                    'safe' => false,
                    'reason' => 'harmful_content',
                    'type' => 'harmful',
                    'response' => $this->getHarmfulContentResponse(),
                ];
            }
        }

        return ['safe' => true, 'reason' => null, 'type' => null];
    }

    /**
     * Check if a request is within the chatbot's scope
     * 
     * @param string $message User message
     * @return array ['in_scope' => bool, 'reason' => string|null]
     */
    public function checkScope(string $message): array
    {
        $message = $this->normalizeMessage($message);

        // First, check if it's clearly system-related
        $isSystemRelated = false;
        foreach ($this->systemTopicPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $isSystemRelated = true;
                break;
            }
        }

        // If system-related, it's in scope
        if ($isSystemRelated) {
            return ['in_scope' => true, 'reason' => null];
        }

        // Check if it's clearly out of scope
        foreach ($this->outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                Log::info('Chatbot: Out-of-scope request detected', [
                    'pattern' => $pattern,
                    'message_snippet' => substr($message, 0, 100),
                ]);
                return [
                    'in_scope' => false,
                    'reason' => 'out_of_scope',
                    'response' => $this->getOutOfScopeResponse(),
                ];
            }
        }

        // For short messages or greetings, allow them
        if (strlen($message) < 20 || $this->isGreeting($message)) {
            return ['in_scope' => true, 'reason' => null];
        }

        // If not clearly in or out of scope, allow but flag for monitoring
        return ['in_scope' => true, 'reason' => null, 'uncertain' => true];
    }

    /**
     * Check if the request is trying to make the bot perform actions
     * 
     * @param string $message User message
     * @param string $intent Detected intent
     * @return array ['is_action_request' => bool, 'guidance' => string|null]
     */
    public function checkActionRequest(string $message, string $intent = ''): array
    {
        $message = $this->normalizeMessage($message);

        // Check for action request patterns
        foreach ($this->actionRequestPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return [
                    'is_action_request' => true,
                    'guidance' => $this->getActionGuidanceResponse($intent),
                ];
            }
        }

        return ['is_action_request' => false, 'guidance' => null];
    }

    /**
     * Generate role restriction message
     * 
     * @param string $requestedFeature The feature being requested
     * @param string $currentRole User's current role
     * @param array $allowedRoles Roles that can access this feature
     * @return string Restriction message
     */
    public function getRoleRestrictionMessage(
        string $requestedFeature,
        string $currentRole,
        array $allowedRoles
    ): string {
        $roleNames = array_map(fn($r) => ucfirst($r), $allowedRoles);
        $roleList = count($roleNames) > 1 
            ? implode(', ', array_slice($roleNames, 0, -1)) . ' or ' . end($roleNames)
            : $roleNames[0];

        $messages = [
            'view_all_appointments' => "Viewing all appointments is restricted to {$roleList} accounts. As a " . ucfirst($currentRole) . ", you can view your own appointments by asking 'Show my appointments'.",
            'approve_appointment' => "Approving appointments requires {$roleList} privileges. As a " . ucfirst($currentRole) . ", you can check your appointment status instead.",
            'decline_appointment' => "Declining appointments requires {$roleList} privileges. You can cancel your own appointments if needed.",
            'approve_refund' => "Refund approvals are handled by {$roleList}. You can request a refund and track its status.",
            'process_payment' => "Payment processing is restricted to {$roleList}. You can check your payment status or make payments through the payment portal.",
            'view_analytics' => "Analytics and reports are only available to {$roleList}. I can help you with your personal appointment and payment information instead.",
            'manage_users' => "User management is an {$roleList}-only feature. I can help you update your own profile information.",
            'default' => "This feature is restricted to {$roleList} accounts. Your current role (" . ucfirst($currentRole) . ") doesn't have access to this functionality. Is there something else I can help you with?",
        ];

        return $messages[$requestedFeature] ?? $messages['default'];
    }

    /**
     * Get transparency response when data is unavailable
     * 
     * @param string $dataType Type of data that's unavailable
     * @return string Transparency message
     */
    public function getTransparencyResponse(string $dataType): string
    {
        $responses = [
            'appointment' => "I don't have access to appointment information at the moment. Please check the Appointments section in your dashboard or contact support for assistance.",
            'payment' => "I cannot retrieve payment details right now. Please visit the Payments section or contact the cashier for accurate information.",
            'refund' => "Refund information is not available to me at this time. Please check your refund status in the dashboard or contact an administrator.",
            'user' => "I don't have access to user account details. Please check your profile settings or contact support.",
            'service' => "Service information is currently unavailable. Please visit our Services page for the most up-to-date information.",
            'schedule' => "I cannot access schedule data at the moment. Please check the booking calendar for available slots.",
            'general' => "I don't have access to that information in the system. Please contact support or check the relevant section in your dashboard.",
        ];

        return $responses[$dataType] ?? $responses['general'];
    }

    /**
     * Get response for inappropriate content
     */
    private function getInappropriateContentResponse(): string
    {
        $responses = [
            "I'm here to help with system-related questions. Let's keep our conversation respectful and professional. How can I assist you with appointments, services, or payments?",
            "I understand you may be frustrated, but I'm unable to respond to inappropriate language. I'm happy to help if you have questions about our services, appointments, or your account.",
            "Let's maintain a professional conversation. I'm here to assist you with booking appointments, checking statuses, understanding services, and answering your questions about the system.",
        ];

        return $responses[array_rand($responses)];
    }

    /**
     * Get response for harmful content
     */
    private function getHarmfulContentResponse(): string
    {
        return "I'm not able to assist with that request. If you're experiencing difficulties, please consider reaching out to appropriate support services. I'm here to help with system-related questions about appointments, services, and payments.";
    }

    /**
     * Get response for out-of-scope requests
     * Provides a friendly response without listing commands
     */
    private function getOutOfScopeResponse(): string
    {
        $responses = [
            "I appreciate your question, but that's outside the scope of what I'm designed to help with. I'm your assistant for this appointment booking system. Is there anything I can help you with regarding appointments or services?",
            "I'm sorry, but I can only assist with matters related to this appointment system. If you have questions about booking, services, or your account, I'd be happy to help!",
            "That's not something I'm able to help with, as my expertise is limited to this booking system. Feel free to ask me about appointments, services, or payments instead!",
            "I wish I could help with that, but it's outside my capabilities. I specialize in appointment booking assistance. What can I help you with regarding your appointments today?",
            "I'm focused specifically on helping you with this appointment system. While I can't assist with that particular request, I'm here if you need help with bookings, services, or account-related questions!",
        ];

        return $responses[array_rand($responses)];
    }

    /**
     * Get guidance response for action requests
     */
    private function getActionGuidanceResponse(string $intent = ''): string
    {
        $guidance = [
            'approve_appointment' => "I can't approve appointments directly, but I can guide you through the process. To approve appointments:\n\n1. Go to the **Admin Dashboard**\n2. Navigate to **Pending Appointments**\n3. Review the appointment details\n4. Click **Approve** or **Decline**\n\nWould you like me to show you the pending appointments that need review?",
            'process_payment' => "I'm unable to process payments on your behalf, but here's how you can do it:\n\n1. Go to the **Payments** section\n2. Select the appointment to pay for\n3. Choose your payment method\n4. Complete the transaction\n\nNeed help understanding the payment process?",
            'cancel_appointment' => "I can't cancel appointments directly. To cancel an appointment:\n\n1. Go to **My Appointments**\n2. Find the appointment you want to cancel\n3. Click **Cancel Appointment**\n4. Confirm your cancellation\n\nWould you like to see your upcoming appointments?",
            'default' => "I'm designed to assist, inform, and guide - but I cannot perform actions on your behalf. This ensures security and accuracy in the system.\n\nI can:\n✓ Explain how to do something\n✓ Show you relevant information\n✓ Guide you through processes\n✓ Answer your questions\n\nWhat would you like to know?",
        ];

        return $guidance[$intent] ?? $guidance['default'];
    }

    /**
     * Normalize message for pattern matching
     */
    private function normalizeMessage(string $message): string
    {
        // Convert to lowercase
        $message = mb_strtolower($message);
        
        // Remove excessive punctuation
        $message = preg_replace('/[!?]{2,}/', '?', $message);
        
        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', trim($message));
        
        // Remove special characters but keep Filipino characters
        $message = preg_replace('/[^\w\s\-\'àáâãäåèéêëìíîïòóôõöùúûüñ]/u', '', $message);
        
        return $message;
    }

    /**
     * Check if message is a greeting
     */
    private function isGreeting(string $message): bool
    {
        $greetings = [
            'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
            'kumusta', 'musta', 'magandang umaga', 'magandang hapon', 'magandang gabi',
            'yo', 'sup', 'greetings', 'howdy',
        ];

        $message = strtolower(trim($message));
        
        foreach ($greetings as $greeting) {
            if (str_starts_with($message, $greeting) || $message === $greeting) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get error handling response
     * 
     * @param string $errorType Type of error
     * @param string $context Additional context
     * @return array Response with suggestions
     */
    public function getErrorResponse(string $errorType, string $context = ''): array
    {
        $responses = [
            'database_error' => [
                'response' => "I'm having trouble accessing the system data right now. This could be a temporary issue.",
                'suggestions' => [
                    'Try refreshing the page',
                    'Wait a moment and try again',
                    'Contact support if the issue persists',
                ],
                'next_steps' => 'If you need immediate assistance, please contact our support team directly.',
            ],
            'authentication_error' => [
                'response' => "There seems to be an issue with your session. You may need to log in again.",
                'suggestions' => [
                    'Try logging out and back in',
                    'Clear your browser cache',
                    'Use the login page to re-authenticate',
                ],
                'next_steps' => 'After logging in, you\'ll have full access to your account features.',
            ],
            'permission_error' => [
                'response' => "You don't have permission to access this feature with your current account type.",
                'suggestions' => [
                    'Check if you\'re logged into the correct account',
                    'Contact an administrator for access',
                    'Review the feature requirements',
                ],
                'next_steps' => 'I can help you with features available to your account type.',
            ],
            'validation_error' => [
                'response' => "The information provided doesn't seem to be in the correct format.",
                'suggestions' => [
                    'Double-check the information you entered',
                    'Make sure all required fields are filled',
                    'Try using a different format (e.g., date format)',
                ],
                'next_steps' => 'Let me know what you\'re trying to do, and I\'ll guide you through it.',
            ],
            'not_found' => [
                'response' => "I couldn't find what you're looking for in the system.",
                'suggestions' => [
                    'Verify the ID or reference number',
                    'Check if the item exists in your account',
                    'Try searching with different criteria',
                ],
                'next_steps' => 'Would you like me to help you search for something else?',
            ],
            'general' => [
                'response' => "Something went wrong while processing your request.",
                'suggestions' => [
                    'Try again in a moment',
                    'Rephrase your question',
                    'Contact support for assistance',
                ],
                'next_steps' => 'I\'m here to help - let me know if there\'s another way I can assist you.',
            ],
        ];

        return $responses[$errorType] ?? $responses['general'];
    }

    /**
     * Format consistent professional response
     * 
     * @param string $mainContent Main response content
     * @param array $options Additional options (suggestions, data, etc.)
     * @return array Formatted response
     */
    public function formatProfessionalResponse(string $mainContent, array $options = []): array
    {
        $response = [
            'response' => $mainContent,
            'tone' => 'professional',
            'formatted' => true,
        ];

        if (!empty($options['suggestions'])) {
            $response['suggestions'] = $options['suggestions'];
        }

        if (!empty($options['data'])) {
            $response['data'] = $options['data'];
        }

        if (!empty($options['next_steps'])) {
            $response['next_steps'] = $options['next_steps'];
        }

        if (!empty($options['disclaimer'])) {
            $response['disclaimer'] = $options['disclaimer'];
        }

        return $response;
    }
}
