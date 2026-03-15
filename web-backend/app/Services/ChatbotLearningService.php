<?php

namespace App\Services;

use App\Models\ChatbotAnalytics;
use App\Models\ChatbotFeedback;
use App\Models\ChatbotInteractionLog;
use App\Models\KnowledgeBase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ChatbotLearningService - Real Feedback-Driven Learning System
 *
 * Unlike ChatbotSelfImprovementService (which only generates reports), this service
 * implements actual closed-loop learning that modifies chatbot behavior over time.
 *
 * Learning mechanisms:
 * 1. Feedback-Driven Prompt Refinement - negative feedback patterns produce corrections
 *    that get injected into future system prompts via DynamicSystemPromptService.
 * 2. Knowledge Gap Auto-Detection - low-confidence topics are flagged and can be filled
 *    programmatically into the knowledge_base table.
 * 3. Response Quality Scoring - multi-dimensional heuristic scoring for every response.
 * 4. Adaptive Prompt Weights - role-specific emphasis adjustments derived from feedback.
 * 5. Retrieval Quality Learning - document boost/demote factors based on user feedback.
 *
 * Data flow:
 *   User feedback -> processNewFeedback() -> aggregation -> corrections/boosts/gaps
 *   System prompt builder calls getActiveCorrections() + getPromptAdjustments()
 *   RAG pipeline calls getDocumentBoosts()
 *   Scheduled job calls detectAndFillGaps()
 */
class ChatbotLearningService
{
    // ─── Cache Configuration ────────────────────────────────────────

    /** How long aggregated learning data stays in cache before recomputation. */
    private const CACHE_TTL = 1800; // 30 minutes

    /** Short TTL for data that should refresh more often. */
    private const SHORT_CACHE_TTL = 600; // 10 minutes

    /** Long TTL for stable data (document boosts, corrections). */
    private const LONG_CACHE_TTL = 7200; // 2 hours

    // ─── Learning Thresholds ────────────────────────────────────────

    /** Minimum negative feedback count on a topic before generating a correction. */
    private const CORRECTION_THRESHOLD = 3;

    /** Minimum low-confidence hits on a topic before flagging as a knowledge gap. */
    private const GAP_DETECTION_THRESHOLD = 5;

    /** Confidence score below which a response is considered "low-confidence". */
    private const LOW_CONFIDENCE_CUTOFF = 0.5;

    /** Lookback window for feedback aggregation (days). */
    private const FEEDBACK_LOOKBACK_DAYS = 30;

    /** Lookback window for gap detection (days). */
    private const GAP_LOOKBACK_DAYS = 14;

    /** Maximum number of active corrections kept in the prompt at once. */
    private const MAX_ACTIVE_CORRECTIONS = 15;

    /** Maximum boost/demote factor for documents. */
    private const MAX_BOOST_FACTOR = 2.0;
    private const MIN_BOOST_FACTOR = 0.2;

    // ─── Cache Keys ─────────────────────────────────────────────────

    private const CACHE_KEY_CORRECTIONS = 'chatbot_learning_active_corrections';
    private const CACHE_KEY_GAPS = 'chatbot_learning_detected_gaps';
    private const CACHE_KEY_DOC_BOOSTS = 'chatbot_learning_doc_boosts';
    private const CACHE_KEY_PROMPT_ADJ_PREFIX = 'chatbot_learning_prompt_adj_';
    private const CACHE_KEY_QUALITY_HISTORY = 'chatbot_learning_quality_history';
    private const CACHE_KEY_TOPIC_FEEDBACK_PREFIX = 'chatbot_learning_topic_fb_';

    // ─── Table existence flags (lazy-checked) ───────────────────────

    private ?bool $hasFeedbackTable = null;
    private ?bool $hasInteractionTable = null;
    private ?bool $hasAnalyticsTable = null;
    private ?bool $hasKnowledgeTable = null;

    // =========================================================================
    //  1. FEEDBACK-DRIVEN PROMPT REFINEMENT
    // =========================================================================

