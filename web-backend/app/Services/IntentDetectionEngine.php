<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * IntentDetectionEngine
 * 
 * Implements RULE 2: Intent-Based Knowledge Routing (NO MIXING)
 * 
 * Before answering, this service:
 * 1. Detects the SINGLE primary intent
 * 2. Maps it to the correct knowledge category
 * 3. Ensures no mixing of unrelated knowledge sources
 * 4. If intent is unclear, requests clarification instead of guessing
 */
class IntentDetectionEngine
{
    /**
     * Intent categories - these are the only valid knowledge buckets
     */
    private array $intentCategories = [
        'appointments' => [
            'keywords' => ['appointment', 'booking', 'schedule', 'time slot', 'reserve', 'slot'],
            'subintents' => ['check_status', 'book_new', 'reschedule', 'cancel', 'view_available'],
        ],
        'users' => [
            'keywords' => ['user', 'account', 'profile', 'member', 'customer'],
            'subintents' => ['view_profile', 'update_info', 'list_users'],
        ],
        'roles_and_permissions' => [
            'keywords' => ['role', 'permission', 'access', 'can_i', 'allowed', 'restriction', 'capability'],
            'subintents' => ['check_permission', 'view_role', 'capability_check'],
        ],
        'system_rules' => [
            'keywords' => ['rule', 'policy', 'require', 'must', 'cannot', 'allowed', 'forbidden'],
            'subintents' => ['check_rule', 'understand_policy', 'requirement'],
        ],
        'errors_and_issues' => [
            'keywords' => ['error', 'fail', 'broken', 'problem', 'issue', 'not working', 'bug', 'stuck'],
            'subintents' => ['report_error', 'troubleshoot', 'seek_solution'],
        ],
        'faqs' => [
            'keywords' => ['how', 'what', 'why', 'where', 'when', 'explain', 'help'],
            'subintents' => ['general_question', 'learning', 'information_request'],
        ],
        'policies' => [
            'keywords' => ['policy', 'terms', 'condition', 'agreement', 'rule', 'regulation'],
            'subintents' => ['read_policy', 'understand_terms', 'check_compliance'],
        ],
        'payments' => [
            'keywords' => ['payment', 'pay', 'charge', 'cost', 'fee', 'price', 'transaction', 'paid'],
            'subintents' => ['check_payment_status', 'make_payment', 'view_history'],
        ],
        'refunds' => [
            'keywords' => ['refund', 'return', 'reimburse', 'money back', 'refund request'],
            'subintents' => ['request_refund', 'check_refund_status', 'understand_refund_policy'],
        ],
    ];

    /**
     * Detect the primary intent from user input
     * Returns structured intent info or null if unclear
     */
    public function detect(string $userInput, ?string $previousIntent = null): ?array
    {
        Log::debug('[IntentDetectionEngine] Detecting intent', [
            'input_length' => strlen($userInput),
            'has_context' => $previousIntent !== null,
        ]);

        // Normalize input
        $input = $this->normalizeInput($userInput);

        // Step 1: Extract keywords
        $keywordMatches = $this->matchKeywords($input);

        // Step 2: Determine primary category
        $primaryCategory = $this->determinePrimaryCategory($keywordMatches);

        // Step 3: If multiple categories match, it's ambiguous
        if ($primaryCategory === null) {
            Log::info('[IntentDetectionEngine] Ambiguous intent - no clear primary category');
            return [
                'detected' => false,
                'reason' => 'ambiguous',
                'matched_categories' => array_keys($keywordMatches),
                'confidence' => 0.0,
                'clarification_needed' => true,
            ];
        }

        // Step 4: Extract subintent
        $subintent = $this->determineSubintent($input, $primaryCategory);

        // Step 5: Calculate confidence
        $confidence = $this->calculateConfidence($keywordMatches, $primaryCategory);

        $result = [
            'detected' => true,
            'category' => $primaryCategory,
            'subintent' => $subintent,
            'keywords' => $keywordMatches[$primaryCategory] ?? [],
            'confidence' => $confidence,
            'requires_clarification' => $confidence < 0.5,
            'reasoning' => "Detected intent: {$primaryCategory}" . ($subintent ? " → {$subintent}" : ''),
        ];

        Log::debug('[IntentDetectionEngine] Intent detected', $result);
        return $result;
    }

    /**
     * Normalize user input for intent matching
     */
    private function normalizeInput(string $input): string
    {
        // Convert to lowercase
        $input = strtolower(trim($input));
        
        // Remove common punctuation
        $input = preg_replace('/[?!.,;:\/\-]+/', ' ', $input);
        
        // Remove extra spaces
        $input = preg_replace('/\s+/', ' ', $input);

        return trim($input);
    }

    /**
     * Match keywords from input against all categories
     * Returns array of matched keywords per category
     */
    private function matchKeywords(string $input): array
    {
        $matches = [];

        foreach ($this->intentCategories as $category => $config) {
            $categoryKeywords = $config['keywords'];
            $foundKeywords = [];

            foreach ($categoryKeywords as $keyword) {
                // Exact word match (word boundary)
                if (preg_match('/\b' . preg_quote($keyword) . '\b/i', $input)) {
                    $foundKeywords[] = $keyword;
                }
            }

            if (!empty($foundKeywords)) {
                $matches[$category] = $foundKeywords;
            }
        }

        return $matches;
    }

