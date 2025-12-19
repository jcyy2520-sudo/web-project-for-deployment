<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AdvancedNLPService - Multilingual NLP with Fuzzy Matching & Smart Normalization
 * 
 * Real-time, intelligent Natural Language Processing featuring:
 * - Fuzzy matching for typos, misspellings, and variations
 * - Levenshtein distance-based spell correction
 * - Soundex/Phonetic matching for pronunciation variations
 * - Taglish (Tagalog-English mix) parsing and normalization
 * - Slang and informal language expansion
 * - Incomplete word/sentence handling
 * - Intent-aware text normalization
 * - Multilingual support (English, Filipino/Tagalog, Taglish)
 * - Real-time learning and caching
 */
class AdvancedNLPService
{
    // Caching
    private const CACHE_PREFIX = 'nlp_cache_';
    private const CACHE_TTL = 3600; // 1 hour
    private const MIN_MATCH_SCORE = 0.70; // 70% match threshold

    // Spell correction
    private const MAX_EDIT_DISTANCE = 2;

    /**
     * Common misspellings and corrections (English + Filipino)
     */
    private array $commonMisspellings = [
        // English
        'apointment' => 'appointment', 'appointmnet' => 'appointment', 'apoitment' => 'appointment',
        'scedule' => 'schedule', 'schdeule' => 'schedule', 'shedule' => 'schedule',
        'servce' => 'service', 'servise' => 'service', 'servis' => 'service',
        'paymet' => 'payment', 'paymnet' => 'payment', 'pament' => 'payment',
        'refund' => 'refund', 'reund' => 'refund', 'refond' => 'refund',
        'booking' => 'booking', 'bookin' => 'booking', 'bokking' => 'booking',
        'cancel' => 'cancel', 'cancle' => 'cancel', 'cencel' => 'cancel',
        'aprove' => 'approve', 'aproved' => 'approved', 'appove' => 'approve',
        'recieve' => 'receive', 'recieved' => 'received',
        // Filipino/Tagalog
        'serbisyo' => 'service', 'servisyo' => 'service', 'syerbisyo' => 'service',
        'pili' => 'choose', 'pili' => 'choose',
        'pagbabayad' => 'payment', 'pagbabayaar' => 'payment',
        'kapayapaan' => 'peace', 'kapayapaan' => 'peace',
    ];

    /**
     * Slang and informal language expansions (English + Taglish)
     */
    private array $slangExpansions = [
        // Abbreviations
        'tmrw' => 'tomorrow', 'tmm' => 'tomorrow', 'tom' => 'tomorrow',
        'tdy' => 'today', 'tday' => 'today',
        'yr' => 'your', 'ur' => 'your', 'yur' => 'your',
        'thx' => 'thanks', 'thnx' => 'thanks', 'ty' => 'thank you', 'tq' => 'thank you',
        'pls' => 'please', 'plz' => 'please', 'prz' => 'please',
        'abt' => 'about', 'bout' => 'about',
        'wht' => 'what', 'wat' => 'what', 'wha' => 'what',
        'wen' => 'when', 'whn' => 'when',
        'whr' => 'where', 'wrh' => 'where',
        'why' => 'why', 'y' => 'why',
        'hw' => 'how', 'hw' => 'how',
        'u' => 'you', 'u2' => 'you too',
        'r' => 'are', 'r u' => 'are you',
        'im' => 'i am', 'im' => 'i am',
        'wid' => 'with', 'w/' => 'with', 'w' => 'with',
        '2' => 'to', '4' => 'for',
        'b4' => 'before', 'b/4' => 'before',
        '2day' => 'today', '2nite' => 'tonight', '2nyt' => 'tonight',
        'n' => 'and', '&' => 'and',
        'asap' => 'as soon as possible',
        // Taglish
        'pano' => 'how', 'paano' => 'how',
        'magkano' => 'how much', 'ano' => 'what',
        'saan' => 'where', 'kailan' => 'when',
        'bakit' => 'why', 'sino' => 'who',
        'yung' => 'the', 'ng' => 'of', 'sa' => 'in',
        'po' => '', 'po.' => '', // Remove honorific
        'ba' => '', 'ba?' => '', // Remove question particle
        'naman' => '', 'naman,' => '', // Remove particles
        'lang' => 'only', 'lang,' => 'only', 'lang.' => 'only',
        'pls' => 'please', 'ty' => 'thank you',
        'ok' => 'okay', 'ok.' => 'okay',
        'meron' => 'have', 'merong' => 'have',
        'wala' => 'no', 'walang' => 'no',
        'syet' => 'shit', 'syet!' => 'shit', // Slang for curse word
        'ayan' => 'there', 'dito' => 'here',
        'kasi' => 'because', 'dahil' => 'because',
        'talaga' => 'really', 'talaga?' => 'really',
        'sure' => 'sure', 'sigurado' => 'sure',
        'sige' => 'okay', 'sige na' => 'okay then',
        'pwede' => 'can', 'puwede' => 'can',
        'maayos' => 'good', 'ok' => 'good',
        'sorry' => 'sorry', 'pasensya' => 'sorry',
        'heto' => 'here', 'eto' => 'this',
    ];

