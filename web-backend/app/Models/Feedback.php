<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use SoftDeletes;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'email',
        'message',
        'rating',
        'feedback_type',
        'is_testimonial',
        'featured_at',
        'is_reported',
        'reported_reason',
        'reported_explanation',
        'reported_by_admin',
        'is_blocked',
        'blocked_until'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'rating' => 'integer',
        'is_testimonial' => 'boolean',
        'is_reported' => 'boolean',
        'is_blocked' => 'boolean',
        'reported_by_admin' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'blocked_until' => 'datetime',
        'featured_at' => 'datetime'
    ];

    protected $appends = ['privacy_safe_username', 'masked_initial'];

    /**
     * Get the user that provided this feedback
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin that reported this feedback
     */
    public function reportedByAdmin()
    {
        return $this->belongsTo(User::class, 'reported_by_admin');
    }

    /**
     * Scope to get only testimonials
     */
    public function scopeTestimonials($query)
    {
        return $query->where('is_testimonial', true);
    }

    /**
     * Scope to search feedback
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('email', 'like', "%{$searchTerm}%")
                     ->orWhere('message', 'like', "%{$searchTerm}%");
    }

    /**
     * Scope to filter by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope to sort feedback
     */
    public function scopeSortBy($query, $sortBy = 'created_at', $direction = 'desc')
    {
        return $query->orderBy($sortBy, $direction);
    }

    /**
     * Scope to filter by feedback type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('feedback_type', $type);
    }

    /**
     * Scope to get only reported feedback
     */
    public function scopeReported($query)
    {
        return $query->where('is_reported', true);
    }

    /**
     * Scope to get only non-deleted feedback
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope to get user feedback with pagination
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->active();
    }

    /**
     * Check if user has reached feedback rate limit
     */
    public static function hasReachedRateLimit($userId, $email)
    {
        $settings = FeedbackSettings::first();
        $rateLimit = $settings->rate_limit ?? 2;
        $cooldownDays = $settings->cooldown_days ?? 7;

        $lastWeekFeedback = self::where(function ($query) use ($userId, $email) {
            $query->where('user_id', $userId)
                  ->orWhere('email', $email);
        })
        ->where('created_at', '>=', now()->subDays($cooldownDays))
        ->count();

        return $lastWeekFeedback >= $rateLimit;
    }

    /**
     * Get feedback count for user/email in current period
     */
    public static function getFeedbackCount($userId, $email, $days = 7)
    {
        return self::where(function ($query) use ($userId, $email) {
            $query->where('user_id', $userId)
                  ->orWhere('email', $email);
        })
        ->where('created_at', '>=', now()->subDays($days))
        ->count();
    }

    /**
     * Get the oldest feedback in the current cooldown period
     * Returns the date when the rate limit will reset
     */
    public static function getNextAvailableDate($userId, $email, $days = 7)
    {
        $oldestFeedback = self::where(function ($query) use ($userId, $email) {
            $query->where('user_id', $userId)
                  ->orWhere('email', $email);
        })
        ->where('created_at', '>=', now()->subDays($days))
        ->orderBy('created_at', 'asc')
        ->first();

        if ($oldestFeedback) {
            return $oldestFeedback->created_at->addDays($days);
        }

        return null;
    }

    /**
     * Get privacy-safe username from email
     */
    public function getPrivacySafeUsernameAttribute()
    {
        if ($this->user && $this->user->name) {
            // Extract first name only
            $nameParts = explode(' ', trim($this->user->name));
            return $nameParts[0];
        }

        // Extract first name from email
        $email = $this->email;
        $namePart = explode('@', $email)[0];
        // Take first part before any numbers or special chars
        $firstName = preg_replace('/[0-9_.-].*/', '', $namePart);
        return ucfirst($firstName) ?: 'User';
    }

    /**
     * Get masked username initial for avatar
     */
    public function getMaskedInitialAttribute()
    {
        $username = $this->privacy_safe_username;
        return strtoupper(substr($username, 0, 1));
    }
}
