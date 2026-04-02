<?php

namespace App\Http\Controllers;

use App\Services\MLDecisionSupportService;
use App\Traits\SafeExperimentalFeature;
use Illuminate\Http\Request;

class DecisionSupportController extends Controller
{
    use SafeExperimentalFeature;

    protected MLDecisionSupportService $mlService;

    public function __construct(MLDecisionSupportService $mlService)
    {
        $this->mlService = $mlService;
    }

    /**
     * Get ML-backed time slot recommendations for a specific date
     * GET /api/decision-support/time-slot-recommendations
     */
    public function getTimeSlotRecommendations(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'appointment_date' => 'required|date',
                'duration_minutes' => 'nullable|integer|min:15|max:240',
                'user_id' => 'nullable|exists:users,id',
                'service_id' => 'nullable|exists:services,id',
            ]);

            try {
                $result = $this->mlService->getTimeSlotRecommendations(
                    $request->appointment_date,
                    $request->duration_minutes ?? 30
                );

                return response()->json([
                    'success' => true,
                    'data' => $result['slots'] ?? [],
                    'summary' => $result['summary'] ?? [],
                    'meta' => [
                        'engine' => $result['engine'] ?? 'none',
                    ],
                    'status' => $result['status'] ?? 'ok',
                    'message' => $result['message'] ?? 'Time slot recommendations retrieved successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error: ' . $e->getMessage() : 'Error retrieving time slot recommendations',
                ], 500);
            }
        }, 'decision_support.time_slot_recommendations');
    }

    /**
     * Get ML-backed risk assessment for a specific appointment
     * GET /api/decision-support/appointment-risk/{appointmentId}
     */
    public function getAppointmentRisk($appointmentId)
    {
        return $this->wrapExperimental(function () use ($appointmentId) {
            try {
                $assessment = $this->mlService->getAppointmentRiskAssessment(
                    (int) $appointmentId
                );

                if (!$assessment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Appointment not found',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $assessment,
                    'message' => 'Risk assessment retrieved successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error: ' . $e->getMessage() : 'Error retrieving risk assessment',
                ], 500);
            }
        }, 'decision_support.appointment_risk');
    }

    /**
     * Get workload optimization recommendations
     * GET /api/decision-support/workload-optimization
     */
    public function getWorkloadOptimization(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'appointment_date' => 'required|date',
            ]);

            try {
                $result = $this->mlService->getWorkloadOptimization(
                    $request->appointment_date
                );

                return response()->json([
                    'success' => true,
                    'data' => $result['staff'],
                    'summary' => $result['summary'],
                    'insights' => $result['insights'],
                    'message' => 'Workload optimization recommendations retrieved successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error: ' . $e->getMessage() : 'Error retrieving workload recommendations',
                ], 500);
            }
        }, 'decision_support.workload_optimization');
    }

    /**
     * Get customer insights for decision support
     * GET /api/decision-support/customer-insights/{customerId}
     */
    public function getCustomerInsights($customerId)
    {
        return $this->wrapExperimental(function () use ($customerId) {
            try {
                $insights = $this->mlService->getCustomerInsights(
                    (int) $customerId
                );

                if (!$insights) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer not found',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $insights,
                    'message' => 'Customer insights retrieved successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error: ' . $e->getMessage() : 'Error retrieving customer insights',
                ], 500);
            }
        }, 'decision_support.customer_insights');
    }

    /**
     * Get comprehensive decision support dashboard
     * GET /api/decision-support/dashboard
     */
    public function getDashboard(Request $request)
    {
        return $this->wrapExperimental(function () use ($request) {
            $request->validate([
                'appointment_date' => 'required|date',
            ]);

            try {
                $dashboard = $this->mlService->getDashboardData(
                    $request->appointment_date
                );

                return response()->json([
                    'success' => true,
                    'data' => $dashboard,
                    'message' => 'Decision support dashboard retrieved successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Error: ' . $e->getMessage() : 'Error retrieving dashboard',
                ], 500);
            }
        }, 'decision_support.dashboard');
    }

    // ─── New ML Endpoints ────────────────────────────────────────────────

    /**
     * Get data quality report for ML training readiness
     * GET /api/decision-support/data-quality
     */
    public function getDataQuality()
    {
        try {
            $quality = $this->mlService->getDataQuality();
            return response()->json([
                'success' => true,
                'data' => $quality,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking data quality: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger ML model training
     * POST /api/decision-support/train
     * Admin only
     */
    public function trainModel(Request $request)
    {
        try {
            $result = $this->mlService->trainModel();
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Training failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log appointment outcome for ML feedback loop
     * POST /api/decision-support/outcome
     */
    public function logOutcome(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
            'outcome' => 'required|string|in:completed,cancelled,no_show',
            'feedback' => 'nullable|string|in:accepted,rejected,overridden',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->mlService->logOutcome(
                $request->appointment_id,
                $request->outcome,
                $request->feedback,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error logging outcome: ' . $e->getMessage(),
            ], 500);
        }
    }
}
