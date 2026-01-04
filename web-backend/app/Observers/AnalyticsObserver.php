<?php

namespace App\Observers;

use App\Events\AnalyticsUpdated;
use App\Models\Appointment;
use App\Models\Refund;
use Illuminate\Support\Facades\Cache;

class AnalyticsObserver
{
    /**
     * Clear analytics cache when an appointment is created, updated, or deleted
     */
    public function created(Appointment $appointment)
    {
        $this->invalidateAnalyticsCache();
    }

    public function updated(Appointment $appointment)
    {
        // Only invalidate if status changed (as that affects analytics)
        if ($appointment->isDirty('status')) {
            $this->invalidateAnalyticsCache();
        }
    }

    public function deleted(Appointment $appointment)
    {
        $this->invalidateAnalyticsCache();
    }

    /**
     * Clear analytics cache when a refund is created, updated, or completed
     */
    public function saved(Refund $refund)
    {
        // Invalidate on any refund change (status updates affect revenue)
        $this->invalidateAnalyticsCache();
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
