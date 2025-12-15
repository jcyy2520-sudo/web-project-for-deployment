# Tokenized URLs & UUID Implementation Guide

## Overview
This implementation provides secure tokenized URLs and UUID-based resource identification for your application. All sensitive operations are protected with time-limited tokens.

## Architecture

### Components Implemented

1. **UUID System** - Every user now has a unique UUID
   - Automatically generated on user creation
   - Used for resource identification
   - Prevents ID enumeration attacks

2. **TokenService** - Central service for token operations
   - Generate tokenized URLs for sensitive operations
   - Validate and verify tokens
   - Automatic expiration handling
   - Token revocation capabilities

3. **AccessToken Model** - Database-backed token storage
   - Stores hashed tokens (never plain text)
   - Tracks token purpose and metadata
   - Manages expiration dates
   - Prevents token reuse

4. **ValidateAccessToken Middleware** - Protects routes
   - Validates token format and hash
   - Checks expiration
   - Verifies token purpose

## Security Features

✅ **Token Hashing** - Tokens are SHA256 hashed in database
✅ **Expiration** - All tokens have configurable expiration times
✅ **UUID Anonymity** - UUIDs cannot be sequentially enumerated
✅ **Single Use Tracking** - Each token use is logged
✅ **Purpose Binding** - Tokens tied to specific actions
✅ **Revocation** - Tokens can be revoked immediately
✅ **Throttling** - Sensitive endpoints rate-limited

## Available Routes

### 1. Password Reset (1 hour expiration)
```
POST /api/password-reset-request
Body: { "email": "user@example.com" }

POST /api/password-reset/{uuid}
Body: { 
  "token": "...", 
  "password": "new_password",
  "password_confirmation": "new_password"
}
```

### 2. Email Verification (24 hour expiration)
```
GET /api/verify-email/{uuid}
Query: ?token=...
```

### 3. Share Links (7 day expiration)
```
POST /api/generate-share-token/{resourceType}/{resourceId}
Headers: Authorization: Bearer {token}

GET /api/shared-resource/{uuid}
Query: ?token=...
```

## Token Expiration Times

- **Default**: 1 hour (3600 seconds)
- **Email Verification**: 24 hours (86400 seconds)
- **Password Reset**: 1 hour (3600 seconds)
- **Share Links**: 7 days (604800 seconds)

Customize in `app/Services/TokenService.php`

## Usage Examples

### Generate Password Reset Link
```php
$user = User::find($userId);
$tokenData = TokenService::generateTokenizedUrl(
    $user->id,
    'password_reset',
    3600
);

// Send link: $tokenData['secure_url']
```

### Verify Token
```php
$result = TokenService::verifyTokenByUuid(
    $uuid,
    $token
);

if ($result) {
    $user = $result['user'];
    $purpose = $result['purpose'];
}
```

### Revoke Tokens
```php
// Revoke single token
TokenService::revokeToken($token);

// Revoke all user tokens
TokenService::revokeAllUserTokens($userId);
```

### Get User by UUID
```php
$user = TokenService::getUserByUuid($uuid);
```

## Database Schema

### users table
- `id` (primary key)
- `uuid` (unique, non-nullable)
- ... other user columns

### access_tokens table
- `id` (primary key)
- `token_uuid` (unique identifier)
- `user_id` (foreign key)
- `user_uuid` (denormalized for queries)
- `token_hash` (SHA256, unique)
- `purpose` (email_verification, password_reset, share_link, etc)
- `metadata` (JSON, additional context)
- `expires_at` (datetime)
- `used_at` (datetime)
- `created_at`, `updated_at`

Indexes optimized for:
- Token hash lookups
- User + purpose queries
- Expiration time checks

## Integration Points

### Protected Routes
Add middleware to routes needing token validation:
```php
Route::get('/sensitive/{uuid}', Controller@action)
    ->middleware('token:purpose_name');
```

### Email Notifications
Tokens are safe to include in URLs:
- No database lookups required to send
- Hashed in database (cannot reverse)
- Time-limited by default
- Can be revoked if compromised

### Frontend Implementation
```javascript
// Password reset link from email
fetch('/api/password-reset/uuid?token=token_value', {
  method: 'POST',
  body: JSON.stringify({ 
    password: 'new_pass',
    password_confirmation: 'new_pass'
  })
})

// Share link access
fetch('/api/shared-resource/uuid?token=token_value')
```

## Monitoring & Maintenance

### Check Active Tokens
```php
AccessToken::where('expires_at', '>', now())->count()
```

### Clean Expired Tokens
```php
// Create a scheduled job that runs daily
AccessToken::where('expires_at', '<', now())->delete();
```

### Audit Token Usage
```php
// All tokens with usage timestamps
AccessToken::where('used_at', '!=', null)->get()
```

## Best Practices

1. **Always use HTTPS** - Tokens in URLs require encryption in transit
2. **Short expiration** - Shorter is better for sensitive operations
3. **Email only in messages** - Never log plaintext tokens
4. **Revoke on login** - Clear old tokens when user logs in
5. **Validate purpose** - Always check token purpose matches operation
6. **Rate limit** - Throttle password reset and verification endpoints
7. **Monitor failures** - Log failed token validations for security alerts

## Troubleshooting

### Token not validating
- Check expiration: `$token->expires_at < now()`
- Verify hashing: `hash('sha256', $token) === $token->token_hash`
- Confirm purpose: Matches expected purpose value

### UUID issues
- All users must have UUID (migration handles this)
- Use `TokenService::ensureUserHasUuid($user)` to backfill
- Never modify UUID after creation

### Performance
- Tokens indexed by hash (O(1) lookup)
- User queries filtered by purpose (indexed)
- Regular cleanup of expired tokens recommended

## Migration Path

For existing applications:
1. ✅ Migration runs automatically: adds UUID to existing users
2. ✅ AccessToken table created in separate migration
3. ✅ No data loss - existing authentication unaffected
4. ✅ Backwards compatible with sanctum tokens

All users automatically get UUIDs on first migration run.
