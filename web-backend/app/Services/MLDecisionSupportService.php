<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * ML-backed Decision Support Service.
 * Replaces the hardcoded rule-based DecisionSupportService with real ML predictions.
 * When no ML model is trained, returns structured guidance instead of fake predictions.
 */
class MLDecisionSupportService
{
    private MLServiceClient $mlClient;

    public function __construct(MLServiceClient $mlClient)
    {
        $this->mlClient = $mlClient;
    }

    /**
     * Get ML-backed time slot recommendations.
     */
    public function getTimeSlotRecommendations(string $appointmentDate, int $duration = 30): array
    {
        if (!$this->mlAvailable()) {
            return $this->noModelResponse('time_slot_recommendations', $appointmentDate);
        }

        try {
            $result = $this->mlClient->predictSlotRank($appointmentDate);

            if (isset($result['error'])) {
                return $this->serviceUnavailableResponse('time_slot_recommendations');
            }

            $slots = collect($result['data'] ?? [])
                ->map(function ($slot) {
                    $tag = 'Available';
                    if (($slot['predicted_score'] ?? 0) >= 0.4) $tag = 'Best Time';
                    elseif (($slot['predicted_score'] ?? 0) >= 0.3) $tag = 'Recommended';
                    if ($slot['status'] === 'full') $tag = 'Full';
                    elseif ($slot['status'] === 'filling_up') $tag = 'Filling Up';

                    return [
                        'time' => $slot['time'],
                        'score' => round(($slot['predicted_score'] ?? 0) * 100),
                        'max_score' => 100,
                        'tag' => $tag,
                        'available' => $slot['status'] !== 'full',
                        'current_bookings' => $slot['current_bookings'] ?? 0,
                        'confidence' => $slot['confidence'] ?? 0,
                        'confidence_label' => $slot['confidence_label'] ?? 'low',
                        'reasoning' => $slot['reasoning'] ?? [],
                        'historical_completion_rate' => $slot['historical_completion_rate'] ?? 0,
                    ];
                })
                ->values()
                ->toArray();

            return [
                'slots' => array_slice($slots, 0, 10),
                'summary' => [
                    'total_slots' => $result['total_slots'] ?? 0,
                    'available_slots' => $result['available_slots'] ?? 0,
                ],
                'engine' => 'ml',
            ];
        } catch (\Exception $e) {
            Log::error('ML slot recommendation failed: ' . $e->getMessage());
            return $this->serviceUnavailableResponse('time_slot_recommendations');
        }
    }

    /**
     * Get ML-backed appointment risk assessment.
     */
    public function getAppointmentRiskAssessment(int $appointmentId): ?array
    {
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            return null;
        }

        if (!$this->mlAvailable()) {
            return $this->noModelRiskResponse($appointment);
        }