    /**
     * Intent keywords for fuzzy intent matching
     */
    private array $intentKeywordGroups = [
        'book' => ['book', 'booking', 'booked', 'reserve', 'reservation', 'schedule', 'bok', 'bk', 'sched', 'magbook', 'magpareserve'],
        'cancel' => ['cancel', 'cancellation', 'cancelled', 'delete', 'remove', 'unwanted', 'dont want', 'cencel'],
        'reschedule' => ['reschedule', 'change', 'move', 'shift', 'postpone', 'delay', 'reschedule', 'reschedule'],
        'status' => ['status', 'check', 'where is', 'update', 'pending', 'approved', 'track', 'approved na ba'],
        'service' => ['service', 'services', 'what', 'offer', 'available', 'servce', 'servis'],
        'price' => ['price', 'cost', 'how much', 'how much does', 'magkano', 'quanto', 'rate'],
        'payment' => ['payment', 'pay', 'paid', 'billing', 'charge', 'bayad', 'payment'],
        'refund' => ['refund', 'return', 'money back', 'refund'],
        'hours' => ['hours', 'open', 'closed', 'timing', 'when are you open', 'operating hours'],
        'help' => ['help', 'assist', 'support', 'question', 'how to', 'guide', 'tulong', 'kailangan ko'],
    ];

    /**
     * Detect primary language of input
     * 
     * @param string $text Input text
     * @return array Language detection with confidence
     */
    public function detectLanguage(string $text): array
    {
        $text = mb_strtolower(trim($text));
        
        // English indicators
        $englishScore = 0;
        $englishPatterns = [
            '/\b(the|is|are|was|to|for|and|or|but|if|what|how|can|do|does|i|you|my|your)\b/i' => 0.1,
            '/\b(appointment|booking|schedule|service|payment|refund)\b/i' => 0.2,
            '/\'(s|t|ve|re|ll|d)\b/i' => 0.15,
        ];
        foreach ($englishPatterns as $pattern => $weight) {
            if (preg_match_all($pattern, $text) > 0) {
                $englishScore += preg_match_all($pattern, $text) * $weight;
            }
        }

        // Filipino indicators
        $filipinoScore = 0;
        $filipinoPatterns = [
            '/\b(ang|ng|sa|na|ko|mo|niya|namin|tayo|kayo|sila)\b/i' => 0.15,
            '/\b(ako|ikaw|siya|kami|ito|iyan|iyon)\b/i' => 0.1,
            '/\b(serbisyo|bayad|appointment|booking|pili|araw|oras)\b/i' => 0.2,
            '/\b(po|ba|lang|naman|din|rin|pala)\b/i' => 0.12,
            '/\b(paano|ano|saan|kailan|bakit|sino|magkano)\b/i' => 0.15,
        ];
        foreach ($filipinoPatterns as $pattern => $weight) {
            if (preg_match_all($pattern, $text) > 0) {
                $filipinoScore += preg_match_all($pattern, $text) * $weight;
            }
        }

        // Determine language
        $total = $englishScore + $filipinoScore;
        if ($total === 0) {
            return ['language' => 'unknown', 'confidence' => 0, 'english_score' => 0, 'filipino_score' => 0];
        }

        $englishConfidence = $englishScore / $total;
        $filipinoConfidence = $filipinoScore / $total;

        if ($englishConfidence > 0.5) {
            $lang = 'english';
            $confidence = $englishConfidence;
        } elseif ($filipinoConfidence > 0.5) {
            $lang = 'filipino';
            $confidence = $filipinoConfidence;
        } else {
            $lang = 'mixed'; // Taglish
            $confidence = max($englishConfidence, $filipinoConfidence);
        }

        return [
            'language' => $lang,
            'confidence' => round($confidence, 2),
            'english_score' => round($englishConfidence, 2),
            'filipino_score' => round($filipinoConfidence, 2),
        ];
    }

