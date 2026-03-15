<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\MlOutcomeLog;
use Illuminate\Support\Facades\Log;

/**
 * Automatically logs appointment outcomes for ML retraining
 * when an appointment reaches a terminal status.
 */
class MlOutcomeObserver
{
    private array $terminalStatuses = ['completed', 'cancelled', 'no_show'];

    public function updated(Appointment $appointment): void
    {
        // Only log when status changes to a terminal state
        if (!$appointment->isDirty('status')) {
            return;
        }

        $newStatus = $appointment->status;
        if (!in_array($newStatus, $this->terminalStatuses)) {
            return;
        }

        try {
            MlOutcomeLog::create([
                'appointment_id' => $appointment->id,
                'prediction_type' => 'risk',
                'actual_outcome' => $newStatus,
                'logged_by' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log ML outcome: ' . $e->getMessage());
        }
    }
}
