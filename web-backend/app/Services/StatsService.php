<?php

namespace App\Services;

use Exception;

/**
 * StatsService: Handles dashboard statistics and analytics
 * Responsibilities: Aggregating and calculating dashboard metrics
 */
class StatsService
{
    private UserService $userService;
    private AppointmentService $appointmentService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->appointmentService = new AppointmentService();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        try {
            $totalUsers = $this->getTotalUsers();
            $totalAppointments = $this->getTotalAppointments();
            $pendingAppointments = $this->getPendingAppointments();
            $revenue = $this->calculateRevenue();

            return [
                'totalUsers' => $totalUsers,
                'totalAppointments' => $totalAppointments,
                'pendingAppointments' => $pendingAppointments,
                'revenue' => $revenue,
                'timestamp' => now(),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get dashboard stats: ' . $e->getMessage());
        }
    }

    /**
     * Get total users count
     */
    private function getTotalUsers(): int
    {
        try {
            return $this->userService->getUsersByRole('client')->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get total appointments count
     */
    private function getTotalAppointments(): int
    {
        try {
            return $this->appointmentService->getAppointmentsByStatus('completed')->count() +
                   $this->appointmentService->getAppointmentsByStatus('pending')->count() +
                   $this->appointmentService->getAppointmentsByStatus('approved')->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get pending appointments count
     */
    private function getPendingAppointments(): int
    {
        try {
            return $this->appointmentService->getAppointmentsByStatus('pending')->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate revenue from completed appointments with service prices
     */
    private function calculateRevenue(): float
    {
        try {
            $revenue = \DB::table('appointments')
                ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                ->where('appointments.status', 'completed')
                ->select(\DB::raw('COALESCE(SUM(services.price), 0) as total'))
                ->value('total');
            
            return (float)$revenue;
        } catch (Exception $e) {
            \Log::error('Revenue calculation failed: ' . $e->getMessage());
            return 0.00;
        }
    }
}
