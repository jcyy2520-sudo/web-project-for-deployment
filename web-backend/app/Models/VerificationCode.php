<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used',
        'ip_address'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean'
    ];

    // Check if code is valid (not used and not expired)
    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    // Mark as used
    public function markAsUsed()
    {
        $this->update(['used' => true]);
    }

    // Scope for valid codes
    public function scopeValid($query)
    {
        return $query->where('used', false)
                    ->where('expires_at', '>', now());
    }

    // Scope for expired codes
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}