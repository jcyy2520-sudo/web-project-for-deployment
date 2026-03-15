<?php

namespace App\Observers;

use App\Events\AnalyticsUpdated;
use App\Models\Appointment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AnalyticsObserver
{
    /**
     * Clear analytics cache when an appointment or refund is created
     */
    public function created(Model $model)
    {
        $this->invalidateAnalyticsCache();
    }

    /**
     * Clear analytics cache when an appointment or refund is updated
     */
    public function updated(Model $model)
    {
        // For appointments, only invalidate if status changed (as that affects analytics)
        if ($model instanceof Appointment && !$model->isDirty('status')) {
            return;
        }
        $this->invalidateAnalyticsCache();
    }

    /**
     * Clear analytics cache when an appointment or refund is deleted
     */
    public function deleted(Model $model)
    {
        $this->invalidateAnalyticsCache();
    }

    /**
     * Clear analytics cache when a model is saved (covers both create and update)
     * This is particularly important for refund status changes that affect revenue
     */
    public function saved(Model $model)
    {
        // For refunds, always invalidate (status updates affect revenue)
        if ($model instanceof Refund) {
            $this->invalidateAnalyticsCache();
        }
        // For appointments, the created/updated hooks already handle invalidation
    }

    /**
     * Invalidate all analytics caches and broadcast update
     */
    private function invalidateAnalyticsCache()
    {
        try {
            // Clear all analytics-related caches
            Cache::forget('analytics_slot_utilization_7');
            Cache::forget('analytics_slot_utilization_30');
            Cache::forget('analytics_slot_utilization_90');
            Cache::forget('analytics_slot_utilization_365');
            Cache::forget('analytics_no_show_patterns_30');
            Cache::forget('analytics_no_show_patterns_90');
            Cache::forget('analytics_demand_forecast_30');
            Cache::forget('analytics_demand_forecast_90');
            Cache::forget('analytics_quality_report_30');
            Cache::forget('analytics_quality_report_90');
            Cache::forget('analytics_auto_alerts');
            Cache::forget('analytics_dashboard_comprehensive');
            Cache::forget('analytics_dashboard_realtime');

            // Broadcast update event to all connected admin clients
            broadcast(new AnalyticsUpdated([
                'updated_at' => now(),
                'message' => 'Analytics data has been updated due to a data change.',
            ]))->toOthers();

            \Log::info('Analytics cache invalidated and update broadcasted');
        } catch (\Exception $e) {
            \Log::warning('Error invalidating analytics cache: ' . $e->getMessage());
        }
    }
}