        try {
            $result = $this->mlClient->predictRisk($appointmentId);

            if (isset($result['error'])) {
                return $this->serviceUnavailableRiskResponse($appointment);
            }

            if (($result['status'] ?? '') === 'no_model') {
                return $this->noModelRiskResponse($appointment);
            }

            $data = $result['data'] ?? [];

            return [
                'appointment_id' => $appointmentId,
                'risk_score' => round(($data['risk_score'] ?? 0) * 100),
                'risk_level' => $data['risk_level'] ?? 'unknown',
                'completion_probability' => $data['completion_probability'] ?? 0,
                'confidence' => $data['confidence'] ?? 0,
                'confidence_label' => $data['confidence_label'] ?? 'low',
                'risk_factors' => $this->formatFeatureImportances($data['feature_importances'] ?? [], 'risk'),
                'positive_factors' => $this->formatFeatureImportances($data['feature_importances'] ?? [], 'positive'),
                'reasoning' => $data['reasoning'] ?? [],
                'recommendations' => $this->generateRiskRecommendations($data),
                'model_info' => $data['model_info'] ?? [],
                'customer_stats' => $data['appointment_meta'] ?? [],
                'engine' => 'ml',
            ];
        } catch (\Exception $e) {
            Log::error('ML risk assessment failed: ' . $e->getMessage());
            return $this->serviceUnavailableRiskResponse($appointment);
        }
    }

    /**
     * Get workload optimization data.
     */
    public function getWorkloadOptimization(string $appointmentDate): array
    {
        $staffMembers = User::where('role', 'staff')->get();
        $staffData = [];
        $totalAppointments = 0;

        foreach ($staffMembers as $staff) {
            $count = Appointment::where('staff_id', $staff->id)
                ->where('appointment_date', $appointmentDate)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            $totalAppointments += $count;
            $maxCapacity = 10;

            $status = 'available';
            if ($count >= $maxCapacity) $status = 'overloaded';
            elseif ($count >= 5) $status = 'busy';

            $staffData[] = [
                'staff_id' => $staff->id,
                'name' => "{$staff->first_name} {$staff->last_name}",
                'appointment_count' => $count,
                'max_capacity' => $maxCapacity,
                'utilization' => round($count / $maxCapacity * 100),
                'status' => $status,
            ];
        }

        usort($staffData, fn ($a, $b) => $b['appointment_count'] <=> $a['appointment_count']);

        $overloaded = array_filter($staffData, fn ($s) => $s['status'] === 'overloaded');
        $underloaded = array_filter($staffData, fn ($s) => $s['appointment_count'] <= 2);

        $insights = [];
        if (count($overloaded) > 0) {
            $insights[] = [
                'type' => 'warning',
                'message' => count($overloaded) . ' staff member(s) are overloaded. Consider redistribution.',
            ];
        }
        if (count($underloaded) > 0 && count($overloaded) > 0) {
            $insights[] = [
                'type' => 'suggestion',
                'message' => 'Workload can be balanced by redirecting appointments to less busy staff.',
            ];
        }

        return [
            'staff' => $staffData,
            'summary' => [
                'total_staff' => count($staffMembers),
                'total_appointments' => $totalAppointments,
                'overloaded_count' => count($overloaded),
                'available_count' => count($underloaded),
            ],
            'insights' => $insights,
        ];
    }

    /**
     * Get customer insights.
     */
    public function getCustomerInsights(int $customerId): ?array
    {
        $user = User::find($customerId);
        if (!$user) {
            return null;
        }

        $appointments = Appointment::where('user_id', $customerId)->get();
        $total = $appointments->count();
        $completed = $appointments->where('status', 'completed')->count();
        $cancelled = $appointments->where('status', 'cancelled')->count();
        $noShow = $appointments->where('status', 'no_show')->count();

        return [
            'customer_id' => $customerId,
            'name' => "{$user->first_name} {$user->last_name}",
            'total_appointments' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'completion_rate' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            'cancellation_rate' => $total > 0 ? round($cancelled / $total * 100, 1) : 0,
            'no_show_rate' => $total > 0 ? round($noShow / $total * 100, 1) : 0,
            'risk_profile' => $this->customerRiskProfile($total, $cancelled, $noShow),
        ];
    }

    /**
     * Get dashboard data with ML model status.
     */
    public function getDashboardData(string $appointmentDate): array
    {
        $appointments = Appointment::whereDate('appointment_date', $appointmentDate)->get();
        $mlStatus = $this->mlAvailable() ? $this->mlClient->getStatus() : null;

        $total = $appointments->count();
        $pending = $appointments->where('status', 'pending')->count();
        $approved = $appointments->where('status', 'approved')->count();
        $completed = $appointments->where('status', 'completed')->count();

        return [
            'quick_stats' => [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'completed' => $completed,
            ],
            'ml_status' => [
                'available' => $this->mlAvailable(),
                'has_model' => $mlStatus['model']['has_model'] ?? false,
                'algorithm' => $mlStatus['model']['algorithm'] ?? null,
                'trained_at' => $mlStatus['model']['trained_at'] ?? null,
            ],
            'workload' => $this->getWorkloadOptimization($appointmentDate),
        ];
    }

    /**
     * Get data quality report from ML service.
     */
    public function getDataQuality(): array
    {
        if (!$this->mlClient->isAvailable()) {
            return [
                'status' => 'service_unavailable',
                'message' => 'ML service is not running. Start it with: python ml-service/main.py',
            ];
        }

        return $this->mlClient->getDataQuality();
    }

    /**
     * Trigger ML model training.
     */
    public function trainModel(): array
    {
        if (!$this->mlClient->isAvailable()) {
            return [
                'status' => 'service_unavailable',
                'message' => 'ML service is not running. Start it with: python ml-service/main.py',
            ];
        }

        return $this->mlClient->train();
    }

    /**
     * Log outcome feedback.
     */
    public function logOutcome(int $appointmentId, string $outcome, ?string $feedback = null, ?string $reason = null): array
    {
        if (!$this->mlClient->isAvailable()) {
            return ['status' => 'service_unavailable'];
        }

        return $this->mlClient->logFeedback($appointmentId, $outcome, $feedback, $reason);
    }

    // ─── Internal Helpers ────────────────────────────────────────────

    private function mlAvailable(): bool
    {
        return $this->mlClient->isAvailable() && $this->mlClient->hasTrainedModel();
    }

    private function noModelResponse(string $feature, string $date = ''): array
    {
        $quality = $this->mlClient->isAvailable() ? $this->mlClient->getDataQuality() : [];

        return [
            'status' => 'no_model',
            'message' => 'ML model not yet trained. Insufficient historical data or training not initiated.',
            'data_quality' => $quality['data'] ?? [],
            'action_required' => 'Go to Decision Support > Data Quality tab and click "Train Model" when 50+ appointment records are available.',
            'recommendations' => [],
            'slots' => [],
            'engine' => 'none',
        ];
    }

    private function noModelRiskResponse(Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'status' => 'no_model',
            'message' => 'ML model not trained. Cannot assess risk without trained model. Collect 50+ appointment records to enable predictions.',
            'risk_score' => null,
            'risk_level' => 'unknown',
            'confidence' => 0,
            'engine' => 'none',
        ];
    }

    private function serviceUnavailableResponse(string $feature): array
    {
        return [
            'status' => 'service_unavailable',
            'message' => 'ML service is temporarily unavailable. Predictions cannot be served.',
            'recommendations' => [],
            'slots' => [],
            'engine' => 'none',
        ];
    }

    private function serviceUnavailableRiskResponse(Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'status' => 'service_unavailable',
            'message' => 'ML service temporarily unavailable.',
            'risk_score' => null,
            'risk_level' => 'unknown',
            'engine' => 'none',
        ];
    }

    private function formatFeatureImportances(array $importances, string $type): array
    {
        return collect($importances)
            ->filter(function ($item) use ($type) {
                if ($type === 'risk') {
                    return ($item['direction'] ?? '') === 'increases_risk';
                }
                return ($item['direction'] ?? '') === 'decreases_risk';
            })
            ->map(function ($item) use ($type) {
                return [
                    'factor' => $item['display_name'] ?? $item['feature'],
                    'impact' => $item['importance'] ?? 0,
                    'direction' => $item['direction'] ?? '',
                    'value' => $item['value'] ?? 0,
                    'icon' => $type === 'risk' ? '⚠' : '✓',
                ];
            })
            ->values()
            ->toArray();
    }

    private function generateRiskRecommendations(array $data): array
    {
        $recs = [];
        $level = $data['risk_level'] ?? 'low';

        if ($level === 'high') {
            $recs[] = ['priority' => 'high', 'text' => 'Consider sending a confirmation reminder closer to the appointment date.'];
            $recs[] = ['priority' => 'high', 'text' => 'Request advance payment or deposit to reduce no-show risk.'];
        } elseif ($level === 'medium') {
            $recs[] = ['priority' => 'medium', 'text' => 'Send a standard reminder notification a day before.'];
        }

        return $recs;
    }

    private function extractStrengths(array $staff): array
    {
        $strengths = [];
        if (($staff['completion_rate'] ?? 0) >= 0.9) $strengths[] = 'Top performer';
        if (($staff['workload_today'] ?? 0) <= 2) $strengths[] = 'Low workload today';
        if (($staff['specialization_count'] ?? 0) >= 5) $strengths[] = 'Service specialist';
        if (($staff['total_handled'] ?? 0) >= 50) $strengths[] = 'Highly experienced';
        return $strengths;
    }

    private function extractConsiderations(array $staff): array
    {
        $considerations = [];
        if (($staff['workload_today'] ?? 0) >= 6) $considerations[] = 'Heavy workload today';
        if (($staff['total_handled'] ?? 0) < 5) $considerations[] = 'Limited experience';
        if (!($staff['is_available'] ?? true)) $considerations[] = 'Currently unavailable';
        return $considerations;
    }

    private function customerRiskProfile(int $total, int $cancelled, int $noShow): string
    {
        if ($total < 3) return 'new_customer';
        $failRate = ($cancelled + $noShow) / $total;
        if ($failRate >= 0.4) return 'high_risk';
        if ($failRate >= 0.2) return 'medium_risk';
        return 'reliable';
    }
}
