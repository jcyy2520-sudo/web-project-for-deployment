<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * LanguageDetectionService
 * 
 * Detects the language of user messages and provides multilingual response capabilities.
 * Supports: English, Filipino/Tagalog, Taglish (mixed), Spanish, and common Asian languages.
 */
class LanguageDetectionService
{
    /**
     * Language-specific keywords and patterns for detection
     */
    private array $languagePatterns = [
        'tl' => [ // Tagalog/Filipino
            'keywords' => [
                'ako', 'ikaw', 'siya', 'kami', 'tayo', 'kayo', 'sila',
                'ang', 'ng', 'sa', 'na', 'nang', 'mga',
                'ko', 'mo', 'niya', 'namin', 'natin', 'ninyo', 'nila',
                'ito', 'iyan', 'iyon', 'dito', 'diyan', 'doon',
                'ano', 'sino', 'kailan', 'saan', 'bakit', 'paano', 'magkano',
                'at', 'o', 'pero', 'kasi', 'dahil', 'kung', 'kapag',
                'gusto', 'kailangan', 'pwede', 'dapat', 'baka', 'siguro',
                'oo', 'hindi', 'opo', 'wala', 'meron', 'mayroon',
                'salamat', 'pasensya', 'paumanhin', 'pakiusap',
                'magandang', 'umaga', 'hapon', 'gabi', 'araw',
                'bukas', 'kahapon', 'ngayon', 'mamaya', 'kanina',
                'appointment', 'serbisyo', 'bayad', 'tanong', 'tulong',
                'po', 'naman', 'ba', 'pala', 'din', 'rin', 'lang', 'lamang',
            ],
            'patterns' => [
                '/\b(pa)?ka(in|on|an)\b/i',
                '/\b(mag|nag|um|in|i|an|hin)\w+/i',
                '/\bpaki\w+/i',
                '/\bmga\s+\w+/i',
            ],
            'weight' => 1.5,
        ],
        'en' => [ // English
            'keywords' => [
                'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
                'have', 'has', 'had', 'do', 'does', 'did',
                'will', 'would', 'could', 'should', 'may', 'might', 'must',
                'i', 'you', 'he', 'she', 'it', 'we', 'they',
                'my', 'your', 'his', 'her', 'its', 'our', 'their',
                'this', 'that', 'these', 'those',
                'what', 'who', 'when', 'where', 'why', 'how', 'which',
                'and', 'or', 'but', 'if', 'because', 'although',
                'want', 'need', 'can', 'please', 'thank', 'thanks',
                'appointment', 'schedule', 'book', 'cancel', 'reschedule',
                'service', 'payment', 'refund', 'price', 'cost',
                'help', 'support', 'question', 'problem', 'issue',
            ],
            'patterns' => [
                '/\b(can|could|would|should)\s+\w+/i',
                '/\b(i\'m|i\'ve|i\'ll|you\'re|we\'re|they\'re)\b/i',
                '/\b(don\'t|doesn\'t|didn\'t|won\'t|wouldn\'t)\b/i',
            ],
            'weight' => 1.0,
        ],
        'es' => [ // Spanish
            'keywords' => [
                'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
                'es', 'son', 'está', 'están', 'ser', 'estar',
                'tengo', 'tiene', 'tienen', 'quiero', 'quiere',
                'yo', 'tú', 'él', 'ella', 'nosotros', 'ellos',
                'mi', 'tu', 'su', 'nuestro',
                'qué', 'quién', 'cuándo', 'dónde', 'por qué', 'cómo',
                'y', 'o', 'pero', 'porque', 'si', 'cuando',
                'gracias', 'por favor', 'ayuda',
                'cita', 'horario', 'reservar', 'cancelar',
            ],
            'patterns' => [
                '/\b(ción|sión|mente)\b/i',
                '/\b(ar|er|ir)$/i',
            ],
            'weight' => 1.2,
        ],
        'zh' => [ // Chinese (Simplified)
            'keywords' => [],
            'patterns' => [
                '/[\x{4e00}-\x{9fff}]/u', // Chinese characters
            ],
            'weight' => 2.0,
        ],
        'ja' => [ // Japanese
            'keywords' => [],
            'patterns' => [
                '/[\x{3040}-\x{309f}]/u', // Hiragana
                '/[\x{30a0}-\x{30ff}]/u', // Katakana
            ],
            'weight' => 2.0,
        ],
        'ko' => [ // Korean
            'keywords' => [],
            'patterns' => [
                '/[\x{ac00}-\x{d7af}]/u', // Korean Hangul
            ],
            'weight' => 2.0,
        ],
    ];

