<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SAFETY WRAPPER FOR EXPERIMENTAL FEATURES
 * Prevents broken experimental endpoints from crashing the system
 */
trait SafeExperimentalFeature
{
    /**
     * Wrap experimental features with error handling
     * Returns safe response if feature fails
     */
    protected function wrapExperimental($callback, $featureName)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            // Log the error
            Log::warning("Experimental feature failed: $featureName", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return safe fallback response
            return response()->json([
                'success' => false,
                'message' => "Feature '$featureName' is currently unavailable. Please try again later.",
                'status' => 'experimental_unavailable',
                'experimental' => true,
                'retry_after' => 300 // Try again in 5 minutes
            ], 503);
        }
    }

    /**
     * Check if feature is available before running
     */
    protected function isExperimentalAvailable($featureName)
    {
        $experimental = config('features.experimental', []);
        return in_array($featureName, $experimental);
    }

    /**
     * Get experimental feature status
     */
    protected function getExperimentalStatus()
    {
        return response()->json([
            'experimental_features' => [
                'analytics' => config('features.experimental_analytics', false),
                'decision_support' => config('features.experimental_decision_support', false),
                'batch_operations' => config('features.experimental_batch', false),
                'document_versioning' => config('features.experimental_documents', false),
                'auto_notifications' => config('features.experimental_notifications', false),
            ]
        ]);
    }
}
