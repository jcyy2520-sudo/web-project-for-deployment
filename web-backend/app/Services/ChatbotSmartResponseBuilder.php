<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ChatbotSmartResponseBuilder
 * 
 * Builds contextual, role-aware, intelligent responses based on:
 * - Detected intent and entities
 * - User role and capabilities
 * - Real-time system data
 * - Action results
 * - User sentiment and urgency
 * 
 * Features:
 * - Personalized responses for each role
 * - Data-driven content (no guessing)
 * - Contextual follow-up questions
 * - Clear call-to-action
 * - Professional but friendly tone
 */
class ChatbotSmartResponseBuilder
{
    private ChatbotRoleAwarenessService $roleService;
    private ChatbotRealTimeDataService $dataService;
    private ChatbotNLUService $nluService;

    public function __construct(
        ChatbotRoleAwarenessService $roleService,
        ChatbotRealTimeDataService $dataService,
        ChatbotNLUService $nluService
    ) {
        $this->roleService = $roleService;
        $this->dataService = $dataService;
        $this->nluService = $nluService;
    }

    /**
     * Build a smart response based on context
     * 
     * @param array $context Response context
     * @return array Response with metadata
     */
    public function build(array $context): array
    {
        try {
            $intent = $context['intent'] ?? 'general_question';
            $userId = $context['user_id'] ?? null;
            $roleInfo = $context['role_info'] ?? $this->roleService->detectUserRole($userId);
            $entities = $context['entities'] ?? [];
            $sentiment = $context['sentiment'] ?? ['sentiment' => 'neutral', 'score' => 3];
            $actionResult = $context['action_result'] ?? null;
            $isFollowup = $context['is_followup'] ?? false;
            $mergedEntities = $context['merged_entities'] ?? $entities;
            $language = $context['language'] ?? 'english';

            // Use merged entities for follow-up intents
            if ($isFollowup && !empty($mergedEntities)) {
                $entities = $mergedEntities;
            }

            // Handle confirmation follow-ups
            if (str_ends_with($intent, '_confirm')) {
                return $this->handleConfirmation($context, $roleInfo, $language);
            }
            
            if (str_ends_with($intent, '_cancel')) {
                return $this->handleCancellation($context, $roleInfo, $language);
            }

            // Build response based on intent
            $response = match ($intent) {
                'view_appointments' => $this->buildViewAppointmentsResponse($userId, $roleInfo, $language),
                'check_appointment_status' => $this->buildCheckStatusResponse($userId, $entities, $roleInfo, $language),
                'book_appointment' => $this->buildBookAppointmentResponse($roleInfo, $language),
                'cancel_appointment' => $this->buildCancelAppointmentResponse($userId, $entities, $roleInfo, $language),
                'reschedule_appointment' => $this->buildRescheduleResponse($userId, $entities, $roleInfo, $language),
                'view_payments' => $this->buildViewPaymentsResponse($userId, $roleInfo, $language),
                'check_payment_status' => $this->buildCheckPaymentStatusResponse($userId, $entities, $roleInfo, $language),
                'process_payment' => $this->buildProcessPaymentResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'request_refund' => $this->buildRequestRefundResponse($userId, $entities, $roleInfo, $language),
                'view_refunds' => $this->buildViewRefundsResponse($userId, $roleInfo, $language),
                'check_refund_status' => $this->buildCheckRefundStatusResponse($userId, $entities, $roleInfo, $language),
                'approve_refund' => $this->buildApproveRefundResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'reject_refund' => $this->buildRejectRefundResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'process_refund' => $this->buildProcessRefundResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'view_services' => $this->buildViewServicesResponse($roleInfo, $language),
                'service_details' => $this->buildServiceDetailsResponse($entities, $roleInfo, $language),
                'service_pricing' => $this->buildServicePricingResponse($entities, $roleInfo, $language),
                'view_availability' => $this->buildViewAvailabilityResponse($entities, $roleInfo, $language),
                'business_hours' => $this->buildBusinessHoursResponse($roleInfo, $language),
                'view_profile' => $this->buildViewProfileResponse($userId, $roleInfo, $language),
                'edit_profile' => $this->buildEditProfileResponse($userId, $roleInfo, $language),
                'view_pending_appointments' => $this->buildPendingAppointmentsResponse($roleInfo, $language),
                'approve_appointment' => $this->buildApproveAppointmentResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'decline_appointment' => $this->buildDeclineAppointmentResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'complete_appointment' => $this->buildCompleteAppointmentResponse($userId, $entities, $roleInfo, $actionResult, $language),
                'view_all_appointments' => $this->buildViewAllAppointmentsResponse($roleInfo, $language),
                'view_analytics' => $this->buildViewAnalyticsResponse($roleInfo, $language),
                'manage_users' => $this->buildManageUsersResponse($roleInfo, $language),
                'view_pending_payments' => $this->buildPendingPaymentsResponse($roleInfo, $language),
                'view_pending_refunds' => $this->buildPendingRefundsResponse($roleInfo, $language),
                'shift_report' => $this->buildShiftReportResponse($userId, $roleInfo, $language),
                'verify_receipt' => $this->buildVerifyReceiptResponse($entities, $roleInfo, $language),
                'system_status' => $this->buildSystemStatusResponse($roleInfo, $language),
                'help' => $this->buildHelpResponse($roleInfo, $language),
                'greeting' => $this->buildGreetingResponse($roleInfo, $language),
                'farewell' => $this->buildFarewellResponse($roleInfo, $language),
                default => $this->buildGeneralResponse($context),
            };

            // Add sentiment-aware adjustments
            if (isset($sentiment['score']) && $sentiment['score'] >= 4) {
                $response['tone_adjustment'] = 'empathetic';
                $response['priority'] = true;
                
                // Prepend empathetic message for frustrated users
                if (in_array($sentiment['sentiment'] ?? '', ['frustrated', 'angry', 'negative'])) {
                    $empathyMsg = $language === 'filipino' 
                        ? "Naiintindihan ko po na medyo nakakafrustrate ito. Tutulungan ko po kayo agad.\n\n"
                        : "I understand this might be frustrating. Let me help you right away.\n\n";
                    $response['response'] = $empathyMsg . $response['response'];
                }
            }

            // Add role-specific metadata
            $response['meta'] = [
                'intent' => $intent,
                'role' => $roleInfo['primary_role'] ?? 'guest',
                'is_authenticated' => $roleInfo['is_authenticated'] ?? false,
                'timestamp' => now()->toDateTimeString(),
                'entities_used' => count($entities),
                'data_sources' => $response['data_sources'] ?? ['database'],
                'is_followup' => $isFollowup,
                'language' => $language,
            ];
            
            // Add source tracking
            $response['meta']['source'] = $response['has_data'] ?? false ? 'realtime_data' : 'smart_builder';

            return $response;
        } catch (\Exception $e) {
            Log::error('Error building response', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * Handle confirmation follow-up
     */
    private function handleConfirmation(array $context, array $roleInfo, string $language = 'english'): array
    {
        $originalIntent = $context['original_intent'] ?? '';
        $entities = $context['inherited_entities'] ?? [];

        return match ($originalIntent) {
            'cancel_appointment' => [
                'response' => "✅ Your appointment has been cancelled successfully. You'll receive a confirmation email shortly.",
                'action_executed' => true,
                'action_type' => 'cancel_appointment',
            ],
            'request_refund' => [
                'response' => "✅ Your refund request has been submitted successfully. You'll receive updates via email.",
                'action_executed' => true,
                'action_type' => 'request_refund',
            ],
            default => [
                'response' => "✅ Action confirmed. Your request has been processed.",
                'action_executed' => true,
            ],
        };
    }

    /**
     * Handle cancellation follow-up
     */
    private function handleCancellation(array $context, array $roleInfo): array
    {
        return [
            'response' => "No problem! The action has been cancelled. Is there anything else I can help you with?",
            'action_executed' => false,
            'action_type' => 'cancelled_by_user',
        ];
    }

    /**
     * Build response for viewing appointments
     */
    private function buildViewAppointmentsResponse(int $userId = null, array $roleInfo, string $language = 'english'): array
    {
        if (!$userId) {
            $msg = $language === 'filipino' 
                ? "Para makita ang mga appointments niyo, mag-login po muna sa account niyo. Pagkatapos mag-login, makikita niyo na ang lahat ng bookings niyo kasama ang status."
                : "To view your appointments, please log in to your account. Once logged in, you'll see all your bookings with their current status.";
            return [
                'response' => $msg,
                'action_suggested' => 'login',
            ];
        }

        $appointments = $this->dataService->getUserAppointments($userId);

        if (empty($appointments)) {
            $msg = $language === 'filipino'
                ? "Wala pa kayong appointments. Gusto niyo bang mag-book? Pwede ko kayong tulungan mag-schedule ng appointment sa aming services."
                : "You don't have any appointments yet. Would you like to book one? I can help you schedule an appointment with our services.";
            return [
                'response' => $msg,
                'suggested_action' => 'book_appointment',
                'has_data' => false,
            ];
        }

        $formattedList = collect($appointments)->map(function ($apt) {
            $status = strtoupper($apt['status']);
            return "• **{$apt['date']} at {$apt['time']}** - {$apt['service']} (Status: {$status})";
        })->implode("\n");

        $upcoming = collect($appointments)->filter(fn($a) => $a['is_upcoming'])->count();
        $overdue = collect($appointments)->filter(fn($a) => $a['is_overdue'])->count();

        if ($language === 'filipino') {
            $response = "Meron kayong **" . count($appointments) . " appointment(s)**:\n\n{$formattedList}";
            
            if ($overdue > 0) {
                $response .= "\n\n⚠️ Meron kayong **{$overdue} overdue appointment(s)** na kailangan ng attention.";
            }
        } else {
            $response = "You have **" . count($appointments) . " appointment(s)**:\n\n{$formattedList}";

            if ($overdue > 0) {
                $response .= "\n\n⚠️ You have **{$overdue} overdue appointment(s)** that need attention.";
            }
        }

        return [
            'response' => $response,
            'summary' => [
                'total' => count($appointments),
                'upcoming' => $upcoming,
                'overdue' => $overdue,
            ],
            'has_data' => true,
            'data_sources' => ['appointments_table'],
        ];
    }

    /**
     * Build response for checking appointment status
     */
    private function buildCheckStatusResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            return [
                'response' => "I need to know which appointment you're asking about. Could you provide the appointment ID or the date of the appointment?",
                'requires_clarification' => true,
                'clarification_type' => 'appointment_id',
            ];
        }

        $appointment = $this->dataService->getAppointmentDetails($appointmentId);

        if (!$appointment) {
            return [
                'response' => "I couldn't find an appointment with ID #{$appointmentId}. Please check the ID and try again.",
                'has_data' => false,
            ];
        }

        // Check authorization
        if ($userId && $userId !== $appointment['user_id'] && $roleInfo['primary_role'] === 'client') {
            return [
                'response' => "You don't have permission to view this appointment.",
                'error' => 'unauthorized',
            ];
        }

        $status = strtoupper($appointment['status']);
        $paymentStatus = strtoupper($appointment['payment_status']);

        $response = "**Appointment #" . $appointmentId . " Status**\n\n";
        $response .= "• **Status:** {$status}\n";
        $response .= "• **Date:** {$appointment['date']}\n";
        $response .= "• **Time:** {$appointment['time']}\n";
        $response .= "• **Service:** {$appointment['service']}\n";
        $response .= "• **Payment Status:** {$paymentStatus}\n";

        if ($appointment['payment_amount']) {
            $response .= "• **Amount:** PHP " . number_format($appointment['payment_amount'], 2) . "\n";
        }

        if ($appointment['notes']) {
            $response .= "• **Notes:** {$appointment['notes']}\n";
        }

        return [
            'response' => $response,
            'appointment' => $appointment,
            'has_data' => true,
            'data_sources' => ['appointments_table'],
        ];
    }

    /**
     * Build response for booking appointment
     */
    private function buildBookAppointmentResponse(array $roleInfo): array
    {
        if (!$roleInfo['is_authenticated']) {
            return [
                'response' => "To book an appointment, please **log in or register** first. Registration is quick and free!\n\nOnce you're registered, you'll be able to:\n• Choose your preferred date and time\n• Select from our available services\n• View instant confirmation\n\nWould you like help registering?",
                'action_needed' => 'authentication',
            ];
        }

        $services = $this->dataService->getAvailableServices();
        $businessHours = $this->dataService->getBusinessHours();

        if (empty($services)) {
            return [
                'response' => "I'm unable to retrieve available services at the moment. Please try again later or contact our office directly.",
                'error' => 'no_services_available',
            ];
        }

        $servicesList = collect($services)->map(fn($s) => "• {$s['name']} - PHP " . number_format($s['price'], 2))->implode("\n");

        $response = "Great! You're ready to book. Here are our available services:\n\n{$servicesList}\n\n";
        $response .= "To complete your booking, please visit your dashboard and select:\n";
        $response .= "1. Your preferred **service**\n";
        $response .= "2. Desired **date and time**\n";
        $response .= "3. Any **special notes** or documents\n\n";
        $response .= "Your appointment will be pending approval. You'll receive a notification once it's confirmed.";

        return [
            'response' => $response,
            'services' => $services,
            'has_data' => true,
            'next_step' => 'visit_booking_page',
        ];
    }

    /**
     * Build response for canceling appointment
     */
    private function buildCancelAppointmentResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            return [
                'response' => "Which appointment would you like to cancel? Please provide the appointment ID or date.",
                'requires_clarification' => true,
            ];
        }

