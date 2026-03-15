<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountAppeal extends Model
{
    protected $fillable = [
        'token',
        'user_id',
        'user_email',
        'user_name',
        'action_type',
        'action_reason',
        'appeal_category',
        'appeal_message',
        // 'status', 'admin_response', 'acted_by', 'resolved_by', 'resolved_at' excluded —
        // admin-only resolution fields, set explicitly in controller logic.
        'appeal_submitted_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'appeal_submitted_at' => 'datetime',
    ];

    /**
     * Generate a unique appeal token.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Appeal categories available for users.
     */
    public static function appealCategories(): array
    {
        return [
            'wrongful_action' => 'I believe this action was taken by mistake',
            'account_recovery' => 'I need to recover important data from my account',
            'misunderstanding' => 'There was a misunderstanding that led to this action',
            'policy_violation' => 'I was not aware of the policy I violated',
            'technical_issue' => 'A technical issue caused the problem',
            'other' => 'Other reason',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actedByAdmin()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function resolvedByAdmin()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('appeal_submitted_at');
    }

    /**
     * Check if the appeal has been submitted by the user.
     */
    public function isSubmitted(): bool
    {
        return $this->appeal_submitted_at !== null;
    }

    /**
     * Check if the appeal is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
