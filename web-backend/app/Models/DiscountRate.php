<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_type',
        'discount_percentage',
        'description',
        'is_active'
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Get discount rate by type
     */
    public static function getByType($type)
    {
        return self::where('discount_type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active discounts
     */
    public static function activeDiscounts()
    {
        return self::where('is_active', true)->get();
    }
}
