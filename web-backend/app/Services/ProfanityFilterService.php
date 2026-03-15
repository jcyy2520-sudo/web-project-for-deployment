<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ProfanityFilterService
{
    /**
     * List of blocked words/patterns for usernames.
     * Covers slurs, hate speech, profanity, threats, and inappropriate content.
     */
    protected array $blockedPatterns = [
        // Profanity
        'fuck', 'shit', 'ass(?:hole)?', 'bitch', 'damn', 'crap', 'dick', 'cock',
        'pussy', 'bastard', 'cunt', 'piss', 'whore', 'slut', 'wank',
        'bollocks', 'bugger', 'twat', 'tit(?:s)?', 'motherfuck',
        'wtf', 'stfu', 'lmfao',

        // Racial slurs & hate speech
        'nigger', 'nigga', 'n[1i!]gg[ae3]r?', 'chink', 'spic', 'kike', 'gook',
        'wetback', 'beaner', 'cracker', 'honky', 'gringo', 'jap',
        'paki', 'raghead', 'towelhead', 'camel\s*jockey',
        'white\s*suprem', 'naz[i1]', 'kkk', 'aryan',
        'holocaust\s*den', 'genocide',

        // Homophobic / transphobic slurs
        'fag(?:got)?', 'dyke', 'tranny', 'shemale', 'homo',

        // Sexual content
        'porn', 'xxx', 'hentai', 'nude', 'naked', 'sex(?:y)?',
        'dildo', 'vibrator', 'orgasm', 'blowjob', 'handjob',
        'cum(?:shot)?', 'anal', 'penis', 'vagina', 'erotic',
        'milf', 'bdsm', 'fetish',

        // Violence / threats
        'kill\s*(?:you|your|them|myself)', 'murder', 'rape', 'rapist',
        'shoot(?:ing)?', 'bomb(?:ing)?', 'terror(?:ist)?', 'suicide',
        'die\s*(?:you|bitch)', 'stab', 'strangle',

        // Dangerous / harmful
        'pedophile', 'pedo', 'predator', 'child\s*abuse',
        'human\s*traffic', 'drug\s*deal', 'meth',
        'cocaine', 'heroin',

        // Impersonation / admin-related (only exact matches or with numbers)
        'administrator', 'moderator', 'sysadmin', 'superuser',

        // Spam patterns
        'free\s*money', 'click\s*here', 'buy\s*now',
    ];

    /**
     * Check if a username contains inappropriate content.
     *
     * @param string $username
     * @return array{is_clean: bool, reason: string|null}
     */
    public function checkUsername(string $username): array
    {
        $normalized = $this->normalize($username);

        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match('/\b' . $pattern . '\b/iu', $normalized)) {
                Log::warning('Profanity filter blocked username', [
                    'username' => $username,
                    'matched_pattern' => $pattern,
                ]);

                return [
                    'is_clean' => false,
                    'reason' => 'This username contains inappropriate or offensive content and cannot be used.',
                ];
            }

            // Also check without word boundaries for concatenated patterns
            if (preg_match('/' . $pattern . '/iu', $normalized)) {
                Log::warning('Profanity filter blocked username (no boundary)', [
                    'username' => $username,
                    'matched_pattern' => $pattern,
                ]);

                return [
                    'is_clean' => false,
                    'reason' => 'This username contains inappropriate or offensive content and cannot be used.',
                ];
            }
        }

        return ['is_clean' => true, 'reason' => null];
    }

    /**
     * Normalize text by replacing common leet-speak substitutions.
     */
    protected function normalize(string $text): string
    {
        $text = strtolower($text);

        // Replace common leet-speak characters
        $replacements = [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
            '8' => 'b',
            '9' => 'g',
            '@' => 'a',
            '$' => 's',
            '!' => 'i',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        // Remove repeated characters (e.g., "fuuuck" -> "fuck")
        $text = preg_replace('/(.)\1{2,}/', '$1$1', $text);

        // Remove common separator characters used to bypass filters
        $text = preg_replace('/[\s\-_\.]+/', '', $text);

        return $text;
    }
}
