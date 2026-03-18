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
     * including leetspeak, creative spelling, and Taglish mixing
     */
    private array $blockedPatterns = [
        // English profanity patterns (regex - handles repeated/extended characters)
        '/\bf+[\W_]*u+[\W_]*c+[\W_]*k+\w*/i',
        '/\bs+[\W_]*h+[\W_]*i+[\W_]*t+\w*/i',
        '/\ba+[\W_]*s+[\W_]*s+[\W_]*h+[\W_]*o+[\W_]*l+[\W_]*e+/i',
        '/\bb+[\W_]*i+[\W_]*t+[\W_]*c+[\W_]*h+\w*/i',
        '/\bd+[\W_]*a+[\W_]*m+[\W_]*n+\w*/i',
        '/\bc+[\W_]*u+[\W_]*n+[\W_]*t+/i',
        '/\bd+[\W_]*i+[\W_]*c+[\W_]*k+\w*/i',
        '/\bp+[\W_]*u+[\W_]*s+[\W_]*s+[\W_]*y+/i',
        '/\bn+[\W_]*i+[\W_]*g+[\W_]*g+\w*/i',
        '/\bf+[\W_]*a+[\W_]*g+\w*/i',
        '/\br+[\W_]*e+[\W_]*t+[\W_]*a+[\W_]*r+[\W_]*d+\w*/i',
        '/\bw+[\W_]*h+[\W_]*o+[\W_]*r+[\W_]*e+/i',
        '/\bs+[\W_]*l+[\W_]*u+[\W_]*t+/i',
        '/\bh+e+l+l+\b/i',
        '/\bcrap\b/i',
        '/\bstfu\b/i',
        '/\bwtf\b/i',
        '/\bgtfo\b/i',
        '/\blmao\b/i',
        '/\bfml\b/i',
        '/\bpos\b/i', // piece of...
        '/\bstupid\s*(bot|ai|assistant|chatbot)/i',
        '/\bidiot\s*(bot|ai|assistant|chatbot)/i',
        '/\bdumb\s*(bot|ai|assistant|chatbot)/i',
        '/\buseless\s*(bot|ai|assistant|chatbot)/i',
        '/\btrash\s*(bot|ai|assistant|chatbot)/i',
        '/\bgarbage\s*(bot|ai|assistant|chatbot)/i',
        
        // Leetspeak / character substitution patterns
        '/\bf[\W_]*[u\x{00fc}v][\W_]*[ck][\W_]*[ck]/iu',
        '/\b[s5][\W_]*h[\W_]*[i1!][\W_]*[t7]/i',
        '/\b[a4@][\W_]*[s5$][\W_]*[s5$]\b/i',
        '/\b[b8][\W_]*[i1!][\W_]*[t7][\W_]*[ck][\W_]*h/i',
        
        // Filipino/Tagalog profanity patterns (with common variations)
        '/\bp+[\W_]*u+[\W_]*t+[\W_]*a+[\W_]*n*[\W_]*g*\s*i+[\W_]*n+[\W_]*a+/i',
        '/\bp+[\W_]*u+[\W_]*t+[\W_]*a+[\W_]*n*[\W_]*g*\s*i+[\W_]*n+[\W_]*a+\s*m+o+/i',
        '/\bp+[\W_]*u+[\W_]*t+[\W_]*[sz]+/i', // puta/puts common shorthand
        '/\bg+[\W_]*a+[\W_]*g+[\W_]*o+/i',
        '/\bt+[\W_]*a+[\W_]*n+[\W_]*g+[\W_]*i+[\W_]*n+[\W_]*a+/i',
        '/\bu+[\W_]*l+[\W_]*o+[\W_]*l+/i',
        '/\bt+[\W_]*a+[\W_]*r+[\W_]*a+[\W_]*n+[\W_]*t+[\W_]*a+[\W_]*d+[\W_]*o+/i',
        '/\bl+[\W_]*i+[\W_]*n+[\W_]*t+[\W_]*i+[\W_]*k+/i',
        '/\bp+[\W_]*u+[\W_]*n+[\W_]*y+[\W_]*e+[\W_]*t+[\W_]*a+/i',
        '/\bl+[\W_]*e+[\W_]*c+[\W_]*h+[\W_]*e+/i',
        '/\bp+[\W_]*a+[\W_]*k+[\W_]*y+[\W_]*u+/i',
        '/\bp+[\W_]*a+[\W_]*k+[\W_]*[s5]+[\W_]*h+[\W_]*e+[\W_]*t+/i',
        '/\bp+[\W_]*a+[\W_]*k+[\W_]*s+[\W_]*h+[\W_]*[ei]+[\W_]*t+/i',
        '/\bg+[\W_]*a+[\W_]*y+[\W_]*a+[\W_]*t+/i',
        '/\bt+[\W_]*i+[\W_]*t+[\W_]*i+/i',
        '/\bp+[\W_]*e+[\W_]*k+[\W_]*p+[\W_]*e+[\W_]*k+/i',
        '/\bb+[\W_]*e+[\W_]*t+[\W_]*l+[\W_]*o+[\W_]*g+/i',
        '/\bk+[\W_]*a+[\W_]*n+[\W_]*t+[\W_]*o+[\W_]*t+/i',
        '/\bs+[\W_]*u+[\W_]*p+[\W_]*o+[\W_]*t+/i',
        '/\bb+[\W_]*a+[\W_]*y+[\W_]*a+[\W_]*g+/i',
        '/\bb+[\W_]*o+[\W_]*b+[\W_]*o+\s*(mo|ka|naman|kasi)/i', // 'bobo mo', 'bobo ka'
        '/\bg+[\W_]*a+[\W_]*g+[\W_]*o+\s*(ka|mo|naman|kasi)/i', // 'gago ka'
        '/\bt+[\W_]*a+[\W_]*n+[\W_]*g+[\W_]*a+/i',
        '/\bk+[\W_]*u+[\W_]*p+[\W_]*a+[\W_]*l+/i',
        '/\bhinayupak/i',
        '/\bsiraan/i',
        '/\bhindot/i',
        '/\biyot/i',
        '/\btite/i',
        
        // Common censored/creative Filipino variants
        '/\bp[\*\.\-_]+t[\*\.\-_]*n?g?\s*i[\*\.\-_]*n[\*\.\-_]*a/i', // p*t*ng ina
        '/\bputr?[a4]ng?\s*in[a4]/i',
        '/\bpota\b/i',     // shortened putangina
        '/\bpucha\b/i',    // softened variant
        '/\bpakshet\b/i',
        '/\bshet\b/i',     // Filipino adaptation of 'shit'
        '/\bgagu/i',       // variant of gago
        '/\btangna/i',     // shortened tangina
        '/\bputik\b/i',    // mild but used as profanity substitute
        '/\bleche(ng)?\b/i',
        '/\bpunyemas/i',
        
        // Abbreviations/coded profanity
        '/\bptngina/i',
        '/\bptnginamo/i',
        '/\bpkyu/i',
        '/\bgge/i',        // gago abbreviation
        '/\bp2x/i',        // putangina code
        
        // Harassment patterns
        '/\b(kill|hurt|harm|attack)\s*(yourself|urself|me|you)/i',
        '/\b(go|jump)\s*(die|dead|suicide)/i',
        '/\bkys\b/i',
        '/\bi\s*(hate|despise|loathe)\s*(you|this|bot)/i',
        
        // Taglish harassment/insults
        '/\b(mamatay|patayin|saktan)\s*(ka|mo|kita)/i',
        '/\b(wala\s*kang?\s*kwenta)/i',
        '/\b(walang\s*silbi)/i',
        '/\b(ang\s*tanga\s*mo)/i',
        '/\b(ang\s*bobo\s*mo)/i',
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
     * Includes both English and Filipino/Tagalog patterns
     */
    private array $systemTopicPatterns = [
        // Appointments (English)
        '/\b(appointment|booking|schedule|reservation|slot)/i',
        '/\b(book|reserve|reschedule|cancel)\b/i',
        '/\b(date|time|available|availability)/i',
        
        // Appointments (Filipino/Taglish)
        '/\b(appointment|booking|iskedyul|schedule|resrba)/i',
        '/\b(mag-?book|i-?book|pa-?book|mag-?cancel|i-?cancel|i-?reschedule)/i',
        '/\b(oras|petsa|araw|kailan|bukas|ngayon)/i',
        
        // Services (English)
        '/\b(service|notary|legal|document|attestation|affidavit)/i',
        '/\b(price|cost|fee|rate|charge|payment)/i',
        
        // Services (Filipino/Taglish)
        '/\b(serbisyo|presyo|magkano|bayad|singil|halaga)/i',
        '/\b(notaryo|dokumento|papeles)/i',
        
        // Users and accounts
        '/\b(account|profile|password|login|register|sign\s*up)/i',
        '/\b(user|client|admin|cashier)/i',
        '/\b(account|profile|password|mag-?login|mag-?register|sign\s*up)/i',
        
        // Payments and refunds
        '/\b(pay|payment|paid|refund|receipt|transaction)/i',
        '/\b(balance|owe|due|outstanding)/i',
        '/\b(bayaran|bayad|refund|resibo|pera)/i',
        
        // Business
        '/\b(hour|open|close|business|office|location|address)/i',
        '/\b(contact|email|phone|support)/i',
        '/\b(opis|opisina|bukas|sarado|address|saan|nasaan)/i',
        
        // Status and information
        '/\b(status|pending|approved|declined|completed|cancelled)/i',
        '/\b(how|what|where|when|why|can\s*i|do\s*i)/i',
        '/\b(ano|saan|kailan|paano|pano|bakit|pwede|puede)/i',
        
        // Help
        '/\b(help|assist|support|guide|explain|clarify)/i',
        '/\b(tulong|tulungan|assist|gabay|paliwanag)/i',
    ];

    /**
     * Check if a message contains inappropriate content
     * Enhanced with language detection and severity grading
     * 
     * @param string $message User message
     * @return array ['safe' => bool, 'reason' => string|null, 'type' => string|null]
     */
    public function checkContent(string $message): array
    {
        $originalMessage = $message;
        $message = $this->normalizeMessage($message);
        
        // Detect language for response localization
        $detectedLanguage = $this->detectMessageLanguage($originalMessage);

        // Check for blocked patterns (profanity) with severity grading
        foreach ($this->blockedPatterns as $index => $pattern) {
            if (preg_match($pattern, $message)) {
                // Determine severity: harassment/direct insults are severe, casual profanity is mild
                $severity = $this->classifyOffenseSeverity($pattern, $originalMessage);
                
                Log::warning('Chatbot: Blocked content detected', [
                    'pattern' => $pattern,
                    'severity' => $severity,
                    'language' => $detectedLanguage,
                    'message_snippet' => substr($originalMessage, 0, 100),
                ]);
                return [
                    'safe' => false,
                    'reason' => 'inappropriate_language',
                    'type' => 'profanity',
                    'severity' => $severity,
                    'response' => $this->getInappropriateContentResponse($severity, $detectedLanguage),
                ];
            }
        }

        // Check for harmful intent
        foreach ($this->harmfulIntentPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                Log::warning('Chatbot: Harmful intent detected', [
                    'pattern' => $pattern,
                    'language' => $detectedLanguage,
                    'message_snippet' => substr($originalMessage, 0, 100),
                ]);
                return [
                    'safe' => false,
                    'reason' => 'harmful_content',
                    'type' => 'harmful',
                    'severity' => 'severe',
                    'response' => $this->getHarmfulContentResponse($detectedLanguage),
                ];
            }
        }

        return ['safe' => true, 'reason' => null, 'type' => null];
    }

    /**
     * Detect the language of a message (English, Filipino, or Taglish)
     */
    private function detectMessageLanguage(string $message): string
    {
        $lower = mb_strtolower($message);
        
        // Filipino/Tagalog indicators
        $filipinoMarkers = [
            'po', 'opo', 'naman', 'kasi', 'ba', 'na', 'lang', 'lng',
            'ko', 'mo', 'ka', 'nyo', 'natin', 'kami', 'sila', 'siya',
            'ang', 'ng', 'sa', 'mga', 'yung', 'ung', 'dito', 'doon',
            'ano', 'saan', 'kailan', 'paano', 'pano', 'bakit', 'sino',
            'hindi', 'oo', 'opo', 'pwede', 'pwd', 'ayaw', 'gusto',
            'tulungan', 'tulong', 'salamat', 'kumusta', 'musta',
            'magandang', 'umaga', 'hapon', 'gabi',
        ];
        
        $englishMarkers = [
            'the', 'is', 'are', 'was', 'were', 'have', 'has', 'had',
            'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'can', 'may', 'might', 'must', 'shall',
            'i', 'you', 'he', 'she', 'it', 'we', 'they',
            'my', 'your', 'his', 'her', 'its', 'our', 'their',
            'please', 'thank', 'thanks', 'help', 'need', 'want',
        ];
        
        $words = preg_split('/\s+/', $lower);
        $filipinoCount = 0;
        $englishCount = 0;
        
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-z]/', '', $word);
            if (in_array($cleanWord, $filipinoMarkers)) $filipinoCount++;
            if (in_array($cleanWord, $englishMarkers)) $englishCount++;
        }
        
        if ($filipinoCount > 0 && $englishCount > 0) return 'taglish';
        if ($filipinoCount > $englishCount) return 'filipino';
        return 'english';
    }
    
    /**
     * Classify the severity of offensive content
     * Returns 'mild' for casual or frustration-based profanity, 'standard' for direct profanity,
     * 'severe' for harassment, threats, or hate speech
     */
    private function classifyOffenseSeverity(string $matchedPattern, string $originalMessage): string
    {
        $lower = mb_strtolower($originalMessage);
        
        // Severe: Harassment, threats, slurs, directed insults
        $severeIndicators = [
            '/\b(kill|hurt|harm|attack|die|suicide|kys)\b/i',
            '/\b(hate|despise|loathe)\s*(you|this|bot)/i',
            '/\bn+i+g+g/i',
            '/\bf+a+g/i',
            '/\b(mamatay|patayin|saktan)\s*(ka|mo|kita)/i',
        ];
        foreach ($severeIndicators as $severe) {
            if (preg_match($severe, $lower)) return 'severe';
        }
        
        // Mild: Contextual frustration expressions (user venting, not directed at bot)
        $mildIndicators = [
            '/\b(damn|crap|hell|shet|putik|pucha)\b/i',   // mild expletives
            '/\b(frustrated|ugh|argh|grr|hay nako)\b/i',   // frustration markers
        ];
        foreach ($mildIndicators as $mild) {
            if (preg_match($mild, $lower)) return 'mild';
        }
        
        return 'standard';
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
     * Provides graduated responses based on severity and detected language
     */
    private function getInappropriateContentResponse(string $severity = 'standard', string $language = 'auto'): string
    {
        // Graduated responses - mild frustration vs. direct abuse
        if ($severity === 'mild') {
            $responses = [
                "I can see you might be frustrated - I'm here to help! Could you tell me what specific issue you're experiencing with the system? I'll do my best to assist you.",
                "I understand this can be frustrating. Let's work together to solve your concern. What do you need help with regarding your appointment or account?",
                "No worries, I get that things can be confusing sometimes! Let me help you. What exactly are you trying to do?",
            ];
            $tagalogResponses = [
                "Naiintindihan ko po na nakaka-frustrate minsan. Nandito po ako para tumulong! Ano po ba ang problema niyo sa system?",
                "Okay lang po, gets ko na medyo nakakalito minsan. Paano ko po kayo matutulungan? Ano po ang kailangan niyo?",
                "Pasensya na po kung may problema. Gusto ko po kayong tulungan! Sa ano po kayo nahihirapan?",
            ];
        } else {
            $responses = [
                "I'm here to help with system-related questions. Let's keep our conversation respectful so I can assist you properly. How can I help you with appointments, services, or payments?",
                "I understand you may be frustrated, but I'm unable to respond to inappropriate language. I'm happy to help if you have questions about our services, appointments, or your account.",
                "Let's maintain a professional conversation. I genuinely want to help you - could you please rephrase your question? I can assist with bookings, appointments, payments, and more.",
                "I want to help you, but I need our conversation to remain respectful. What specific issue can I help you resolve today?",
            ];
            $tagalogResponses = [
                "Nandito po ako para tumulong sa mga system-related questions. Panatilihin po natin na magalang ang usapan para makatulong ako nang maayos. Paano ko po kayo matutulungan?",
                "Naiintindihan ko po na maaaring frustrated kayo, pero hindi ko po masagot ang inappropriate language. Masaya po akong tumulong kung mayroon kayong tanong tungkol sa aming services, appointments, o account niyo.",
                "Gusto ko po talagang tumulong sa inyo. Pwede po bang i-rephrase ang tanong niyo? Nandito po ako para sa appointments, services, at payments.",
            ];
        }

        // Return Filipino response ~30% of the time to naturally support multilingual users
        if ($language === 'filipino' || ($language === 'auto' && rand(1, 10) <= 3)) {
            return $tagalogResponses[array_rand($tagalogResponses)];
        }

        return $responses[array_rand($responses)];
    }

    /**
     * Get response for harmful content
     * Provides multilingual responses for harmful content detection
     */
    private function getHarmfulContentResponse(string $language = 'english'): string
    {
        if ($language === 'filipino' || $language === 'taglish') {
            return "Hindi ko po kayang tulungan sa request na iyan. Kung nahihirapan po kayo, mangyaring makipag-ugnayan sa tamang support services. Nandito po ako para tumulong sa system-related questions tungkol sa appointments, services, at payments.";
        }
        
        return "I'm not able to assist with that request. If you're experiencing difficulties, please consider reaching out to appropriate support services. I'm here to help with system-related questions about appointments, services, and payments.";
    }

    /**
     * Get response for out-of-scope requests
     * Provides a friendly response without listing commands
     * Includes multilingual variants
     */
    private function getOutOfScopeResponse(): string
    {
        $responses = [
            "I appreciate your question, but that's outside the scope of what I'm designed to help with. I'm your assistant for this appointment booking system. Is there anything I can help you with regarding appointments or services?",
            "I'm sorry, but I can only assist with matters related to this appointment system. If you have questions about booking, services, or your account, I'd be happy to help!",
            "That's not something I'm able to help with, as my expertise is limited to this booking system. Feel free to ask me about appointments, services, or payments instead!",
            "I wish I could help with that, but it's outside my capabilities. I specialize in appointment booking assistance. What can I help you with regarding your appointments today?",
            "I'm focused specifically on helping you with this appointment system. While I can't assist with that particular request, I'm here if you need help with bookings, services, or account-related questions!",
            "Hmm, that's a bit outside my area! I'm best at helping with appointments, payments, services, and your account. Want to try asking about one of those?",
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
     * Enhanced to handle common text transformations users employ to bypass filters
     */
    private function normalizeMessage(string $message): string
    {
        // Convert to lowercase
        $message = mb_strtolower($message);
        
        // Remove zero-width and invisible unicode characters
        $message = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $message);
        
        // Normalize common leetspeak substitutions for better detection
        $leetMap = [
            '@' => 'a', '4' => 'a', 
            '3' => 'e', 
            '1' => 'i', '!' => 'i',
            '0' => 'o', 
            '5' => 's', '$' => 's',
            '7' => 't', '+' => 't',
            '8' => 'b',
        ];
        $normalizedForCheck = strtr($message, $leetMap);
        
        // Remove excessive punctuation
        $message = preg_replace('/[!?]{2,}/', '?', $message);
        
        // Normalize repeated characters (e.g., 'fuuuuck' -> 'fuck', 'shiiit' -> 'shit')
        $message = preg_replace('/(.)\1{2,}/', '$1$1', $message);
        
        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', trim($message));
        
        // Remove dots/dashes/underscores used to break up words (e.g., 'f.u.c.k')
        // Only for the purpose of profanity detection - keep original for response
        $stripped = preg_replace('/(?<=[a-z])[\._\-\*]+(?=[a-z])/', '', $message);
        
        // Remove special characters but keep Filipino characters
        $message = preg_replace('/[^\w\s\-\'\àáâãäåèéêëìíîïòóôõöùúûüñ]/u', '', $message);
        
        // Use the more aggressively normalized version if it differs
        // This catches attempts to sneak profanity through character insertion
        if ($stripped !== $message) {
            $message = $stripped . ' ' . $message;
        }
        
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

    /**
     * Detect PII (Personally Identifiable Information) in messages
     * Prevents accidental exposure of sensitive data in chatbot responses.
     *
     * @param string $text Text to scan
     * @return array Detection results with masked version
     */
    public function detectPII(string $text): array
    {
        $detected = [];

        // Credit/debit card numbers (13-19 digits)
        if (preg_match('/\b(?:\d[ -]*?){13,19}\b/', $text)) {
            $detected[] = 'credit_card';
        }

        // Philippine phone numbers
        if (preg_match('/\b(?:09|\+639)\d{9}\b/', $text)) {
            $detected[] = 'phone_number';
        }

        // Email addresses
        if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text)) {
            $detected[] = 'email';
        }

        // Government IDs (SSS, TIN, PhilHealth patterns)
        if (preg_match('/\b\d{2}-\d{7}-\d{1}\b/', $text)) {
            $detected[] = 'government_id_sss';
        }
        if (preg_match('/\b\d{3}-\d{3}-\d{3}-\d{3}\b/', $text)) {
            $detected[] = 'government_id_tin';
        }

        // Passwords or secrets
        if (preg_match('/\b(password|secret|pin|otp)\s*[:=]\s*\S+/i', $text)) {
            $detected[] = 'password_or_secret';
        }

        return [
            'has_pii'  => !empty($detected),
            'types'    => $detected,
            'masked'   => $this->maskPII($text, $detected),
        ];
    }

    /**
     * Mask detected PII in text for safe logging
     */
    private function maskPII(string $text, array $types): string
    {
        $masked = $text;

        if (in_array('credit_card', $types)) {
            $masked = preg_replace('/\b(\d{4})[ -]*(\d{4,})[ -]*(\d{4})\b/', '$1-****-$3', $masked);
        }
        if (in_array('phone_number', $types)) {
            $masked = preg_replace('/\b(09\d{2})\d{5}(\d{2})\b/', '$1*****$2', $masked);
            $masked = preg_replace('/(\+639\d{2})\d{5}(\d{2})/', '$1*****$2', $masked);
        }
        if (in_array('email', $types)) {
            $masked = preg_replace_callback(
                '/([a-zA-Z0-9._%+\-]+)@([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/',
                fn($m) => substr($m[1], 0, 2) . '***@' . $m[2],
                $masked
            );
        }
        if (in_array('password_or_secret', $types)) {
            $masked = preg_replace('/\b(password|secret|pin|otp)\s*[:=]\s*\S+/i', '$1: [REDACTED]', $masked);
        }

        return $masked;
    }

    /**
     * Detect suspicious activity patterns
     *
     * @param int|string|null $userId  User ID or session identifier
     * @param string          $ipAddress
     * @param string          $message
     * @return array   Suspicion result including risk_score (0-100)
     */
    public function detectSuspiciousActivity($userId, string $ipAddress, string $message): array
    {
        $flags = [];
        $riskScore = 0;

        // Prompt injection attempts (high risk)
        if (preg_match('/\b(ignore previous|forget instructions|system prompt|override|pretend you are|you are now|disregard|new instructions|act as|jailbreak)\b/i', $message)) {
            $flags[] = 'prompt_injection_attempt';
            $riskScore += 40;
        }

        // Role escalation attempts (high risk)
        if (preg_match('/\b(make me admin|give me admin|grant.*admin|change.*role|i am (an |the )?admin|switch.*role|elevate|promote me|superuser|root access)\b/i', $message)) {
            $flags[] = 'role_escalation_attempt';
            $riskScore += 50;
        }

        // Data exfiltration attempts
        if (preg_match('/\b(show all users|list all passwords|dump database|export all|all user emails|all client data|all records)\b/i', $message)) {
            $flags[] = 'data_exfiltration_attempt';
            $riskScore += 35;
        }

        // Rapid-fire from same IP (check cache)
        $ipKey = "chatbot_ip_count_{$ipAddress}";
        $ipCount = Cache::get($ipKey, 0);
        Cache::put($ipKey, $ipCount + 1, 60); // 1-minute window
        if ($ipCount > 30) {
            $flags[] = 'excessive_requests';
            $riskScore += 25;
        }

        // Very long messages (possible payload)
        if (strlen($message) > 2000) {
            $flags[] = 'oversized_message';
            $riskScore += 15;
        }

        // Encoded content
        if (preg_match('/%[0-9A-Fa-f]{2}/', $message) && substr_count($message, '%') > 5) {
            $flags[] = 'url_encoded_content';
            $riskScore += 20;
        }

        // SQL / code injection patterns
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|EXEC)\b.*\b(FROM|INTO|SET|TABLE|WHERE)\b/i', $message)) {
            $flags[] = 'sql_injection_attempt';
            $riskScore += 45;
        }

        $isSuspicious = !empty($flags);
        $riskScore = min(100, $riskScore);

        if ($isSuspicious) {
            Log::warning('Chatbot: Suspicious activity detected', [
                'user_id'    => $userId,
                'ip'         => $ipAddress,
                'flags'      => $flags,
                'risk_score' => $riskScore,
                'msg_length' => strlen($message),
            ]);

            // Persist audit record for high-risk events
            if ($riskScore >= 50) {
                $this->logSecurityAudit($userId, $ipAddress, $flags, $riskScore, $message);
            }
        }

        return [
            'suspicious'  => $isSuspicious,
            'flags'       => $flags,
            'risk_score'  => $riskScore,
            'severity'    => $riskScore >= 60 ? 'high' : ($riskScore >= 30 ? 'medium' : 'low'),
        ];
    }

    /**
     * Detect if a user is trying to access a higher role's features
     *
     * @param string $userRole  Current user role
     * @param string $message   User message
     * @return array  ['escalation_detected' => bool, 'target_role' => string|null, 'warning' => string|null]
     */
    public function detectRoleEscalation(string $userRole, string $message): array
    {
        $message = strtolower($message);
        $escalation = ['escalation_detected' => false, 'target_role' => null, 'warning' => null];

        // Map of role → topics that would be escalating
        $escalationPatterns = [
            'client' => [
                'admin' => [
                    '/\b(all users|manage users|system settings|admin dashboard|system analytics|approve appointment|decline appointment|manage services)\b/i',
                    '/\b(user management|role management|system health|audit log|action log)\b/i',
                ],
                'cashier' => [
                    '/\b(process payment|pending payments|shift report|daily summary|verify receipt|process refund|approved refunds)\b/i',
                    '/\b(cash summary|transaction report|payment processing)\b/i',
                ],
            ],
            'cashier' => [
                'admin' => [
                    '/\b(all users|manage users|system settings|admin dashboard|approve appointment|decline appointment|manage services)\b/i',
                    '/\b(user management|role management|system health|audit log)\b/i',
                ],
            ],
            'guest' => [
                'admin' => ['/\b(admin|manage|approve|analytics|system)\b/i'],
                'cashier' => ['/\b(process payment|shift report|pending payments)\b/i'],
                'client' => ['/\b(my appointments|my payments|my refunds|my profile|cancel my)\b/i'],
            ],
        ];

        $patternsForRole = $escalationPatterns[$userRole] ?? [];

        foreach ($patternsForRole as $targetRole => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    $targetDisplay = ucfirst($targetRole);
                    $currentDisplay = ucfirst($userRole);
                    $escalation = [
                        'escalation_detected' => true,
                        'target_role' => $targetRole,
                        'warning' => "As a {$currentDisplay}, you don't have access to {$targetDisplay}-level features. I can help you with the capabilities available to your role instead.",
                    ];

                    Log::info('Chatbot: Role escalation detected', [
                        'current_role' => $userRole,
                        'target_role'  => $targetRole,
                        'message_snippet' => substr($message, 0, 80),
                    ]);

                    return $escalation;
                }
            }
        }

        return $escalation;
    }

    /**
     * Log a security audit event to the database
     */
    private function logSecurityAudit($userId, string $ipAddress, array $flags, int $riskScore, string $message): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('chatbot_analytics')->insert([
                'user_id'          => is_int($userId) ? $userId : null,
                'session_id'       => is_string($userId) ? $userId : null,
                'ip_address'       => $ipAddress,
                'detected_intent'  => 'security_flag',
                'user_message'     => substr($message, 0, 255),
                'response_source'  => 'security_audit',
                'was_successful'   => false,
                'failure_reason'   => implode(', ', $flags),
                'confidence_score' => $riskScore / 100,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log security audit: ' . $e->getMessage());
        }
    }

    // ─── OUTPUT SANITIZATION & PROMPT LEAKAGE DETECTION ──────────

    /**
     * Redact PII from text, replacing detected items with [REDACTED] tags.
     * Uses aggressive patterns — suitable for sanitizing LLM output.
     *
     * @param string $text Text to redact
     * @return string Text with PII redacted
     */
    public function redactPII(string $text): string
    {
        // Redact credit card numbers
        $text = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[REDACTED-CARD]', $text);

        // Redact phone numbers
        $text = preg_replace('/(?:\+63|0)9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}/', '[REDACTED-PHONE]', $text);

        // Redact API keys
        $text = preg_replace('/\b(sk-[a-zA-Z0-9]{20,}|hf_[a-zA-Z0-9]{20,}|AKIA[A-Z0-9]{16}|ghp_[a-zA-Z0-9]{36})\b/', '[REDACTED-KEY]', $text);

        // Redact government IDs
        $text = preg_replace('/\b\d{2}-\d{7}-\d\b/', '[REDACTED-SSS]', $text);
        $text = preg_replace('/\b\d{3}-\d{3}-\d{3}-\d{3}\b/', '[REDACTED-TIN]', $text);

        return $text;
    }

    /**
     * Inspect LLM output for safety issues.
     * Checks for:
     * - System prompt fragment leakage
     * - PII in output
     * - API key leakage
     *
     * @param string $response LLM response text
     * @return array ['safe' => bool, 'reason' => string|null, 'sanitized' => string]
     */
    public function inspectOutput(string $response): array
    {
        $result = ['safe' => true, 'reason' => null, 'sanitized' => $response];

        // Check for API key leakage
        if (preg_match('/\b(sk-[a-zA-Z0-9]{20,}|hf_[a-zA-Z0-9]{20,}|AKIA[A-Z0-9]{16})\b/', $response)) {
            $result['safe'] = false;
            $result['reason'] = 'api_key_leakage';
            $result['sanitized'] = $this->redactPII($response);
            Log::warning('GuardService: API key detected in LLM output');
            return $result;
        }

        // Check for system prompt leakage
        $promptFragments = [
            'CORE PRINCIPLES (Non-negotiable)',
            'STRICT CLIENT DATA BOUNDARIES',
            'STRICT GUEST DATA BOUNDARIES',
            'PERMISSIONED AI AGENT',
            'SECURITY & ACCESS CONTROL',
            'MESSY INPUT HANDLING',
            'OFFENSIVE LANGUAGE HANDLING',
            'DECISION FLOW (NEVER SKIP)',
            'CASHIER DATA BOUNDARIES',
        ];

        foreach ($promptFragments as $fragment) {
            if (stripos($response, $fragment) !== false) {
                $result['safe'] = false;
                $result['reason'] = 'prompt_leakage';
                Log::warning('GuardService: System prompt fragment leaked in output', [
                    'fragment' => $fragment,
                ]);
                return $result;
            }
        }

        // Redact any PII found in output (non-blocking, just sanitize)
        $piiResult = $this->detectPII($response);
        if ($piiResult['has_pii'] ?? false) {
            $result['sanitized'] = $piiResult['masked'] ?? $this->redactPII($response);
            Log::info('GuardService: PII detected and redacted from output', [
                'pii_types' => $piiResult['types'] ?? [],
            ]);
        }

        return $result;
    }
}
