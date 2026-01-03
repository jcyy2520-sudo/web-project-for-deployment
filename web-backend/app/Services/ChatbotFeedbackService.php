<?php

namespace App\Services;

use App\Models\ChatbotFeedback;
use App\Models\ChatbotInteractionLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ChatbotFeedbackService - Feedback Loop & Continuous Improvement System
 * 
 * Implements:
 * - Interaction logging for all chatbot conversations
 * - User feedback collection (thumbs up/down, corrections)
 * - Wrong answer tracking and correction storage
 * - Analytics for identifying problem areas
 * - Data export for model retraining
 * 
 * The feedback loop is critical for improving accuracy over time.
 * Every interaction is logged, every correction is stored, enabling:
 * - Identifying frequently wrong answers
 * - Building training data from corrections
 * - Tracking accuracy metrics over time
 */
class ChatbotFeedbackService
{
    private const CACHE_TTL = 3600; // 1 hour
    
    /**
     * Log an interaction (called after every chatbot response)
     * 
     * @param array $data Interaction data
     * @return string|null Interaction ID for feedback reference
     */
    public function logInteraction(array $data): ?string
    {
        try {
            $interactionId = Str::uuid()->toString();
            
            $log = ChatbotInteractionLog::create([
                'interaction_id' => $interactionId,
                'user_id' => $data['user_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'user_message' => $data['user_message'] ?? '',
                'bot_response' => $data['bot_response'] ?? '',
                'intent_detected' => $data['intent'] ?? null,
                'confidence_score' => $data['confidence'] ?? null,
                'context_sources' => json_encode($data['context_used'] ?? []),
                'llm_provider' => $data['llm_provider'] ?? null,
                'processing_time_ms' => $data['processing_time_ms'] ?? null,
                'response_source' => $data['response_source'] ?? 'llm',
                'was_fallback' => $data['was_fallback'] ?? false,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);
            
            return $interactionId;
            
        } catch (\Exception $e) {
            Log::error('Failed to log chatbot interaction: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Record user feedback on a specific interaction
     * 
     * @param string $interactionId The interaction to rate
     * @param array $feedback Feedback data (rating, correction, comments)
     * @return bool Success status
     */
    public function recordFeedback(string $interactionId, array $feedback): bool
    {
        try {
            // Verify interaction exists
            $interaction = ChatbotInteractionLog::where('interaction_id', $interactionId)->first();
            
            if (!$interaction) {
                Log::warning('Feedback for non-existent interaction: ' . $interactionId);
                return false;
            }
            
            // Create or update feedback record
            ChatbotFeedback::updateOrCreate(
                ['interaction_id' => $interactionId],
                [
                    'user_id' => $feedback['user_id'] ?? $interaction->user_id,
                    'rating' => $feedback['rating'] ?? null, // 1-5 or thumbs up/down
                    'is_helpful' => $feedback['is_helpful'] ?? null, // true/false
                    'is_correct' => $feedback['is_correct'] ?? null, // true/false
                    'correction_text' => $feedback['correction'] ?? null, // User's correction
                    'expected_response' => $feedback['expected_response'] ?? null,
                    'feedback_category' => $feedback['category'] ?? null, // wrong_info, unclear, off_topic, etc.
                    'comments' => $feedback['comments'] ?? null,
                    'submitted_at' => now(),
                ]
            );
            
            // Update interaction with feedback status
            $interaction->update([
                'has_feedback' => true,
                'feedback_rating' => $feedback['rating'] ?? $feedback['is_helpful'] ?? null,
            ]);
            
            // If marked as wrong, add to correction queue for retraining
            if (($feedback['is_correct'] ?? null) === false || ($feedback['rating'] ?? 5) <= 2) {
                $this->queueForRetraining($interactionId, $interaction, $feedback);
            }
            
            // Clear relevant caches
            $this->clearFeedbackCaches();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to record feedback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Queue an interaction for retraining consideration
     */
    private function queueForRetraining(string $interactionId, $interaction, array $feedback): void
    {
        try {
            // Store in a separate corrections table/cache for batch processing
            $correctionData = [
                'interaction_id' => $interactionId,
                'user_message' => $interaction->user_message,
                'wrong_response' => $interaction->bot_response,
                'correct_response' => $feedback['correction'] ?? $feedback['expected_response'] ?? null,
                'feedback_category' => $feedback['category'] ?? 'general',
                'created_at' => now(),
            ];
            
            // Store in cache for quick access (also in DB via the feedback table)
            $corrections = Cache::get('chatbot_corrections_queue', []);
            $corrections[] = $correctionData;
            Cache::put('chatbot_corrections_queue', $corrections, now()->addDays(30));
            
            Log::info('Queued interaction for retraining', ['interaction_id' => $interactionId]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to queue for retraining: ' . $e->getMessage());
        }
    }
    
    /**
     * Get feedback analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        $cacheKey = 'chatbot_feedback_analytics_' . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($filters) {
            try {
                $query = ChatbotInteractionLog::query();
                
                // Apply date filters
                if (!empty($filters['start_date'])) {
                    $query->where('created_at', '>=', $filters['start_date']);
                }
                if (!empty($filters['end_date'])) {
                    $query->where('created_at', '<=', $filters['end_date']);
                }
                
                $totalInteractions = $query->count();
                $withFeedback = (clone $query)->where('has_feedback', true)->count();
                
                // Get feedback distribution
                $feedbackStats = ChatbotFeedback::selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) as helpful,
                    SUM(CASE WHEN is_helpful = 0 THEN 1 ELSE 0 END) as not_helpful,
                    SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as marked_wrong,
                    AVG(rating) as avg_rating
                ')->first();
                
                // Get common issues
                $commonIssues = ChatbotFeedback::where('is_correct', false)
                    ->orWhere('is_helpful', false)
                    ->selectRaw('feedback_category, COUNT(*) as count')
                    ->groupBy('feedback_category')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
                
                // Get fallback rate
                $fallbackCount = (clone $query)->where('was_fallback', true)->count();
                
                return [
                    'total_interactions' => $totalInteractions,
                    'interactions_with_feedback' => $withFeedback,
                    'feedback_rate' => $totalInteractions > 0 ? round($withFeedback / $totalInteractions * 100, 2) : 0,
                    'helpful_count' => $feedbackStats->helpful ?? 0,
                    'not_helpful_count' => $feedbackStats->not_helpful ?? 0,
                    'marked_wrong_count' => $feedbackStats->marked_wrong ?? 0,
                    'average_rating' => round($feedbackStats->avg_rating ?? 0, 2),
                    'satisfaction_rate' => $feedbackStats->total > 0 
                        ? round(($feedbackStats->helpful / $feedbackStats->total) * 100, 2) 
                        : 0,
                    'fallback_count' => $fallbackCount,
                    'fallback_rate' => $totalInteractions > 0 ? round($fallbackCount / $totalInteractions * 100, 2) : 0,
                    'common_issues' => $commonIssues->toArray(),
                ];
                
            } catch (\Exception $e) {
                Log::error('Failed to get feedback analytics: ' . $e->getMessage());
                return [
                    'error' => 'Failed to retrieve analytics',
                    'total_interactions' => 0,
                ];
            }
        });
    }
    
    /**
     * Get wrong answers for review/retraining
     */
    public function getWrongAnswers(int $limit = 50, int $offset = 0): array
    {
        try {
            $wrongAnswers = ChatbotFeedback::with('interactionLog')
                ->where(function($q) {
                    $q->where('is_correct', false)
                        ->orWhere('rating', '<=', 2);
                })
                ->orderByDesc('submitted_at')
                ->skip($offset)
                ->take($limit)
                ->get();
            
            return $wrongAnswers->map(function($feedback) {
                return [
                    'interaction_id' => $feedback->interaction_id,
                    'user_message' => $feedback->interactionLog->user_message ?? '',
                    'wrong_response' => $feedback->interactionLog->bot_response ?? '',
                    'correction' => $feedback->correction_text,
                    'expected_response' => $feedback->expected_response,
                    'category' => $feedback->feedback_category,
                    'comments' => $feedback->comments,
                    'submitted_at' => $feedback->submitted_at,
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            Log::error('Failed to get wrong answers: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Export corrections for model retraining
     * 
     * Returns data in a format suitable for fine-tuning or RAG updates
     */
    public function exportCorrectionsForTraining(): array
    {
        try {
            $corrections = ChatbotFeedback::with('interactionLog')
                ->whereNotNull('correction_text')
                ->orWhereNotNull('expected_response')
                ->get();
            
            $trainingData = [];
            
            foreach ($corrections as $correction) {
                if (!$correction->interactionLog) continue;
                
                $trainingData[] = [
                    'input' => $correction->interactionLog->user_message,
                    'wrong_output' => $correction->interactionLog->bot_response,
                    'correct_output' => $correction->expected_response ?? $correction->correction_text,
                    'category' => $correction->feedback_category,
                    'timestamp' => $correction->submitted_at,
                ];
            }
            
            return [
                'count' => count($trainingData),
                'data' => $trainingData,
                'exported_at' => now()->toIso8601String(),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to export corrections: ' . $e->getMessage());
            return ['count' => 0, 'data' => [], 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get frequently asked questions that the bot struggles with
     */
    public function getProblematicQueries(int $limit = 20): array
    {
        try {
            // Find messages that frequently result in low ratings or fallbacks
            return ChatbotInteractionLog::selectRaw('
                user_message,
                COUNT(*) as occurrence_count,
                SUM(CASE WHEN was_fallback = 1 THEN 1 ELSE 0 END) as fallback_count,
                AVG(feedback_rating) as avg_rating
            ')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('user_message')
            ->havingRaw('fallback_count > 0 OR avg_rating < 3')
            ->orderByDesc('occurrence_count')
            ->limit($limit)
            ->get()
            ->toArray();
            
        } catch (\Exception $e) {
            Log::error('Failed to get problematic queries: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark a correction as applied (after retraining/knowledge base update)
     */
    public function markCorrectionApplied(string $interactionId): bool
    {
        try {
            ChatbotFeedback::where('interaction_id', $interactionId)
                ->update(['correction_applied' => true, 'applied_at' => now()]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark correction applied: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear feedback-related caches
     */
    private function clearFeedbackCaches(): void
    {
        Cache::forget('chatbot_feedback_analytics_' . md5('[]'));
    }
}
