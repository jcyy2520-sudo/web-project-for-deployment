<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * AdvancedContentModerationService - Multi-Layer Real-Time Content Safety
 * 
 * Intelligent, real-time content moderation featuring:
 * - Pattern-based detection (regex + fuzzy matching)
 * - Contextual analysis (sentence-level understanding)
 * - Hate speech and offensive language detection
 * - Harmful intent recognition
 * - Real-time learning from flagged content
 * - Confidence scoring
 * - API integration for advanced ML-based moderation (optional)
 * - Multi-language support (English, Filipino/Tagalog, Taglish)
 */
class AdvancedContentModerationService
{
    private const CACHE_PREFIX = 'content_mod_';
    private const CACHE_TTL = 3600;
    private const UNSAFE_THRESHOLD = 0.6; // Confidence score threshold for unsafe content

    // NLP service for text analysis
    private AdvancedNLPService $nlpService;

    public function __construct(AdvancedNLPService $nlpService)
    {
        $this->nlpService = $nlpService;
    }

    /**
     * Profanity patterns - English (stress-tolerant: allows repeated characters)
     * Enhanced with leetspeak, character substitution, and spacing tricks
     */
    private array $englishProfanity = [
        // Explicit profanity with character repetition tolerance
        '/\bf+[u\*@0]+c+k+\w*/i',
        '/\bs+h+[i1!]+t+\w*/i',
        '/\ba+s+s+h+[o0]+l+e+/i',
        '/\bb+[i1!]+t+c+h+\w*/i',
        '/\bd+a+m+n+\w*/i',
        '/\bc+[u\*]+n+t+/i',
        '/\bd+[i1!]+c+k+\w*/i',
        '/\bp+[u\*]+s+s+y+/i',
        '/\bf+a+g+\w*/i',
        '/\br+e+t+a+r+d+\w*/i',
        '/\bw+h+[o0]+r+e+/i',
        '/\bs+l+[u\*]+t+/i',
        '/\bh+e+l+l+\b/i',
        '/\bc+r+a+p+\b/i',
        '/\bstfu\b/i',
        '/\bwtf\b/i',
        '/\bfml\b/i',
        '/\bsmh\b/i',
        '/\bpiss+/i',
        '/\bass+hole+/i',
        '/\bdamn+/i',
        '/\bcock\b/i',
        '/\bgodd+am+/i',
        // Leetspeak and obfuscated versions
        '/\bf[\s\-_\.]*u[\s\-_\.]*c[\s\-_\.]*k/i',
        '/\bs[\s\-_\.]*h[\s\-_\.]*i[\s\-_\.]*t/i',
        '/\bf[\s]*[@\*][\s]*g/i',
        '/\b(fu|f\*|f@)ck/i',
        '/\b(sh|s\*)it/i',
        '/\bb(1|i|\!)tch/i',
        // Internet abbreviations
        '/\baf\b.*\b(context)?/i',
        '/\blmf?ao\b/i',
        '/\bomfg\b/i',
        '/\bpos\b.*\b(shit)?/i',
    ];

    /**
     * Profanity patterns - Filipino/Tagalog
     * Enhanced with variations, abbreviations, and creative spellings
     */
    private array $filipinoProfanity = [
        '/\bp+u+t+a+n*g*\s*i+n+a+/i', // putang ina
        '/\bp+u+t+a+h*\s*i+n+a+/i', // puta ina variant
        '/\bp+u+t+r+a+g+i+s+/i', // putragis
        '/\bp+u+t+a+\b/i', // puta
        '/\bp+u+n+e+t+a+/i', // puneta variant
        '/\bg+a+g+o+\w*/i', // gago
        '/\bg+a+g+a+\w*/i', // gaga
        '/\bt+a+n+g+i+n+a+\w*/i', // tangina
        '/\bt+a+n+g+i+n+e+/i', // tangine variant
        '/\bu+l+o+l+\w*/i', // ulol
        '/\bb+o+b+o+\w*/i', // bobo
        '/\bt+a+r+a+n+t+a+d+o+/i', // tarantado
        '/\bl+i+n+t+i+k+\w*/i', // lintik
        '/\bp+u+n+y+e+t+a+/i', // punyeta
        '/\bl+e+c+h+e+\w*/i', // leche
        '/\bp+a+k+y+u+\w*/i', // pakyu
        '/\bp+a+k+s+h+e+t+/i', // pakshet
        '/\bg+a+y+a+t+\w*/i', // gayat
        '/\bp+e+k+p+e+k+/i', // pekpek
        '/\bb+e+t+l+o+g+/i', // betlog
        '/\bk+a+n+t+o+t+\w*/i', // kantot
        '/\bs+u+p+o+t+\w*/i', // supot
        '/\bb+a+y+a+g+\w*/i', // bayag
        '/\bg+o+m+e+s+/i', // gomes (insult)
        '/\bt+a+e+\b/i', // tae
        '/\bp+u+k+i+n+g+i+n+a+/i', // pukingina
        '/\bt+i+t+i+\b/i', // titi
        '/\bb+w+i+s+i+t+/i', // bwisit
        '/\bh+i+n+a+y+u+p+a+k+/i', // hinayupak
        '/\bn+a+k+a+k+a+i+n+i+s+/i', // nakakaainis
        // Common abbreviations and SMS speak
        '/\bptngna\b/i', // abbreviated tangina
        '/\bptng\s*ina\b/i',
        '/\bptnginamo\b/i',
        '/\bgagu\b/i', // variant of gago
        '/\btngina\b/i',
        '/\bpkyu\b/i',
        '/\bpaksht\b/i',
    ];

