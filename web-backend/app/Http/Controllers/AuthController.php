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
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    // Rate limiting for registration attempts
    const MAX_REGISTRATION_ATTEMPTS = 5;
    const MAX_VERIFICATION_ATTEMPTS = 3;

    public function registerStep1(Request $request)
    {
        Log::info('=== REGISTER STEP 1 STARTED ===', $request->all());

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

        // DEBUG: Log database state before validation
        Log::info('DEBUG: Checking database state for email: ' . $request->email);
        $userCount = User::where('email', $request->email)->count();
        $verificationCount = VerificationCode::where('email', $request->email)->count();
        Log::info('DEBUG: Users with this email: ' . $userCount . ', Verification codes: ' . $verificationCount);

        $request->validate([
            'username' => 'required|string|unique:users|min:3|max:255|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'email.unique' => 'This email is already registered. Please use a different email or sign in.',
            'username.unique' => 'This username is already taken. Please choose a different username.'
        ]);

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
        Log::info('Generated verification code: ' . $verificationCode);

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
            'code' => $verificationCode,
            'expires_at' => $verification->expires_at,
            'ip' => $request->ip()
        ]);

        try {
            // Send email with verification code
            Log::info('Attempting to send email to: ' . $request->email);
            Mail::to($request->email)->send(new VerificationCodeMail($verificationCode));
            
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
    Log::info('=== VERIFY CODE STARTED ===', $request->all());

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
        'code' => $request->code
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
        
        return response()->json([
            'message' => 'Email not found. Please complete the registration process first.'
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
        
        // Log all available codes for this email for debugging
        $availableCodes = VerificationCode::where('email', $request->email)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->get();
            
        Log::warning('Invalid or expired verification code', [
            'email' => $request->email,
            'code_attempted' => $request->code,
            'available_codes' => $availableCodes->pluck('code'),
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
        'code' => $request->code,
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
    Log::info('=== COMPLETE REGISTRATION STARTED ===', $request->all());

    // Custom validation without unique checks first
    $validator = Validator::make($request->all(), [
        'username' => 'required|string|min:3|max:255|regex:/^[a-zA-Z0-9_]+$/',
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
        'username.regex' => 'Username can only contain letters, numbers, and underscores.'
    ]);

    if ($validator->fails()) {
        Log::warning('Validation failed in completeRegistration', $validator->errors()->toArray());
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
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
            'available_verifications' => VerificationCode::where('email', $request->email)->get()
        ]);
        return response()->json([
            'message' => 'Email verification required or verification has expired. Please restart the registration process.'
        ], 422);
    }

    Log::info('✅ Email verification confirmed for registration', [
        'email' => $request->email,
        'verification_id' => $wasVerified->id,
        'code_used' => $wasVerified->code
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

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'role' => 'client',
            'email_verified_at' => now(),
            'is_active' => true,
            'registration_ip' => $request->ip(),
        ]);

        Log::info('✅ User created successfully', ['user_id' => $user->id]);

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
        $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;
        Log::info('✅ Auth token created for user: ' . $user->id);
    } catch (\Exception $e) {
        Log::error('❌ Token creation failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Registration completed but login failed. Please try logging in.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
            ],
            'success' => true
        ]);
    }

    // Clear registration rate limit
    RateLimiter::clear('register:' . ($request->ip() ?? 'unknown'));

    return response()->json([
        'message' => 'User registered successfully',
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
        'token' => $token,
        'token_expires_at' => now()->addDays(7)->toISOString(),
        'success' => true
    ]);
}

    public function resendVerificationCode(Request $request)
    {
        Log::info('=== RESEND VERIFICATION CODE ===', $request->all());

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
        Log::info('Generated new verification code for resend: ' . $verificationCode);

        // Create new verification code
        $verification = VerificationCode::create([
            'email' => $request->email,
            'code' => $verificationCode,
            'expires_at' => now()->addMinutes(30),
            'used' => false,
            'ip_address' => $request->ip(),
        ]);

        try {
            Mail::to($request->email)->send(new VerificationCodeMail($verificationCode));
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

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            RateLimiter::hit($key, 300); // 5 minutes
            Log::warning('❌ USER NOT FOUND with email: ' . $request->email);
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

        // Check if password matches
        $passwordMatches = Hash::check($request->password, $user->password);
        Log::info('🔐 PASSWORD CHECK: ' . ($passwordMatches ? '✅ MATCH' : '❌ NO MATCH'));

        if (!$passwordMatches) {
            RateLimiter::hit($key, 300); // 5 minutes
            Log::warning('❌ PASSWORD MISMATCH for user: ' . $user->email);
            return response()->json([
                'message' => 'Invalid credentials',
                'success' => false
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            Log::warning('❌ USER ACCOUNT INACTIVE: ' . $user->email);
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact support.',
                'success' => false
            ], 401);
        }

        Log::info('✅ ALL CHECKS PASSED for user: ' . $user->email);

        try {
            // Clear login rate limit on success
            RateLimiter::clear($key);

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
                ],
                'token' => $token,
                'token_expires_at' => now()->addDays(7)->toISOString(),
                'success' => true
            ];

            Log::info('✅ LOGIN SUCCESSFUL - Sending response');

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
            $request->user()->currentAccessToken()->delete();
            Log::info('User logged out successfully');

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

    /**
     * Debug endpoint to check email status
     */
    public function debugEmailStatus(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $userExists = User::where('email', $request->email)->exists();
        $verificationCodes = VerificationCode::where('email', $request->email)->get();
        
        return response()->json([
            'email' => $request->email,
            'user_exists' => $userExists,
            'verification_codes_count' => $verificationCodes->count(),
            'verification_codes' => $verificationCodes,
            'database' => config('database.connections.mysql.database')
        ]);
    }
}