<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
        try {
            $mode = $request->query('mode', 'login');
            if (!in_array($mode, ['login', 'register'], true)) {
                $mode = 'login';
            }

            \Log::info('Redirecting to Google OAuth', [
                'mode' => $mode,
            ]);

            return Socialite::driver('google')
                ->stateless()
                ->with(['state' => $mode])
                ->scopes(['profile', 'email'])
                ->redirect();
        } catch (\Exception $e) {
            \Log::error('Google redirect error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return $this->redirectToFrontend([
                'oauth' => 'error',
                'message' => 'Failed to start Google authentication. Please try again.',
                'tab' => 'login',
            ]);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $mode = $request->query('state', 'login');
            if (!in_array($mode, ['login', 'register'], true)) {
                $mode = 'login';
            }

            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // SECURITY: Explicitly verify that the email is verified by Google
            $isEmailVerified = $googleUser->user['email_verified'] ?? false;
            if (!$isEmailVerified) {
                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'Your Google email is not verified. Please verify your Google account first.',
                    'tab' => $mode,
                ]);
            }

            $googleId = $googleUser->getId();
            $googleEmail = filter_var($googleUser->getEmail(), FILTER_SANITIZE_EMAIL);
            $googleName = strip_tags($googleUser->getName());
            $givenName = strip_tags($googleUser->user['given_name'] ?? '');
            $familyName = strip_tags($googleUser->user['family_name'] ?? '');

            $userByGoogle = User::where('google_id', $googleId)->first();
            $userByEmail = $googleEmail ? User::where('email', $googleEmail)->first() : null;

            if ($mode === 'login') {
                if ($userByGoogle) {
                    if (!$userByGoogle->is_active || in_array($userByGoogle->account_status ?? 'active', ['blocked', 'deactivated', 'deleted'])) {
                        return $this->redirectToFrontend([
                            'oauth' => 'error',
                            'message' => 'Your account is not active. Please contact support.',
                            'tab' => 'login',
                        ]);
                    }

                    return $this->redirectWithToken($userByGoogle, [
                        'oauth' => 'success',
                        'message' => 'Logged in successfully with Google.',
                        'profile_completed' => $userByGoogle->profile_completed ? '1' : '0',
                    ]);
                }

                if ($userByEmail && empty($userByEmail->google_id)) {
                    if (!$userByEmail->is_active || in_array($userByEmail->account_status ?? 'active', ['blocked', 'deactivated', 'deleted'])) {
                        return $this->redirectToFrontend([
                            'oauth' => 'error',
                            'message' => 'Your account is not active. Please contact support.',
                            'tab' => 'login',
                        ]);
                    }

                    // Link Google to existing email/password account on first successful Google login.
                    $userByEmail->google_id = $googleId;
                    if (empty($userByEmail->email_verified_at)) {
                        $userByEmail->email_verified_at = now();
                    }
                    $userByEmail->save();

                    return $this->redirectWithToken($userByEmail, [
                        'oauth' => 'success',
                        'message' => 'Logged in successfully with Google.',
                        'profile_completed' => $userByEmail->profile_completed ? '1' : '0',
                    ]);
                }

                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'Google account is not registered yet. Please create an account first.',
                    'tab' => 'login',
                ]);
            }

            // Register mode
            if ($userByGoogle) {
                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'This Google account is already registered. Please sign in instead.',
                    'tab' => 'register',
                ]);
            }

            if ($userByEmail && empty($userByEmail->google_id)) {
                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'This email is already registered. Please sign in using email and password.',
                    'tab' => 'register',
                ]);
            }

            if ($userByEmail && !empty($userByEmail->google_id)) {
                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'This Google account is already registered. Please sign in instead.',
                    'tab' => 'register',
                ]);
            }

            if (empty($googleEmail)) {
                return $this->redirectToFrontend([
                    'oauth' => 'error',
                    'message' => 'Google did not provide an email for this account.',
                    'tab' => 'register',
                ]);
            }

            $newUser = User::create([
                'uuid' => (string) Str::uuid(),
                'username' => $this->generateUniqueUsername($googleName ?: $googleEmail),
                'email' => $googleEmail,
                'first_name' => $givenName ?: null,
                'last_name' => $familyName ?: null,
                'google_id' => $googleId,
                'password_login_enabled' => false,
                'profile_completed' => false,
                'verification_method' => 'google',
            ]);

            // Set sensitive fields explicitly (not via mass assignment — these are excluded from $fillable)
            $newUser->password = Hash::make(Str::random(48));
            $newUser->role = 'client';
            $newUser->is_active = true;
            $newUser->email_verified_at = now();
            $newUser->save();

            return $this->redirectWithToken($newUser, [
                'oauth' => 'success',
                'message' => 'Google registration successful. Please complete your profile.',
                'profile_completed' => '0',
                'new_user' => '1',
            ]);

        } catch (Exception $e) {
            \Log::error('Google callback error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->redirectToFrontend([
                'oauth' => 'error',
                'message' => 'Failed to authenticate with Google. Please try again.',
                'tab' => $request->query('state', 'login') === 'register' ? 'register' : 'login',
            ]);
        }
    }

    /**
     * Generate unique username
     */
    private function generateUniqueUsername($name)
    {
        $baseUsername = Str::slug($name);
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Verify user from email link
     */
    public function verifyEmail($verificationCode)
    {
        $user = User::where('verification_code', $verificationCode)
            ->where('verification_code_expires_at', '>', now())
            ->first();

        if (!$user) {
            return $this->redirectToFrontend([
                'oauth' => 'error',
                'message' => 'Invalid or expired verification code.',
                'tab' => 'login',
            ]);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return $this->redirectToFrontend([
            'oauth' => 'success',
            'message' => 'Email verified successfully. Please complete your profile.',
            'tab' => 'login',
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail()
    {
        $user = auth()->user();

        if (!$user || $user->email_verified_at) {
            return response()->json([
                'message' => 'No verification email needed.',
                'success' => true,
            ]);
        }

        // Check rate limit (don't send more than once per minute)
        if ($user->verification_code_expires_at && $user->verification_code_expires_at->diffInMinutes(now()) < -23) {
            return response()->json([
                'message' => 'Please wait before requesting another verification email.',
                'success' => false,
            ], 429);
        }

        $verificationCode = Str::random(32);
        $user->update([
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'message' => 'Verification email resent.',
            'success' => true,
        ]);
    }

    private function frontendUrl(): string
    {
        return rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
    }

    private function redirectToFrontend(array $params = [], string $path = '/auth/callback'): \Illuminate\Http\RedirectResponse
    {
        $url = $this->frontendUrl() . $path;
        if (!empty($params)) {
            // Use hash fragment to avoid exposing OAuth payload in query string routes.
            $url .= '#' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return redirect()->away($url);
    }

    private function redirectWithToken(User $user, array $params = []): \Illuminate\Http\RedirectResponse
    {
        $token = $user->createToken('google_oauth_token', ['*'], now()->addDays(7))->plainTextToken;
        $params['token'] = $token;
        return $this->redirectToFrontend($params);
    }
}