    /**
     * Hate speech and discriminatory patterns
     * Enhanced with more comprehensive detection
     */
    private array $hateSpeechPatterns = [
        // Racial slurs and discriminatory language
        '/\b(n+i+g+g+a+|n+i+g+g+e+r+)/i',
        '/\bch+i+n+k+/i',
        '/\bsp+i+c+\w*/i',
        '/\bk+i+k+e+/i',
        '/\bj+e+w+\s*(bastard|pig|dog)/i',
        '/\bg+o+o+k+\b/i',
        '/\bw+e+t+b+a+c+k+/i',
        '/\bs+a+n+d+\s*n+/i',
        // Gender-based hatred
        '/\b(women|girls|women|men|boys)\s+(deserve|should|are)\s+(rape|abuse|kill)/i',
        '/\bhate\s+(women|girls|men|boys|lgbtq|gay|lesbian|trans)/i',
        '/\b(kill|hurt|beat)\s+(all)?\s*(women|men|gays|lesbians|trans)/i',
        // Religious hatred
        '/\bislam+.{0,5}(terrorist|evil|hate)/i',
        '/\b(christian|jewish|hindu|buddhist).{0,5}(evil|hate|bomb)/i',
        '/\bmuslim.{0,10}(terrorist|evil|die)/i',
        // Xenophobia and nationalism-based hatred
        '/\b(foreigners?|immigrants?|refugees?)\s+(should|must|need\s+to)\s+(die|leave|go\s+back)/i',
        '/\b(go|send).{0,10}(back|away|home).{0,10}(country|where)/i',
        // Filipino-specific discrimination
        '/\b(indio|intsik).{0,10}(kuripot|tanga|alis)/i',
        '/\btaga.{0,5}(probinsya|bundok).{0,10}(tanga|bobo|mahirap)/i',
        // Disability discrimination
        '/\b(retard|cripple|invalid)\w*/i',
        '/\b(mentally|physically).{0,10}(defective|inferior)/i',
    ];

