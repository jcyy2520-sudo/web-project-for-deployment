<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileCompletionController extends Controller
{
    /**
     * Get current user's profile status
     */
    public function getStatus()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'profile_completed' => $user->profile_completed,
            'email_verified' => !is_null($user->email_verified_at),
            'verification_method' => $user->verification_method,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'address' => $user->address,
            ]
        ]);
    }

    /**
     * Complete user profile
     */
    public function complete(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if email is verified for Google users
        if ($user->verification_method === 'google' && is_null($user->email_verified_at)) {
            return response()->json([
                'error' => 'Please verify your email first before completing your profile.'
            ], 422);
        }

        // Validate input — rules match the main registration flow (AuthController::completeRegistration)
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-]+$/'],
            'address' => 'required|string|max:500',
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\.]+$/'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and periods.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and periods.',
            'phone.regex' => 'Phone number may only contain digits, spaces, hyphens, and an optional leading +.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Update user profile
        $user->update([
            'phone' => $request->phone,
            'address' => $request->address,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'profile_completed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile completed successfully',
            'user' => $user
        ]);
    }

    /**
     * Check if user can book appointment
     */
    public function canBookAppointment()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['can_book' => false, 'reason' => 'Not authenticated'], 401);
        }

        if (!$user->profile_completed) {
            return response()->json([
                'can_book' => false,
                'reason' => 'Please complete your profile first'
            ]);
        }

        // Additional checks can be added here
        // e.g., email verified, account active, etc.

        return response()->json(['can_book' => true]);
    }
}
