<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $fillable = [
        'email',
        'code',
        'used',
        'expires_at',
        'ip_address',
    ];

    protected $hidden = [
        'code',
        'ip_address',
    ];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope: active (not used, not expired)
     */
    public function scopeActive($query)
    {
        return $query->where('used', false)->where('expires_at', '>', now());
    }
}
