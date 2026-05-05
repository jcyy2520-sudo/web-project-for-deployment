<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Support\Str;

class TokenService
{
    const TOKEN_LENGTH = 32;
    const DEFAULT_EXPIRATION = 3600; // 1 hour
    const EMAIL_VERIFICATION_EXPIRATION = 86400; // 24 hours
    const PASSWORD_RESET_EXPIRATION = 3600; // 1 hour
    const SHARE_LINK_EXPIRATION = 604800; // 7 days

    /**
     * Generate a tokenized URL payload for sensitive operations.
     */
    public static function generateTokenizedUrl($userId, $purpose = 'general', $expiresIn = null, $metadata = null, $baseUrl = null)
    {
        if (is_string($metadata) && $baseUrl === null) {
            $baseUrl = $metadata;
            $metadata = null;
        }

        if (!$expiresIn) {
            $expiresIn = self::getExpirationForPurpose($purpose);
        }

        $tokenData = AccessToken::createToken($userId, $purpose, $expiresIn, $metadata);
        $baseUrl = rtrim((string) ($baseUrl ?? config('app.url')), '/');

        return [
            'token' => $tokenData['token'],
            'uuid' => $tokenData['uuid'],
            'expires_at' => $tokenData['expires_at'],
            'url' => "{$baseUrl}/verify-token?token={$tokenData['token']}&uuid={$tokenData['uuid']}&purpose={$purpose}",
            'secure_url' => "{$baseUrl}/api/verify-token/{$tokenData['uuid']}"
        ];
    }

    /**
     * Verify token and get user
     */
    public static function verifyToken($token, $purpose = null)
    {
        $accessToken = AccessToken::validateToken($token);

        if (!$accessToken) {
            return null;
        }

        if ($purpose && $accessToken->purpose !== $purpose) {
            return null;
        }

        if ($accessToken->isExpired()) {
            return null;
        }

        $accessToken->markAsUsed();

        return [
            'user' => $accessToken->user,
            'token_data' => $accessToken,
            'purpose' => $accessToken->purpose,
            'metadata' => $accessToken->metadata
        ];
    }

    /**
     * Verify token by UUID
     * 
     * SECURITY: Token is REQUIRED for verification to prevent UUID-only access.
     * The $token parameter is kept nullable only for backward compatibility with
     * email verification links that use UUID-only flow.
     */
    public static function verifyTokenByUuid($tokenUuid, $token = null)
    {
        $accessToken = AccessToken::where('token_uuid', $tokenUuid)->first();

        if (!$accessToken) {
            return null;
        }

        if ($accessToken->isExpired()) {
            return null;
        }

        // Validate token hash if provided
        if ($token) {
            $tokenHash = hash('sha256', $token);
            if (!hash_equals($accessToken->token_hash, $tokenHash)) {
                return null;
            }
        } elseif ($accessToken->purpose !== 'email_verification') {
            // SECURITY: Require token for all purposes except email verification
            // to prevent UUID-only access to sensitive operations like password reset
            return null;
        }

        $accessToken->markAsUsed();

        return [
            'user' => $accessToken->user,
            'token_data' => $accessToken,
            'purpose' => $accessToken->purpose,
            'metadata' => $accessToken->metadata
        ];
    }

    /**
     * Revoke a token
     */
    public static function revokeToken($token)
    {
        return AccessToken::revokeToken($token);
    }

    /**
     * Revoke all tokens for a user
     */
    public static function revokeAllUserTokens($userId)
    {
        return AccessToken::revokeAllUserTokens($userId);
    }

    /**
     * Get expiration time based on purpose
     */
    private static function getExpirationForPurpose($purpose)
    {
        return match ($purpose) {
            'email_verification' => self::EMAIL_VERIFICATION_EXPIRATION,
            'password_reset' => self::PASSWORD_RESET_EXPIRATION,
            'share_link' => self::SHARE_LINK_EXPIRATION,
            default => self::DEFAULT_EXPIRATION,
        };
    }

    /**
     * Generate UUID for resources
     */
    public static function generateUuid()
    {
        return (string) Str::uuid();
    }

    /**
     * Ensure user has UUID
     */
    public static function ensureUserHasUuid(User $user)
    {
        if (!$user->uuid) {
            $user->update(['uuid' => self::generateUuid()]);
        }
        return $user;
    }

    /**
     * Get user by UUID
     */
    public static function getUserByUuid($uuid)
    {
        return User::where('uuid', $uuid)->first();
    }

    /**
     * Get secure user data (without sensitive fields)
     */
    public static function getSecureUserData(User $user)
    {
        return [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'display_name' => trim(collect([$user->first_name, $user->last_name])->filter()->implode(' ')),
        ];
    }
}
