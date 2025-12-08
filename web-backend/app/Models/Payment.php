<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'recorded_by',
        'service_price',
        'amount_paid',
        'discount_amount',
        'shortfall',
        'payment_method_id',
        'payment_status',
        'notes',
        'goods_description',
        'payment_date',
        'is_edited',
        'edit_notes',
        'is_pwd',
        'pwd_discount_amount',
        'is_senior_citizen',
        'senior_discount_amount',
        'is_student',
        'student_discount_amount',
        'total_discount_applied',
        'amount_after_discount',
        'last_edited_at',
        'last_edited_by'
    ];

    protected $casts = [
        'service_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shortfall' => 'decimal:2',
        'payment_date' => 'datetime',
        'is_edited' => 'boolean',
        'is_pwd' => 'boolean',
        'pwd_discount_amount' => 'decimal:2',
        'is_senior_citizen' => 'boolean',
        'senior_discount_amount' => 'decimal:2',
        'is_student' => 'boolean',
        'student_discount_amount' => 'decimal:2',
        'total_discount_applied' => 'decimal:2',
        'amount_after_discount' => 'decimal:2',
        'last_edited_at' => 'datetime'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function lastEditedBy()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Scope: Get unpaid or partial payments
     */
    public function scopeOutstanding($query)
    {
        return $query->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
    }

    /**
     * Scope: Get paid appointments
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Calculate shortfall automatically
     */
    public static function calculateShortfall($servicePrice, $amountPaid, $discountAmount = 0)
    {
        return max(0, $servicePrice - $amountPaid - $discountAmount);
    }

    /**
     * Determine payment status
     */
    public static function determinePaymentStatus($servicePrice, $amountPaid)
    {
        if ($amountPaid <= 0) {
            return 'unpaid';
        } elseif ($amountPaid < $servicePrice) {
            return 'partial';
        } elseif ($amountPaid >= $servicePrice) {
            return 'paid';
        }
    }

    /**
     * Apply category discount (PWD, Senior Citizen, Student)
     */
    public function applyDiscounts($pwdRate = 0, $seniorRate = 0, $studentRate = 0)
    {
        $basePrice = $this->service_price;
        $totalDiscount = 0;

        // PWD Discount
        if ($this->is_pwd && $pwdRate > 0) {
            $this->pwd_discount_amount = ($basePrice * $pwdRate) / 100;
            $totalDiscount += $this->pwd_discount_amount;
        } else {
            $this->pwd_discount_amount = 0;
        }

        // Senior Citizen Discount
        if ($this->is_senior_citizen && $seniorRate > 0) {
            $this->senior_discount_amount = ($basePrice * $seniorRate) / 100;
            $totalDiscount += $this->senior_discount_amount;
        } else {
            $this->senior_discount_amount = 0;
        }

        // Student Discount
        if ($this->is_student && $studentRate > 0) {
            $this->student_discount_amount = ($basePrice * $studentRate) / 100;
            $totalDiscount += $this->student_discount_amount;
        } else {
            $this->student_discount_amount = 0;
        }

        $this->total_discount_applied = $totalDiscount;
        $this->amount_after_discount = max(0, $basePrice - $totalDiscount);

        return $this;
    }

    /**
     * Get total discount amount (category + manual)
     */
    public function getTotalDiscountAttribute()
    {
        return ($this->total_discount_applied ?? 0) + ($this->discount_amount ?? 0);
    }
}
