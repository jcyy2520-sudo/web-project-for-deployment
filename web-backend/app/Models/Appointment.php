<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable fields.
     *
     * SECURITY: status, payment_status, payment_amount, processed_by,
     * completed_by are deliberately EXCLUDED. These must be set explicitly
     * in controller logic to prevent clients from manipulating appointment
     * or payment state via crafted POST data.
     */
    protected $fillable = [
        'user_id',
        'type',
        'service_id',
        'service_type',
        'appointment_date',
        'appointment_time',
        'start_time',
        'end_time',
        'discount_amount',
        'discount_type',
        'payment_type',
        'payment_date',
        'payment_notes',
        'purpose',
        'documents',
        'identification_type',
        'notes',
        'staff_notes',
        'completed_at',
        'completion_notes',
        'outcome_status',
        'original_price',
        'reminder_sent',
        'reminder_sent_at',
        'reminder_level',
        'decline_reason',
        'cancellation_reason',
        'paymongo_checkout_id',
        'paymongo_payment_id',
        'paymongo_checkout_url',
        'paymongo_status',
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'documents' => 'array',
        'completed_at' => 'datetime',
        'payment_date' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'reminder_level' => 'integer',
        'payment_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'appointment_service')
                    ->withPivot('price_at_booking')
                    ->withTimestamps();
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get total paid across all payment records for this appointment
     */
    public function getTotalPaidAttribute()
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function completionRecord()
    {
        return $this->hasOne(CompletionRecord::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function activeRefund()
    {
        return $this->hasOne(Refund::class)->where('status', '!=', 'rejected')->latest();
    }

    // Add this method for appointment types
    public static function getTypes()
    {
        return [
            'consultation' => 'Legal Consultation',
            'document_review' => 'Document Review',
            'contract_drafting' => 'Contract Drafting',
            'court_representation' => 'Court Representation',
            'notary_services' => 'Notary Services',
            'legal_opinion' => 'Legal Opinion',
            'case_evaluation' => 'Case Evaluation',
            'document_notarization' => 'Document Notarization',
            'affidavit' => 'Affidavit',
            'power_of_attorney' => 'Power of Attorney',
            'loan_signing' => 'Loan Signing',
            'real_estate_documents' => 'Real Estate Documents',
            'will_and_testament' => 'Will and Testament',
            'other' => 'Other Legal Services'
        ];
    }

    /**
     * Check if appointment can be completed
     * Only approved appointments can be marked as completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'approved' && !$this->completed_at;
    }

    /**
     * Get completion information
     */
    public function getCompletionInfo()
    {
        return [
            'is_completed' => $this->status === 'completed',
            'completed_at' => $this->completed_at,
            'completion_notes' => $this->completion_notes,
            'completed_by' => $this->completedBy ? $this->completedBy->name : null,
            'can_be_completed' => $this->canBeCompleted()
        ];
    }

    /**
     * Get formatted completion date
     */
    public function getFormattedCompletionDate(): ?string
    {
        return $this->completed_at ? $this->completed_at->format('F d, Y \a\t g:i A') : null;
    }
}