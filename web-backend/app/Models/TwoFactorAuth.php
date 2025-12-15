<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Two-Factor Authentication Model
 * 
 * Stores 2FA configuration and secrets for users.
 * All sensitive data (secret, recovery codes) are encrypted at rest.
 */
class TwoFactorAuth extends Model
{
    use HasFactory;

    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'secret',
        'recovery_codes',
        'enabled',
        'enabled_at',
        'last_used_at',
        'preferred_method',
        'phone_number',
        'failed_attempts',
        'locked_until',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_attempts' => 'integer',
    ];

    protected $hidden = [
        'secret',
        'recovery_codes',
    ];

    /**
     * Get the user that owns the 2FA configuration
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Encrypt the secret before storing
     */
    public function setSecretAttribute(?string $value): void
    {
        $this->attributes['secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt the secret when retrieving
     */
    public function getSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Encrypt recovery codes before storing
     */
    public function setRecoveryCodesAttribute(?array $value): void
    {
        $this->attributes['recovery_codes'] = $value 
            ? Crypt::encryptString(json_encode($value)) 
            : null;
    }

    /**
     * Decrypt recovery codes when retrieving
     */
    public function getRecoveryCodesAttribute(?string $value): ?array
    {
        if (!$value) {
            return null;
        }
        
        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if account is locked due to too many failed attempts
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Increment failed attempts and potentially lock the account
     */
    public function incrementFailedAttempts(): void
    {
        $this->failed_attempts++;
        
        // Lock after 5 failed attempts for 15 minutes
        if ($this->failed_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
        }
        
        $this->save();
    }

    /**
     * Reset failed attempts on successful verification
     */
    public function resetFailedAttempts(): void
    {
        $this->failed_attempts = 0;
        $this->locked_until = null;
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * Use a recovery code
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->recovery_codes;
        
        if (!$codes) {
            return false;
        }

        $key = array_search($code, $codes);
        
        if ($key === false) {
            return false;
        }

        // Remove the used code
        unset($codes[$key]);
        $this->recovery_codes = array_values($codes);
        $this->save();

        return true;
    }

    /**
     * Generate new recovery codes
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        
        $this->recovery_codes = $codes;
        $this->save();

        return $codes;
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRemainingRecoveryCodesCount(): int
    {
        return $this->recovery_codes ? count($this->recovery_codes) : 0;
    }
}
