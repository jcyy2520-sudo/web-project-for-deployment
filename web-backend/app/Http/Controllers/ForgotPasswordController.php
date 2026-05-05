<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetCode;
use App\Mail\PasswordResetCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
    private function forgotPasswordSuccessResponse(string $email, bool $resent = false)
    {
        $message = $resent
            ? 'If this email is registered, a new verification code has been sent.'
            : 'If this email is registered, a verification code has been sent. The code will expire in 15 minutes.';

        if (app()->environment('local')) {
            $message .= ' If nothing arrives during local testing, check the backend log because local mail can fall back there.';
        }

        return [
            'message' => $message,
            'email' => $email,
            'expires_in' => '15 minutes',
        ];
    }

    private function dispatchPasswordResetCodeMail(string $email, string $code): void
    {
        if (!app()->environment('local')) {
            Mail::to($email)->queue(new PasswordResetCodeMail($code));
            Log::info('Password reset code sent to: ' . $email, [
                'delivery_channel' => 'default',
            ]);

            return;
        }

        try {
            Mail::mailer('smtp')->to($email)->send(new PasswordResetCodeMail($code));
            Log::info('Password reset code sent to: ' . $email, [
                'delivery_channel' => 'smtp',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('SMTP delivery failed for password reset email, falling back to log mailer.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            Mail::mailer('log')->to($email)->send(new PasswordResetCodeMail($code));

            Log::info('Password reset code written to backend log for local delivery fallback.', [
                'email' => $email,
                'delivery_channel' => 'log',
            ]);
        }
    }

    /**
     * Step 1: Send a reset code to the user's email
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // SECURITY: Use generic response to prevent email enumeration.
        // Internally check if user exists and only proceed if they do.
        $user = User::where('email', $email)->first();

        if (!$user || $user->trashed()) {
            // SECURITY: Return same success message to prevent email enumeration.
            // Log internally for monitoring without revealing to attacker.
            Log::info('Password reset requested for non-existent/deleted email', [
                'ip' => $request->ip()
            ]);
            return response()->json($this->forgotPasswordSuccessResponse($email));
        }

        // Clean up old codes for this email
        PasswordResetCode::where('email', $email)->delete();

        // Generate a 6-digit code
        $code = sprintf("%06d", random_int(1, 999999));

        // Store the code
        PasswordResetCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->dispatchPasswordResetCodeMail($email, $code);

            return response()->json($this->forgotPasswordSuccessResponse($email));
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email: ' . $e->getMessage());
            PasswordResetCode::where('email', $email)->delete();

            return response()->json([
                'message' => 'Failed to send verification email. Please try again later.'
            ], 500);
        }
    }

    /**
     * Step 2: Verify the code
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $resetCode = PasswordResetCode::findActiveMatchForEmail($request->email, $request->code);

        if (!$resetCode) {
            return response()->json([
                'message' => 'Invalid or expired verification code. Please request a new one.'
            ], 422);
        }

        // Mark code as used
        $resetCode->update(['used' => true]);

        return response()->json([
            'message' => 'Code verified successfully. You can now set a new password.',
            'verified' => true,
            'email' => $request->email,
        ]);
    }

    /**
     * Step 3: Reset the password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // at least one lowercase letter
                'regex:/[A-Z]/',      // at least one uppercase letter
                'regex:/[0-9]/',      // at least one digit
            ],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        // Verify code was used (verified) recently
        $wasVerified = PasswordResetCode::where('email', $request->email)
            ->where('used', true)
            ->where('expires_at', '>', now()->subMinutes(15))
            ->where('created_at', '>', now()->subHour())
            ->latest()
            ->first();

        if (!$wasVerified) {
            return response()->json([
                'message' => 'Verification expired or not completed. Please restart the password reset process.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Update password - use explicit assignment since 'password' is excluded from $fillable
        // for mass-assignment protection. $user->update() would silently ignore it.
        $user->password = Hash::make($request->password);
        $user->password_login_enabled = true;
        $user->save();

        // Revoke all existing tokens (force re-login)
        $user->tokens()->delete();

        // Clean up all reset codes for this email
        PasswordResetCode::where('email', $request->email)->delete();

        Log::info('Password reset successfully for: ' . $request->email);

        return response()->json([
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
            'success' => true,
        ]);
    }

    /**
     * Resend the code
     */
    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // SECURITY: Generic message to prevent email enumeration
            return response()->json($this->forgotPasswordSuccessResponse($request->email, true));
        }

        // Clean up old codes
        PasswordResetCode::where('email', $request->email)->delete();

        $code = sprintf("%06d", random_int(1, 999999));

        PasswordResetCode::create([
            'email' => $request->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->dispatchPasswordResetCodeMail($request->email, $code);

            return response()->json($this->forgotPasswordSuccessResponse($request->email, true));
        } catch (\Exception $e) {
            Log::error('Failed to resend password reset email: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send email. Please try again.'
            ], 500);
        }
    }
}
