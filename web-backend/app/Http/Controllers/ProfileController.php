<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\ActionLog;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();
        
        return response()->json([
            'data' => $user,
            'success' => true
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $user->update($request->only([
            'first_name', 
            'last_name', 
            'username', 
            'email', 
            'phone', 
            'address'
        ]));

        ActionLog::log('update_profile', "Updated profile information", 'User', $user->id);

        return response()->json([
            'data' => $user,
            'message' => 'Profile updated successfully',
            'success' => true
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'success' => false
            ], 401);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // at least one lowercase letter
                'regex:/[A-Z]/',      // at least one uppercase letter
                'regex:/[0-9]/',      // at least one digit
            ],
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'new_password.confirmed' => 'New password and confirmation do not match.',
            'new_password.min' => 'New password must be at least 8 characters.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']],
                'success' => false
            ], 422);
        }

        // Ensure new password is different from current
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'New password must be different from your current password',
                'errors' => ['new_password' => ['New password must be different from your current password']],
                'success' => false
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password)
        ])->save();

        ActionLog::log('change_password', "Changed account password", 'User', $user->id);

        return response()->json([
            'message' => 'Password updated successfully',
            'success' => true
        ]);
    }

    /**
     * Allow Google-first users to enable password login.
     * If password login is already enabled, current_password is required.
     */
    public function setPassword(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'success' => false,
            ], 401);
        }

        $rules = [
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ];

        if ($user->password_login_enabled) {
            $rules['current_password'] = 'required|string';
        }

        $request->validate($rules, [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'new_password.confirmed' => 'Password confirmation does not match.',
        ]);

        if ($user->password_login_enabled && !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'success' => false,
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password),
            'password_login_enabled' => true,
        ])->save();

        return response()->json([
            'message' => 'Password login has been enabled successfully.',
            'success' => true,
        ]);
    }
}