    /**
     * Response templates for different languages
     */
    private array $responseTemplates = [
        'greeting' => [
            'en' => 'Hello! How can I help you today?',
            'tl' => 'Kumusta! Paano kita matutulungan ngayon?',
            'es' => '¡Hola! ¿Cómo puedo ayudarte hoy?',
        ],
        'thank_you' => [
            'en' => 'You\'re welcome! Is there anything else I can help you with?',
            'tl' => 'Walang anuman! May iba pa ba akong maitutulong?',
            'es' => '¡De nada! ¿Hay algo más en lo que pueda ayudarte?',
        ],
        'out_of_scope' => [
            'en' => 'I\'m sorry, this question is outside the scope of my assistance. Please contact support for further help.',
            'tl' => 'Pasensya na, ang tanong na ito ay lampas sa saklaw ng aking tulong. Mangyaring makipag-ugnayan sa suporta para sa karagdagang tulong.',
            'es' => 'Lo siento, esta pregunta está fuera del alcance de mi asistencia. Por favor contacte a soporte para más ayuda.',
        ],
        'rate_limited' => [
            'en' => 'You\'ve reached the message limit for this conversation. Please start a new conversation to continue.',
            'tl' => 'Naabot mo na ang limitasyon ng mensahe para sa usapang ito. Mangyaring magsimula ng bagong usapan upang magpatuloy.',
            'es' => 'Has alcanzado el límite de mensajes para esta conversación. Por favor inicia una nueva conversación para continuar.',
        ],
        'calm_response' => [
            'en' => 'I understand this might be frustrating. Let me help you with that.',
            'tl' => 'Naiintindihan ko na maaaring nakakabahala ito. Hayaan mong tulungan kita.',
            'es' => 'Entiendo que esto puede ser frustrante. Déjame ayudarte con eso.',
        ],
        'appointment_booked' => [
            'en' => 'Your appointment has been booked successfully!',
            'tl' => 'Matagumpay na nai-book ang iyong appointment!',
            'es' => '¡Tu cita ha sido reservada exitosamente!',
        ],
        'appointment_cancelled' => [
            'en' => 'Your appointment has been cancelled.',
            'tl' => 'Nakansela na ang iyong appointment.',
            'es' => 'Tu cita ha sido cancelada.',
        ],
        'error_occurred' => [
            'en' => 'An error occurred. Please try again or contact support.',
            'tl' => 'May naganap na error. Pakisubukan muli o makipag-ugnayan sa suporta.',
            'es' => 'Ocurrió un error. Por favor intenta de nuevo o contacta a soporte.',
        ],
        'profanity_warning' => [
            'en' => 'Please keep the conversation respectful. I\'m here to help you.',
            'tl' => 'Mangyaring panatilihing magalang ang usapan. Nandito ako upang tulungan ka.',
            'es' => 'Por favor mantén la conversación respetuosa. Estoy aquí para ayudarte.',
        ],
    ];

