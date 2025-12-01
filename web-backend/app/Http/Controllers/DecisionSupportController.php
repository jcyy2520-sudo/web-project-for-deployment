<?php

namespace App\Http\Controllers;

use App\Services\DecisionSupportService;
use Illuminate\Http\Request;

class DecisionSupportController extends Controller
{
    protected $decisionSupportService;

    public function __construct(DecisionSupportService $decisionSupportService)
    {
        $this->decisionSupportService = $decisionSupportService;
    }

    /**
     * Get staff recommendations for an appointment
     * GET /api/decision-support/staff-recommendations
     */
    public function getStaffRecommendations(Request $request)
    {
        $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'service_type' => 'nullable|string',
            'customer_id' => 'nullable|exists:users,id',
        ]);

        try {
            $recommendations = $this->decisionSupportService->getStaffRecommendations(
                $request->appointment_date,
                $request->appointment_time,
                $request->service_type,
                $request->customer_id
            );

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'message' => 'Staff recommendations retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving staff recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get time slot recommendations for a specific date
     * GET /api/decision-support/time-slot-recommendations
     */
    public function getTimeSlotRecommendations(Request $request)
    {
        $request->validate([
            'appointment_date' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:240',
        ]);

        try {
            $recommendations = $this->decisionSupportService->getTimeSlotRecommendations(
                $request->appointment_date,
                $request->duration_minutes ?? 30
            );

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'message' => 'Time slot recommendations retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving time slot recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get risk assessment for a specific appointment
     * GET /api/decision-support/appointment-risk/{appointmentId}
     */
    public function getAppointmentRisk($appointmentId)
    {
        try {
            $assessment = $this->decisionSupportService->getAppointmentRiskAssessment($appointmentId);

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $assessment,
                'message' => 'Risk assessment retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving risk assessment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workload optimization recommendations
     * GET /api/decision-support/workload-optimization
     */
    public function getWorkloadOptimization(Request $request)
    {
        $request->validate([
            'appointment_date' => 'required|date',
        ]);

        try {
            $recommendations = $this->decisionSupportService->getWorkloadOptimization(
                $request->appointment_date
            );

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'message' => 'Workload optimization recommendations retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workload recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive decision support dashboard
     * GET /api/decision-support/dashboard
     */
    public function getDashboard(Request $request)
    {
        $request->validate([
            'appointment_date' => 'required|date',
        ]);

        try {
            $appointmentDate = $request->appointment_date;

            $dashboard = [
                'date' => $appointmentDate,
                'workload_overview' => $this->decisionSupportService->getWorkloadOptimization($appointmentDate),
                'time_slot_recommendations' => $this->decisionSupportService->getTimeSlotRecommendations($appointmentDate),
                'generated_at' => now(),
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Decision support dashboard retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}
