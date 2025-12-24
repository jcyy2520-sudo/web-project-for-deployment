<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotDataObserver
{
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
     * Clear relevant chatbot caches based on the model type
     */
    private function clearChatbotCache($model): void
    {
        try {
            $modelName = class_basename($model);
            Log::debug("ChatbotDataObserver: Clearing cache for {$modelName}");

            // 1. Clear global system stats cache
            Cache::forget('chatbot_system_stats');
            Cache::forget('chatbot_todays_summary');
            Cache::forget('chatbot_available_services');
            Cache::forget('chatbot_business_hours');
            Cache::forget('chatbot_all_appointments');
            Cache::forget('chatbot_pending_appointments');
            Cache::forget('chatbot_pending_payments');
            Cache::forget('chatbot_pending_refunds');

            // 2. Clear user-specific caches if applicable
            if (isset($model->user_id)) {
                $this->clearUserCaches($model->user_id);
            }

            // 3. If it's a User model, clear its own cache
            if ($modelName === 'User') {
                $this->clearUserCaches($model->id);
            }

            // 4. Clear specific appointment cache if it's an appointment
            if ($modelName === 'Appointment') {
                Cache::forget("chatbot_appointment_{$model->id}");
                Cache::forget("chatbot_availability_" . ($model->appointment_date?->format('Y-m-d') ?? ''));
            }

            // 5. Automatically trigger Knowledge Sync for critical configuration changes
            if ($modelName === 'Service' || $modelName === 'AppointmentSettings') {
                Log::info("ChatbotDataObserver: Triggering knowledge sync due to {$modelName} update");
                \Illuminate\Support\Facades\Artisan::queue('chatbot:sync-knowledge');
            }

        } catch (\Exception $e) {
            Log::error("Failed to clear chatbot cache: " . $e->getMessage());
        }
    }

    private function clearUserCaches(int $userId): void
    {
        $statuses = ['all', 'pending', 'approved', 'completed', 'cancelled'];
        foreach ($statuses as $status) {
            Cache::forget("chatbot_appointments_user_{$userId}_{$status}");
        }
        Cache::forget("chatbot_payments_user_{$userId}");
        Cache::forget("chatbot_refunds_user_{$userId}");
    }
}
