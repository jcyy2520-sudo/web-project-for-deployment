<?php

namespace App\Http\Controllers;

use App\Models\MessageSettings;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MessageSettingsController extends Controller
{
    /**
     * Get current message settings
     */
    public function show(Request $request)
    {
        try {
            $settings = MessageSettings::getSettings();

            return response()->json([
                'data' => $settings,
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Message settings fetch error', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ]);

            return response()->json([
                'message' => 'Failed to fetch message settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Update message settings (admin only)
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_message_limit' => 'required|integer|min:1|max:50',
            ]);

            $settings = MessageSettings::getSettings();
            $oldLimit = $settings->user_message_limit;

            $settings->update([
                'user_message_limit' => $validated['user_message_limit'],
                'last_updated_by' => $request->user()->id,
            ]);

            // Clear cached settings so all users get updated value immediately
            Cache::forget('message_settings');

            // Log the action
            ActionLog::log(
                'update',
                "Updated user message limit from {$oldLimit} to {$validated['user_message_limit']}",
                'MessageSettings',
                $settings->id
            );

            return response()->json([
                'message' => 'Message settings updated successfully',
                'data' => $settings->fresh(),
                'success' => true
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('Message settings update error', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ]);

            return response()->json([
                'message' => 'Failed to update message settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }
}