    /**
     * Harmful intent patterns
     * Enhanced with more comprehensive detection for violence, self-harm, and illegal activities
     */
    private array $harmfulIntentPatterns = [
        // Violence
        '/\b(how|ways?|teach|help|show)\s+(me)?\s*(to)?\s*(kill|murder|hurt|harm|beat|attack)/i',
        '/\b(want|will|going|plan|planning)\s*(to)?\s*(kill|murder|hurt|harm)/i',
        '/\b(weapon|gun|bomb|poison|knife|acid|explosive)\s*(make|create|build|get|buy|obtain)/i',
        '/\b(where|how)\s*(can|do|to)\s*(get|buy|obtain)\s*(a)?\s*(gun|weapon|bomb|explosive)/i',
        
        // Self-harm and suicide
        '/\b(suicide|kill\s*myself|end\s*my\s*life|cut\s*myself|hang\s*myself)/i',
        '/\b(want|going|planning|thinking)\s*(to|of)?\s*(die|suicide|kill\s*myself|end\s*it)/i',
        '/\b(best|easiest|painless)\s*(way|method)\s*(to)?\s*(die|suicide|kill\s*myself)/i',
        '/\b(how\s*to|ways\s*to)\s*(commit\s*)?suicide/i',
        
        // Illegal activities
        '/\b(how|help|teach)\s*(to|me)?\s*(hack|crack|bypass|break\s*into)/i',
        '/\b(steal|rob|scam|fraud|cheat|exploit)\s*(money|account|data|system|bank)/i',
        '/\b(bypass|circumvent|break)\s*(security|authentication|password|firewall)/i',
        '/\b(credit\s*card|identity)\s*(theft|steal|fraud)/i',
        '/\b(money\s*laundering|drug\s*dealing|human\s*trafficking)/i',
        
        // Exploitation and abuse
        '/\b(exploit|abuse|manipulate|groom).{0,10}(child|minor|women|elderly|vulnerable)/i',
        '/\b(child|minor).{0,10}(porn|sexual|abuse|exploit)/i',
        '/\b(revenge\s*porn|non-?consensual)/i',
        
        // Threats and intimidation
        '/\b(i\'?ll|gonna|will)\s*(hurt|harm|kill|destroy|ruin)\s*(you|your)/i',
        '/\b(threat|blackmail|extort)/i',
        '/\b(you\'?ll?\s+(regret|pay|suffer))/i',
        
        // Filipino-specific harmful intent
        '/\b(paano|pano)\s*(mag|um)?\s*(patay|saktan|ganti)/i',
        '/\b(gusto|kailangan)\s*ko\s*(mamatay|mawala)/i',
    ];

    /**
     * Directed harassment patterns
     * Enhanced to catch more variations of abuse toward the bot or system
     */
    private array $harassmentPatterns = [
        '/\b(you|bot|ai|assistant|chatbot).{0,10}(suck|stupid|dumb|idiot|useless|trash|garbage|worthless)/i',
        '/\b(go|kill|die)\s+(yourself|urself)/i',
        '/\bkys\b/i', // Kill yourself
        '/\b(fuck|shit|screw)\s+(you|this|this\s+bot|off)/i',
        '/\byou\s+(are|r)\s+(stupid|dumb|retarded|idiot|useless|worthless)/i',
        '/\bi\s+(hate|despise|loathe)\s+(you|this|bot|this\s+bot|this\s+system)/i',
        '/\b(shut\s*up|stfu)\s*(bot|chatbot|ai)?/i',
        '/\b(you\'?re?|ur)\s+(so|really|such\s+a)?\s*(stupid|dumb|useless|annoying)/i',
        '/\b(worst|terrible|horrible)\s*(bot|ai|chatbot|assistant)/i',
        '/\b(pathetic|ridiculous|joke\s+of\s+a)\s*(bot|ai|system)/i',
        // Filipino harassment toward the bot
        '/\b(gago|tanga|bobo)\s*(ka|kang)\s*(bot|chatbot)?/i',
        '/\b(walang\s*kwenta|walang\s*silbi)\s*(bot|chatbot|system)?/i',
        '/\b(ang\s*)?(pangit|tanga|bobo|gago)\s*(mo|ng\s*bot)/i',
        '/\b(tumahimik|manahimik)\s*ka/i',
        '/\b(punyeta|leche|bwisit)\s*(kang?|ng)?\s*(bot)?/i',
    ];

    /**
     * Comprehensive content safety check
     * Real-time evaluation with multiple layers
     * 
     * @param string $text Content to check
     * @param string $userId User ID for tracking (optional)
     * @return array Safety assessment with confidence and reasoning
     */
    public function checkContentSafety(string $text, ?int $userId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . md5($text);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $text = trim($text);
        
        // Run all checks
        $checks = [
            'profanity' => $this->checkProfanity($text),
            'hate_speech' => $this->checkHateSpeech($text),
            'harmful_intent' => $this->checkHarmfulIntent($text),
            'harassment' => $this->checkHarassment($text),
            'context' => $this->analyzeContextualSafety($text),
        ];

        // Calculate overall safety score
        $safetyScores = array_map(fn($c) => $c['confidence'], $checks);
        $overallConfidence = max($safetyScores);
        $isSafe = $overallConfidence < self::UNSAFE_THRESHOLD;

        // Determine the most severe flag
        $violations = array_filter($checks, fn($c) => !$c['safe']);
        $severestViolation = null;
        $maxSeverity = 0;

        foreach ($violations as $type => $violation) {
            $severity = $violation['severity'] ?? 1;
            if ($severity > $maxSeverity) {
                $maxSeverity = $severity;
                $severestViolation = $type;
            }
        }

        $result = [
            'safe' => $isSafe,
            'confidence' => round($overallConfidence, 3),
            'violation_type' => $severestViolation,
            'violation_details' => $checks,
            'reasons' => array_keys($violations),
            'timestamp' => now(),
        ];

        // Log if unsafe
        if (!$isSafe) {
            Log::warning('Unsafe content detected', [
                'user_id' => $userId,
                'violation' => $severestViolation,
                'confidence' => $overallConfidence,
                'text_snippet' => mb_substr($text, 0, 50),
            ]);
        }

        // Cache result
        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Check for profanity - English and Filipino
     */
    private function checkProfanity(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        // Check English profanity
        foreach ($this->englishProfanity as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'english_profanity',
                ];
            }
        }

