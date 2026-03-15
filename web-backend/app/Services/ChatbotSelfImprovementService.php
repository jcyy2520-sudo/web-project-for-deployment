<?php

namespace App\Services;

use App\Models\ChatbotAnalytics;
use App\Models\ChatbotConversation;
use App\Models\ChatbotFeedback;
use App\Models\ChatbotInteractionLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ChatbotSelfImprovementService - Continuous Self-Assessment & Improvement
 *
 * Periodically evaluates chatbot performance and generates actionable
 * improvement recommendations for developers and administrators.
 *
 * Features:
 * - Response quality scoring (clarity, completeness, usefulness)
 * - Error trend analysis and root cause identification
 * - User satisfaction tracking over time
 * - Knowledge gap detection from unanswered/low-confidence queries
 * - Performance regression detection
 * - Automated improvement suggestion generation
 * - Developer-facing performance reports
 */
class ChatbotSelfImprovementService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Generate a comprehensive self-evaluation report
     *
     * @param string $period  'day', 'week', or 'month'
     * @return array Report data with metrics, trends, and recommendations
     */
    public function generateSelfEvaluationReport(string $period = 'week'): array
    {
        $cacheKey = "chatbot_self_eval_{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period) {
            $since = match ($period) {
                'day'   => Carbon::now()->subDay(),
                'week'  => Carbon::now()->subWeek(),
                'month' => Carbon::now()->subMonth(),
                default => Carbon::now()->subWeek(),
            };

            return [
                'period'              => $period,
                'generated_at'        => now()->toIso8601String(),
                'overall_score'       => $this->calculateOverallScore($since),
                'response_quality'    => $this->assessResponseQuality($since),
                'user_satisfaction'   => $this->measureUserSatisfaction($since),
                'error_analysis'      => $this->analyzeErrors($since),
                'knowledge_gaps'      => $this->detectKnowledgeGaps($since),
                'performance_metrics' => $this->getPerformanceMetrics($since),
                'engagement_metrics'  => $this->getEngagementMetrics($since),
                'role_breakdown'      => $this->getRoleBreakdown($since),
                'recommendations'     => $this->generateRecommendations($since),
                'trending_topics'     => $this->getTrendingTopics($since),
            ];
        });
    }

    /**
     * Calculate an overall quality score (0-100)
     */
    private function calculateOverallScore(Carbon $since): array
    {
        try {
            $analytics = ChatbotAnalytics::where('created_at', '>=', $since)->get();

            if ($analytics->isEmpty()) {
                return ['score' => 0, 'grade' => 'N/A', 'message' => 'No data available for this period.'];
            }

            $total = $analytics->count();
            $successful = $analytics->where('was_successful', true)->count();
            $successRate = $total > 0 ? ($successful / $total) * 100 : 0;

            $avgResponseTime = $analytics->avg('response_time_ms') ?? 0;
            $responseTimeScore = max(0, 100 - ($avgResponseTime / 50)); // Penalise slow

            $feedbackScore = $this->getFeedbackScore($since);

            // Weighted score
            $score = round(
                ($successRate * 0.4) +
                ($responseTimeScore * 0.2) +
                ($feedbackScore * 0.4)
            );

            $grade = match (true) {
                $score >= 90 => 'A',
                $score >= 80 => 'B',
                $score >= 70 => 'C',
                $score >= 60 => 'D',
                default      => 'F',
            };

            return [
                'score'   => min(100, max(0, $score)),
                'grade'   => $grade,
                'message' => $this->getScoreMessage($grade),
                'breakdown' => [
                    'success_rate'       => round($successRate, 1),
                    'response_time_score' => round($responseTimeScore, 1),
                    'feedback_score'     => round($feedbackScore, 1),
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('Self-improvement score calculation failed: ' . $e->getMessage());
            return ['score' => 0, 'grade' => 'N/A', 'message' => 'Unable to calculate score.'];
        }
    }

    /**
     * Assess response quality across multiple dimensions
     */
    private function assessResponseQuality(Carbon $since): array
    {
        try {
            $interactions = ChatbotInteractionLog::where('created_at', '>=', $since)
                ->limit(500)
                ->get();

            if ($interactions->isEmpty()) {
                return ['status' => 'no_data'];
            }

            $empty   = $interactions->filter(fn($i) => empty(trim($i->bot_response ?? '')))->count();
            $fallback = $interactions->where('was_fallback', true)->count();
            $total   = $interactions->count();

            // Length analysis
            $lengths = $interactions->map(fn($i) => strlen($i->bot_response ?? ''));
            $avgLen  = $lengths->avg();
            $tooShort = $lengths->filter(fn($l) => $l < 30)->count();
            $tooLong  = $lengths->filter(fn($l) => $l > 3000)->count();

            // Confidence analysis
            $avgConfidence = $interactions->avg('confidence_score') ?? 0;
            $lowConfidence = $interactions->filter(fn($i) => ($i->confidence_score ?? 1) < 0.5)->count();

            return [
                'total_interactions'   => $total,
                'empty_responses'      => $empty,
                'fallback_responses'   => $fallback,
                'fallback_rate'        => round(($fallback / max($total, 1)) * 100, 1),
                'avg_response_length'  => round($avgLen),
                'too_short_responses'  => $tooShort,
                'too_long_responses'   => $tooLong,
                'avg_confidence'       => round($avgConfidence, 3),
                'low_confidence_count' => $lowConfidence,
                'quality_issues' => array_filter([
                    $empty > 0 ? "{$empty} empty responses detected" : null,
                    $fallback > ($total * 0.2) ? "High fallback rate ({$fallback}/{$total})" : null,
                    $avgConfidence < 0.5 ? "Low average confidence ({$avgConfidence})" : null,
                    $tooShort > ($total * 0.1) ? "{$tooShort} responses may be too brief" : null,
                ]),
            ];
        } catch (\Exception $e) {
            Log::warning('Response quality assessment failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Measure user satisfaction from feedback data
     */
    private function measureUserSatisfaction(Carbon $since): array
    {
        try {
            $feedback = ChatbotFeedback::where('submitted_at', '>=', $since)->get();

            if ($feedback->isEmpty()) {
                return [
                    'status'  => 'no_feedback',
                    'message' => 'No user feedback collected in this period. Consider prompting users for feedback.',
                ];
            }

            $total     = $feedback->count();
            $helpful   = $feedback->where('is_helpful', true)->count();
            $unhelpful = $feedback->where('is_helpful', false)->count();
            $correct   = $feedback->where('is_correct', true)->count();
            $incorrect = $feedback->where('is_correct', false)->count();
            $avgRating = $feedback->whereNotNull('rating')->avg('rating');

            // Category breakdown
            $categories = $feedback->whereNotNull('feedback_category')
                ->groupBy('feedback_category')
                ->map->count()
                ->sortDesc()
                ->toArray();

            return [
                'total_feedback'      => $total,
                'helpful_count'       => $helpful,
                'unhelpful_count'     => $unhelpful,
                'helpful_rate'        => round(($helpful / max($total, 1)) * 100, 1),
                'accuracy_rate'       => round(($correct / max($correct + $incorrect, 1)) * 100, 1),
                'average_rating'      => $avgRating ? round($avgRating, 2) : null,
                'feedback_categories' => $categories,
                'corrections_needed'  => $incorrect,
            ];
        } catch (\Exception $e) {
            Log::warning('User satisfaction measurement failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }
    }

    /**
     * Analyse error patterns and trends
     */
    private function analyzeErrors(Carbon $since): array
    {
        try {
            $errors = ChatbotAnalytics::where('created_at', '>=', $since)
                ->where('was_successful', false)
                ->get();

            if ($errors->isEmpty()) {
                return [
                    'total_errors'  => 0,
                    'error_rate'    => 0,
                    'message'       => 'No errors recorded in this period.',
                ];
            }

            $totalAll = ChatbotAnalytics::where('created_at', '>=', $since)->count();

            // Group errors by failure reason
            $byReason = $errors->groupBy('failure_reason')->map->count()->sortDesc()->toArray();

            // Group by day for trend
            $byDay = $errors->groupBy(fn($e) => $e->created_at->format('Y-m-d'))
                ->map->count()
                ->toArray();

            // Identify repeat errors (same user, same error)
            $repeatErrors = $errors->groupBy(fn($e) => $e->user_id . '_' . $e->failure_reason)
                ->filter(fn($group) => $group->count() > 2)
                ->count();

            return [
                'total_errors'   => $errors->count(),
                'error_rate'     => round(($errors->count() / max($totalAll, 1)) * 100, 1),
                'by_reason'      => $byReason,
                'daily_trend'    => $byDay,
                'repeat_errors'  => $repeatErrors,
                'most_common'    => array_key_first($byReason) ?? 'none',
                'recommendations' => $this->getErrorRecommendations($byReason),
            ];
        } catch (\Exception $e) {
            Log::warning('Error analysis failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }
    }

    /**
     * Detect knowledge gaps from low-confidence and unanswered queries
     */
    private function detectKnowledgeGaps(Carbon $since): array
    {
        try {
            // Find low-confidence interactions
            $lowConf = ChatbotInteractionLog::where('created_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('confidence_score', '<', 0.5)
                      ->orWhere('was_fallback', true);
                })
                ->limit(100)
                ->get();

            if ($lowConf->isEmpty()) {
                return ['gaps_found' => 0, 'message' => 'No significant knowledge gaps detected.'];
            }

            // Extract common topics from low-confidence queries
            $topicCounts = [];
            foreach ($lowConf as $interaction) {
                $intent = $interaction->intent_detected ?? 'unknown';
                $topicCounts[$intent] = ($topicCounts[$intent] ?? 0) + 1;
            }
            arsort($topicCounts);

            // Get sample questions for each gap
            $gapDetails = [];
            foreach (array_slice($topicCounts, 0, 10, true) as $topic => $count) {
                $samples = $lowConf->where('intent_detected', $topic)
                    ->pluck('user_message')
                    ->unique()
                    ->take(3)
                    ->toArray();

                $gapDetails[] = [
                    'topic'           => $topic,
                    'occurrence_count' => $count,
                    'sample_questions' => $samples,
                    'priority'        => $count >= 5 ? 'high' : ($count >= 3 ? 'medium' : 'low'),
                ];
            }

            return [
                'gaps_found'  => count($gapDetails),
                'total_low_confidence' => $lowConf->count(),
                'gap_details' => $gapDetails,
                'action_items' => array_map(
                    fn($g) => "Add knowledge about '{$g['topic']}' (asked {$g['occurrence_count']} times)",
                    array_filter($gapDetails, fn($g) => $g['priority'] !== 'low')
                ),
            ];
        } catch (\Exception $e) {
            Log::warning('Knowledge gap detection failed: ' . $e->getMessage());
            return ['gaps_found' => 0, 'status' => 'error'];
        }
    }

    /**
     * Get performance metrics (response time, throughput, etc.)
     */
    private function getPerformanceMetrics(Carbon $since): array
    {
        try {
            $analytics = ChatbotAnalytics::where('created_at', '>=', $since)->get();

            if ($analytics->isEmpty()) {
                return ['status' => 'no_data'];
            }

            $responseTimes = $analytics->pluck('response_time_ms')->filter();

            return [
                'total_messages'     => $analytics->count(),
                'avg_response_ms'    => round($responseTimes->avg() ?? 0),
                'p50_response_ms'    => $this->percentile($responseTimes->toArray(), 50),
                'p95_response_ms'    => $this->percentile($responseTimes->toArray(), 95),
                'p99_response_ms'    => $this->percentile($responseTimes->toArray(), 99),
                'max_response_ms'    => $responseTimes->max() ?? 0,
                'min_response_ms'    => $responseTimes->min() ?? 0,
                'slow_responses'     => $responseTimes->filter(fn($t) => $t > 5000)->count(),
                'source_breakdown'   => $analytics->groupBy('response_source')->map->count()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('Performance metrics failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics(Carbon $since): array
    {
        try {
            $conversations = ChatbotConversation::where('created_at', '>=', $since)->get();
            $uniqueUsers = $conversations->pluck('user_id')->filter()->unique()->count();

            $avgMsgsPerConv = $conversations->avg('message_count') ?? 0;
            $returnUsers = DB::table('chatbot_conversations')
                ->where('created_at', '>=', $since)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            return [
                'total_conversations' => $conversations->count(),
                'unique_users'        => $uniqueUsers,
                'returning_users'     => $returnUsers,
                'avg_messages_per_conversation' => round($avgMsgsPerConv, 1),
                'conversations_by_role' => $conversations->groupBy(
                    fn($c) => $c->context_data['role'] ?? 'unknown'
                )->map->count()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('Engagement metrics failed: ' . $e->getMessage());
            return ['status' => 'error'];
        }
    }

    /**
     * Generate actionable recommendations based on analysis
     */
    private function generateRecommendations(Carbon $since): array
    {
        $recommendations = [];

        try {
            $quality = $this->assessResponseQuality($since);
            $errors  = $this->analyzeErrors($since);
            $gaps    = $this->detectKnowledgeGaps($since);
            $perf    = $this->getPerformanceMetrics($since);
            $sat     = $this->measureUserSatisfaction($since);

            // Quality recommendations
            if (($quality['fallback_rate'] ?? 0) > 20) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'quality',
                    'title'    => 'High Fallback Rate',
                    'detail'   => "Fallback rate is {$quality['fallback_rate']}%. Review knowledge base and improve LLM prompt to handle more intents directly.",
                    'action'   => 'Review and expand knowledge base; tune system prompt for common miss topics.',
                ];
            }

            if (($quality['avg_confidence'] ?? 1) < 0.6) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'quality',
                    'title'    => 'Low Average Confidence',
                    'detail'   => "Average confidence is {$quality['avg_confidence']}. Consider adding more training data for common queries.",
                    'action'   => 'Extend NLU intent patterns and knowledge base entries.',
                ];
            }

            // Performance recommendations
            if (($perf['avg_response_ms'] ?? 0) > 3000) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'performance',
                    'title'    => 'Slow Response Time',
                    'detail'   => "Average response time is {$perf['avg_response_ms']}ms. Users may be frustrated.",
                    'action'   => 'Optimise LLM calls, add response caching, and reduce context window size.',
                ];
            }

            if (($perf['slow_responses'] ?? 0) > 10) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'performance',
                    'title'    => 'Multiple Slow Responses',
                    'detail'   => "{$perf['slow_responses']} responses exceeded 5 seconds.",
                    'action'   => 'Investigate P95/P99 latency spikes; consider caching frequent queries.',
                ];
            }

            // Satisfaction recommendations
            if (($sat['helpful_rate'] ?? 100) < 70) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'satisfaction',
                    'title'    => 'Low User Satisfaction',
                    'detail'   => "Only {$sat['helpful_rate']}% of feedback rated as helpful.",
                    'action'   => 'Review corrections in feedback data and update knowledge base accordingly.',
                ];
            }

            // Knowledge gap recommendations
            if (($gaps['gaps_found'] ?? 0) > 0) {
                $highPriority = array_filter(
                    $gaps['gap_details'] ?? [],
                    fn($g) => $g['priority'] === 'high'
                );

                if (!empty($highPriority)) {
                    $topics = array_column($highPriority, 'topic');
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'knowledge',
                        'title'    => 'Knowledge Gaps Detected',
                        'detail'   => 'Users are frequently asking about topics the chatbot cannot answer well: ' . implode(', ', $topics),
                        'action'   => 'Add knowledge base entries for these topics and train on sample questions.',
                    ];
                }
            }

            // Error recommendations
            if (($errors['error_rate'] ?? 0) > 5) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'reliability',
                    'title'    => 'High Error Rate',
                    'detail'   => "Error rate is {$errors['error_rate']}%. Most common: {$errors['most_common']}.",
                    'action'   => 'Fix the most common error cause and add better error handling.',
                ];
            }

            if (($errors['repeat_errors'] ?? 0) > 3) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'reliability',
                    'title'    => 'Repeat Errors for Same Users',
                    'detail'   => "{$errors['repeat_errors']} users experienced the same error multiple times.",
                    'action'   => 'Implement better error recovery and proactive guidance for affected users.',
                ];
            }

            // Role-specific recommendations from breakdown
            $roleBreakdown = $this->getRoleBreakdown($since);
            if (!empty($roleBreakdown['role_recommendations'])) {
                foreach ($roleBreakdown['role_recommendations'] as $roleRec) {
                    $recommendations[] = [
                        'priority' => $roleRec['priority'],
                        'category' => 'role_specific',
                        'title'    => 'Role Issue: ' . ucfirst($roleRec['role']),
                        'detail'   => $roleRec['message'],
                        'action'   => 'Review and optimise chatbot behaviour for ' . $roleRec['role'] . ' role.',
                    ];
                }
            }

            // If no issues found
            if (empty($recommendations)) {
                $recommendations[] = [
                    'priority' => 'info',
                    'category' => 'general',
                    'title'    => 'System Operating Well',
                    'detail'   => 'No significant issues detected. Continue monitoring.',
                    'action'   => 'Keep collecting feedback and monitoring trends.',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Recommendation generation failed: ' . $e->getMessage());
            $recommendations[] = [
                'priority' => 'info',
                'category' => 'system',
                'title'    => 'Report Generation Partial',
                'detail'   => 'Some metrics could not be calculated.',
                'action'   => 'Check logs for details.',
            ];
        }

        // Sort by priority
        usort($recommendations, function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3];
            return ($order[$a['priority']] ?? 4) <=> ($order[$b['priority']] ?? 4);
        });

        return $recommendations;
    }

    /**
     * Get trending topics in user queries
     */
    private function getTrendingTopics(Carbon $since): array
    {
        try {
            return ChatbotAnalytics::where('created_at', '>=', $since)
                ->whereNotNull('detected_intent')
                ->groupBy('detected_intent')
                ->selectRaw('detected_intent, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(15)
                ->pluck('count', 'detected_intent')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // --- Per-Role Analytics ---

    /**
     * Breakdown chatbot performance by user role
     * Provides success rates, common intents, satisfaction, and recommendations per role
     */
    private function getRoleBreakdown(Carbon $since): array
    {
        try {
            $analytics = ChatbotAnalytics::where('created_at', '>=', $since)
                ->whereNotNull('user_role')
                ->get();

            if ($analytics->isEmpty()) {
                return ['status' => 'no_data', 'message' => 'No role-specific data available.'];
            }

            $roles = ['admin', 'cashier', 'client', 'guest'];
            $breakdown = [];

            foreach ($roles as $role) {
                $roleData = $analytics->where('user_role', $role);
                if ($roleData->isEmpty()) {
                    $breakdown[$role] = ['total' => 0, 'status' => 'no_data'];
                    continue;
                }

                $total = $roleData->count();
                $successful = $roleData->where('was_successful', true)->count();
                $avgResponseMs = $roleData->avg('response_time_ms') ?? 0;

                // Top intents for this role
                $topIntents = $roleData->whereNotNull('detected_intent')
                    ->groupBy('detected_intent')
                    ->map->count()
                    ->sortDesc()
                    ->take(5)
                    ->toArray();

                // Source distribution for this role
                $sourceBreak = $roleData->groupBy('response_source')
                    ->map->count()
                    ->toArray();

                // Feedback for this role
                $roleFeedback = ChatbotFeedback::where('submitted_at', '>=', $since)
                    ->whereHas('interaction', function ($q) use ($role) {
                        $q->where('user_role', $role);
                    })
                    ->get();

                $helpfulRate = 0;
                if ($roleFeedback->isNotEmpty()) {
                    $helpful = $roleFeedback->where('is_helpful', true)->count();
                    $helpfulRate = round(($helpful / max($roleFeedback->count(), 1)) * 100, 1);
                }

                // Error count for this role
                $errors = $roleData->where('was_successful', false)->count();

                $breakdown[$role] = [
                    'total' => $total,
                    'success_rate' => round(($successful / max($total, 1)) * 100, 1),
                    'error_count' => $errors,
                    'error_rate' => round(($errors / max($total, 1)) * 100, 1),
                    'avg_response_ms' => round($avgResponseMs),
                    'top_intents' => $topIntents,
                    'source_breakdown' => $sourceBreak,
                    'feedback_helpful_rate' => $helpfulRate,
                    'feedback_count' => $roleFeedback->count(),
                ];
            }

            // Generate per-role recommendations
            $roleRecommendations = [];
            foreach ($breakdown as $role => $data) {
                if (($data['total'] ?? 0) === 0) continue;

                if (($data['error_rate'] ?? 0) > 10) {
                    $roleRecommendations[] = [
                        'role' => $role,
                        'priority' => 'high',
                        'message' => ucfirst($role) . " users have a {$data['error_rate']}% error rate. Investigate common failure points for this role.",
                    ];
                }

                if (($data['feedback_helpful_rate'] ?? 100) < 60 && ($data['feedback_count'] ?? 0) >= 3) {
                    $roleRecommendations[] = [
                        'role' => $role,
                        'priority' => 'high',
                        'message' => ucfirst($role) . " satisfaction is only {$data['feedback_helpful_rate']}%. Review and improve responses for this role's common intents.",
                    ];
                }

                if (($data['avg_response_ms'] ?? 0) > 4000) {
                    $roleRecommendations[] = [
                        'role' => $role,
                        'priority' => 'medium',
                        'message' => ucfirst($role) . " responses average {$data['avg_response_ms']}ms. Consider caching common queries for this role.",
                    ];
                }
            }

            return [
                'breakdown' => $breakdown,
                'role_recommendations' => $roleRecommendations,
            ];
        } catch (\Exception $e) {
            Log::warning('Role breakdown analysis failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // --- Helpers ---

    private function getFeedbackScore(Carbon $since): float
    {
        try {
            $feedback = ChatbotFeedback::where('submitted_at', '>=', $since)->get();
            if ($feedback->isEmpty()) return 75; // Neutral default

            $helpful = $feedback->where('is_helpful', true)->count();
            $total   = $feedback->count();
            return ($helpful / max($total, 1)) * 100;
        } catch (\Exception $e) {
            return 75;
        }
    }

    private function getScoreMessage(string $grade): string
    {
        return match ($grade) {
            'A'     => 'Excellent performance. The chatbot is operating at a high level of quality.',
            'B'     => 'Good performance with room for improvement in specific areas.',
            'C'     => 'Acceptable performance. Several areas need attention.',
            'D'     => 'Below average. Significant improvements needed.',
            'F'     => 'Poor performance. Urgent attention required across multiple areas.',
            default => 'Insufficient data to evaluate.',
        };
    }

    private function getErrorRecommendations(array $byReason): array
    {
        $recs = [];
        foreach (array_slice($byReason, 0, 3, true) as $reason => $count) {
            $recs[] = "Fix '{$reason}' errors ({$count} occurrences)";
        }
        return $recs;
    }

    private function percentile(array $values, int $p): int
    {
        if (empty($values)) return 0;
        sort($values);
        $index = ceil(count($values) * ($p / 100)) - 1;
        return (int) ($values[max(0, $index)] ?? 0);
    }
}
