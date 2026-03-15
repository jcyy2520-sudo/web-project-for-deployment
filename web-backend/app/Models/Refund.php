<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'requested_by',
        'refund_amount',
        'original_amount',
        'reason',
        'description',
        // 'status', 'approved_by', 'approval_notes', 'approved_at', 'completed_at', 'transaction_id',
        // 'rejection_reason' excluded — admin-only fields, set explicitly in controller logic.
        'refund_method',
        'payment_method_reversed',
        'is_partial'
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_partial' => 'boolean'
    ];

    /**
     * Relationship: Appointment
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Relationship: User who requested the refund
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship: Admin who approved the refund
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope: Get pending refunds
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved refunds
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get completed refunds
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get rejected refunds
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Get by date range
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
