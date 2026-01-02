<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeedbackSettingsController extends Controller
{
    /**
     * Get feedback settings
     */
    public function show(Request $request)
    {
        try {
            $settings = FeedbackSettings::getSettings();

            return response()->json([
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback settings fetch error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update feedback settings
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate_limit' => 'nullable|integer|min:1|max:100',
                'cooldown_days' => 'nullable|integer|min:1|max:365',
                'profanity_filter_enabled' => 'nullable|boolean',
                'duplicate_detection_enabled' => 'nullable|boolean',
                'profanity_list' => 'nullable|array'
            ]);

            $settings = FeedbackSettings::getSettings();
            // Ensure profanity_list is stored as JSON string if provided as array
            if (isset($validated['profanity_list']) && is_array($validated['profanity_list'])) {
                $validated['profanity_list'] = json_encode($validated['profanity_list']);
            }

            $settings->update(array_filter($validated));

            return response()->json([
                'message' => 'Settings updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Feedback settings update error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
