<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token_uuid',
        'user_id',
        'user_uuid',
        'token_hash',
        'purpose',
        'metadata',
        'expires_at',
        'used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'metadata' => 'json'
    ];

    protected $hidden = [
        'token_hash'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function createToken($userId, $purpose = 'general', $expiresIn = 3600, $metadata = null)
    {
        $user = User::findOrFail($userId);
        
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        
        $accessToken = self::create([
            'token_uuid' => \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'user_uuid' => $user->uuid,
            'token_hash' => $tokenHash,
            'purpose' => $purpose,
            'metadata' => $metadata,
            'expires_at' => now()->addSeconds($expiresIn)
        ]);

        return [
            'token' => $token,
            'uuid' => $accessToken->token_uuid->toString(),
            'expires_at' => $accessToken->expires_at
        ];
    }

    public static function validateToken($token)
    {
        $tokenHash = hash('sha256', $token);
        
        $accessToken = self::where('token_hash', $tokenHash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $accessToken;
    }

    public static function revokeToken($token)
    {
        $tokenHash = hash('sha256', $token);
        return self::where('token_hash', $tokenHash)->update(['expires_at' => now()]);
    }

    public static function revokeAllUserTokens($userId)
    {
        return self::where('user_id', $userId)->update(['expires_at' => now()]);
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function markAsUsed()
    {
        $this->update(['used_at' => now()]);
    }
}