    /**
     * Determine the single PRIMARY category
     * If multiple categories match equally, return null (ambiguous)
     */
    private function determinePrimaryCategory(array $keywordMatches): ?string
    {
        if (empty($keywordMatches)) {
            return null;
        }

        // If only one category matched, it's primary
        if (count($keywordMatches) === 1) {
            return array_key_first($keywordMatches);
        }

        // Multiple categories matched - find the one with most keywords
        $categoryScores = [];
        foreach ($keywordMatches as $category => $keywords) {
            // Score = number of keywords + keyword specificity
            $categoryScores[$category] = count($keywords);
        }

        // Get max score
        $maxScore = max($categoryScores);
        $primaryCategories = array_filter($categoryScores, fn($score) => $score === $maxScore);

        // If tied, it's ambiguous
        if (count($primaryCategories) > 1) {
            return null;
        }

        return array_key_first($primaryCategories);
    }

    /**
     * Determine the specific subintent within the category
     */
    private function determineSubintent(string $input, string $category): ?string
    {
        $config = $this->intentCategories[$category];
        $subintents = $config['subintents'] ?? [];

        // Map of subintent keywords
        $subintentPatterns = [
            // Appointments subintents
            'check_status' => ['status', 'check', 'see', 'view', 'what is'],
            'book_new' => ['book', 'new', 'schedule', 'make', 'create'],
            'reschedule' => ['reschedule', 'change', 'move', 'different time'],
            'cancel' => ['cancel', 'remove', 'delete'],
            'view_available' => ['available', 'slots', 'times', 'when can'],
            
            // Users subintents
            'view_profile' => ['profile', 'see', 'view', 'show me'],
            'update_info' => ['update', 'change', 'edit', 'modify'],
            'list_users' => ['list', 'show all', 'get all'],
            
            // Error subintents
            'report_error' => ['report', 'found', 'bug', 'issue'],
            'troubleshoot' => ['fix', 'solve', 'help me', 'why not'],
            'seek_solution' => ['how to fix', 'what to do', 'solution'],
            
            // Payment subintents
            'check_payment_status' => ['status', 'paid', 'payment', 'check'],
            'make_payment' => ['pay', 'submit', 'process'],
            'view_history' => ['history', 'transactions', 'past'],
            
            // Refund subintents
            'request_refund' => ['request', 'want', 'need'],
            'check_refund_status' => ['status', 'where', 'check'],
            'understand_refund_policy' => ['how', 'why', 'rules', 'policy'],
        ];

        // Find matching subintent
        foreach ($subintentPatterns as $subintent => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($input, $pattern) !== false) {
                    return $subintent;
                }
            }
        }

        return null;
    }

    /**
     * Calculate confidence of the detection
     * 0.0 = no confidence, 1.0 = perfect confidence
     */
    private function calculateConfidence(array $keywordMatches, string $primaryCategory): float
    {
        if (empty($keywordMatches)) {
            return 0.0;
        }

        // Base confidence on keyword count
        $keywordCount = count($keywordMatches[$primaryCategory] ?? []);
        
        if ($keywordCount >= 3) {
            $confidence = 0.95;
        } elseif ($keywordCount === 2) {
            $confidence = 0.75;
        } elseif ($keywordCount === 1) {
            $confidence = 0.50;
        } else {
            $confidence = 0.0;
        }

        // Reduce confidence if other categories also matched
        $otherMatches = count($keywordMatches) - 1;
        if ($otherMatches > 0) {
            $confidence *= (1.0 - ($otherMatches * 0.1));
        }

        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Check if intent is valid (within scope)
     */
    public function isValidIntent(?array $intent): bool
    {
        if (!$intent || !isset($intent['category'])) {
            return false;
        }

        return isset($this->intentCategories[$intent['category']]);
    }

    /**
     * Get all valid intent categories
     */
    public function getValidCategories(): array
    {
        return array_keys($this->intentCategories);
    }

    /**
     * Get category info
     */
    public function getCategoryInfo(string $category): ?array
    {
        return $this->intentCategories[$category] ?? null;
    }

    /**
     * Suggest clarification message if intent is ambiguous
     */
    public function getSuggestionForAmbiguousIntent(?array $detection, ?string $role = null): string
    {
        $isFil = in_array($role, ['client_fil', 'cashier_fil', 'admin_fil']);

        if (!$detection || $detection['detected'] === false) {
            if ($isFil) {
                return "Hindi ko clear kung ano ang gusto ninyo. Pwede po ba kayong magbigay ng mas specific na tanong?";
            } else {
                return "I'm not entirely clear on what you're asking. Could you be more specific?";
            }
        }

        if (!empty($detection['matched_categories']) && count($detection['matched_categories']) > 1) {
            $categories = implode(', ', $detection['matched_categories']);
            if ($isFil) {
                return "Maaaring puwede itong tungkol sa: {$categories}. Alin po ang mas specific?";
            } else {
                return "This could be related to: {$categories}. Which one specifically?";
            }
        }

        if ($isFil) {
            return "Pakiklarify po kung ano ang hinahanap ninyo.";
        } else {
            return "Could you clarify what you're looking for?";
        }
    }
}