        $appointment = $this->dataService->getAppointmentDetails($appointmentId);

        if (!$appointment) {
            return [
                'response' => "I couldn't find appointment #{$appointmentId}. Please verify the ID.",
                'has_data' => false,
            ];
        }

        // Authorization check
        if ($userId && $userId !== $appointment['user_id'] && $roleInfo['primary_role'] === 'client') {
            return [
                'response' => "You don't have permission to cancel this appointment.",
                'error' => 'unauthorized',
            ];
        }

        if ($appointment['status'] === 'cancelled') {
            return [
                'response' => "This appointment is already cancelled.",
                'appointment_id' => $appointmentId,
            ];
        }

        if ($appointment['status'] === 'completed') {
            return [
                'response' => "This appointment has already been completed and cannot be cancelled.",
                'appointment_id' => $appointmentId,
            ];
        }

        $response = "**Confirm Cancellation**\n\n";
        $response .= "Are you sure you want to cancel appointment #{$appointmentId}?\n";
        $response .= "• **Date:** {$appointment['date']}\n";
        $response .= "• **Time:** {$appointment['time']}\n";
        $response .= "• **Service:** {$appointment['service']}\n";

        if ($appointment['payment_amount']) {
            $response .= "• **Amount Paid:** PHP " . number_format($appointment['payment_amount'], 2) . "\n";
        }