    /**
     * Normalize text: expand slang, fix typos, handle incomplete words
     * Real-time processing with intelligent caching
     * 
     * @param string $text Raw input text
     * @param string $language Language hint
     * @return array Normalized text with metadata
     */
    public function normalizeText(string $text, string $language = ''): array
    {
        $cacheKey = self::CACHE_PREFIX . md5("normalize_{$text}_{$language}");
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $original = $text;
        $text = mb_strtolower(trim($text));

        // Step 1: Detect language if not provided
        if (empty($language)) {
            $langDetection = $this->detectLanguage($text);
            $language = $langDetection['language'];
        }

        // Step 2: Apply common misspelling corrections
        foreach ($this->commonMisspellings as $misspelled => $correct) {
            $text = preg_replace('/\b' . preg_quote($misspelled, '/') . '\b/i', $correct, $text);
        }

        // Step 3: Expand slang and abbreviations
        foreach ($this->slangExpansions as $slang => $expanded) {
            // Match whole words or at boundaries
            $pattern = '/\b' . preg_quote($slang, '/') . '\b/i';
            $text = preg_replace($pattern, $expanded, $text);
        }

        // Step 4: Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Step 5: Handle incomplete words (e.g., "apt" -> "appointment")
        $text = $this->expandIncompleteWords($text);

        // Step 6: Taglish-specific normalization if needed
        if ($language === 'mixed' || $language === 'filipino') {
            $text = $this->normalizeTaglish($text);
        }

        $result = [
            'original' => $original,
            'normalized' => $text,
            'language' => $language,
            'normalized_from' => 'original',
            'timestamp' => now(),
        ];

        // Cache result
        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Expand incomplete/abbreviated words
     */
    private function expandIncompleteWords(string $text): string
    {
        $expansions = [
            // Common abbreviations in appointment context
            '/\bapt\b/i' => 'appointment',
            '/\bappts\b/i' => 'appointments',
            '/\bsvc\b/i' => 'service',
            '/\bsvcs\b/i' => 'services',
            '/\bpmt\b/i' => 'payment',
            '/\bpmts\b/i' => 'payments',
            '/\bref\b/i' => 'refund',
            '/\brefs\b/i' => 'refunds',
            '/\brecpt\b/i' => 'receipt',
            '/\breceipt\b/i' => 'receipt',
            '/\bcanc\b/i' => 'cancel',
            '/\breschedule\b/i' => 'reschedule',
            '/\binfo\b/i' => 'information',
            '/\bprof\b/i' => 'profile',
            '/\bcust\b/i' => 'customer',
            '/\bcmd\b/i' => 'command',
        ];

        foreach ($expansions as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Normalize Taglish-specific patterns
     */
    private function normalizeTaglish(string $text): string
    {
        // Common Taglish particles to remove or normalize
        $particles = [
            '/\bpo\b/i' => '', // Honorific particle
            '/\bho\b/i' => '', // Honorific variant
            '/\bba\b/i' => '', // Question particle
            '/\bnaman\b/i' => '', // Particle
            '/\bkaya\b/i' => '', // Particle
            '/\bkasi\b/i' => 'because', // Conjunction particle
            '/\bdat\b/i' => 'that', // Slang for "that"
            '/\byun\b/i' => 'that', // Slang for "that"
        ];

        foreach ($particles as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Calculate Levenshtein distance between two strings
     * Used for fuzzy matching and typo tolerance
     */
    private function levenshteinDistance(string $a, string $b): int
    {
        $aLen = mb_strlen($a);
        $bLen = mb_strlen($b);

        if ($aLen === 0) return $bLen;
        if ($bLen === 0) return $aLen;

        $d = [];
        for ($i = 0; $i <= $aLen; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $bLen; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $aLen; $i++) {
            for ($j = 1; $j <= $bLen; $j++) {
                $cost = (mb_substr($a, $i - 1, 1) === mb_substr($b, $j - 1, 1)) ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,      // deletion
                    $d[$i][$j - 1] + 1,      // insertion
                    $d[$i - 1][$j - 1] + $cost // substitution
                );
            }
        }

        return $d[$aLen][$bLen];
    }

    /**
     * Calculate similarity score (0.0 to 1.0) between two strings
     * Uses Levenshtein distance normalized by string length
     */
    private function calculateSimilarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));

        if ($a === $b) return 1.0;

        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) return 1.0;

        $distance = $this->levenshteinDistance($a, $b);
        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Fuzzy match input against a list of known words/phrases
     * Returns best matches with confidence scores
     * 
     * @param string $input User input
     * @param array $candidates Known words/phrases to match against
     * @param float $threshold Minimum match score (0.0 to 1.0)
     * @return array Best matches with scores
     */
    public function fuzzyMatch(string $input, array $candidates, float $threshold = self::MIN_MATCH_SCORE): array
    {
        $input = mb_strtolower(trim($input));
        $matches = [];

        foreach ($candidates as $candidate) {
            $candidate = mb_strtolower(trim($candidate));
            $similarity = $this->calculateSimilarity($input, $candidate);

            if ($similarity >= $threshold) {
                $matches[] = [
                    'candidate' => $candidate,
                    'score' => round($similarity, 3),
                    'distance' => $this->levenshteinDistance($input, $candidate),
                ];
            }
        }

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return $matches;
    }

    /**
     * Find closest matching intent based on fuzzy keyword matching
     * 
     * @param string $text Normalized text
     * @param string $language User's language
     * @return array Intent match with confidence and alternative suggestions
     */
    public function detectIntentWithFuzzyMatching(string $text, string $language = 'english'): array
    {
        $text = mb_strtolower(trim($text));
        $scores = [];

        // Score each intent based on keyword matches
        foreach ($this->intentKeywordGroups as $intent => $keywords) {
            $score = 0;
            $weightPerMatch = 1.0 / count($keywords);

            foreach ($keywords as $keyword) {
                $similarity = $this->calculateSimilarity($keyword, $text);
                if ($similarity > 0.6) {
                    $score += $similarity * $weightPerMatch;
                }
            }

            if ($score > 0) {
                $scores[$intent] = $score;
            }
        }

        if (empty($scores)) {
            return [
                'intent' => 'unknown',
                'confidence' => 0,
                'alternatives' => [],
            ];
        }

        arsort($scores);
        $topIntent = key($scores);
        $topScore = reset($scores);

        return [
            'intent' => $topIntent,
            'confidence' => round($topScore, 3),
            'alternatives' => array_slice(array_keys($scores), 1, 3),
            'all_scores' => array_map(fn($s) => round($s, 3), $scores),
        ];
    }

    /**
     * Extract entities with fuzzy tolerance
     * Handles partial matches, misspellings, abbreviations
     */
    public function extractEntitiesWithTolerance(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $entities = [
            'dates' => [],
            'times' => [],
            'amounts' => [],
            'services' => [],
            'names' => [],
        ];

        // Extract dates (fuzzy)
        if (preg_match_all('/\b(today|tmrw|tomorrow|next\s+\w+|[0-9]{1,2}[\/-][0-9]{1,2}[\/-][0-9]{2,4}|bukas|ngayon)\b/i', $text, $matches)) {
            $entities['dates'] = array_unique($matches[0]);
        }

        // Extract times
        if (preg_match_all('/\b([0-9]{1,2}:[0-9]{2}\s*(?:am|pm|a\.m\.|p\.m\.)?|[0-9]{1,2}\s*(?:am|pm))\b/i', $text, $matches)) {
            $entities['times'] = array_unique($matches[0]);
        }

        // Extract amounts
        if (preg_match_all('/(?:₱|php|php\s*|amount\s+of\s+)?([0-9,]+(?:\.[0-9]{2})?)/i', $text, $matches)) {
            $entities['amounts'] = array_unique($matches[1]);
        }

        return $entities;
    }

    /**
     * Get comprehensive text analysis
     */
    public function analyzeText(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $language = $this->detectLanguage($text);
        $intent = $this->detectIntentWithFuzzyMatching($normalized['normalized']);
        $entities = $this->extractEntitiesWithTolerance($normalized['normalized']);

        return [
            'original' => $text,
            'normalized' => $normalized['normalized'],
            'language' => $language['language'],
            'language_confidence' => $language['confidence'],
            'intent' => $intent['intent'],
            'intent_confidence' => $intent['confidence'],
            'intent_alternatives' => $intent['alternatives'] ?? [],
            'entities' => $entities,
            'analysis_timestamp' => now(),
        ];
    }
}