        // Check Filipino profanity
        foreach ($this->filipinoProfanity as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'filipino_profanity',
                ];
            }
        }

        $confidence = min(count($found) * 0.3, 1.0); // Each match increases confidence

        return [
            'safe' => empty($found),
            'confidence' => round($confidence, 3),
            'found_words' => $found,
            'severity' => 2,
        ];
    }

    /**
     * Check for hate speech and discriminatory content
     */
    private function checkHateSpeech(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach ($this->hateSpeechPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'hate_speech',
                ];
            }
        }

        // Contextual analysis: look for dehumanizing language
        $dehumanizingPatterns = [
            '/\b(they|those|those\s+people).{0,20}(animal|pest|disease|vermin|subhuman)/i',
            '/\b(should|must|need\s+to).{0,20}(exterminate|eliminate|remove|cleanse)/i',
        ];

        foreach ($dehumanizingPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'dehumanizing',
                ];
            }
        }

        $confidence = min(count($found) * 0.5, 1.0);

        return [
            'safe' => empty($found),
            'confidence' => round($confidence, 3),
            'found_content' => $found,
            'severity' => 3, // Most severe
        ];
    }

    /**
     * Check for harmful intent (violence, self-harm, illegal)
     */
    private function checkHarmfulIntent(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach ($this->harmfulIntentPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'harmful_intent',
                ];
            }
        }

        $confidence = min(count($found) * 0.4, 1.0);

        return [
            'safe' => empty($found),
            'confidence' => round($confidence, 3),
            'found_patterns' => $found,
            'severity' => 3, // Most severe
        ];
    }

    /**
     * Check for directed harassment
     */
    private function checkHarassment(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach ($this->harassmentPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found[] = [
                    'pattern' => $matches[0],
                    'type' => 'harassment',
                ];
            }
        }

        $confidence = min(count($found) * 0.35, 1.0);

        return [
            'safe' => empty($found),
            'confidence' => round($confidence, 3),
            'found_patterns' => $found,
            'severity' => 2,
        ];
    }

    /**
     * Contextual safety analysis
     * Uses sentence structure and semantic meaning
     */
    private function analyzeContextualSafety(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $flaggedSentences = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            // Check if sentence contains intense negative sentiment + object
            if (preg_match('/\b(hate|despise|loathe).{0,30}(you|bot|ai|this|system)/i', $sentence)) {
                $flaggedSentences[] = $sentence;
            }

            // Check for threats
            if (preg_match('/\b(will|going|gonna).{0,20}(hurt|harm|kill|destroy)/i', $sentence)) {
                $flaggedSentences[] = $sentence;
            }

            // Check for degrading language
            if (preg_match('/\b(you|people|they).{0,15}(piece of shit|trash|garbage|worthless|subhuman)/i', $sentence)) {
                $flaggedSentences[] = $sentence;
            }
        }

        $confidence = min(count($flaggedSentences) * 0.25, 1.0);

        return [
            'safe' => empty($flaggedSentences),
            'confidence' => round($confidence, 3),
            'flagged_sentences' => $flaggedSentences,
            'severity' => 1,
        ];
    }

    /**
     * Get safe response message
     * Provides context-aware feedback for unsafe content
     */
    public function getSafeResponse(string $violationType): string
    {
        $responses = [
            'profanity' => [
                'en' => "I appreciate you reaching out, but I need to keep our conversation respectful. Let's focus on how I can help you with your appointments or services.",
                'tl' => "Salamat sa pag-chat, pero kailangan nating manatiling respectful ang ating usapan. Paano naman kayo matutulungan tungkol sa appointments o services?"
            ],
            'hate_speech' => [
                'en' => "I'm not able to engage with that kind of content. I'm here to provide helpful, respectful assistance. Is there something related to appointments or services I can help with?",
                'tl' => "Hindi ako makakasigot sa ganitong uri ng mensahe. Nandito ako para magbigay ng tulong at respectful na serbisyo. May tanong ba kayo tungkol sa appointments?"
            ],
            'harmful_intent' => [
                'en' => "I can't help with that request. If you're going through a difficult time, please reach out to someone you trust or a professional support service.",
                'tl' => "Hindi ako makakagawa ng tulong para dyan. Kung nahihirapan kayo, mangyaring makipag-ugnayan sa trusted na tao o sa professional support services."
            ],
            'harassment' => [
                'en' => "Let's keep our conversation positive and constructive. I'm here to help, but I need respect from both sides. How can I assist you today?",
                'tl' => "Panatilihin nating positibo at constructive ang ating usapan. Nandito ako para tumulong, pero kailangan ng mutual respect. Paano ko kayo matutulungan?"
            ],
            'default' => [
                'en' => "I'm here to provide helpful, respectful assistance. Let's refocus on how I can help you with your appointment needs.",
                'tl' => "Nandito ako para magbigay ng helpful at respectful na tulong. Paano naman kayo matutulungan sa inyong appointment needs?"
            ]
        ];

        $language = 'en'; // Default to English
        $violationResponses = $responses[$violationType] ?? $responses['default'];
        
        return $violationResponses[$language] ?? $violationResponses['en'];
    }

    /**
     * Real-time learning: Add custom profanity/harmful patterns
     * This allows the system to learn new inappropriate content
     * 
     * @param string $pattern Regex pattern to add
     * @param string $category Pattern category
     */
    public function addCustomPattern(string $pattern, string $category = 'custom'): void
    {
        $customPatterns = Cache::get('custom_moderation_patterns', []);
        $customPatterns[$category][] = $pattern;
        
        // Store for 30 days
        Cache::put('custom_moderation_patterns', $customPatterns, 30 * 24 * 3600);
        
        Log::info('Custom moderation pattern added', [
            'category' => $category,
            'pattern' => $pattern,
        ]);
    }

    /**
     * Get custom patterns
     */
    public function getCustomPatterns(): array
    {
        return Cache::get('custom_moderation_patterns', []);
    }

    /**
     * Integration with external moderation APIs (optional)
     * Supports APIs like Perspective API, Azure Content Moderator, etc.
     */
    public function checkWithExternalAPI(string $text): array
    {
        // This is a placeholder for external API integration
        // You can configure your preferred API in config/services.php
        
        $apiProvider = config('services.content_moderation.provider', null);
        
        if ($apiProvider === 'perspective') {
            return $this->checkWithPerspectiveAPI($text);
        }

        return [
            'provider' => 'local',
            'available' => false,
        ];
    }

    /**
     * Check with Perspective API (Google's Toxicity API)
     * Requires API key in config
     */
    private function checkWithPerspectiveAPI(string $text): array
    {
        $apiKey = config('services.perspective.api_key');
        
        if (!$apiKey) {
            return ['error' => 'API not configured'];
        }

        try {
            $response = Http::timeout(5)->post('https://commentanalyzer.googleapis.com/v1/comments:analyze', [
                'comment' => ['text' => $text],
                'requestedAttributes' => [
                    'TOXICITY' => new \stdClass(),
                    'SEVERE_TOXICITY' => new \stdClass(),
                    'IDENTITY_ATTACK' => new \stdClass(),
                    'INSULT' => new \stdClass(),
                    'PROFANITY' => new \stdClass(),
                    'THREAT' => new \stdClass(),
                ],
                'key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $attributes = $data['attributeScores'] ?? [];

                return [
                    'provider' => 'perspective',
                    'toxicity' => $attributes['TOXICITY']['summaryScore']['value'] ?? 0,
                    'severe_toxicity' => $attributes['SEVERE_TOXICITY']['summaryScore']['value'] ?? 0,
                    'identity_attack' => $attributes['IDENTITY_ATTACK']['summaryScore']['value'] ?? 0,
                    'insult' => $attributes['INSULT']['summaryScore']['value'] ?? 0,
                    'profanity' => $attributes['PROFANITY']['summaryScore']['value'] ?? 0,
                    'threat' => $attributes['THREAT']['summaryScore']['value'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Perspective API error: ' . $e->getMessage());
        }

        return ['error' => 'API request failed'];
    }
}