    /**
     * Detect the language of a given text
     */
    public function detect(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $scores = [];
        $totalWords = str_word_count($text);
        
        if ($totalWords === 0) {
            return [
                'language' => 'en',
                'confidence' => 0.5,
                'is_mixed' => false,
                'detected_languages' => ['en'],
            ];
        }

        foreach ($this->languagePatterns as $lang => $config) {
            $score = 0;
            $matchedKeywords = [];
            
            // Check keywords
            foreach ($config['keywords'] as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/iu', $text)) {
                    $score += $config['weight'];
                    $matchedKeywords[] = $keyword;
                }
            }
            
            // Check patterns
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $score += $config['weight'] * 2;
                }
            }
            
            $scores[$lang] = [
                'score' => $score,
                'matched' => $matchedKeywords,
            ];
        }

        // Sort by score
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $topLanguages = array_keys(array_filter($scores, fn($s) => $s['score'] > 0));
        $primaryLang = $topLanguages[0] ?? 'en';
        $primaryScore = $scores[$primaryLang]['score'] ?? 0;
        
        // Calculate confidence
        $maxPossibleScore = $totalWords * 2;
        $confidence = min(1.0, $primaryScore / max(1, $maxPossibleScore));
        
        // Check for mixed language (Taglish)
        $isMixed = false;
        $secondaryLang = $topLanguages[1] ?? null;
        
        if ($secondaryLang && isset($scores[$secondaryLang])) {
            $secondaryScore = $scores[$secondaryLang]['score'];
            $ratio = $secondaryScore / max(1, $primaryScore);
            
            // If secondary language has significant presence (>30%), consider it mixed
            if ($ratio > 0.3) {
                $isMixed = true;
            }
        }

        return [
            'language' => $primaryLang,
            'confidence' => round($confidence, 2),
            'is_mixed' => $isMixed,
            'detected_languages' => array_slice($topLanguages, 0, 3),
            'scores' => array_map(fn($s) => $s['score'], $scores),
        ];
    }

    /**
     * Get a response template in the detected language
     */
    public function getTemplate(string $key, string $language = 'en'): string
    {
        $templates = $this->responseTemplates[$key] ?? [];
        
        // Try exact language match
        if (isset($templates[$language])) {
            return $templates[$language];
        }
        
        // Fallback to English
        return $templates['en'] ?? '';
    }

    /**
     * Translate or adapt a response to the target language
     * For production, this could integrate with a translation API
     */
    public function adaptResponse(string $response, string $targetLanguage, array $context = []): string
    {
        // If English, return as-is
        if ($targetLanguage === 'en') {
            return $response;
        }

        // For Tagalog, add polite markers if appropriate
        if ($targetLanguage === 'tl') {
            // Check if response already has Tagalog markers
            if (!preg_match('/\b(po|ho|opo)\b/i', $response)) {
                // Add polite ending for formal contexts
                $isFormale = $context['formal'] ?? true;
                if ($isFormale && !str_ends_with($response, 'po.') && !str_ends_with($response, 'po!')) {
                    $response = rtrim($response, '.!') . ' po.';
                }
            }
        }

        return $response;
    }

    /**
     * Detect if message contains offensive content in multiple languages
     */
    public function containsOffensiveContent(string $text): array
    {
        $offensivePatterns = [
            'en' => [
                'fuck', 'shit', 'damn', 'ass', 'bitch', 'bastard', 'crap',
                'stupid', 'idiot', 'dumb', 'moron', 'retard',
            ],
            'tl' => [
                'putang', 'puta', 'tangina', 'tanginamo', 'tngna', 'gago',
                'ulol', 'bobo', 'tanga', 'tarantado', 'leche', 'punyeta',
                'bwisit', 'pakshet', 'piste', 'kupal', 'pakyu',
            ],
            'es' => [
                'mierda', 'puta', 'cabron', 'pendejo', 'idiota', 'estupido',
            ],
        ];

        $detected = [];
        $normalized = mb_strtolower($text);

        foreach ($offensivePatterns as $lang => $words) {
            foreach ($words as $word) {
                if (strpos($normalized, $word) !== false) {
                    $detected[] = [
                        'word' => $word,
                        'language' => $lang,
                    ];
                }
            }
        }

        return [
            'contains_offensive' => count($detected) > 0,
            'detected_words' => $detected,
            'severity' => count($detected) > 2 ? 'high' : (count($detected) > 0 ? 'medium' : 'none'),
        ];
    }

    /**
     * Get language name from code
     */
    public function getLanguageName(string $code): string
    {
        $names = [
            'en' => 'English',
            'tl' => 'Filipino/Tagalog',
            'es' => 'Spanish',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
        ];

        return $names[$code] ?? 'Unknown';
    }

    /**
     * Get all supported languages
     */
    public function getSupportedLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'native' => 'English'],
            ['code' => 'tl', 'name' => 'Filipino', 'native' => 'Filipino/Tagalog'],
            ['code' => 'es', 'name' => 'Spanish', 'native' => 'Español'],
        ];
    }
}
