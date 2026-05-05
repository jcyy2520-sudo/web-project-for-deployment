<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

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

    protected $hidden = [
        'code',
        'ip_address',
    ];

    public function setCodeAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['code'] = null;
            return;
        }

        $this->attributes['code'] = $this->isHashedCode($value)
            ? $value
            : Hash::make($value);
    }

    // Check if code is valid (not used and not expired)
    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public function matchesCode(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code);
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

    public static function findActiveMatchForEmail(string $email, string $plainCode): ?self
    {
        return static::query()
            ->where('email', $email)
            ->valid()
            ->latest('id')
            ->get()
            ->first(fn (self $verificationCode) => $verificationCode->matchesCode($plainCode));
    }

    private function isHashedCode(string $value): bool
    {
        return ($value !== '') && (($info = password_get_info($value))['algoName'] ?? 'unknown') !== 'unknown';
    }
}