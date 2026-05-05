<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

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

    public function matchesCode(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code);
    }

    /**
     * Scope: active (not used, not expired)
     */
    public function scopeActive($query)
    {
        return $query->where('used', false)->where('expires_at', '>', now());
    }

    public static function findActiveMatchForEmail(string $email, string $plainCode): ?self
    {
        return static::query()
            ->where('email', $email)
            ->active()
            ->latest('id')
            ->get()
            ->first(fn (self $resetCode) => $resetCode->matchesCode($plainCode));
    }

    private function isHashedCode(string $value): bool
    {
        return ($value !== '') && (($info = password_get_info($value))['algoName'] ?? 'unknown') !== 'unknown';
    }
}
