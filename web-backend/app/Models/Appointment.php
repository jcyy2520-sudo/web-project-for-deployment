<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'staff_id',
        'type',
        'service_id',
        'service_type',
        'appointment_date',
        'appointment_time',
        'status',
        'purpose',
        'documents',
        'notes',
        'staff_notes',
        'completed_at',
        'completion_notes',
        'completed_by'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'documents' => 'array',
        'completed_at' => 'datetime',
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

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
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