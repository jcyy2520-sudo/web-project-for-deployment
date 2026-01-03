<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackConfirmation;
use App\Mail\FeedbackReported;
use App\Models\Feedback;
use App\Models\FeedbackSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Validate that email is registered in the system
     */
    private function isEmailRegistered($email)
    {
        return User::where('email', $email)->exists();
    }

    /**
     * Check if user/email is blocked from submitting feedback
     */
    private function isUserBlocked($userId, $email)
    {
        $feedback = Feedback::where(function ($query) use ($userId, $email) {
            if ($userId) {
                $query->where('user_id', $userId);
            }
            $query->orWhere('email', $email);
        })
        ->where('is_blocked', true)
        ->where(function ($query) {
            $query->whereNull('blocked_until')
                  ->orWhere('blocked_until', '>', now());
        })
        ->first();

        return $feedback ? true : false;
    }

    /**
     * Store a new feedback from the landing page or user dashboard
     */
    public function store(Request $request)
    {
        // Validate input - let validation exceptions pass through naturally
        $validated = $request->validate([
            'email' => 'nullable|email',
            'message' => 'required|string|min:10',
            'rating' => 'required|integer|min:1|max:5',
            'feedback_type' => 'nullable|string|in:service_quality,speed,support,system_experience,bug_report,suggestion,other,general',
            'user_id' => 'nullable|integer|exists:users,id'
        ]);

        try {
            // If authenticated, prefer authenticated user's id/email
            $userId = $validated['user_id'] ?? null;
            if (Auth::check()) {
                $userId = Auth::id();
                $validated['user_id'] = $userId;
                $validated['email'] = auth()->user()->email;
            }

            // Ensure an email is provided for public submissions
            if (empty($validated['email']) || !filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'message' => 'Email is required and must be valid.',
                    'error' => 'email_required'
                ], 422);
            }

            // Check that email is registered in the system (public submissions must be from registered emails)
            if (!$this->isEmailRegistered($validated['email'])) {
                return response()->json([
                    'message' => 'The provided email is not registered. Please create an account or log in.',
                    'error' => 'email_not_registered'
                ], 403);
            }

            // Check if user is blocked
            if ($this->isUserBlocked($userId, $validated['email'])) {
                return response()->json([
                    'message' => 'You have been blocked from submitting feedback.',
                    'error' => 'user_blocked'
                ], 403);
            }

            // Check rate limit
            if (Feedback::hasReachedRateLimit($userId, $validated['email'])) {
                $settings = FeedbackSettings::getSettings();
                $nextAvailableAt = Feedback::getNextAvailableDate($userId, $validated['email'], $settings->cooldown_days);
                return response()->json([
                    'message' => "You have reached your feedback limit of {$settings->rate_limit} per {$settings->cooldown_days} days.",
                    'error' => 'rate_limit_reached',
                    'data' => [
                        'limit' => $settings->rate_limit,
                        'cooldown_days' => $settings->cooldown_days,
                        'next_available_at' => $nextAvailableAt ? $nextAvailableAt->toISOString() : null
                    ]
                ], 429);
            }

            // Profanity filter and duplicate detection (configurable)
            $settings = FeedbackSettings::getSettings();

            if ($settings->profanity_filter_enabled) {
                $profanityList = is_array($settings->profanity_list) ? $settings->profanity_list : (json_decode($settings->profanity_list, true) ?? []);
                $lowerMessage = strtolower($validated['message']);
                foreach ($profanityList as $word) {
                    $word = trim(strtolower($word));
                    if ($word === '') continue;
                    if (strpos($lowerMessage, $word) !== false) {
                        return response()->json([
                            'message' => 'Feedback contains disallowed language.',
                            'error' => 'profanity_detected'
                        ], 422);
                    }
                }
            }

            if ($settings->duplicate_detection_enabled) {
                $recentDuplicate = Feedback::where(function ($query) use ($userId, $validated) {
                    $query->where('user_id', $userId)
                          ->orWhere('email', $validated['email']);
                })
                ->where('message', $validated['message'])
                ->where('created_at', '>=', now()->subDays($settings->cooldown_days))
                ->exists();

                if ($recentDuplicate) {
                    return response()->json([
                        'message' => 'Duplicate feedback detected. Please modify your message before submitting again.',
                        'error' => 'duplicate_feedback'
                    ], 409);
                }
            }

            // Set default feedback type
            $validated['feedback_type'] = $validated['feedback_type'] ?? 'general';

            $feedback = Feedback::create($validated);

            // Send confirmation email to user (synchronous for immediate delivery)
            try {
                Mail::to($feedback->email)->send(new FeedbackConfirmation($feedback));
                Log::info('Feedback confirmation email sent successfully', [
                    'feedback_id' => $feedback->id,
                    'email' => $feedback->email
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send feedback confirmation email', [
                    'feedback_id' => $feedback->id,
                    'email' => $feedback->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the request if email fails - feedback is still saved
            }

            return response()->json([
                'message' => 'Thank you for your feedback!',
                'data' => $feedback
            ], 201);

        } catch (\Exception $e) {
            Log::error('Feedback store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to save feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all feedback for admin with pagination, search, sort, and filter
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $filterRating = $request->get('rating', null);
            $filterTestimonial = $request->get('is_testimonial', null);
            $filterType = $request->get('feedback_type', null);
            $filterReported = $request->get('is_reported', null);

            $query = Feedback::query();

            // Apply search
            if ($search) {
                $query->search($search);
            }

            // Apply rating filter
            if ($filterRating) {
                $query->byRating((int)$filterRating);
            }

            // Apply testimonial filter
            if ($filterTestimonial !== null) {
                $query->where('is_testimonial', $filterTestimonial === 'true' || $filterTestimonial === '1');
            }

            // Apply type filter
            if ($filterType && $filterType !== 'all') {
                $query->byType($filterType);
            }

            // Apply reported filter
            if ($filterReported !== null) {
                $query->where('is_reported', $filterReported === 'true' || $filterReported === '1');
            }

            // Apply sorting
            $validSortFields = ['created_at', 'rating', 'email', 'updated_at', 'feedback_type'];
            if (in_array($sortBy, $validSortFields)) {
                $query->orderBy($sortBy, strtoupper($sortOrder) === 'DESC' ? 'desc' : 'asc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // IMPORTANT: Always put featured testimonials first (when not already sorting by is_testimonial)
            if ($sortBy !== 'is_testimonial') {
                // Re-order with testimonials first
                $query->orderByRaw('is_testimonial DESC')
                      ->orderBy($sortBy === 'created_at' || !in_array($sortBy, $validSortFields) ? 'created_at' : $sortBy, 
                               strtoupper($sortOrder) === 'DESC' ? 'desc' : 'asc');
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $feedback = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $feedback->items(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $feedback->lastPage(),
                    'from' => $feedback->firstItem(),
                    'to' => $feedback->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to fetch feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get feedback statistics for admin dashboard
     */
    public function getStats(Request $request)
    {
        try {
            $totalCount = Feedback::count();
            $fiveStarCount = Feedback::where('rating', 5)->count();
            $averageRating = Feedback::avg('rating') ?? 0;
            $fiveStarPercentage = $totalCount > 0 ? round(($fiveStarCount / $totalCount) * 100, 2) : 0;

            return response()->json([
                'data' => [
                    'total_feedback' => $totalCount,
                    'average_rating' => round($averageRating, 2),
                    'five_star_count' => $fiveStarCount,
                    'five_star_percentage' => $fiveStarPercentage,
                    'testimonials_count' => Feedback::where('is_testimonial', true)->count(),
                    'reported_count' => Feedback::where('is_reported', true)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback stats error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single feedback
     */
    public function show($id)
    {
        try {
            $feedback = Feedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'message' => 'Feedback not found'
                ], 404);
            }

            return response()->json([
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark feedback as testimonial or remove testimonial status
     */
    public function updateTestimonial($id, Request $request)
    {
        try {
            $feedback = Feedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'message' => 'Feedback not found'
                ], 404);
            }

            $isTestimonial = $request->get('is_testimonial', true);
            
            // Set featured_at to current time when marking as testimonial (so it appears first)
            // Clear featured_at when unmarking
            $feedback->update([
                'is_testimonial' => $isTestimonial,
                'featured_at' => $isTestimonial ? now() : null
            ]);

            return response()->json([
                'message' => $isTestimonial ? 'Marked as testimonial' : 'Unmarked as testimonial',
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback testimonial update error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report feedback as harmful/abusive
     */
    public function reportFeedback($id, Request $request)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|in:harassment,hate_speech,spam,threats,false_information,other',
                'explanation' => 'nullable|string|max:500'
            ]);

            $feedback = Feedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'message' => 'Feedback not found'
                ], 404);
            }

            $adminId = Auth::id();

            // Update feedback as reported
            $feedback->update([
                'is_reported' => true,
                'reported_reason' => $validated['reason'],
                'reported_explanation' => $validated['explanation'] ?? null,
                'reported_by_admin' => $adminId
            ]);

            // Send email to user
            try {
                Mail::to($feedback->email)->queue(new FeedbackReported($feedback));
            } catch (\Exception $e) {
                Log::warning('Failed to queue feedback reported email', [
                    'feedback_id' => $feedback->id,
                    'email' => $feedback->email,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'message' => 'Feedback reported successfully',
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback report error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to report feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Block user from submitting feedback
     */
    public function blockUser($id, Request $request)
    {
        try {
            $validated = $request->validate([
                'block_until' => 'nullable|date'
            ]);

            $feedback = Feedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'message' => 'Feedback not found'
                ], 404);
            }

            $blockUntil = $validated['block_until'] ? \Carbon\Carbon::parse($validated['block_until']) : null;

            // Block all feedback from this user/email
            Feedback::where(function ($query) use ($feedback) {
                $query->where('user_id', $feedback->user_id)
                      ->orWhere('email', $feedback->email);
            })->update([
                'is_blocked' => true,
                'blocked_until' => $blockUntil
            ]);

            return response()->json([
                'message' => 'User blocked successfully',
                'data' => [
                    'blocked_until' => $blockUntil
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('User block error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to block user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get testimonials for landing page
     */
    public function getTestimonials(Request $request)
    {
        try {
            $limit = $request->get('limit', 3);

            // Sort by featured_at desc so newest featured testimonials appear first
            $testimonials = Feedback::where('is_testimonial', true)
                ->orderByDesc('featured_at')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'data' => $testimonials
            ]);

        } catch (\Exception $e) {
            Log::error('Testimonials fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch testimonials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all testimonials (for modal)
     */
    public function getAllTestimonials(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            // Sort by featured_at desc so newest featured testimonials appear first
            $query = Feedback::where('is_testimonial', true)
                ->orderByDesc('featured_at')
                ->orderByDesc('created_at');

            $total = $query->count();
            $testimonials = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $testimonials->items(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $testimonials->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('All testimonials fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch testimonials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's feedback history
     */
    public function getUserFeedback(Request $request)
    {
        try {
            $userId = Auth::id();
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $filterRating = $request->get('rating', null);
            $filterType = $request->get('feedback_type', null);

            $query = Feedback::where('user_id', $userId)->whereNull('deleted_at');

            // Apply search
            if ($search) {
                $query->where('message', 'like', "%{$search}%");
            }

            // Apply rating filter
            if ($filterRating) {
                $query->where('rating', (int)$filterRating);
            }

            // Apply type filter
            if ($filterType && $filterType !== 'all') {
                $query->where('feedback_type', $filterType);
            }

            // Apply sorting
            $validSortFields = ['created_at', 'rating', 'feedback_type', 'updated_at'];
            if (in_array($sortBy, $validSortFields)) {
                $query->orderBy($sortBy, strtoupper($sortOrder) === 'DESC' ? 'desc' : 'asc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $total = $query->count();
            $feedback = $query->paginate($perPage, ['*'], 'page', $page);

            // Check rate limit
            $settings = FeedbackSettings::getSettings();
            $feedbackCount = Feedback::getFeedbackCount($userId, auth()->user()->email, $settings->cooldown_days);
            $canSubmit = $feedbackCount < $settings->rate_limit;

            return response()->json([
                'data' => $feedback->items(),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $feedback->lastPage()
                ],
                'rate_limit' => [
                    'can_submit' => $canSubmit,
                    'used' => $feedbackCount,
                    'limit' => $settings->rate_limit,
                    'cooldown_days' => $settings->cooldown_days
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('User feedback fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check rate limit for user
     */
    public function checkRateLimit(Request $request)
    {
        try {
            $userId = Auth::id();
            $email = auth()->user()->email ?? null;

            $settings = FeedbackSettings::getSettings();
            $feedbackCount = Feedback::getFeedbackCount($userId, $email, $settings->cooldown_days);
            $canSubmit = $feedbackCount < $settings->rate_limit;
            
            // Get the next available date if limit is reached
            $nextAvailableAt = null;
            if (!$canSubmit) {
                $nextAvailableAt = Feedback::getNextAvailableDate($userId, $email, $settings->cooldown_days);
            }

            return response()->json([
                'data' => [
                    'can_submit' => $canSubmit,
                    'used' => $feedbackCount,
                    'limit' => $settings->rate_limit,
                    'cooldown_days' => $settings->cooldown_days,
                    'is_blocked' => $this->isUserBlocked($userId, $email),
                    'next_available_at' => $nextAvailableAt ? $nextAvailableAt->toISOString() : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Rate limit check error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to check rate limit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete feedback (soft delete)
     */
    public function destroy($id)
    {
        try {
            $feedback = Feedback::find($id);

            if (!$feedback) {
                return response()->json([
                    'message' => 'Feedback not found'
                ], 404);
            }

            $feedback->delete();

            return response()->json([
                'message' => 'Feedback deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback delete error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to delete feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

