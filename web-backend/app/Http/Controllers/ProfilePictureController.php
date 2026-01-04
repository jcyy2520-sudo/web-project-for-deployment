<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ProfilePictureController extends Controller
{
    /**
     * Upload or update a profile picture
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
            ]);

            $user = Auth::user();

            // Delete old profile picture if exists
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Store new profile picture
            $path = $request->file('profile_picture')->store('profiles', 'public');

            // Update user profile picture path
            $user->update(['profile_picture' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'profile_picture' => asset('storage/' . $path)
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete profile picture
     */
    public function destroy()
    {
        try {
            $user = Auth::user();

            if ($user->profile_picture) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }

                $user->update(['profile_picture' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile picture removed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile picture: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's profile picture
     */
    public function show()
    {
        try {
            $user = Auth::user();

            if ($user->profile_picture) {
                return response()->json([
                    'success' => true,
                    'profile_picture' => asset('storage/' . $user->profile_picture)
                ], 200);
            }

            return response()->json([
                'success' => true,
                'profile_picture' => null
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile picture: ' . $e->getMessage()
            ], 500);
        }
    }
}
