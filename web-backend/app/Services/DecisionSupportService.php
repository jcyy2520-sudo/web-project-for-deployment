<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Models\UnavailableDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DecisionSupportService
{
    /**
     * Get staff recommendations for an appointment
     * Recommends the best staff member to assign based on multiple factors
     */
    public function getStaffRecommendations($appointmentDate, $appointmentTime, $serviceType = null, $customerId = null)
    {
        $staffMembers = User::where('role', 'staff')->get();
        
        $recommendations = [];
        
        foreach ($staffMembers as $staff) {
            $score = $this->calculateStaffScore($staff, $appointmentDate, $appointmentTime, $serviceType, $customerId);
            
            $recommendations[] = [
                'staff_id' => $staff->id,
                'name' => "{$staff->first_name} {$staff->last_name}",
                'email' => $staff->email,
                'score' => $score['total'],
                'reasoning' => $score['reasoning'],
                'details' => $score['details'],
                'available' => $score['available'],
            ];
        }
        
        // Sort by score (highest first)
        usort($recommendations, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($recommendations, 0, 3); // Top 3 recommendations
    }

    /**
     * Calculate a score for a staff member for a given appointment
     */
    private function calculateStaffScore($staff, $appointmentDate, $appointmentTime, $serviceType = null, $customerId = null)
    {
        $score = 0;
        $reasoning = [];
        $details = [];
        $available = true;

        // 1. Check availability (highest priority - if unavailable, score is 0)
        $isAvailable = $this->checkStaffAvailability($staff->id, $appointmentDate, $appointmentTime);
        if (!$isAvailable) {
            $available = false;
            return [
                'total' => 0,
                'available' => false,
                'reasoning' => ['Not available on this date/time'],
                'details' => []
            ];
        }

        // 2. Workload balance (25 points max)
        $workloadScore = $this->calculateWorkloadScore($staff->id, $appointmentDate);
        $score += $workloadScore;
        if ($workloadScore > 0) {
            $details['workload'] = $workloadScore;
            $reasoning[] = "Workload: " . ($workloadScore >= 20 ? "Light schedule" : "Moderate schedule");
        }

        // 3. Specialization match (20 points max)
        if ($serviceType) {
            $specializationScore = $this->calculateSpecializationScore($staff, $serviceType);
            $score += $specializationScore;
            if ($specializationScore > 0) {
                $details['specialization'] = $specializationScore;
                $reasoning[] = "Specialization match: " . ($specializationScore >= 15 ? "Excellent" : "Good");
            }
        }

        // 4. Customer preference/history (20 points max)
        if ($customerId) {
            $customerHistoryScore = $this->calculateCustomerHistoryScore($staff->id, $customerId);
            $score += $customerHistoryScore;
            if ($customerHistoryScore > 0) {
                $details['customer_history'] = $customerHistoryScore;
                $reasoning[] = "Customer history: Previous positive interactions";
            }
        }

        // 5. Performance rating (20 points max)
        $performanceScore = $this->calculatePerformanceScore($staff->id);
        $score += $performanceScore;
        if ($performanceScore > 0) {
            $details['performance'] = $performanceScore;
            $reasoning[] = "Performance: " . ($performanceScore >= 15 ? "Excellent" : "Good");
        }

        // 6. Appointment completion rate (15 points max)
        $completionScore = $this->calculateCompletionScore($staff->id);
        $score += $completionScore;
        if ($completionScore > 0) {
            $details['completion_rate'] = $completionScore;
            $reasoning[] = "Completion rate: High reliability";
        }

        return [
            'total' => $score,
            'available' => $available,
            'reasoning' => count($reasoning) > 0 ? $reasoning : ['Suitable candidate'],
            'details' => $details
        ];
    }

    /**
     * Check if staff is available at the specified date and time
     */
    private function checkStaffAvailability($staffId, $appointmentDate, $appointmentTime)
    {
        // Check unavailable dates
        $isDateUnavailable = UnavailableDate::where('user_id', $staffId)
            ->where('date', $appointmentDate)
            ->exists();
        
        if ($isDateUnavailable) {
            return false;
        }

        // Check existing appointments for conflicts
        $hasConflict = Appointment::where('staff_id', $staffId)
            ->where('appointment_date', $appointmentDate)
            ->where('appointment_time', $appointmentTime)
            ->where('status', '!=', 'cancelled')
            ->exists();
        
        return !$hasConflict;
    }

    /**
     * Calculate workload score (less busy is better)
     */
    private function calculateWorkloadScore($staffId, $appointmentDate)
    {
        $appointmentCount = Appointment::where('staff_id', $staffId)
            ->where('appointment_date', $appointmentDate)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($appointmentCount === 0) return 25; // Empty day = best
        if ($appointmentCount <= 2) return 20; // Light
        if ($appointmentCount <= 4) return 15; // Moderate
        if ($appointmentCount <= 6) return 10; // Busy
        return 5; // Very busy
    }

    /**
     * Calculate specialization score based on service type match
     */
    private function calculateSpecializationScore($staff, $serviceType)
    {
        if (!$serviceType) return 0;

        // Get staff's service expertise from appointments
        $staffServiceMatches = Appointment::where('staff_id', $staff->id)
            ->where('service_type', $serviceType)
            ->where('status', 'completed')
            ->count();

        if ($staffServiceMatches >= 10) return 20; // Expert
        if ($staffServiceMatches >= 5) return 15;  // Experienced
        if ($staffServiceMatches >= 2) return 10;  // Has experience
        return 0; // No experience with this service type
    }

    /**
     * Calculate score based on previous customer interactions
     */
    private function calculateCustomerHistoryScore($staffId, $customerId)
    {
        $previousAppointments = Appointment::where('staff_id', $staffId)
            ->where('user_id', $customerId)
            ->where('status', 'completed')
            ->count();

        if ($previousAppointments >= 5) return 20; // Regular customer preference
        if ($previousAppointments >= 3) return 15;
        if ($previousAppointments >= 1) return 10; // Familiar with customer
        return 0;
    }

    /**
     * Calculate performance score based on completion rates
     */
    private function calculatePerformanceScore($staffId)
    {
        $totalAppointments = Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($totalAppointments === 0) return 10; // New staff
        
        $completedAppointments = Appointment::where('staff_id', $staffId)
            ->where('status', 'completed')
            ->count();

        $completionPercentage = ($completedAppointments / $totalAppointments) * 100;

        if ($completionPercentage >= 95) return 20;
        if ($completionPercentage >= 85) return 15;
        if ($completionPercentage >= 75) return 10;
        return 5;
    }

    /**
     * Calculate completion score
     */
    private function calculateCompletionScore($staffId)
    {
        $recentAppointments = Appointment::where('staff_id', $staffId)
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->count();

        if ($recentAppointments === 0) return 10;

        $completedRecent = Appointment::where('staff_id', $staffId)
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->count();

        $rate = ($completedRecent / $recentAppointments) * 100;

        if ($rate >= 90) return 15;
        if ($rate >= 75) return 10;
        if ($rate >= 50) return 5;
        return 0;
    }

    /**
     * Get appointment time slot recommendations
     * Suggests the best available time slots for a given date
     */
    public function getTimeSlotRecommendations($appointmentDate, $durationMinutes = 30)
    {
        $workingHours = [
            'start' => 9,   // 9 AM
            'end' => 17,    // 5 PM
        ];

        $timeSlots = [];
        
        // Generate time slots throughout the day
        for ($hour = $workingHours['start']; $hour < $workingHours['end']; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $timeStr = sprintf('%02d:%02d', $hour, $minute);
                $timeSlots[] = $timeStr;
            }
        }

        $recommendations = [];

        foreach ($timeSlots as $timeSlot) {
            $score = $this->calculateTimeSlotScore($appointmentDate, $timeSlot);
            
            $recommendations[] = [
                'time' => $timeSlot,
                'score' => $score['total'],
                'available' => $score['available'],
                'reasoning' => $score['reasoning'],
                'available_staff' => $score['available_staff_count'],
            ];
        }

        // Sort by score (highest first)
        usort($recommendations, function ($a, $b) {
            if ($b['available'] != $a['available']) {
                return $b['available'] <=> $a['available'];
            }
            return $b['score'] <=> $a['score'];
        });

        return array_slice($recommendations, 0, 5); // Top 5 recommendations
    }

    /**
     * Calculate score for a time slot
     */
    private function calculateTimeSlotScore($appointmentDate, $timeSlot)
    {
        $score = 0;
        $reasoning = [];

        // Check if any staff is available at this time
        $availableStaffCount = User::where('role', 'staff')
            ->whereNotIn('id', function ($query) use ($appointmentDate, $timeSlot) {
                $query->select('staff_id')
                    ->from('appointments')
                    ->where('appointment_date', $appointmentDate)
                    ->where('appointment_time', $timeSlot)
                    ->where('status', '!=', 'cancelled');
            })
            ->count();

        if ($availableStaffCount === 0) {
            return [
                'total' => 0,
                'available' => false,
                'reasoning' => ['No staff available'],
                'available_staff_count' => 0,
            ];
        }

        // Prefer mid-day slots
        list($hour, $minute) = explode(':', $timeSlot);
        $hour = (int)$hour;

        if ($hour >= 10 && $hour <= 14) {
            $score += 20;
            $reasoning[] = "Preferred time: Mid-day slot";
        } elseif ($hour >= 9 && $hour <= 17) {
            $score += 10;
            $reasoning[] = "Good time: Within business hours";
        }

        // Prefer less busy times
        $appointmentCount = Appointment::where('appointment_date', $appointmentDate)
            ->where('appointment_time', $timeSlot)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($appointmentCount === 0) {
            $score += 15;
            $reasoning[] = "High availability: No conflicts";
        } elseif ($appointmentCount <= 2) {
            $score += 10;
            $reasoning[] = "Good availability: Few conflicts";
        }

        // Round times are slightly preferred
        if ($minute === 0) {
            $score += 5;
        }

        return [
            'total' => $score,
            'available' => true,
            'reasoning' => count($reasoning) > 0 ? $reasoning : ['Available time slot'],
            'available_staff_count' => $availableStaffCount,
        ];
    }

    /**
     * Get appointment risk assessment
     * Predicts which appointments are at risk of cancellation or no-show
     */
    public function getAppointmentRiskAssessment($appointmentId)
    {
        $appointment = Appointment::with(['user', 'staff', 'service'])->find($appointmentId);
        
        if (!$appointment) {
            return null;
        }

        $riskScore = 0;
        $riskFactors = [];

        // 1. Customer no-show history
        $totalAppointments = Appointment::where('user_id', $appointment->user_id)->count();
        $cancelledAppointments = Appointment::where('user_id', $appointment->user_id)
            ->where('status', 'cancelled')
            ->count();
        $noShowAppointments = Appointment::where('user_id', $appointment->user_id)
            ->where('status', 'no_show')
            ->count();

        if ($totalAppointments > 0) {
            $cancellationRate = ($cancelledAppointments / $totalAppointments) * 100;
            $noShowRate = ($noShowAppointments / $totalAppointments) * 100;

            if ($noShowRate > 20) {
                $riskScore += 25;
                $riskFactors[] = "High no-show history (" . round($noShowRate, 1) . "%)";
            } elseif ($noShowRate > 10) {
                $riskScore += 15;
                $riskFactors[] = "Moderate no-show history (" . round($noShowRate, 1) . "%)";
            }

            if ($cancellationRate > 30) {
                $riskScore += 20;
                $riskFactors[] = "High cancellation rate (" . round($cancellationRate, 1) . "%)";
            }
        }

        // 2. Appointment proximity (closer appointments are riskier)
        $daysUntilAppointment = Carbon::now()->diffInDays(Carbon::parse($appointment->appointment_date));
        
        if ($daysUntilAppointment <= 1) {
            $riskScore += 5;
            $riskFactors[] = "Last-minute appointment";
        } elseif ($daysUntilAppointment > 30) {
            $riskScore -= 10; // Reduce risk for far-future appointments
            $riskFactors[] = "Well-planned appointment (low urgency)";
        }

        // 3. Peak hours are riskier
        $hour = (int)explode(':', $appointment->appointment_time)[0];
        if ($hour >= 12 && $hour <= 14) { // Lunch time
            $riskScore += 10;
            $riskFactors[] = "During peak hours";
        }

        // 4. Day of week (Mondays and Fridays have higher no-show rates)
        $dayOfWeek = Carbon::parse($appointment->appointment_date)->dayOfWeek;
        if ($dayOfWeek == 1 || $dayOfWeek == 5) {
            $riskScore += 10;
            $riskFactors[] = "Higher no-show day (Monday/Friday)";
        }

        // Determine risk level
        if ($riskScore >= 60) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 30) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }

        return [
            'appointment_id' => $appointmentId,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'recommendations' => $this->getAppointmentRiskRecommendations($riskLevel, $appointment),
        ];
    }

    /**
     * Get recommendations to mitigate appointment risk
     */
    private function getAppointmentRiskRecommendations($riskLevel, $appointment)
    {
        $recommendations = [];

        if ($riskLevel === 'high') {
            $recommendations[] = [
                'action' => 'send_reminder',
                'description' => 'Send SMS/Email reminder 24 hours before',
                'priority' => 'high',
            ];
            $recommendations[] = [
                'action' => 'confirm_call',
                'description' => 'Make confirmation call to customer',
                'priority' => 'high',
            ];
            $recommendations[] = [
                'action' => 'increase_fee',
                'description' => 'Consider prepayment or deposit',
                'priority' => 'medium',
            ];
        } elseif ($riskLevel === 'medium') {
            $recommendations[] = [
                'action' => 'send_reminder',
                'description' => 'Send automated reminder 24 hours before',
                'priority' => 'medium',
            ];
            $recommendations[] = [
                'action' => 'have_backup',
                'description' => 'Keep a backup staff member available',
                'priority' => 'medium',
            ];
        } else {
            $recommendations[] = [
                'action' => 'monitor',
                'description' => 'Standard appointment process',
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }

    /**
     * Get workload optimization recommendations
     * Suggests how to balance workload across staff
     */
    public function getWorkloadOptimization($appointmentDate)
    {
        $staffMembers = User::where('role', 'staff')->get();
        $recommendations = [];

        foreach ($staffMembers as $staff) {
            $appointmentCount = Appointment::where('staff_id', $staff->id)
                ->where('appointment_date', $appointmentDate)
                ->where('status', '!=', 'cancelled')
                ->count();

            $recommendations[] = [
                'staff_id' => $staff->id,
                'staff_name' => "{$staff->first_name} {$staff->last_name}",
                'appointments_scheduled' => $appointmentCount,
                'capacity_percentage' => ($appointmentCount / 10) * 100, // Assuming 10 is max
                'available_slots' => max(0, 10 - $appointmentCount),
                'status' => $appointmentCount >= 8 ? 'overloaded' : ($appointmentCount >= 5 ? 'busy' : 'available'),
            ];
        }

        // Sort by available slots
        usort($recommendations, function ($a, $b) {
            return $b['available_slots'] <=> $a['available_slots'];
        });

        return $recommendations;
    }
}
