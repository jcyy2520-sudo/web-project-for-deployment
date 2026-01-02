<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotDataObserver
 * 
 * Ensures the chatbot always has up-to-date information by:
 * 1. Clearing relevant caches when data changes
 * 2. Triggering knowledge base syncs for configuration changes
 * 3. Invalidating role caches when user roles change
 * 
 * This observer is registered for: Appointment, Payment, Refund, User, Service,
 * AppointmentSettings, and other relevant models in AppServiceProvider.
 */
class ChatbotDataObserver
{
    /**
     * Cache keys that should be cleared on any data change
     */
    private const GLOBAL_CACHE_KEYS = [
        'chatbot_system_stats',
        'chatbot_todays_summary',
        'chatbot_available_services',
        'chatbot_business_hours',
        'chatbot_all_appointments',
        'chatbot_pending_appointments',
        'chatbot_pending_payments',
        'chatbot_pending_refunds',
        'chatbot_system_health',
        'chatbot_analytics_summary',
        'chatbot_staff_list',
        'chatbot_services_list',
    ];

    /**
     * Models that trigger knowledge base sync when changed
     */
    private const KNOWLEDGE_SYNC_MODELS = [
        'Service',
        'AppointmentSettings',
    ];

    /**
     * Handle the "saved" event for any model.
     * This covers created and updated.
     */
    public function saved($model): void
    {
        $this->clearChatbotCache($model);
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted($model): void
    {
        $this->clearChatbotCache($model);
    }

    /**
     * Handle the "restored" event (for soft deletes).
     */
    public function restored($model): void
    {
        $this->clearChatbotCache($model);
    }

    /**
     * Clear relevant chatbot caches based on the model type
     */
    private function clearChatbotCache($model): void
    {
        try {
            $modelName = class_basename($model);
            Log::debug("ChatbotDataObserver: Clearing cache for {$modelName}");

            // 1. Clear global system stats cache
            foreach (self::GLOBAL_CACHE_KEYS as $key) {
                Cache::forget($key);
            }

            // 2. Clear user-specific caches if applicable
            if (isset($model->user_id)) {
                $this->clearUserCaches($model->user_id);
            }

            // 3. If it's a User model, clear its own cache and role cache
            if ($modelName === 'User') {
                $this->clearUserCaches($model->id);
                $this->clearRoleCache($model->id);
            }

            // 4. Clear specific caches based on model type
            $this->clearModelSpecificCaches($model, $modelName);

            // 5. Automatically trigger Knowledge Sync for critical configuration changes
            if (in_array($modelName, self::KNOWLEDGE_SYNC_MODELS)) {
                $this->triggerKnowledgeSync($modelName);
            }

        } catch (\Exception $e) {
            Log::error("Failed to clear chatbot cache: " . $e->getMessage());
        }
    }

    /**
     * Clear user-specific caches
     */
    private function clearUserCaches(int $userId): void
    {
        $statuses = ['all', 'pending', 'approved', 'completed', 'cancelled', 'declined'];
        foreach ($statuses as $status) {
            Cache::forget("chatbot_appointments_user_{$userId}_{$status}");
        }
        Cache::forget("chatbot_payments_user_{$userId}");
        Cache::forget("chatbot_refunds_user_{$userId}");
        Cache::forget("chatbot_notifications_user_{$userId}");
        Cache::forget("chatbot_context_user_{$userId}");
    }

    /**
     * Clear role-related caches for a user
     */
    private function clearRoleCache(int $userId): void
    {
        Cache::forget("chatbot_role_{$userId}");
        Cache::forget("chatbot_capabilities_{$userId}");
        Cache::forget("chatbot_permissions_{$userId}");
    }

    /**
     * Clear model-specific caches
     */
    private function clearModelSpecificCaches($model, string $modelName): void
    {
        switch ($modelName) {
            case 'Appointment':
                Cache::forget("chatbot_appointment_{$model->id}");
                if ($model->appointment_date) {
                    $dateStr = $model->appointment_date instanceof \Carbon\Carbon 
                        ? $model->appointment_date->format('Y-m-d')
                        : $model->appointment_date;
                    Cache::forget("chatbot_availability_{$dateStr}");
                }
                // Clear slot capacity cache for the appointment date
                Cache::forget("chatbot_slots_{$model->appointment_date}");
                break;

            case 'Payment':
                Cache::forget("chatbot_payment_{$model->id}");
                if (isset($model->appointment_id)) {
                    Cache::forget("chatbot_appointment_{$model->appointment_id}");
                }
                break;

            case 'Refund':
                Cache::forget("chatbot_refund_{$model->id}");
                if (isset($model->payment_id)) {
                    Cache::forget("chatbot_payment_{$model->payment_id}");
                }
                break;

            case 'Service':
                Cache::forget("chatbot_service_{$model->id}");
                Cache::forget('chatbot_services_pricing');
                break;

            case 'AppointmentSettings':
                Cache::forget('chatbot_appointment_settings');
                Cache::forget('chatbot_booking_rules');
                break;

            case 'Notification':
                if (isset($model->user_id)) {
                    Cache::forget("chatbot_notifications_user_{$model->user_id}");
                }
                break;
        }
    }

    /**
     * Trigger knowledge base sync for configuration changes
     */
    private function triggerKnowledgeSync(string $modelName): void
    {
        try {
            Log::info("ChatbotDataObserver: Triggering knowledge sync due to {$modelName} update");
            
            // Use queue to avoid blocking the request
            \Illuminate\Support\Facades\Artisan::queue('chatbot:sync-knowledge');
            
            // Also clear the embeddings cache to force re-computation
            Cache::forget('chatbot_embeddings_version');
            Cache::forget('chatbot_knowledge_hash');
        } catch (\Exception $e) {
            Log::warning("Failed to trigger knowledge sync: " . $e->getMessage());
        }
    }
}