    /**
     * Get all active prompt corrections to inject into the system prompt.
     *
     * These are specific instructions derived from accumulated negative feedback.
     * The DynamicSystemPromptService should call this and include the results
     * in the "LEARNED PATTERNS" section of the prompt.
     *
     * @return array<int, array{topic: string, correction_text: string, confidence: float, source_feedback_count: int, created_at: string}>
     */
    public function getActiveCorrections(): array
    {
        if (!$this->tableExists('chatbot_feedback')) {
            return [];
        }

        try {
            return Cache::remember(self::CACHE_KEY_CORRECTIONS, self::LONG_CACHE_TTL, function () {
                return $this->buildActiveCorrections();
            });
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService::getActiveCorrections failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Process new feedback and update the learning state.
     *
     * Call this whenever a user submits feedback. It updates aggregated
     * correction data, document boosts, and quality history in real time.
     *
     * @param int         $messageId      The ChatbotInteractionLog ID or interaction_id reference
     * @param bool        $isHelpful      Whether the user found the response helpful
     * @param string|null $category       Feedback category (wrong_info, unclear, incomplete, outdated, etc.)
     * @param string|null $correctionText Free-form correction text from the user
     */
    public function processNewFeedback(
        int     $messageId,
        bool    $isHelpful,
        ?string $category = null,
        ?string $correctionText = null
    ): void {
        try {
            // Find the interaction to extract topic/context
            $interaction = $this->findInteraction($messageId);

            if (!$interaction) {
                Log::debug('ChatbotLearningService: interaction not found', ['messageId' => $messageId]);
                return;
            }

            $topic = $interaction->intent_detected ?? $this->extractTopicFromMessage($interaction->user_message);

            // --- Update topic-level feedback aggregation ---
            $this->updateTopicFeedback($topic, $isHelpful, $category, $correctionText);

            // --- Update document relevance boosts from context_sources ---
            if (!empty($interaction->context_sources)) {
                $sources = is_array($interaction->context_sources)
                    ? $interaction->context_sources
                    : json_decode($interaction->context_sources, true);

                if (is_array($sources)) {
                    foreach ($sources as $source) {
                        $docId = $source['id'] ?? $source['document_id'] ?? null;
                        if ($docId) {
                            $this->adjustDocumentRelevance((int) $docId, $isHelpful);
                        }
                    }
                }
            }

            // --- Record quality score for trend tracking ---
            $qualityScore = $this->scoreResponse(
                $interaction->user_message,
                $interaction->bot_response,
                [
                    'confidence'     => (float) ($interaction->confidence_score ?? 0),
                    'was_fallback'   => (bool) ($interaction->was_fallback ?? false),
                    'processing_ms'  => (int) ($interaction->processing_time_ms ?? 0),
                    'user_feedback'  => $isHelpful,
                ]
            );
            $this->recordQualityScore($topic, $qualityScore);

            // --- Bust caches that depend on aggregated feedback ---
            Cache::forget(self::CACHE_KEY_CORRECTIONS);
            Cache::forget(self::CACHE_KEY_GAPS);

            // Bust role-specific prompt adjustment caches
            foreach (['admin', 'cashier', 'client', 'guest'] as $role) {
                Cache::forget(self::CACHE_KEY_PROMPT_ADJ_PREFIX . $role);
            }

        } catch (\Exception $e) {
            Log::error('ChatbotLearningService::processNewFeedback failed', [
                'messageId' => $messageId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the active corrections list from aggregated feedback data.
     *
     * @return array
     */
    private function buildActiveCorrections(): array
    {
        $corrections = [];
        $since = Carbon::now()->subDays(self::FEEDBACK_LOOKBACK_DAYS);

        // ── Strategy 1: Category-based corrections from negative feedback ──
        // Group negative feedback by topic (intent_detected) and category
        $negativeFeedback = $this->queryNegativeFeedbackByTopic($since);

        foreach ($negativeFeedback as $topic => $categories) {
            foreach ($categories as $category => $items) {
                $count = count($items);
                if ($count < self::CORRECTION_THRESHOLD) {
                    continue;
                }

                $correctionText = $this->synthesizeCorrectionFromFeedback($topic, $category, $items);
                if (empty($correctionText)) {
                    continue;
                }

                // Confidence is based on how many feedback items agree
                $confidence = min(1.0, $count / (self::CORRECTION_THRESHOLD * 3));

                $corrections[] = [
                    'topic'                 => $topic,
                    'correction_text'       => $correctionText,
                    'confidence'            => round($confidence, 2),
                    'source_feedback_count' => $count,
                    'category'              => $category,
                    'created_at'            => now()->toIso8601String(),
                ];
            }
        }

        // ── Strategy 2: User-provided explicit corrections ──
        $explicitCorrections = $this->queryExplicitCorrections($since);

        foreach ($explicitCorrections as $group) {
            if ($group['count'] < 2) {
                continue; // At least 2 users must agree on a correction direction
            }

            $corrections[] = [
                'topic'                 => $group['topic'],
                'correction_text'       => $group['correction'],
                'confidence'            => round(min(1.0, $group['count'] / 5), 2),
                'source_feedback_count' => $group['count'],
                'category'              => 'user_correction',
                'created_at'            => now()->toIso8601String(),
            ];
        }

        // Sort by confidence descending, limit to MAX_ACTIVE_CORRECTIONS
        usort($corrections, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($corrections, 0, self::MAX_ACTIVE_CORRECTIONS);
    }

    /**
     * Query negative feedback grouped by topic and category.
     *
     * @return array<string, array<string, array>> topic => [category => [feedback items]]
     */
    private function queryNegativeFeedbackByTopic(Carbon $since): array
    {
        $grouped = [];

        try {
            $feedbackRows = DB::table('chatbot_feedback as f')
                ->join('chatbot_interaction_logs as il', 'f.interaction_id', '=', 'il.interaction_id')
                ->where('f.submitted_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('f.is_helpful', false)
                      ->orWhere('f.is_correct', false)
                      ->orWhere('f.rating', '<=', 2);
                })
                ->whereNotNull('f.feedback_category')
                ->select([
                    'il.intent_detected',
                    'il.user_message',
                    'il.bot_response',
                    'f.feedback_category',
                    'f.correction_text',
                    'f.expected_response',
                    'f.comments',
                ])
                ->limit(500)
                ->get();

            foreach ($feedbackRows as $row) {
                $topic = $row->intent_detected ?: $this->extractTopicFromMessage($row->user_message);
                $category = $row->feedback_category;

                $grouped[$topic][$category][] = [
                    'user_message'    => $row->user_message,
                    'bot_response'    => mb_substr($row->bot_response ?? '', 0, 300),
                    'correction_text' => $row->correction_text,
                    'expected'        => $row->expected_response,
                    'comments'        => $row->comments,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService: failed to query negative feedback', [
                'error' => $e->getMessage(),
            ]);
        }

        return $grouped;
    }

    /**
     * Query explicit correction texts from feedback, grouped by topic.
     *
     * @return array<int, array{topic: string, correction: string, count: int}>
     */
    private function queryExplicitCorrections(Carbon $since): array
    {
        $results = [];

        try {
            $rows = DB::table('chatbot_feedback as f')
                ->join('chatbot_interaction_logs as il', 'f.interaction_id', '=', 'il.interaction_id')
                ->where('f.submitted_at', '>=', $since)
                ->whereNotNull('f.correction_text')
                ->where('f.correction_text', '!=', '')
                ->where('f.correction_applied', false)
                ->select([
                    'il.intent_detected',
                    'il.user_message',
                    'f.correction_text',
                ])
                ->limit(200)
                ->get();

            // Group by topic, then summarize the corrections
            $byTopic = [];
            foreach ($rows as $row) {
                $topic = $row->intent_detected ?: $this->extractTopicFromMessage($row->user_message);
                $byTopic[$topic][] = $row->correction_text;
            }

            foreach ($byTopic as $topic => $correctionTexts) {
                // Take the most common/representative correction
                $representative = $this->pickRepresentativeCorrection($correctionTexts);
                $results[] = [
                    'topic'      => $topic,
                    'correction' => $representative,
                    'count'      => count($correctionTexts),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService: failed to query explicit corrections', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Synthesize a corrective instruction from accumulated negative feedback.
     */
    private function synthesizeCorrectionFromFeedback(string $topic, string $category, array $items): string
    {
        $count = count($items);

        // If we have user-provided corrections, use them
        $userCorrections = array_filter(
            array_column($items, 'correction_text'),
            fn($c) => !empty($c)
        );

        if (!empty($userCorrections)) {
            $representative = $this->pickRepresentativeCorrection($userCorrections);
            return "When answering about '{$topic}': {$representative} (based on {$count} user corrections)";
        }

        // Otherwise generate a category-based instruction
        return match ($category) {
            'wrong_info'  => "When answering about '{$topic}', double-check factual accuracy. Users have reported incorrect information {$count} times. Prefer verified knowledge base entries over generated content.",
            'unclear'     => "When answering about '{$topic}', use simpler language and provide step-by-step explanations. Users reported unclear responses {$count} times.",
            'incomplete'  => "When answering about '{$topic}', provide more comprehensive answers including all relevant details, prerequisites, and next steps. Users reported incomplete responses {$count} times.",
            'outdated'    => "When answering about '{$topic}', ensure information is current. Users have flagged outdated information {$count} times. Prioritize recently updated knowledge base entries.",
            'off_topic'   => "When users ask about '{$topic}', stay focused on their specific question. Users reported off-topic responses {$count} times.",
            'too_long'    => "When answering about '{$topic}', keep responses concise and to the point. Users found previous responses too lengthy ({$count} reports).",
            'too_short'   => "When answering about '{$topic}', provide more detail and context. Users found previous responses insufficient ({$count} reports).",
            'rude'        => "When addressing '{$topic}', use an especially warm, patient, and professional tone. Users reported tone issues {$count} times.",
            default       => "When answering about '{$topic}', pay extra attention to quality. This topic has received {$count} negative feedback reports in the '{$category}' category.",
        };
    }

    /**
     * Pick the most representative correction from a list of user-provided texts.
     *
     * Uses a simple frequency heuristic: the correction whose key phrases appear
     * most across all corrections is selected.
     */
    private function pickRepresentativeCorrection(array $corrections): string
    {
        if (count($corrections) === 1) {
            return mb_substr(trim($corrections[0]), 0, 500);
        }

        // Score each correction by how much it overlaps with others
        $scored = [];
        foreach ($corrections as $i => $correction) {
            $words = array_unique(
                array_filter(
                    explode(' ', mb_strtolower(trim($correction))),
                    fn($w) => mb_strlen($w) > 3
                )
            );

            $overlapScore = 0;
            foreach ($corrections as $j => $other) {
                if ($i === $j) {
                    continue;
                }
                $otherLower = mb_strtolower($other);
                foreach ($words as $word) {
                    if (str_contains($otherLower, $word)) {
                        $overlapScore++;
                    }
                }
            }
            $scored[$i] = $overlapScore;
        }

        arsort($scored);
        $bestIndex = array_key_first($scored);

        return mb_substr(trim($corrections[$bestIndex]), 0, 500);
    }

    /**
     * Update the per-topic feedback aggregation cache.
     */
    private function updateTopicFeedback(string $topic, bool $isHelpful, ?string $category, ?string $correctionText): void
    {
        $cacheKey = self::CACHE_KEY_TOPIC_FEEDBACK_PREFIX . md5($topic);

        try {
            $data = Cache::get($cacheKey, [
                'topic'           => $topic,
                'helpful_count'   => 0,
                'unhelpful_count' => 0,
                'categories'      => [],
                'corrections'     => [],
                'last_updated'    => null,
            ]);

            if ($isHelpful) {
                $data['helpful_count']++;
            } else {
                $data['unhelpful_count']++;
            }

            if ($category) {
                $data['categories'][$category] = ($data['categories'][$category] ?? 0) + 1;
            }

            if (!empty($correctionText)) {
                // Keep the last 10 corrections per topic
                $data['corrections'][] = mb_substr($correctionText, 0, 500);
                $data['corrections'] = array_slice($data['corrections'], -10);
            }

            $data['last_updated'] = now()->toIso8601String();

            Cache::put($cacheKey, $data, now()->addDays(self::FEEDBACK_LOOKBACK_DAYS));
        } catch (\Exception $e) {
            Log::debug('ChatbotLearningService: failed to update topic feedback', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  2. KNOWLEDGE GAP AUTO-DETECTION AND FILLING
    // =========================================================================

    /**
     * Detect topics that consistently receive low-confidence responses
     * and recommend actions to fill the gaps.
     *
     * @return array<int, array{
     *   topic: string,
     *   hit_count: int,
     *   avg_confidence: float,
     *   sample_queries: array,
     *   priority: string,
     *   recommended_action: string,
     *   has_kb_coverage: bool
     * }>
     */
    public function detectAndFillGaps(): array
    {
        if (!$this->tableExists('chatbot_interaction_logs')) {
            return [];
        }

        try {
            return Cache::remember(self::CACHE_KEY_GAPS, self::CACHE_TTL, function () {
                return $this->buildGapDetectionReport();
            });
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService::detectAndFillGaps failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Programmatically add a new entry to the knowledge base.
     *
     * Use this to fill detected gaps automatically or via an admin action.
     *
     * @param string $topic   Human-readable topic name (used as title)
     * @param string $content The knowledge content (will be stored as a single chunk)
     * @param string $source  Origin of this knowledge (e.g., "learning_service", "admin_manual")
     * @return bool True if added successfully
     */
    public function addKnowledgeEntry(string $topic, string $content, string $source = 'learning_service'): bool
    {
        if (!$this->tableExists('knowledge_base')) {
            Log::warning('ChatbotLearningService: knowledge_base table does not exist');
            return false;
        }

        try {
            // Check for duplicates - avoid adding content that is too similar to existing entries
            $existing = KnowledgeBase::where('title', $topic)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                Log::info('ChatbotLearningService: knowledge entry already exists, updating', [
                    'topic' => $topic,
                ]);

                $existing->update([
                    'content_chunk' => $content,
                    'metadata'      => array_merge($existing->metadata ?? [], [
                        'source'     => $source,
                        'updated_by' => 'learning_service',
                        'updated_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Clear related caches
                $this->invalidateKnowledgeCaches();

                return true;
            }

            KnowledgeBase::create([
                'title'         => $topic,
                'content_chunk' => $content,
                'chunk_index'   => 0,
                'category'      => 'learned',
                'document_type' => 'auto_generated',
                'embedding'     => '[]', // Embeddings should be generated separately
                'metadata'      => [
                    'source'     => $source,
                    'created_by' => 'learning_service',
                    'auto_generated' => true,
                    'created_at' => now()->toIso8601String(),
                ],
                'is_active'     => true,
            ]);

            Log::info('ChatbotLearningService: added knowledge entry', [
                'topic'  => $topic,
                'source' => $source,
            ]);

            // Clear related caches
            $this->invalidateKnowledgeCaches();

            return true;

        } catch (\Exception $e) {
            Log::error('ChatbotLearningService::addKnowledgeEntry failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build the gap detection report from interaction log data.
     *
     * @return array
     */
    private function buildGapDetectionReport(): array
    {
        $since = Carbon::now()->subDays(self::GAP_LOOKBACK_DAYS);
        $gaps = [];

        try {
            // Find topics with consistently low confidence
            $lowConfTopics = DB::table('chatbot_interaction_logs')
                ->where('created_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('confidence_score', '<', self::LOW_CONFIDENCE_CUTOFF)
                      ->orWhere('was_fallback', true);
                })
                ->whereNotNull('intent_detected')
                ->select([
                    'intent_detected',
                    DB::raw('COUNT(*) as hit_count'),
                    DB::raw('AVG(confidence_score) as avg_confidence'),
                ])
                ->groupBy('intent_detected')
                ->having('hit_count', '>=', self::GAP_DETECTION_THRESHOLD)
                ->orderByDesc('hit_count')
                ->limit(20)
                ->get();

            foreach ($lowConfTopics as $topic) {
                // Get sample queries for this gap
                $samples = DB::table('chatbot_interaction_logs')
                    ->where('created_at', '>=', $since)
                    ->where('intent_detected', $topic->intent_detected)
                    ->where(function ($q) {
                        $q->where('confidence_score', '<', self::LOW_CONFIDENCE_CUTOFF)
                          ->orWhere('was_fallback', true);
                    })
                    ->pluck('user_message')
                    ->unique()
                    ->take(5)
                    ->values()
                    ->toArray();

                // Check if there is already knowledge base coverage
                $hasKbCoverage = $this->topicHasKnowledgeCoverage($topic->intent_detected);

                $priority = $this->calculateGapPriority(
                    $topic->hit_count,
                    (float) ($topic->avg_confidence ?? 0),
                    $hasKbCoverage
                );

                $gaps[] = [
                    'topic'              => $topic->intent_detected,
                    'hit_count'          => (int) $topic->hit_count,
                    'avg_confidence'     => round((float) ($topic->avg_confidence ?? 0), 3),
                    'sample_queries'     => $samples,
                    'priority'           => $priority,
                    'recommended_action' => $this->recommendGapAction($topic->intent_detected, $hasKbCoverage, $priority),
                    'has_kb_coverage'    => $hasKbCoverage,
                ];
            }

            // Also check for "unknown" intent patterns (queries that could not be classified)
            $unknownCount = DB::table('chatbot_interaction_logs')
                ->where('created_at', '>=', $since)
                ->where(function ($q) {
                    $q->whereNull('intent_detected')
                      ->orWhere('intent_detected', 'unknown')
                      ->orWhere('intent_detected', '');
                })
                ->where(function ($q) {
                    $q->where('confidence_score', '<', self::LOW_CONFIDENCE_CUTOFF)
                      ->orWhere('was_fallback', true);
                })
                ->count();

            if ($unknownCount >= self::GAP_DETECTION_THRESHOLD) {
                $unknownSamples = DB::table('chatbot_interaction_logs')
                    ->where('created_at', '>=', $since)
                    ->where(function ($q) {
                        $q->whereNull('intent_detected')
                          ->orWhere('intent_detected', 'unknown')
                          ->orWhere('intent_detected', '');
                    })
                    ->where('was_fallback', true)
                    ->pluck('user_message')
                    ->unique()
                    ->take(10)
                    ->values()
                    ->toArray();

                $gaps[] = [
                    'topic'              => '_unclassified',
                    'hit_count'          => $unknownCount,
                    'avg_confidence'     => 0.0,
                    'sample_queries'     => $unknownSamples,
                    'priority'           => 'high',
                    'recommended_action' => "Review the {$unknownCount} unclassified queries. Many may represent new topics that need intent definitions and knowledge base entries.",
                    'has_kb_coverage'    => false,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService: gap detection query failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Sort by priority (high > medium > low)
        usort($gaps, function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($order[$a['priority']] ?? 3) <=> ($order[$b['priority']] ?? 3);
        });

        return $gaps;
    }

    /**
     * Check whether a topic already has knowledge base entries.
     */
    private function topicHasKnowledgeCoverage(string $topic): bool
    {
        if (!$this->tableExists('knowledge_base')) {
            return false;
        }

        try {
            return KnowledgeBase::where('is_active', true)
                ->where(function ($q) use ($topic) {
                    $q->where('title', 'LIKE', "%{$topic}%")
                      ->orWhere('category', $topic)
                      ->orWhere('content_chunk', 'LIKE', "%{$topic}%");
                })
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Calculate gap priority based on frequency, confidence, and existing coverage.
     */
    private function calculateGapPriority(int $hitCount, float $avgConfidence, bool $hasKbCoverage): string
    {
        $score = 0;

        // More hits = higher priority
        if ($hitCount >= 20) {
            $score += 3;
        } elseif ($hitCount >= 10) {
            $score += 2;
        } else {
            $score += 1;
        }

        // Lower confidence = higher priority
        if ($avgConfidence < 0.2) {
            $score += 3;
        } elseif ($avgConfidence < 0.35) {
            $score += 2;
        } else {
            $score += 1;
        }

        // No KB coverage = higher priority
        if (!$hasKbCoverage) {
            $score += 2;
        }

        if ($score >= 6) {
            return 'high';
        } elseif ($score >= 4) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Generate a recommended action for a detected gap.
     */
    private function recommendGapAction(string $topic, bool $hasKbCoverage, string $priority): string
    {
        if (!$hasKbCoverage) {
            return "No knowledge base coverage exists for '{$topic}'. Create entries covering this topic with accurate, up-to-date information.";
        }

        if ($priority === 'high') {
            return "Knowledge base entries exist for '{$topic}' but are insufficient. Review and expand existing content, add more detail, or update outdated information.";
        }

        return "Monitor '{$topic}' — existing coverage may need refinement. Check if the retrieval pipeline is finding the right documents.";
    }

    // =========================================================================
    //  3. RESPONSE QUALITY SCORING
    // =========================================================================

    /**
     * Score a chatbot response on multiple quality dimensions.
     *
     * Returns a composite score from 0.0 (worst) to 1.0 (best) using heuristics:
     * - Relevance: response length proportional to query complexity
     * - Completeness: presence of structured data (lists, steps, links)
     * - Accuracy: confidence score and whether it was a fallback
     * - User signal: direct user feedback if available
     *
     * @param string $query    The user's original query
     * @param string $response The chatbot's response
     * @param array  $context  Additional context: confidence, was_fallback, processing_ms, user_feedback
     * @return float Score from 0.0 to 1.0
     */
    public function scoreResponse(string $query, string $response, array $context = []): float
    {
        try {
            $scores = [];
            $weights = [];

            // ── Dimension 1: Relevance (response length vs query complexity) ──
            $relevanceScore = $this->scoreRelevance($query, $response);
            $scores[] = $relevanceScore;
            $weights[] = 0.20;

            // ── Dimension 2: Completeness (structure, detail) ──
            $completenessScore = $this->scoreCompleteness($response);
            $scores[] = $completenessScore;
            $weights[] = 0.20;

            // ── Dimension 3: Accuracy (confidence, fallback status) ──
            $accuracyScore = $this->scoreAccuracy($context);
            $scores[] = $accuracyScore;
            $weights[] = 0.25;

            // ── Dimension 4: User feedback signal ──
            if (isset($context['user_feedback'])) {
                $feedbackScore = $context['user_feedback'] ? 1.0 : 0.0;
                $scores[] = $feedbackScore;
                $weights[] = 0.35; // User feedback is the strongest signal
            } else {
                // Redistribute weight when no feedback
                $weights[0] = 0.25; // relevance
                $weights[1] = 0.30; // completeness
                $weights[2] = 0.45; // accuracy
            }

            // Weighted average
            $totalWeight = array_sum($weights);
            $composite = 0.0;
            foreach ($scores as $i => $score) {
                $composite += $score * $weights[$i];
            }

            return round($composite / max($totalWeight, 0.01), 3);

        } catch (\Exception $e) {
            Log::debug('ChatbotLearningService::scoreResponse failed', [
                'error' => $e->getMessage(),
            ]);
            return 0.5; // Neutral default
        }
    }

    /**
     * Score relevance: is the response proportionally sized for the query?
     */
    private function scoreRelevance(string $query, string $response): float
    {
        $queryLen = mb_strlen(trim($query));
        $responseLen = mb_strlen(trim($response));

        if ($responseLen === 0) {
            return 0.0; // Empty response is never relevant
        }

        // A simple/short query should get a concise response
        // A complex/long query deserves a more detailed response
        $queryWordCount = str_word_count($query);
        $responseWordCount = str_word_count($response);

        // Expected ratio: response should be 2-10x the query length
        if ($queryWordCount <= 5) {
            // Short query: response between 20-200 words is ideal
            if ($responseWordCount >= 10 && $responseWordCount <= 250) {
                return 1.0;
            } elseif ($responseWordCount < 10) {
                return 0.3;
            } else {
                // Penalise overly long responses to simple queries
                return max(0.3, 1.0 - (($responseWordCount - 250) / 500));
            }
        }

        // Longer query: ratio-based scoring
        $ratio = $responseWordCount / max($queryWordCount, 1);
        if ($ratio >= 2 && $ratio <= 15) {
            return 1.0;
        } elseif ($ratio < 1) {
            return 0.4; // Response shorter than query
        } elseif ($ratio > 15) {
            return max(0.4, 1.0 - (($ratio - 15) / 30));
        }

        return 0.7; // Reasonable default
    }

    /**
     * Score completeness: does the response have structure and detail?
     */
    private function scoreCompleteness(string $response): float
    {
        $score = 0.5; // Start at baseline

        // Presence of structured elements
        $hasNumberedList = (bool) preg_match('/\d+[\.\)]\s/', $response);
        $hasBulletPoints = str_contains($response, '- ') || str_contains($response, '* ');
        $hasMultipleSentences = substr_count($response, '.') >= 2;
        $hasParagraphs = substr_count($response, "\n") >= 1;
        $hasSpecificData = (bool) preg_match('/\b\d+\b/', $response); // Contains numbers/data
        $hasBoldOrEmphasis = str_contains($response, '**') || str_contains($response, '__');

        if ($hasNumberedList) {
            $score += 0.10;
        }
        if ($hasBulletPoints) {
            $score += 0.08;
        }
        if ($hasMultipleSentences) {
            $score += 0.10;
        }
        if ($hasParagraphs) {
            $score += 0.05;
        }
        if ($hasSpecificData) {
            $score += 0.07;
        }
        if ($hasBoldOrEmphasis) {
            $score += 0.05;
        }

        // Penalise very short responses
        $wordCount = str_word_count($response);
        if ($wordCount < 10) {
            $score -= 0.20;
        }

        // Penalise responses that are only generic/filler
        $genericPhrases = [
            'i\'m sorry', 'i cannot', 'i don\'t know', 'i\'m not sure',
            'please contact', 'unfortunately',
        ];
        $lowerResponse = mb_strtolower($response);
        $genericCount = 0;
        foreach ($genericPhrases as $phrase) {
            if (str_contains($lowerResponse, $phrase)) {
                $genericCount++;
            }
        }
        if ($genericCount >= 2) {
            $score -= 0.15;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Score accuracy based on model confidence and fallback status.
     */
    private function scoreAccuracy(array $context): float
    {
        $confidence = (float) ($context['confidence'] ?? 0.5);
        $wasFallback = (bool) ($context['was_fallback'] ?? false);
        $processingMs = (int) ($context['processing_ms'] ?? 0);

        $score = $confidence; // Start with the model's own confidence

        // Fallback responses score lower
        if ($wasFallback) {
            $score *= 0.4;
        }

        // Very fast responses might indicate cached/templated (not necessarily bad)
        // Very slow might indicate complex processing (not necessarily bad either)
        // Extreme slowness might indicate timeout/partial response
        if ($processingMs > 15000) {
            $score *= 0.8; // Might be a timeout/degraded response
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Record a quality score to the rolling history for trend detection.
     */
    private function recordQualityScore(string $topic, float $score): void
    {
        try {
            $history = Cache::get(self::CACHE_KEY_QUALITY_HISTORY, []);

            $history[] = [
                'topic'     => $topic,
                'score'     => $score,
                'timestamp' => now()->toIso8601String(),
            ];

            // Keep last 1000 entries
            if (count($history) > 1000) {
                $history = array_slice($history, -1000);
            }

            Cache::put(self::CACHE_KEY_QUALITY_HISTORY, $history, now()->addDays(7));
        } catch (\Exception $e) {
            // Non-critical, just log debug
            Log::debug('ChatbotLearningService: failed to record quality score', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get quality score trends over time.
     *
     * Useful for detecting regressions (e.g., average score dropping week-over-week).
     *
     * @param int $days Number of days to look back
     * @return array{current_avg: float, previous_avg: float, trend: string, by_topic: array}
     */
    public function getQualityTrends(int $days = 7): array
    {
        try {
            $history = Cache::get(self::CACHE_KEY_QUALITY_HISTORY, []);

            if (empty($history)) {
                return [
                    'current_avg'  => 0.0,
                    'previous_avg' => 0.0,
                    'trend'        => 'no_data',
                    'by_topic'     => [],
                ];
            }

            $now = Carbon::now();
            $currentPeriod = $now->copy()->subDays($days);
            $previousPeriod = $currentPeriod->copy()->subDays($days);

            $currentScores = [];
            $previousScores = [];
            $byTopic = [];

            foreach ($history as $entry) {
                $entryTime = Carbon::parse($entry['timestamp']);
                $topic = $entry['topic'] ?? 'unknown';
                $score = $entry['score'] ?? 0;

                if ($entryTime->gte($currentPeriod)) {
                    $currentScores[] = $score;
                    $byTopic[$topic][] = $score;
                } elseif ($entryTime->gte($previousPeriod)) {
                    $previousScores[] = $score;
                }
            }

            $currentAvg = !empty($currentScores) ? array_sum($currentScores) / count($currentScores) : 0.0;
            $previousAvg = !empty($previousScores) ? array_sum($previousScores) / count($previousScores) : 0.0;

            // Calculate per-topic averages
            $topicAvgs = [];
            foreach ($byTopic as $topic => $scores) {
                $topicAvgs[$topic] = round(array_sum($scores) / count($scores), 3);
            }
            arsort($topicAvgs);

            $trend = 'stable';
            if ($previousAvg > 0) {
                $change = $currentAvg - $previousAvg;
                if ($change > 0.05) {
                    $trend = 'improving';
                } elseif ($change < -0.05) {
                    $trend = 'declining';
                }
            }

            return [
                'current_avg'  => round($currentAvg, 3),
                'previous_avg' => round($previousAvg, 3),
                'trend'        => $trend,
                'sample_size'  => count($currentScores),
                'by_topic'     => array_slice($topicAvgs, 0, 20, true),
            ];
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService::getQualityTrends failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'current_avg'  => 0.0,
                'previous_avg' => 0.0,
                'trend'        => 'error',
                'by_topic'     => [],
            ];
        }
    }

    // =========================================================================
    //  4. ADAPTIVE PROMPT WEIGHTS
    // =========================================================================

    /**
     * Get prompt adjustments for a given role.
     *
     * Returns a set of directives that the prompt builder should inject
     * to emphasize or de-emphasize certain behaviors based on feedback patterns.
     *
     * @param string $role User role (admin, cashier, client, guest)
     * @return array{emphasis: array<string, float>, directives: string[]}
     */
    public function getPromptAdjustments(string $role): array
    {
        try {
            return Cache::remember(
                self::CACHE_KEY_PROMPT_ADJ_PREFIX . $role,
                self::CACHE_TTL,
                function () use ($role) {
                    return $this->buildPromptAdjustments($role);
                }
            );
        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService::getPromptAdjustments failed', [
                'role'  => $role,
                'error' => $e->getMessage(),
            ]);
            return ['emphasis' => [], 'directives' => []];
        }
    }

    /**
     * Build prompt adjustments from feedback data for a specific role.
     */
    private function buildPromptAdjustments(string $role): array
    {
        $emphasis = [
            'accuracy'        => 1.0, // Baseline weight
            'completeness'    => 1.0,
            'conciseness'     => 1.0,
            'tone'            => 1.0,
            'step_by_step'    => 1.0,
            'data_specificity' => 1.0,
        ];

        $directives = [];

        if (!$this->tableExists('chatbot_feedback') || !$this->tableExists('chatbot_analytics')) {
            return ['emphasis' => $emphasis, 'directives' => $directives];
        }

        $since = Carbon::now()->subDays(self::FEEDBACK_LOOKBACK_DAYS);

        try {
            // Get feedback category counts for this role
            $categoryCounts = DB::table('chatbot_feedback as f')
                ->join('chatbot_analytics as a', function ($join) {
                    $join->on('f.user_id', '=', 'a.user_id')
                         ->on(DB::raw('DATE(f.submitted_at)'), '=', DB::raw('DATE(a.created_at)'));
                })
                ->where('a.user_role', $role)
                ->where('f.submitted_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('f.is_helpful', false)
                      ->orWhere('f.rating', '<=', 2);
                })
                ->whereNotNull('f.feedback_category')
                ->select('f.feedback_category', DB::raw('COUNT(*) as cnt'))
                ->groupBy('f.feedback_category')
                ->pluck('cnt', 'f.feedback_category')
                ->toArray();
        } catch (\Exception $e) {
            // If the join fails (different table structures), try a simpler approach
            try {
                $categoryCounts = DB::table('chatbot_feedback')
                    ->where('submitted_at', '>=', $since)
                    ->where(function ($q) {
                        $q->where('is_helpful', false)
                          ->orWhere('rating', '<=', 2);
                    })
                    ->whereNotNull('feedback_category')
                    ->select('feedback_category', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('feedback_category')
                    ->pluck('cnt', 'feedback_category')
                    ->toArray();
            } catch (\Exception $e2) {
                $categoryCounts = [];
            }
        }

        $totalNegative = array_sum($categoryCounts);

        if ($totalNegative === 0) {
            return ['emphasis' => $emphasis, 'directives' => $directives];
        }

        // Adjust weights based on what users are complaining about
        if (($categoryCounts['wrong_info'] ?? 0) > 2) {
            $emphasis['accuracy'] = 1.5;
            $directives[] = "PRIORITY: Verify factual accuracy before responding. For {$role} users, wrong information was reported " . ($categoryCounts['wrong_info']) . " times recently.";
        }

        if (($categoryCounts['incomplete'] ?? 0) > 2) {
            $emphasis['completeness'] = 1.4;
            $directives[] = "Provide more comprehensive answers for {$role} users. Incomplete responses were reported " . ($categoryCounts['incomplete']) . " times.";
        }

        if (($categoryCounts['unclear'] ?? 0) > 2) {
            $emphasis['step_by_step'] = 1.4;
            $emphasis['conciseness'] = 1.2;
            $directives[] = "Use clearer language and step-by-step formatting for {$role} users. Clarity issues were reported " . ($categoryCounts['unclear']) . " times.";
        }

        if (($categoryCounts['too_long'] ?? 0) > 2) {
            $emphasis['conciseness'] = 1.5;
            $emphasis['completeness'] = max(0.8, $emphasis['completeness'] - 0.2);
            $directives[] = "Keep responses more concise for {$role} users. Verbose responses were reported " . ($categoryCounts['too_long']) . " times.";
        }

        if (($categoryCounts['too_short'] ?? 0) > 2) {
            $emphasis['completeness'] = 1.5;
            $emphasis['conciseness'] = max(0.8, $emphasis['conciseness'] - 0.2);
            $directives[] = "Provide more detailed responses for {$role} users. Insufficient detail was reported " . ($categoryCounts['too_short']) . " times.";
        }

        if (($categoryCounts['outdated'] ?? 0) > 2) {
            $emphasis['accuracy'] = max($emphasis['accuracy'], 1.3);
            $emphasis['data_specificity'] = 1.4;
            $directives[] = "Prioritize the most recently updated knowledge base entries for {$role} users. Outdated information was reported " . ($categoryCounts['outdated']) . " times.";
        }

        if (($categoryCounts['rude'] ?? 0) > 1) {
            $emphasis['tone'] = 1.5;
            $directives[] = "Pay extra attention to tone and warmth for {$role} users. Tone complaints were received " . ($categoryCounts['rude']) . " times.";
        }

        return ['emphasis' => $emphasis, 'directives' => $directives];
    }

    // =========================================================================
    //  5. RETRIEVAL QUALITY LEARNING
    // =========================================================================

    /**
     * Adjust a document's relevance score based on user feedback.
     *
     * Positive feedback nudges the boost factor up; negative nudges it down.
     * The boost factor is bounded between MIN_BOOST_FACTOR and MAX_BOOST_FACTOR.
     *
     * @param int  $documentId The knowledge_base document ID
     * @param bool $wasHelpful Whether the response using this document was helpful
     */
    public function adjustDocumentRelevance(int $documentId, bool $wasHelpful): void
    {
        try {
            $boosts = Cache::get(self::CACHE_KEY_DOC_BOOSTS, []);

            $current = $boosts[$documentId] ?? [
                'factor'         => 1.0,
                'helpful_count'  => 0,
                'unhelpful_count' => 0,
                'last_updated'   => null,
            ];

            if ($wasHelpful) {
                $current['helpful_count']++;
                // Nudge up by a small amount, with diminishing returns
                $nudge = 0.05 / max(1, $current['helpful_count'] / 10);
                $current['factor'] = min(self::MAX_BOOST_FACTOR, $current['factor'] + $nudge);
            } else {
                $current['unhelpful_count']++;
                // Nudge down — unhelpful feedback has stronger signal
                $nudge = 0.08 / max(1, $current['unhelpful_count'] / 10);
                $current['factor'] = max(self::MIN_BOOST_FACTOR, $current['factor'] - $nudge);
            }

            $current['last_updated'] = now()->toIso8601String();
            $boosts[$documentId] = $current;

            Cache::put(self::CACHE_KEY_DOC_BOOSTS, $boosts, now()->addDays(30));

        } catch (\Exception $e) {
            Log::debug('ChatbotLearningService::adjustDocumentRelevance failed', [
                'documentId' => $documentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the current document boost factors.
     *
     * The RAG/retrieval pipeline should multiply its similarity scores
     * by these factors to prefer documents that historically led to
     * good responses.
     *
     * @return array<int, float> documentId => boost_factor (1.0 = neutral)
     */
    public function getDocumentBoosts(): array
    {
        try {
            $boosts = Cache::get(self::CACHE_KEY_DOC_BOOSTS, []);

            // Return just the factor mapping for easy consumption
            $factors = [];
            foreach ($boosts as $docId => $data) {
                $factors[$docId] = round((float) ($data['factor'] ?? 1.0), 3);
            }

            return $factors;

        } catch (\Exception $e) {
            Log::warning('ChatbotLearningService::getDocumentBoosts failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get detailed document boost data including feedback counts.
     *
     * Useful for admin dashboards and debugging.
     *
     * @return array<int, array{factor: float, helpful_count: int, unhelpful_count: int, last_updated: string|null}>
     */
    public function getDocumentBoostDetails(): array
    {
        try {
            return Cache::get(self::CACHE_KEY_DOC_BOOSTS, []);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    //  PUBLIC UTILITY METHODS
    // =========================================================================

    /**
     * Get a complete learning summary suitable for admin dashboards.
     *
     * @return array
     */
    public function getLearningDashboard(): array
    {
        return [
            'active_corrections'     => $this->getActiveCorrections(),
            'detected_gaps'          => $this->detectAndFillGaps(),
            'quality_trends'         => $this->getQualityTrends(),
            'document_boosts'        => $this->getDocumentBoostDetails(),
            'prompt_adjustments'     => [
                'admin'   => $this->getPromptAdjustments('admin'),
                'cashier' => $this->getPromptAdjustments('cashier'),
                'client'  => $this->getPromptAdjustments('client'),
                'guest'   => $this->getPromptAdjustments('guest'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format active corrections as a string block for prompt injection.
     *
     * This is a convenience method that DynamicSystemPromptService can call
     * directly instead of iterating over getActiveCorrections().
     *
     * @return string Ready-to-inject prompt section (empty string if nothing to inject)
     */
    public function getCorrectionsAsPromptBlock(): string
    {
        $corrections = $this->getActiveCorrections();

        if (empty($corrections)) {
            return '';
        }

        $lines = ["## LEARNED CORRECTIONS (from user feedback — apply these when relevant)"];

        foreach ($corrections as $correction) {
            $confidence = $correction['confidence'] ?? 0;
            $count = $correction['source_feedback_count'] ?? 0;
            $text = $correction['correction_text'] ?? '';

            if (empty($text)) {
                continue;
            }

            $confidenceLabel = $confidence >= 0.7 ? 'HIGH' : ($confidence >= 0.4 ? 'MEDIUM' : 'LOW');
            $lines[] = "- [{$confidenceLabel} confidence, {$count} reports] {$text}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format prompt adjustments as a directive block for prompt injection.
     *
     * @param string $role
     * @return string Ready-to-inject prompt section
     */
    public function getAdjustmentsAsPromptBlock(string $role): string
    {
        $adjustments = $this->getPromptAdjustments($role);

        if (empty($adjustments['directives'])) {
            return '';
        }

        $lines = ["## ADAPTIVE BEHAVIOR (learned from {$role} user feedback)"];

        foreach ($adjustments['directives'] as $directive) {
            $lines[] = "- {$directive}";
        }

        return implode("\n", $lines);
    }

    /**
     * Invalidate all learning caches.
     *
     * Call this after bulk data changes (e.g., knowledge base imports,
     * database migrations, or manual cache clear).
     */
    public function invalidateAllCaches(): void
    {
        Cache::forget(self::CACHE_KEY_CORRECTIONS);
        Cache::forget(self::CACHE_KEY_GAPS);
        Cache::forget(self::CACHE_KEY_DOC_BOOSTS);
        Cache::forget(self::CACHE_KEY_QUALITY_HISTORY);

        foreach (['admin', 'cashier', 'client', 'guest'] as $role) {
            Cache::forget(self::CACHE_KEY_PROMPT_ADJ_PREFIX . $role);
        }

        Log::info('ChatbotLearningService: all caches invalidated');
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract a rough topic identifier from a user message when no intent was detected.
     *
     * Uses simple keyword extraction as a fallback for intent detection.
     */
    private function extractTopicFromMessage(string $message): string
    {
        $message = mb_strtolower(trim($message));

        // Remove common filler words
        $stopWords = [
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought',
            'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from',
            'about', 'as', 'into', 'through', 'during', 'before', 'after', 'above',
            'below', 'between', 'and', 'but', 'or', 'nor', 'not', 'so', 'yet',
            'both', 'either', 'neither', 'each', 'every', 'all', 'any', 'few',
            'more', 'most', 'other', 'some', 'such', 'no', 'only', 'own', 'same',
            'than', 'too', 'very', 'just', 'because', 'i', 'me', 'my', 'we', 'our',
            'you', 'your', 'he', 'she', 'it', 'they', 'them', 'this', 'that',
            'what', 'which', 'who', 'whom', 'how', 'when', 'where', 'why',
            'po', 'ko', 'ang', 'ng', 'sa', 'na', 'ba', 'mo', 'naman', 'lang',
            'pa', 'yung', 'mga', 'nyo', 'sya', 'ano', 'paano', 'hi', 'hello',
            'hey', 'please', 'thanks', 'thank',
        ];

        // Extract words, remove stop words
        $words = preg_split('/[\s\?\!\.\,\;\:]+/', $message);
        $meaningful = array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) > 2 && !in_array($w, $stopWords, true);
        });

        // Take up to 3 most significant words as the topic
        $topicWords = array_slice(array_values($meaningful), 0, 3);

        if (empty($topicWords)) {
            return 'general';
        }

        return implode('_', $topicWords);
    }

    /**
     * Find an interaction record by ID (tries both primary key and interaction_id).
     */
    private function findInteraction(int $messageId): ?ChatbotInteractionLog
    {
        if (!$this->tableExists('chatbot_interaction_logs')) {
            return null;
        }

        try {
            // Try by primary key first
            $interaction = ChatbotInteractionLog::find($messageId);

            if (!$interaction) {
                // Try by interaction_id as string
                $interaction = ChatbotInteractionLog::where('interaction_id', (string) $messageId)->first();
            }

            return $interaction;
        } catch (\Exception $e) {
            Log::debug('ChatbotLearningService: findInteraction failed', [
                'messageId' => $messageId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if a database table exists, with result memoization.
     */
    private function tableExists(string $table): bool
    {
        $prop = match ($table) {
            'chatbot_feedback'         => 'hasFeedbackTable',
            'chatbot_interaction_logs' => 'hasInteractionTable',
            'chatbot_analytics'        => 'hasAnalyticsTable',
            'knowledge_base'           => 'hasKnowledgeTable',
            default                    => null,
        };

        if ($prop !== null && $this->{$prop} !== null) {
            return $this->{$prop};
        }

        try {
            $exists = Schema::hasTable($table);
        } catch (\Exception $e) {
            $exists = false;
        }

        if ($prop !== null) {
            $this->{$prop} = $exists;
        }

        return $exists;
    }

    /**
     * Invalidate knowledge base related caches (called after KB changes).
     */
    private function invalidateKnowledgeCaches(): void
    {
        Cache::forget(self::CACHE_KEY_GAPS);
        Cache::forget(self::CACHE_KEY_DOC_BOOSTS);

        // Also invalidate the DynamicKnowledgeFeedService caches if they exist
        Cache::forget('knowledge_feed_errors');
    }
}
