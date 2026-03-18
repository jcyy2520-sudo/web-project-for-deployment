<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Registered;
use App\Mail\RegistrationDecisionMail;

use App\Models\AuditLog;
use App\Models\ActionLog;
use App\Services\ProfanityFilterService;

class AuthController extends Controller
{
    // Rate limiting for registration attempts
    const MAX_REGISTRATION_ATTEMPTS = 5;
    const MAX_VERIFICATION_ATTEMPTS = 3;

    public function registerStep1(Request $request)
    {
        Log::info('=== REGISTER STEP 1 STARTED ===', ['email' => $request->email, 'ip' => $request->ip()]);

        // Rate limiting for registration attempts
        $key = 'register:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, self::MAX_REGISTRATION_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for registration', ['ip' => $request->ip()]);
            return response()->json([
                'message' => 'Too many registration attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ], 429);
        }
        RateLimiter::hit($key, 300); // 5 minutes

        // Validation check (debug logging removed for production safety)

        $request->validate([
            'username' => 'required|string|unique:users|min:3|max:50',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ], [
            'email.unique' => 'This email is already registered. Please use a different email or sign in.',
            'username.unique' => 'This username is already taken. Please choose a different username.',
        ]);

        // Profanity check on username
        $profanityFilter = app(ProfanityFilterService::class);
        $usernameCheck = $profanityFilter->checkUsername($request->username);
        if (!$usernameCheck['is_clean']) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['username' => [$usernameCheck['reason']]]
            ], 422);
        }

        // Additional explicit check (in case validation cache is causing issues)
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            Log::warning('Email already registered (additional check): ' . $request->email, [
                'user_id' => $existingUser->id,
                'username' => $existingUser->username
            ]);
            return response()->json([
                'message' => 'Email already registered. Please use a different email or sign in.'
            ], 422);
        }

        // Clean up old verification codes for this email
        $deleted = VerificationCode::where('email', $request->email)->delete();
        Log::info('Cleaned up old verification codes: ' . $deleted . ' deleted');

        // Generate secure verification code
        $verificationCode = $this->generateSecureVerificationCode();
        Log::info('Verification code generated for: ' . $request->email);

        // Create verification code
        $verification = VerificationCode::create([
            'email' => $request->email,
            'code' => $verificationCode,
            'expires_at' => now()->addMinutes(30),
            'used' => false,
            'ip_address' => $request->ip(),
        ]);

        Log::info('Verification code saved to database', [
            'email' => $request->email,
            'expires_at' => $verification->expires_at,
            'ip' => $request->ip()
        ]);

        try {
            // Send email with verification code
            Log::info('Attempting to send email to: ' . $request->email);
            Mail::to($request->email)->queue(new VerificationCodeMail($verificationCode));
            
            Log::info('✅ Verification email sent successfully to: ' . $request->email);
            
        } catch (\Exception $e) {
            Log::error('❌ Email sending failed: ' . $e->getMessage());
            Log::error('Email error details: ', ['exception' => $e]);
            
            // Delete the verification code since email failed
            $verification->delete();
            
            return response()->json([
                'message' => 'Failed to send verification email. Please check your email address and try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Email service unavailable'
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code sent to your email. The code will expire in 30 minutes.',
            'email' => $request->email,
            'username' => $request->username,
            'expires_in' => '30 minutes',
            'resend_available_after' => 60 // seconds
        ]);
    }

    public function verifyCode(Request $request)
{
    Log::info('=== VERIFY CODE STARTED ===', ['email' => $request->email, 'ip' => $request->ip()]);

    // Rate limiting for verification attempts
    $key = 'verify:' . ($request->ip() ?? 'unknown');
    if (RateLimiter::tooManyAttempts($key, self::MAX_VERIFICATION_ATTEMPTS)) {
        $seconds = RateLimiter::availableIn($key);
        Log::warning('Rate limit exceeded for verification', ['ip' => $request->ip()]);
        return response()->json([
            'message' => 'Too many verification attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
        ], 429);
    }

    $request->validate([
        'email' => 'required|email',
        'code' => 'required|string|size:6|regex:/^[0-9]+$/',
    ]);

    Log::info('Looking for verification code', [
        'email' => $request->email,
        'ip' => $request->ip()
    ]);

    // SECURITY: Check if email has ANY verification record first
    $emailExists = VerificationCode::where('email', $request->email)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->exists();

    if (!$emailExists) {
        RateLimiter::hit($key, 300); // 5 minutes
        Log::warning('Email not found in registration', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);
        
        // Generic message to prevent email enumeration
        return response()->json([
            'message' => 'Invalid or expired verification code. Please request a new code.'
        ], 422);
    }

    // Find the verification code - FIXED: Check for exact match and validity
    $verification = VerificationCode::where('email', $request->email)
        ->where('code', $request->code)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();

    if (!$verification) {
        RateLimiter::hit($key, 300); // 5 minutes
        
        // SECURITY: Log failure without exposing actual codes
        $availableCount = VerificationCode::where('email', $request->email)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->count();
            
        Log::warning('Invalid or expired verification code', [
            'email' => $request->email,
            'available_codes_count' => $availableCount,
            'ip' => $request->ip(),
            'attempts' => RateLimiter::attempts($key)
        ]);
        
        return response()->json([
            'message' => 'Invalid or expired verification code. Please request a new code.',
            'attempts_remaining' => self::MAX_VERIFICATION_ATTEMPTS - RateLimiter::attempts($key)
        ], 422);
    }

    // Mark as used
    $verification->update(['used' => true]);
    Log::info('✅ Verification code marked as used', [
        'email' => $request->email,
        'verification_id' => $verification->id
    ]);

    // Clear rate limiting for successful verification
    RateLimiter::clear($key);

    return response()->json([
        'message' => 'Email verified successfully',
        'verified' => true,
        'email' => $request->email,
        'verified_at' => now()->toISOString()
    ]);
}

    public function completeRegistration(Request $request)
{
    Log::info('=== COMPLETE REGISTRATION STARTED ===', ['email' => $request->email, 'ip' => $request->ip()]);

    // Custom validation without unique checks first
    $validator = Validator::make($request->all(), [
        'username' => 'required|string|min:3|max:50',
        'email' => 'required|email',
        'password' => [
            'required',
            'string',
            'min:8',
        ],
        'first_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
        'last_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
        'phone' => 'required|string|max:20|regex:/^\+?[0-9\s\-\(\)]+$/',
        'address' => 'required|string|max:500',
    ], [
        'first_name.regex' => 'First name can only contain letters and spaces.',
        'last_name.regex' => 'Last name can only contain letters and spaces.',
        'phone.regex' => 'Please enter a valid phone number.',
    ]);

    if ($validator->fails()) {
        Log::warning('Validation failed in completeRegistration', $validator->errors()->toArray());
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Profanity check on username
    $profanityFilter = app(ProfanityFilterService::class);
    $usernameCheck = $profanityFilter->checkUsername($request->username);
    if (!$usernameCheck['is_clean']) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['username' => [$usernameCheck['reason']]]
        ], 422);
    }

    // STRICTER VERIFICATION CHECK - FIXED
    $wasVerified = VerificationCode::where('email', $request->email)
        ->where('used', true)
        ->where('expires_at', '>', now()->subMinutes(30)) // Must not be expired
        ->where('created_at', '>', now()->subHours(1)) // Must be created within last hour
        ->latest() // Get the most recent one
        ->first();

    if (!$wasVerified) {
        Log::warning('Email not verified or verification expired/stolen', [
            'email' => $request->email,
            'available_verifications_count' => VerificationCode::where('email', $request->email)->count()
        ]);
        return response()->json([
            'message' => 'Email verification required or verification has expired. Please restart the registration process.'
        ], 422);
    }

    Log::info('✅ Email verification confirmed for registration', [
        'email' => $request->email,
        'verification_id' => $wasVerified->id,
    ]);

    // Check if user already exists (final check)
    $existingUser = User::where('email', $request->email)->first();
    if ($existingUser) {
        Log::warning('Email already registered during process: ' . $request->email);
        return response()->json([
            'message' => 'This email has been registered during the verification process. Please try again with a different email.'
        ], 422);
    }

    // Check if username is taken
    $existingUsername = User::where('username', $request->username)->first();
    if ($existingUsername) {
        Log::warning('Username already taken: ' . $request->username);
        return response()->json([
            'message' => 'This username is already taken. Please choose a different username.'
        ], 422);
    }

    try {
        DB::beginTransaction();

        $user = new User([
            'username' => $request->username,
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'email_verified_at' => now(),
            'registration_ip' => $request->ip(),
            'verification_method' => 'email',
            'profile_completed' => true, // Email-registered users complete all fields during registration
        ]);

        // Set sensitive fields explicitly (not via mass assignment for security)
        $user->password = Hash::make($request->password);
        $user->is_active = false;
        $user->role = 'client';
        $user->password_login_enabled = true;
        $user->verification_code = Str::random(64);
        $user->verification_code_expires_at = now()->addHours(24);
        $user->save();

        Log::info('✅ User created successfully', ['user_id' => $user->id]);

        // Invalidate relevant caches so new user appears in admin listings
        Cache::forget('users_count');
        Cache::forget('public_init_data');
        Cache::forget('public_services');

        // Clean up ALL verification codes for this email (used and unused)
        $deletedCount = VerificationCode::where('email', $request->email)->delete();
        Log::info('Cleaned up verification codes for: ' . $request->email . ' (' . $deletedCount . ' codes deleted)');

        // Fire registered event
        event(new Registered($user));

        DB::commit();

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ User creation failed: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'message' => 'Failed to create user account. Please try again.',
            'error' => config('app.debug') ? $e->getMessage() : 'Database error'
        ], 500);
    }

    try {
        $confirmUrl = URL::temporarySignedRoute(
            'registration.confirm',
            now()->addHours(24),
            ['token' => $user->verification_code]
        );

        $denyUrl = URL::temporarySignedRoute(
            'registration.reject',
            now()->addHours(24),
            ['token' => $user->verification_code]
        );

        Mail::to($user->email)->queue(new RegistrationDecisionMail($user, $confirmUrl, $denyUrl));
    } catch (\Exception $e) {
        Log::error('Failed to send registration decision email: ' . $e->getMessage());
        return response()->json([
            'message' => 'Registration created, but we could not send the confirmation email. Please try again.',
            'success' => false,
        ], 500);
    }

    // Clear registration rate limit
    RateLimiter::clear('register:' . ($request->ip() ?? 'unknown'));

    ActionLog::create([
        'user_id' => $user->id,
        'action' => 'register',
        'description' => "User {$user->first_name} {$user->last_name} ({$user->email}) registered",
        'model_type' => 'User',
        'model_id' => $user->id,
        'ip_address' => request()->ip(),
        'user_agent' => request()->header('User-Agent'),
        'status' => 'success',
        'integrity_hash' => hash_hmac('sha256', "register|{$user->id}|" . now()->toISOString(), config('app.key', 'fallback')),
    ]);

    return response()->json([
        'message' => 'Registration submitted. Check your email and click It is me to activate login.',
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->role,
            'phone' => $user->phone,
            'address' => $user->address,
        ],
        'success' => true
    ]);
}

    public function resendVerificationCode(Request $request)
    {
        Log::info('=== RESEND VERIFICATION CODE ===', ['email' => $request->email, 'ip' => $request->ip()]);

        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limiting for resend attempts
        $key = 'resend:' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) { // Max 3 resends per email
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Resend rate limit exceeded', ['email' => $request->email]);
            return response()->json([
                'message' => 'Too many resend attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ], 429);
        }
        RateLimiter::hit($key, 600); // 10 minutes

        // Check if user already exists
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            Log::warning('Cannot resend verification - email already registered: ' . $request->email);
            return response()->json([
                'message' => 'Email already registered. Please sign in instead.'
            ], 422);
        }

        // Clean up old verification codes
        $deleted = VerificationCode::where('email', $request->email)->delete();
        Log::info('Cleaned up old verification codes for resend: ' . $deleted . ' deleted');

        // Generate new verification code
        $verificationCode = $this->generateSecureVerificationCode();
        Log::info('New verification code generated for resend: ' . $request->email);

        // Create new verification code
        $verification = VerificationCode::create([
            'email' => $request->email,
            'code' => $verificationCode,
            'expires_at' => now()->addMinutes(30),
            'used' => false,
            'ip_address' => $request->ip(),
        ]);

        try {
            Mail::to($request->email)->queue(new VerificationCodeMail($verificationCode));
            Log::info('✅ Resent verification email to: ' . $request->email);
            
            return response()->json([
                'message' => 'New verification code sent to your email.',
                'email' => $request->email,
                'expires_in' => '30 minutes'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Resend email failed: ' . $e->getMessage());
            $verification->delete();
            
            return response()->json([
                'message' => 'Failed to send verification email. Please try again.'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        Log::info('=== LOGIN ATTEMPT STARTED ===', ['email' => $request->email]);

        // Rate limiting for login attempts
        $key = 'login:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Login rate limit exceeded', ['ip' => $request->ip()]);
            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ], 429);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        Log::info('Looking for user with email: ' . $request->email);

        // Include soft-deleted users so auto-archived accounts can be restored on login
        $user = User::withTrashed()->where('email', $request->email)->first();

        if (!$user) {
            // Timing attack mitigation: perform a dummy hash check so the response time
            // is indistinguishable from an invalid-password path.
            // Must use a valid bcrypt hash — Laravel's BcryptHasher rejects malformed hashes.
            Hash::check($request->password, '$2y$12$ttn2FOy3LM87u5IVyxnyyeZzEa.7hj6QcnsJc2mM5Skvn64OXN70.');
            
            RateLimiter::hit($key, 300); // 5 minutes
            Log::warning('❌ USER NOT FOUND with email: ' . $request->email);
            
            // Log failed login attempt for security audit
            $this->logFailedLogin($request->email, $request->ip(), 'user_not_found');
            
            return response()->json([
                'message' => 'Invalid credentials',
                'success' => false
            ], 401);
        }

        Log::info('✅ USER FOUND:', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active ? 'true' : 'false'
        ]);

        // Email-registered accounts must confirm "It's me" from email before first login.
        if (!$user->is_active
            && $user->verification_method === 'email'
            && !empty($user->verification_code)
            && $user->verification_code_expires_at
            && $user->verification_code_expires_at->isFuture()) {
            return response()->json([
                'message' => 'Please confirm your registration from your email by clicking It is me before logging in.',
                'success' => false,
            ], 422);
        }

        // Google-first accounts may not have password login enabled yet.
        if (($user->verification_method === 'google' || !empty($user->google_id))
            && isset($user->password_login_enabled)
            && !$user->password_login_enabled) {
            return response()->json([
                'message' => 'Password login is not enabled for this account yet. Use Google Sign-In or use Forgot Password to set a password first.',
                'success' => false,
            ], 422);
        }

        // Check if password matches
        $passwordMatches = Hash::check($request->password, $user->password);
        // Password check result logged securely (no password data in logs)

        if (!$passwordMatches) {
            RateLimiter::hit($key, 300); // 5 minutes
            Log::warning('❌ PASSWORD MISMATCH for user: ' . $user->email);
            
            // Log failed login attempt for security audit
            $this->logFailedLogin($request->email, $request->ip(), 'invalid_password', $user->id);
            
            return response()->json([
                'message' => 'Invalid credentials',
                'success' => false
            ], 401);
        }

        // Auto-restore archived users on login (30-day inactivity archive)
        if ($user->trashed() && $user->account_status === 'archived') {
            $user->restore();
            $user->is_active = true;
            $user->account_status = 'active';
            $user->account_status_reason = null;
            $user->last_activity_at = now();
            $user->save();
            Log::info('✅ AUTO-RESTORED archived user on login: ' . $user->email);
            try {
                ActionLog::log('auto_restore', "Auto-restored archived user on login: {$user->first_name} {$user->last_name} ({$user->email})", 'User', $user->id);
            } catch (\Exception $e) {
                Log::warning('Action log failed for auto-restore: ' . $e->getMessage());
            }
        }

        // Check if user is active and not blocked/deactivated/deleted
        if (!$user->is_active || in_array($user->account_status ?? 'active', ['blocked', 'deactivated', 'deleted'])) {
            $statusMessages = [
                'blocked' => 'Your account has been blocked. You may submit an appeal.',
                'deactivated' => 'Your account has been deactivated. Please contact support.',
                'deleted' => 'This account no longer exists.',
            ];
            $msg = $statusMessages[$user->account_status ?? ''] ?? 'Your account has been deactivated. Please contact support.';
            Log::warning('USER ACCOUNT INACTIVE/BLOCKED: ' . $user->email . ' status=' . ($user->account_status ?? 'inactive'));

            // Log failed login attempt for security audit
            $this->logFailedLogin($request->email, $request->ip(), 'account_inactive', $user->id);

            return response()->json([
                'message' => $msg,
                'success' => false
            ], 401);
        }

        Log::info('✅ ALL CHECKS PASSED for user: ' . $user->email);

        try {
            // Clear login rate limit on success
            RateLimiter::clear($key);

            // Record last activity
            $user->last_activity_at = now();
            $user->save();

            // Create token with expiration
            $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;
            Log::info('✅ TOKEN CREATED successfully for user: ' . $user->id);
            
            $response = [
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url,
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
                'token_expires_at' => now()->addDays(7)->toISOString(),
                'success' => true
            ];

            Log::info('✅ LOGIN SUCCESSFUL - Sending response');

            // Log action separately so failures don't break the login response
            try {
                ActionLog::log('login', "User {$user->first_name} {$user->last_name} ({$user->email}) logged in", 'User', $user->id);
            } catch (\Exception $logException) {
                Log::warning('⚠️ Action log failed (non-blocking): ' . $logException->getMessage());
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('❌ TOKEN CREATION FAILED: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Login failed - please try again',
                'success' => false
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            if ($user) {
                $userId = $user->id;
                $userName = $user->first_name . ' ' . $user->last_name;
                $userEmail = $user->email;
                $user->currentAccessToken()->delete();
                Log::info('User logged out successfully');

                // Log action separately so failures don't break the logout response
                try {
                    ActionLog::create([
                        'user_id' => $userId,
                        'action' => 'logout',
                        'description' => "User {$userName} ({$userEmail}) logged out",
                        'model_type' => 'User',
                        'model_id' => $userId,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->header('User-Agent'),
                        'status' => 'success',
                        'integrity_hash' => hash_hmac('sha256', "logout|{$userId}|" . now()->toISOString(), config('app.key', 'fallback')),
                    ]);
                } catch (\Exception $logException) {
                    Log::warning('⚠️ Action log failed (non-blocking): ' . $logException->getMessage());
                }
            } else {
                Log::info('Logout called but no user was authenticated');
            }

            return response()->json([
                'message' => 'Logged out successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Logout failed',
                'success' => false
            ], 500);
        }
    }

    public function confirmRegistration(Request $request, string $token)
    {
        if (!$request->hasValidSignature()) {
            return response('Invalid or expired confirmation link.', 403);
        }

        $user = User::where('verification_code', $token)
            ->where('verification_code_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response('This confirmation link is invalid or has expired.', 410);
        }

        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->is_active = true;
        $user->save();

        return redirect()->away(rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/auth/callback#registration=confirmed');
    }

    public function rejectRegistration(Request $request, string $token)
    {
        if (!$request->hasValidSignature()) {
            return response('Invalid or expired rejection link.', 403);
        }

        $user = User::where('verification_code', $token)
            ->where('verification_code_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response('This rejection link is invalid or has expired.', 410);
        }

        $user->is_active = false;
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->tokens()->delete();
        $user->save();

        return response('The registration was marked as not me and the account is blocked from login.', 200);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'data' => $user,
            'success' => true
        ]);
    }

    /**
     * Generate a secure verification code
     */
    private function generateSecureVerificationCode(): string
    {
        return sprintf("%06d", random_int(1, 999999));
    }

    /**
     * Check verification status (for frontend)
     */
    public function checkVerificationStatus(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $isVerified = VerificationCode::where('email', $request->email)
            ->where('used', true)
            ->where('expires_at', '>', now()->subMinutes(30))
            ->exists();

        return response()->json([
            'verified' => $isVerified,
            'email' => $request->email
        ]);
    }

    // SECURITY: debugEmailStatus method removed — it exposed verification codes and database name.
    // Rollback: restore this method from version control if needed for local debugging only.

    /**
     * Log failed login attempts for security monitoring
     */
    private function logFailedLogin(string $email, ?string $ip, string $reason, ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'login_failed',
                'entity_type' => 'auth',
                'entity_id' => $userId,
                'description' => "Failed login attempt for {$email}: {$reason}",
                'old_values' => null,
                'new_values' => [
                    'email' => $email,
                    'reason' => $reason,
                    'timestamp' => now()->toISOString(),
                ],
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'status' => 'failed',
                'error_message' => $reason,
            ]);

            // Check for suspicious patterns (many failed attempts from same IP)
            $recentFailures = AuditLog::where('action', 'login_failed')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentFailures >= 10) {
                Log::channel('security')->critical('Potential brute force attack detected', [
                    'ip' => $ip,
                    'email' => $email,
                    'failed_attempts_last_hour' => $recentFailures,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log login attempt: ' . $e->getMessage());
        }
    }
}