        $response .= "\nPlease confirm by replying **yes** to proceed with cancellation.";

        return [
            'response' => $response,
            'appointment_id' => $appointmentId,
            'requires_confirmation' => true,
            'action_type' => 'cancel_appointment',
        ];
    }

    /**
     * Build response for rescheduling appointment
     */
    private function buildRescheduleResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            return [
                'response' => "Which appointment would you like to reschedule? Please provide the appointment ID.",
                'requires_clarification' => true,
            ];
        }

        $appointment = $this->dataService->getAppointmentDetails($appointmentId);

        if (!$appointment) {
            return [
                'response' => "I couldn't find appointment #{$appointmentId}.",
                'has_data' => false,
            ];
        }

        $newDate = $entities['date'] ?? null;
        $newTime = $entities['time'] ?? null;

        if (!$newDate || !$newTime) {
            return [
                'response' => "I found your appointment on **{$appointment['date']}** at **{$appointment['time']}**. What's your preferred new date and time?",
                'appointment' => $appointment,
                'requires_new_slot' => true,
            ];
        }

        return [
            'response' => "Perfect! I'll reschedule your appointment from **{$appointment['date']}** to your new date. Please confirm this change in your dashboard.",
            'appointment' => $appointment,
            'action_needed' => 'confirm_reschedule',
        ];
    }

    /**
     * Build response for viewing payments
     */
    private function buildViewPaymentsResponse(int $userId = null, array $roleInfo): array
    {
        if (!$userId) {
            return [
                'response' => "Please log in to view your payment history.",
                'action_needed' => 'authentication',
            ];
        }

        $payments = $this->dataService->getUserPayments($userId);

        if (empty($payments)) {
            return [
                'response' => "You don't have any recorded payments yet.",
                'has_data' => false,
            ];
        }

        $formattedList = collect($payments)->map(function ($p) {
            return "• PHP " . number_format($p['amount'], 2) . " - {$p['status']} ({$p['created_at']})";
        })->implode("\n");

        return [
            'response' => "**Your Payments**\n\n{$formattedList}",
            'payment_count' => count($payments),
            'has_data' => true,
        ];
    }

    /**
     * Build response for checking payment status
     */
    private function buildCheckPaymentStatusResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            return [
                'response' => "Which appointment's payment would you like to check? Please provide the appointment ID.",
                'requires_clarification' => true,
            ];
        }

        $appointment = $this->dataService->getAppointmentDetails($appointmentId);

        if (!$appointment) {
            return [
                'response' => "I couldn't find appointment #{$appointmentId}.",
                'has_data' => false,
            ];
        }

        $paymentStatus = strtoupper($appointment['payment_status']);
        $response = "**Payment Status for Appointment #{$appointmentId}**\n\n";
        $response .= "• **Status:** {$paymentStatus}\n";
        $response .= "• **Amount Due:** PHP " . number_format($appointment['payment_amount'], 2) . "\n";

        if ($appointment['discount_amount']) {
            $response .= "• **Discount:** PHP " . number_format($appointment['discount_amount'], 2) . "\n";
        }

        return [
            'response' => $response,
            'appointment' => $appointment,
            'has_data' => true,
        ];
    }

    /**
     * Build response for processing payment (cashier)
     */
    private function buildProcessPaymentResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if ($roleInfo['primary_role'] !== 'cashier' && $roleInfo['primary_role'] !== 'admin') {
            return [
                'response' => "Only cashiers and admins can process payments.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "✅ Payment processed successfully! Appointment #{$actionResult['appointment_id']} is now marked as paid.",
                'action_result' => $actionResult,
            ];
        }

        return [
            'response' => "Payment processing requires an appointment ID. Which appointment's payment would you like to process?",
            'requires_clarification' => true,
        ];
    }

    /**
     * Build response for requesting refund
     */
    private function buildRequestRefundResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        if (!$userId) {
            return [
                'response' => "Please log in to request a refund.",
                'action_needed' => 'authentication',
            ];
        }

        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            $appointments = $this->dataService->getUserAppointments($userId);
            if (empty($appointments)) {
                return [
                    'response' => "You don't have any appointments with payments to refund.",
                    'has_data' => false,
                ];
            }

            $appointmentsList = collect($appointments)
                ->filter(fn($a) => $a['payment_status'] !== 'refunded')
                ->map(fn($a) => "• #{$a['id']} - {$a['date']} ({$a['service']})")
                ->implode("\n");

            return [
                'response' => "Which appointment would you like to request a refund for?\n\n{$appointmentsList}",
                'requires_clarification' => true,
            ];
        }

        return [
            'response' => "I'll help you request a refund for appointment #{$appointmentId}. Our finance team will review your request and respond within 3-5 business days.",
            'appointment_id' => $appointmentId,
            'action_type' => 'request_refund',
        ];
    }

    /**
     * Build response for viewing refunds
     */
    private function buildViewRefundsResponse(int $userId = null, array $roleInfo): array
    {
        if (!$userId) {
            return [
                'response' => "Please log in to view your refunds.",
                'action_needed' => 'authentication',
            ];
        }

        $refunds = $this->dataService->getUserRefunds($userId);

        if (empty($refunds)) {
            return [
                'response' => "You don't have any refund requests.",
                'has_data' => false,
            ];
        }

        $formattedList = collect($refunds)->map(function ($r) {
            return "• PHP " . number_format($r['amount'], 2) . " - Status: " . strtoupper($r['status']);
        })->implode("\n");

        return [
            'response' => "**Your Refunds**\n\n{$formattedList}",
            'refund_count' => count($refunds),
            'has_data' => true,
        ];
    }

    /**
     * Build response for checking refund status
     */
    private function buildCheckRefundStatusResponse(int $userId = null, array $entities, array $roleInfo): array
    {
        if (!$userId) {
            return [
                'response' => "Please log in to check refund status.",
                'action_needed' => 'authentication',
            ];
        }

        $refunds = $this->dataService->getUserRefunds($userId);
        $pendingCount = collect($refunds)->filter(fn($r) => $r['status'] === 'pending')->count();

        $response = "**Your Refund Status**\n\n";
        $response .= "• **Pending Refunds:** {$pendingCount}\n";
        $response .= "• **Total Refunds:** " . count($refunds) . "\n\n";
        $response .= "Refund requests are typically processed within 3-5 business days.";

        return [
            'response' => $response,
            'refunds' => $refunds,
            'has_data' => true,
        ];
    }

    /**
     * Build response for viewing services
     */
    private function buildViewServicesResponse(array $roleInfo): array
    {
        $services = $this->dataService->getAvailableServices();

        if (empty($services)) {
            return [
                'response' => "No services are currently available. Please try again later.",
                'has_data' => false,
            ];
        }

        $formattedList = collect($services)->map(function ($s) {
            $price = "PHP " . number_format($s['price'], 2);
            $duration = $s['duration_minutes'] ? " ({$s['duration_minutes']} min)" : "";
            return "• **{$s['name']}** - {$price}{$duration}\n  {$s['description']}";
        })->implode("\n\n");

        return [
            'response' => "**Our Available Services**\n\n{$formattedList}",
            'service_count' => count($services),
            'has_data' => true,
        ];
    }

    /**
     * Build response for service details
     */
    private function buildServiceDetailsResponse(array $entities, array $roleInfo): array
    {
        $serviceName = $entities['service'] ?? null;
        $services = $this->dataService->getAvailableServices();

        if (!$serviceName) {
            return [
                'response' => "Which service would you like to know more about?",
                'services' => $services,
                'requires_clarification' => true,
            ];
        }

        $service = collect($services)->first(fn($s) => stripos($s['name'], $serviceName) !== false);

        if (!$service) {
            return [
                'response' => "I couldn't find a service matching '{$serviceName}'. Available services: " .
                    collect($services)->pluck('name')->implode(', '),
                'has_data' => false,
            ];
        }

        return [
            'response' => "**{$service['name']}**\n\n{$service['description']}\n\n• **Price:** PHP " . number_format($service['price'], 2),
            'service' => $service,
            'has_data' => true,
        ];
    }

    /**
     * Build response for service pricing
     */
    private function buildServicePricingResponse(array $entities, array $roleInfo): array
    {
        $services = $this->dataService->getAvailableServices();

        if (empty($services)) {
            return [
                'response' => "Pricing information is currently unavailable.",
                'has_data' => false,
            ];
        }

        $minPrice = collect($services)->min('price');
        $maxPrice = collect($services)->max('price');

        $formattedList = collect($services)->map(fn($s) => "• {$s['name']}: PHP " . number_format($s['price'], 2))->implode("\n");

        $response = "**Service Pricing**\n\n{$formattedList}\n\n";
        $response .= "**Price Range:** PHP " . number_format($minPrice, 2) . " - PHP " . number_format($maxPrice, 2);

        return [
            'response' => $response,
            'services' => $services,
            'has_data' => true,
        ];
    }

    /**
     * Build response for viewing availability
     */
    private function buildViewAvailabilityResponse(array $entities, array $roleInfo): array
    {
        $date = $entities['date'] ?? null;

        if (!$date) {
            return [
                'response' => "For which date would you like to check availability? (Please provide in YYYY-MM-DD format)",
                'requires_clarification' => true,
            ];
        }

        $availability = $this->dataService->getDateAvailability($date['raw'] ?? date('Y-m-d'));

        if (empty($availability)) {
            return [
                'response' => "No availability information available for that date.",
                'has_data' => false,
            ];
        }

        $formattedSlots = collect($availability)->map(function ($status, $time) {
            return "• {$time} - {$status}";
        })->implode("\n");

        $dateLabel = $date['raw'] ?? 'selected date';
        return [
            'response' => "**Available Slots for {$dateLabel}**\n\n{$formattedSlots}",
            'availability' => $availability,
            'has_data' => true,
        ];
    }

    /**
     * Build response for business hours
     */
    private function buildBusinessHoursResponse(array $roleInfo): array
    {
        $hours = $this->dataService->getBusinessHours();

        if (!isset($hours['business_hours'])) {
            return [
                'response' => "Business hours information is not currently available.",
                'has_data' => false,
            ];
        }

        $response = "**Our Business Hours**\n\n{$hours['business_hours']}\n\n";
        $response .= $hours['is_open_today'] ? "✅ We're open today!" : "🔒 We're closed today.";

        return [
            'response' => $response,
            'hours' => $hours,
            'has_data' => true,
        ];
    }

    /**
     * Build response for pending appointments (admin)
     */
    private function buildPendingAppointmentsResponse(array $roleInfo): array
    {
        if ($roleInfo['primary_role'] !== 'admin' && $roleInfo['primary_role'] !== 'staff') {
            return [
                'response' => "Only admins and staff can view pending appointments.",
                'error' => 'unauthorized',
            ];
        }

        $pending = $this->dataService->getPendingAppointments();

        if (empty($pending)) {
            return [
                'response' => "✅ No pending appointments! All appointments have been approved or handled.",
                'has_data' => false,
            ];
        }

        $formattedList = collect($pending)->map(function ($apt) {
            return "• **#{$apt['id']}** - {$apt['user_name']} ({$apt['date']} {$apt['time']}) - Waiting {$apt['days_waiting']} days";
        })->implode("\n");

        return [
            'response' => "**Pending Appointments (" . count($pending) . ")**\n\n{$formattedList}\n\nUse 'approve appointment #ID' or 'decline appointment #ID' to take action.",
            'pending_count' => count($pending),
            'pending' => $pending,
            'has_data' => true,
        ];
    }

    /**
     * Build response for approving appointment (admin)
     */
    private function buildApproveAppointmentResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if ($roleInfo['primary_role'] !== 'admin' && $roleInfo['primary_role'] !== 'staff') {
            return [
                'response' => "Only admins and staff can approve appointments.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "✅ Appointment #{$actionResult['appointment_id']} has been approved!",
                'action_result' => $actionResult,
            ];
        }

        return [
            'response' => "Which appointment would you like to approve? Please provide the appointment ID.",
            'requires_clarification' => true,
        ];
    }

    /**
     * Build response for declining appointment
     */
    private function buildDeclineAppointmentResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if ($roleInfo['primary_role'] !== 'admin' && $roleInfo['primary_role'] !== 'staff') {
            return [
                'response' => "Only admins and staff can decline appointments.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "✅ Appointment #{$actionResult['appointment_id']} has been declined.",
                'action_result' => $actionResult,
            ];
        }

        return [
            'response' => "Which appointment would you like to decline? Please provide the appointment ID.",
            'requires_clarification' => true,
        ];
    }

    /**
     * Build response for pending payments (cashier)
     */
    private function buildPendingPaymentsResponse(array $roleInfo): array
    {
        if (!in_array($roleInfo['primary_role'], ['cashier', 'admin'])) {
            return [
                'response' => "Only cashiers and admins can view pending payments.",
                'error' => 'unauthorized',
            ];
        }

        $pending = $this->dataService->getPendingPayments();

        if (empty($pending)) {
            return [
                'response' => "✅ All payments are collected! No pending payments.",
                'has_data' => false,
            ];
        }

        $overdue = collect($pending)->filter(fn($p) => $p['is_overdue'])->count();

        $formattedList = collect($pending)->map(function ($p) {
            $badge = $p['is_overdue'] ? "⚠️ OVERDUE" : "📋";
            return "{$badge} #{$p['appointment_id']} - {$p['user_name']} - PHP " . number_format($p['amount_due'], 2);
        })->implode("\n");

        return [
            'response' => "**Pending Payments (" . count($pending) . ")**\n\n{$formattedList}",
            'pending_count' => count($pending),
            'overdue_count' => $overdue,
            'pending' => $pending,
            'has_data' => true,
        ];
    }

    /**
     * Build response for pending refunds (cashier)
     */
    private function buildPendingRefundsResponse(array $roleInfo): array
    {
        if (!in_array($roleInfo['primary_role'], ['cashier', 'admin'])) {
            return [
                'response' => "Only cashiers and admins can view pending refunds.",
                'error' => 'unauthorized',
            ];
        }

        $pending = $this->dataService->getPendingRefunds();

        if (empty($pending)) {
            return [
                'response' => "✅ No pending refunds to process!",
                'has_data' => false,
            ];
        }

        $formattedList = collect($pending)->map(function ($r) {
            return "• **#{$r['id']}** - {$r['user_name']} - PHP " . number_format($r['amount'], 2) . " - Pending {$r['days_pending']} days";
        })->implode("\n");

        return [
            'response' => "**Pending Refunds (" . count($pending) . ")**\n\n{$formattedList}",
            'pending_count' => count($pending),
            'pending' => $pending,
            'has_data' => true,
        ];
    }

    /**
     * Build response for shift report (cashier)
     */
    private function buildShiftReportResponse(int $userId = null, array $roleInfo): array
    {
        if ($roleInfo['primary_role'] !== 'cashier') {
            return [
                'response' => "Only cashiers can view shift reports.",
                'error' => 'unauthorized',
            ];
        }

        return [
            'response' => "Shift report generation in progress. Please visit your cashier dashboard for detailed transaction summaries.",
            'action_needed' => 'visit_dashboard',
        ];
    }

    /**
     * Build response for system status
     */
    private function buildSystemStatusResponse(array $roleInfo): array
    {
        $status = $this->dataService->getSystemStatus();

        $response = "**System Status**\n\n";
        $response .= "• **Status:** " . strtoupper($status['status']) . "\n";
        $response .= "• **Database:** Connected ✅\n";
        $response .= "• **Total Users:** " . $status['total_users'] . "\n";
        $response .= "• **Total Appointments:** " . $status['total_appointments'] . "\n";
        $response .= "• **Pending Items:** " . ($status['pending_items']['pending_appointments'] +
            $status['pending_items']['pending_payments'] +
            $status['pending_items']['pending_refunds']) . "\n";

        return [
            'response' => $response,
            'status' => $status,
            'has_data' => true,
        ];
    }

    /**
     * Build response for help request
     */
    private function buildHelpResponse(array $roleInfo, string $language = 'english'): array
    {
        $role = $roleInfo['primary_role'];

        if ($language === 'filipino') {
            $helpByRole = [
                'client' => "👋 **Kumusta! Ako ang AI assistant niyo.**\n\nPwede ko kayong tulungan sa:\n\n📅 **Mga Appointment**\n• Tingnan appointments - \"Ipakita ang appointments ko\"\n• Check status - \"Ano na status ng appointment ko?\"\n• Cancel - \"I-cancel ang appointment #123\"\n• Reschedule - \"Ilipat ang appointment ko\"\n\n💳 **Payments at Refunds**\n• Payment status - \"Check ang bayad ko\"\n• Request refund - \"Gusto ko ng refund sa appointment #123\"\n• Refund status - \"Nasaan na ang refund ko?\"\n\n📋 **Mga Services**\n• Tingnan services - \"Ano ang mga services niyo?\"\n• Presyo - \"Magkano ang notary service?\"\n• Availability - \"Kelan kayo available?\"\n\nMag-type lang po ng tanong niyo!",
                'admin' => "👋 **Admin Assistant po ay Ready!**\n\nPwede ko po kayong tulungan sa:\n\n📅 **Appointment Management**\n• \"Ipakita ang pending appointments\"\n• \"Approve ang appointment #123\"\n• \"Decline ang appointment #123\"\n• \"Complete ang appointment #123\"\n• \"Lahat ng appointments\"\n\n💰 **Financial Operations**\n• \"Ipakita pending payments\"\n• \"Ipakita pending refunds\"\n• \"Approve ang refund #123\"\n• \"Process ang refund #123\"\n\n📊 **System Management**\n• \"System status\"\n• \"Analytics\"\n• \"Manage users\"\n\nGumamit po ng natural language o specific IDs!",
                'cashier' => "👋 **Cashier Assistant po ay Ready!**\n\nPwede ko po kayong tulungan sa:\n\n💳 **Payments**\n• \"Ipakita pending payments\"\n• \"Process payment para sa #123\"\n• \"Verify receipt\"\n\n💰 **Refunds**\n• \"Ipakita pending refunds\"\n• \"Process refund #123\"\n• \"Refund requests\"\n\n📊 **Reports**\n• \"Shift report\"\n• \"Mga transactions ko ngayon\"\n• \"Daily summary\"\n\n✅ **Quick Actions**\n• \"Complete appointment #123\"\n• \"Check appointment details\"\n\nTutulungan ko po kayong mag-process ng transactions!",
                'guest' => "👋 **Welcome po! Ako ang AI assistant niyo.**\n\nBilang guest, pwede ko kayong tulungan sa:\n\n📋 **Information**\n• \"Ano ang mga services niyo?\"\n• \"Magkano po?\"\n• \"Ano ang business hours niyo?\"\n• \"Paano mag-book ng appointment?\"\n\n🔐 **Getting Started**\n• \"Paano mag-register?\"\n• \"Paano mag-login?\"\n\n**Para ma-access ang ibang features tulad ng:**\n• Pag-book ng appointments\n• Pagtingin sa history niyo\n• Pag-bayad\n• Pag-request ng refunds\n\nPaki-**register** o **login** muna po!\n\nMag-type po ng tanong niyo o sabihin 'hi' para magsimula.",
            ];
        } else {
            $helpByRole = [
                'client' => "👋 **Hello! I'm your AI assistant.**\n\nI can help you with:\n\n📅 **Appointments**\n• View appointments - \"Show my appointments\"\n• Check status - \"What's my appointment status?\"\n• Cancel - \"Cancel appointment #123\"\n• Reschedule - \"Reschedule my appointment\"\n\n💳 **Payments & Refunds**\n• Payment status - \"Check my payment\"\n• Request refund - \"I want a refund for appointment #123\"\n• Refund status - \"Where is my refund?\"\n\n📋 **Services**\n• View services - \"What services do you offer?\"\n• Pricing - \"How much is notary service?\"\n• Availability - \"When are you available?\"\n\nJust type your question naturally!",
                'admin' => "👋 **Admin Assistant Ready!**\n\nI can help you with:\n\n📅 **Appointment Management**\n• \"Show pending appointments\"\n• \"Approve appointment #123\"\n• \"Decline appointment #123\"\n• \"Complete appointment #123\"\n• \"View all appointments\"\n\n💰 **Financial Operations**\n• \"Show pending payments\"\n• \"Show pending refunds\"\n• \"Approve refund #123\"\n• \"Process refund #123\"\n\n📊 **System Management**\n• \"System status\"\n• \"View analytics\"\n• \"Manage users\"\n\nUse natural language or provide specific IDs!",
                'cashier' => "👋 **Cashier Assistant Ready!**\n\nI can help you with:\n\n💳 **Payments**\n• \"Show pending payments\"\n• \"Process payment for #123\"\n• \"Verify receipt\"\n\n💰 **Refunds**\n• \"Show pending refunds\"\n• \"Process refund #123\"\n• \"Refund requests\"\n\n📊 **Reports**\n• \"Generate shift report\"\n• \"My transactions today\"\n• \"Daily summary\"\n\n✅ **Quick Actions**\n• \"Complete appointment #123\"\n• \"Check appointment details\"\n\nI'll help you process transactions quickly!",
                'guest' => "👋 **Welcome! I'm your AI assistant.**\n\nAs a guest, I can help you with:\n\n📋 **Information**\n• \"What services do you offer?\"\n• \"How much does it cost?\"\n• \"What are your business hours?\"\n• \"How do I book an appointment?\"\n\n🔐 **Getting Started**\n• \"How do I register?\"\n• \"How do I log in?\"\n\n**To access more features like:**\n• Booking appointments\n• Viewing your history\n• Making payments\n• Requesting refunds\n\nPlease **register** or **log in** first!\n\nType your question or say 'hi' to get started.",
            ];
        }

        $help = $helpByRole[$role] ?? $helpByRole['guest'];

        return [
            'response' => $help,
            'role_specific' => true,
            'has_data' => true,
        ];
    }

    /**
     * Build greeting response
     */
    private function buildGreetingResponse(array $roleInfo, string $language = 'english'): array
    {
        $role = $roleInfo['primary_role'] ?? 'guest';
        $name = $roleInfo['first_name'] ?? '';
        
        if ($language === 'filipino') {
            $greeting = $name ? "Kumusta, {$name}! " : "Kumusta po! ";
            
            $roleGreetings = [
                'client' => "{$greeting}👋 Paano ko po kayo matutulungan ngayon? Pwede ko kayong tulungan sa mga appointments, payments, refunds, at iba pa!",
                'admin' => "{$greeting}👋 Admin dashboard po ay ready na. Pwede ko po kayong tulungan sa pag-manage ng appointments, users, payments, at system operations.",
                'cashier' => "{$greeting}👋 Cashier mode po ay active na. Pwede ko po kayong tulungan sa pag-process ng payments, refunds, at pag-generate ng reports.",
                'guest' => "{$greeting}👋 Welcome po! Ako ang AI assistant niyo. Pwede ko po kayong i-inform tungkol sa aming services, pricing, at business hours. Mag-register o mag-login po para ma-access lahat ng features!",
            ];
        } else {
            $greeting = $name ? "Hello, {$name}! " : "Hello! ";
            
            $roleGreetings = [
                'client' => "{$greeting}👋 How can I assist you today? I can help with appointments, payments, refunds, and more!",
                'admin' => "{$greeting}👋 Admin dashboard ready. I can help you manage appointments, users, payments, and system operations.",
                'cashier' => "{$greeting}👋 Cashier mode active. I can help you process payments, handle refunds, and generate reports.",
                'guest' => "{$greeting}👋 Welcome! I'm your AI assistant. I can tell you about our services, pricing, and business hours. Register or log in to access all features!",
            ];
        }

        return [
            'response' => $roleGreetings[$role] ?? $roleGreetings['guest'],
            'has_data' => true,
        ];
    }

    /**
     * Build farewell response
     */
    private function buildFarewellResponse(array $roleInfo, string $language = 'english'): array
    {
        $name = $roleInfo['first_name'] ?? '';

        if ($language === 'filipino') {
            $farewell = $name ? "Paalam po, {$name}!" : "Paalam po!";
            return [
                'response' => "{$farewell} 👋 Salamat po sa pag-chat sa akin. Magandang araw po sa inyo! Bumalik lang po kayo kung kailangan niyo ng tulong.",
                'has_data' => true,
            ];
        }
        
        $farewell = $name ? "Goodbye, {$name}!" : "Goodbye!";
        return [
            'response' => "{$farewell} 👋 Thank you for chatting with me. Have a great day! Feel free to come back if you need any help.",
            'has_data' => true,
        ];
    }

    /**
     * Build response for viewing profile
     */
    private function buildViewProfileResponse(int $userId = null, array $roleInfo, string $language = 'english'): array
    {
        if (!$userId) {
            $msg = $language === 'filipino' 
                ? "Mag-login po muna para makita ang profile niyo."
                : "Please log in to view your profile.";
            return [
                'response' => $msg,
                'action_needed' => 'authentication',
            ];
        }

        $name = trim(($roleInfo['first_name'] ?? '') . ' ' . ($roleInfo['last_name'] ?? ''));
        $email = $roleInfo['email'] ?? 'Not set';
        $role = ucfirst($roleInfo['primary_role'] ?? 'client');

        if ($language === 'filipino') {
            $response = "**Ang Profile Niyo**\n\n";
            $response .= "• **Pangalan:** {$name}\n";
            $response .= "• **Email:** {$email}\n";
            $response .= "• **Role:** {$role}\n";
            $response .= "• **Status:** Active ✅\n\n";
            $response .= "Para i-update ang profile niyo, pumunta sa dashboard settings.";
        } else {
            $response = "**Your Profile**\n\n";
            $response .= "• **Name:** {$name}\n";
            $response .= "• **Email:** {$email}\n";
            $response .= "• **Role:** {$role}\n";
            $response .= "• **Status:** Active ✅\n\n";
            $response .= "To update your profile, visit your dashboard settings.";
        }

        return [
            'response' => $response,
            'has_data' => true,
        ];
    }

    /**
     * Build response for editing profile
     */
    private function buildEditProfileResponse(int $userId = null, array $roleInfo, string $language = 'english'): array
    {
        if (!$userId) {
            $msg = $language === 'filipino'
                ? "Mag-login po muna para ma-edit ang profile niyo."
                : "Please log in to edit your profile.";
            return [
                'response' => $msg,
                'action_needed' => 'authentication',
            ];
        }

        if ($language === 'filipino') {
            return [
                'response' => "Para i-update ang profile information niyo:\n\n1. Pumunta sa **Dashboard**\n2. I-click ang **Settings** o **Profile**\n3. I-update ang details niyo\n4. I-click ang **Save**\n\nPwede niyo i-update ang:\n• Pangalan\n• Phone number\n• Address\n• Password\n\nGusto niyo bang dalhin ko kayo doon?",
                'action_suggested' => 'navigate_to_profile',
                'has_data' => true,
            ];
        }

        return [
            'response' => "To update your profile information:\n\n1. Go to your **Dashboard**\n2. Click on **Settings** or **Profile**\n3. Update your details\n4. Click **Save**\n\nYou can update your:\n• Name\n• Phone number\n• Address\n• Password\n\nWould you like me to take you there?",
            'action_suggested' => 'navigate_to_profile',
            'has_data' => true,
        ];
    }

    /**
     * Build response for completing appointment
     */
    private function buildCompleteAppointmentResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult, string $language = 'english'): array
    {
        if (!in_array($roleInfo['primary_role'], ['admin', 'cashier', 'staff'])) {
            $msg = $language === 'filipino'
                ? "Mga admin, staff, at cashiers lang po ang pwedeng mag-mark ng appointments bilang complete."
                : "Only admins, staff, and cashiers can mark appointments as complete.";
            return [
                'response' => $msg,
                'error' => 'unauthorized',
            ];
        }

        $appointmentId = $entities['appointment_id'] ?? null;

        if ($actionResult && $actionResult['success']) {
            $msg = $language === 'filipino'
                ? "✅ Ang appointment #{$actionResult['appointment_id']} ay naka-mark na bilang **COMPLETED**!\n\nAng client ay na-notify na po."
                : "✅ Appointment #{$actionResult['appointment_id']} has been marked as **COMPLETED**!\n\nThe client has been notified.";
            return [
                'response' => $msg,
                'action_result' => $actionResult,
                'action_executed' => true,
            ];
        }

        if (!$appointmentId) {
            $msg = $language === 'filipino'
                ? "Aling appointment po ang gusto niyong i-mark bilang complete? Pakibigay po ang appointment ID.\n\nExample: \"Complete appointment #123\""
                : "Which appointment would you like to mark as complete? Please provide the appointment ID.\n\nExample: \"Complete appointment #123\"";
            return [
                'response' => $msg,
                'requires_clarification' => true,
            ];
        }

        $msg = $language === 'filipino'
            ? "Ready na po i-complete ang appointment #{$appointmentId}. Pakiconfirm po ang action na ito."
            : "Ready to complete appointment #{$appointmentId}. Please confirm this action.";
        return [
            'response' => $msg,
            'appointment_id' => $appointmentId,
            'requires_confirmation' => true,
            'action_type' => 'complete_appointment',
        ];
    }

    /**
     * Build response for viewing all appointments (admin)
     */
    private function buildViewAllAppointmentsResponse(array $roleInfo, string $language = 'english'): array
    {
        if (!in_array($roleInfo['primary_role'], ['admin', 'cashier'])) {
            $msg = $language === 'filipino'
                ? "Mga admin at cashiers lang po ang pwedeng makakita ng lahat ng appointments."
                : "Only admins and cashiers can view all appointments.";
            return [
                'response' => $msg,
                'error' => 'unauthorized',
            ];
        }

        $appointments = $this->dataService->getAllAppointments(20);
        
        if (empty($appointments)) {
            $msg = $language === 'filipino'
                ? "Walang appointments na nahanap sa system."
                : "No appointments found in the system.";
            return [
                'response' => $msg,
                'has_data' => false,
            ];
        }

        $today = collect($appointments)->filter(fn($a) => $a['is_today'] ?? false)->count();
        $pending = collect($appointments)->filter(fn($a) => $a['status'] === 'pending')->count();

        $formattedList = collect($appointments)->take(10)->map(function ($apt) {
            $status = strtoupper($apt['status']);
            $badge = match ($apt['status']) {
                'pending' => '🟡',
                'approved' => '🟢',
                'completed' => '✅',
                'cancelled' => '❌',
                default => '⚪',
            };
            return "{$badge} **#{$apt['id']}** - {$apt['user_name']} - {$apt['date']} ({$status})";
        })->implode("\n");

        $response = "**All Appointments** (Showing 10 of " . count($appointments) . ")\n\n";
        $response .= "📊 **Today:** {$today} | **Pending:** {$pending}\n\n";
        $response .= $formattedList;
        $response .= "\n\nVisit the admin dashboard for the complete list.";

        return [
            'response' => $response,
            'total' => count($appointments),
            'today_count' => $today,
            'pending_count' => $pending,
            'has_data' => true,
        ];
    }

    /**
     * Build response for viewing analytics (admin)
     */
    private function buildViewAnalyticsResponse(array $roleInfo): array
    {
        if ($roleInfo['primary_role'] !== 'admin') {
            return [
                'response' => "Only admins can view system analytics.",
                'error' => 'unauthorized',
            ];
        }

        $analytics = $this->dataService->getSystemAnalytics();

        $response = "**📊 System Analytics**\n\n";
        $response .= "**This Week:**\n";
        $response .= "• New Appointments: {$analytics['appointments_this_week']}\n";
        $response .= "• Completed: {$analytics['completed_this_week']}\n";
        $response .= "• Revenue: PHP " . number_format($analytics['revenue_this_week'], 2) . "\n\n";
        $response .= "**Overall:**\n";
        $response .= "• Total Users: {$analytics['total_users']}\n";
        $response .= "• Total Appointments: {$analytics['total_appointments']}\n";
        $response .= "• Completion Rate: {$analytics['completion_rate']}%\n\n";
        $response .= "Visit the admin dashboard for detailed charts and reports.";

        return [
            'response' => $response,
            'analytics' => $analytics,
            'has_data' => true,
        ];
    }

    /**
     * Build response for managing users (admin)
     */
    private function buildManageUsersResponse(array $roleInfo): array
    {
        if ($roleInfo['primary_role'] !== 'admin') {
            return [
                'response' => "Only admins can manage users.",
                'error' => 'unauthorized',
            ];
        }

        $userStats = $this->dataService->getUserStats();

        $response = "**👥 User Management**\n\n";
        $response .= "**Statistics:**\n";
        $response .= "• Total Users: {$userStats['total']}\n";
        $response .= "• Active: {$userStats['active']}\n";
        $response .= "• Clients: {$userStats['clients']}\n";
        $response .= "• Staff: {$userStats['staff']}\n";
        $response .= "• New This Week: {$userStats['new_this_week']}\n\n";
        $response .= "To manage users, please visit the Admin Dashboard > Users section.";

        return [
            'response' => $response,
            'user_stats' => $userStats,
            'has_data' => true,
        ];
    }

    /**
     * Build response for approving refund
     */
    private function buildApproveRefundResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if (!in_array($roleInfo['primary_role'], ['admin', 'cashier'])) {
            return [
                'response' => "Only admins and cashiers can approve refunds.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "✅ Refund #{$actionResult['refund_id']} has been **APPROVED**!\n\nThe refund is now ready for processing.",
                'action_result' => $actionResult,
                'action_executed' => true,
            ];
        }

        $refundId = $entities['refund_id'] ?? null;

        if (!$refundId) {
            return [
                'response' => "Which refund would you like to approve? Please provide the refund ID.\n\nExample: \"Approve refund #123\"",
                'requires_clarification' => true,
            ];
        }

        return [
            'response' => "Ready to approve refund #{$refundId}. Please confirm this action.",
            'refund_id' => $refundId,
            'requires_confirmation' => true,
            'action_type' => 'approve_refund',
        ];
    }

    /**
     * Build response for rejecting refund
     */
    private function buildRejectRefundResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if (!in_array($roleInfo['primary_role'], ['admin', 'cashier'])) {
            return [
                'response' => "Only admins and cashiers can reject refunds.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "❌ Refund #{$actionResult['refund_id']} has been **REJECTED**.\n\nThe client has been notified.",
                'action_result' => $actionResult,
                'action_executed' => true,
            ];
        }

        $refundId = $entities['refund_id'] ?? null;

        if (!$refundId) {
            return [
                'response' => "Which refund would you like to reject? Please provide the refund ID and reason.\n\nExample: \"Reject refund #123\"",
                'requires_clarification' => true,
            ];
        }

        return [
            'response' => "Ready to reject refund #{$refundId}. Please provide a reason for rejection.",
            'refund_id' => $refundId,
            'requires_confirmation' => true,
            'action_type' => 'reject_refund',
        ];
    }

    /**
     * Build response for processing refund
     */
    private function buildProcessRefundResponse(int $userId = null, array $entities, array $roleInfo, ?array $actionResult): array
    {
        if (!in_array($roleInfo['primary_role'], ['admin', 'cashier'])) {
            return [
                'response' => "Only admins and cashiers can process refunds.",
                'error' => 'unauthorized',
            ];
        }

        if ($actionResult && $actionResult['success']) {
            return [
                'response' => "✅ Refund #{$actionResult['refund_id']} has been **PROCESSED**!\n\nAmount: PHP " . number_format($actionResult['amount'] ?? 0, 2) . "\n\nThe client will receive their refund shortly.",
                'action_result' => $actionResult,
                'action_executed' => true,
            ];
        }

        $refundId = $entities['refund_id'] ?? null;

        if (!$refundId) {
            return [
                'response' => "Which refund would you like to process? Please provide the refund ID.\n\nExample: \"Process refund #123\"",
                'requires_clarification' => true,
            ];
        }

        return [
            'response' => "Ready to process refund #{$refundId}. Please confirm this action and the refund method.",
            'refund_id' => $refundId,
            'requires_confirmation' => true,
            'action_type' => 'process_refund',
        ];
    }

    /**
     * Build response for verifying receipt (cashier)
     */
    private function buildVerifyReceiptResponse(array $entities, array $roleInfo): array
    {
        if ($roleInfo['primary_role'] !== 'cashier' && $roleInfo['primary_role'] !== 'admin') {
            return [
                'response' => "Only cashiers and admins can verify receipts.",
                'error' => 'unauthorized',
            ];
        }

        $appointmentId = $entities['appointment_id'] ?? null;

        if (!$appointmentId) {
            return [
                'response' => "Please provide the appointment ID or receipt number to verify.\n\nExample: \"Verify receipt for appointment #123\"",
                'requires_clarification' => true,
            ];
        }

        $appointment = $this->dataService->getAppointmentDetails($appointmentId);

        if (!$appointment) {
            return [
                'response' => "Could not find appointment #{$appointmentId}. Please check the ID and try again.",
                'has_data' => false,
            ];
        }

        $response = "**Receipt Verification for #{$appointmentId}**\n\n";
        $response .= "• **Client:** {$appointment['user_name']}\n";
        $response .= "• **Service:** {$appointment['service']}\n";
        $response .= "• **Date:** {$appointment['date']}\n";
        $response .= "• **Amount:** PHP " . number_format($appointment['payment_amount'] ?? 0, 2) . "\n";
        $response .= "• **Payment Status:** " . strtoupper($appointment['payment_status'] ?? 'N/A') . "\n";
        $response .= "• **Appointment Status:** " . strtoupper($appointment['status']) . "\n\n";
        $response .= "✅ Receipt details verified successfully.";

        return [
            'response' => $response,
            'appointment' => $appointment,
            'has_data' => true,
        ];
    }

    /**
     * Build response for general questions
     */
    private function buildGeneralResponse(array $context): array
    {
        $message = $context['message'] ?? $context['user_message'] ?? 'your question';
        $roleInfo = $context['role_info'] ?? [];
        $role = $roleInfo['primary_role'] ?? 'guest';

        // Try to provide contextual help based on message content
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'urgent') || str_contains($lowerMessage, 'emergency')) {
            return [
                'response' => "I understand this is urgent. Please describe your issue in detail, or if you need immediate assistance, you may contact our office directly.\n\nAlternatively, you can:\n• Check your appointment status\n• View pending items\n• Request support through messages",
                'priority' => true,
            ];
        }

        $helpSuggestion = match ($role) {
            'admin' => "Try commands like:\n• \"Show pending appointments\"\n• \"System status\"\n• \"View analytics\"",
            'cashier' => "Try commands like:\n• \"Pending payments\"\n• \"Process payment #123\"\n• \"Shift report\"",
            'client' => "Try commands like:\n• \"My appointments\"\n• \"Check payment\"\n• \"View services\"",
            default => "Try asking about:\n• Services we offer\n• Business hours\n• How to register",
        };

        return [
            'response' => "I'm here to help! I couldn't quite understand what you meant by:\n\n> \"{$message}\"\n\n{$helpSuggestion}\n\nOr type **'help'** to see all available commands.",
            'requires_clarification' => true,
        ];
    }

    /**
     * Build error response
     */
    private function buildErrorResponse(string $error): array
    {
        return [
            'response' => "I encountered an issue while processing your request. Please try again or contact support if the problem persists.\n\nYou can also try:\n• Rephrasing your question\n• Using simpler commands\n• Typing 'help' for available options",
            'error' => config('app.debug') ? $error : 'processing_error',
            'success' => false,
            'has_data' => false,
        ];
    }
}
