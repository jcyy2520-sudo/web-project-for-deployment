<?php

namespace App\Services;

use App\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-Factor Authentication Service
 * 
 * Provides infrastructure for implementing 2FA including:
 * - TOTP (Time-based One-Time Password) support
 * - Recovery codes management
 * - 2FA verification attempt tracking
 */
class TwoFactorAuthService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }
    /**
     * Enable 2FA for a user
     */
    public function enable(User $user, string $method = 'totp'): array
    {
        // Generate a secret key (32 character base32 string)
        $secret = $this->generateSecret();

        $twoFactor = TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $secret,
                'preferred_method' => $method,
                'enabled' => false, // Not enabled until confirmed
            ]
        );

        // Generate recovery codes
        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        return [
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
            'qr_code_url' => $this->getQrCodeUrl($user, $secret),
            'message' => 'Scan the QR code with your authenticator app, then confirm with a code.',
        ];
    }

    /**
     * Confirm 2FA setup with a verification code
     */
    public function confirm(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();

        if (!$twoFactor || !$twoFactor->secret) {
            return false;
        }

        // Verify the code
        if (!$this->verifyCode($twoFactor->secret, $code)) {
            $this->logAttempt($user->id, 'totp', false);
            return false;
        }

        // Enable 2FA
        $twoFactor->update([
            'enabled' => true,
            'enabled_at' => now(),
        ]);

        // Update user
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->logAttempt($user->id, 'totp', true);

        return true;
    }

    /**
     * Verify a 2FA code during login
     */
    public function verify(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('enabled', true)
            ->first();

        if (!$twoFactor) {
            return false;
        }

        // Check if locked
        if ($twoFactor->isLocked()) {
            Log::warning('2FA verification attempted on locked account', [
                'user_id' => $user->id,
                'locked_until' => $twoFactor->locked_until,
            ]);
            return false;
        }

        // Try TOTP verification
        if ($this->verifyCode($twoFactor->secret, $code)) {
            $twoFactor->resetFailedAttempts();
            $this->logAttempt($user->id, 'totp', true);
            return true;
        }

        // Try recovery code
        if ($twoFactor->useRecoveryCode($code)) {
            $twoFactor->resetFailedAttempts();
            $this->logAttempt($user->id, 'recovery_code', true);
            
            // Warn if low on recovery codes
            if ($twoFactor->getRemainingRecoveryCodesCount() <= 2) {
                Log::warning('User running low on 2FA recovery codes', [
                    'user_id' => $user->id,
                    'remaining' => $twoFactor->getRemainingRecoveryCodesCount(),
                ]);
            }
            
            return true;
        }

        // Failed verification
        $twoFactor->incrementFailedAttempts();
        $this->logAttempt($user->id, 'totp', false);

        return false;
    }

    /**
     * Disable 2FA for a user
     */
    public function disable(User $user, string $password): bool
    {
        // Verify password before disabling
        if (!\Hash::check($password, $user->password)) {
            return false;
        }

        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        
        if ($twoFactor) {
            $twoFactor->delete();
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        return true;
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user): ?array
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('enabled', true)
            ->first();

        if (!$twoFactor) {
            return null;
        }

        return $twoFactor->generateRecoveryCodes();
    }

    /**
     * Check if 2FA is required for a user
     */
    public function isRequired(User $user): bool
    {
        // 2FA is required for admins in production
        if ($user->role === 'admin' && config('app.env') === 'production') {
            return true;
        }

        // Check if user has 2FA enabled
        return $user->two_factor_enabled;
    }

    /**
     * Get 2FA status for a user
     */
    public function getStatus(User $user): array
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();

        return [
            'enabled' => (bool) $user->two_factor_enabled,
            'confirmed_at' => $user->two_factor_confirmed_at,
            'method' => $twoFactor?->preferred_method ?? 'totp',
            'recovery_codes_remaining' => $twoFactor?->getRemainingRecoveryCodesCount() ?? 0,
            'last_used_at' => $twoFactor?->last_used_at,
        ];
    }

    /**
     * Generate a random secret using Google2FA
     */
    private function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Verify TOTP code using Google2FA
     * 
     * Allows for time drift of 1 window (30 seconds) in each direction
     */
    private function verifyCode(string $secret, string $code): bool
    {
        try {
            // Allow 1 window of time drift (30 seconds each direction)
            return $this->google2fa->verifyKey($secret, $code, 1);
        } catch (\Exception $e) {
            Log::error('TOTP verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate QR code URL for authenticator app setup
     */
    private function getQrCodeUrl(User $user, string $secret): string
    {
        $issuer = urlencode(config('app.name', 'Laravel'));
        
        return $this->google2fa->getQRCodeUrl(
            $issuer,
            $user->email,
            $secret
        );
    }

    /**
     * Get current TOTP code for testing purposes (development only)
     */
    public function getCurrentCode(User $user): ?string
    {
        if (config('app.env') === 'production') {
            return null;
        }

        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        
        if (!$twoFactor || !$twoFactor->secret) {
            return null;
        }

        return $this->google2fa->getCurrentOtp($twoFactor->secret);
    }

    /**
     * Log 2FA verification attempt
     */
    private function logAttempt(int $userId, string $method, bool $successful): void
    {
        try {
            DB::table('two_factor_attempts')->insert([
                'user_id' => $userId,
                'method' => $method,
                'successful' => $successful,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log 2FA attempt: ' . $e->getMessage());
        }
    }
}
