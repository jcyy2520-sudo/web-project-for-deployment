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
                'contact_info' => $this->buildContactInfoResponse($roleInfo, $language),
                'location_info' => $this->buildLocationInfoResponse($roleInfo, $language),
                'about_business' => $this->buildAboutBusinessResponse($roleInfo, $language),
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
            
            // Add action buttons for navigation
            $actionButtons = $this->getActionButtonsForIntent($intent, $roleInfo, $entities);
            if (!empty($actionButtons)) {
                $response['action_buttons'] = $actionButtons;
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Error building response', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * Get action buttons for navigation based on intent and role
     * 
     * @param string $intent The detected intent
     * @param array $roleInfo User role information
     * @param array $entities Extracted entities
     * @return array Action buttons with labels and routes
     */
    private function getActionButtonsForIntent(string $intent, array $roleInfo, array $entities = []): array
    {
        $role = $roleInfo['primary_role'] ?? 'guest';
        $buttons = [];

        // Define action buttons based on intent
        $intentButtons = [
            'view_appointments' => [
                'client' => [
                    ['label' => 'View My Appointments', 'route' => '/appointments', 'icon' => '📅', 'type' => 'primary'],
                    ['label' => 'Book New', 'route' => '/appointments/book', 'icon' => '➕', 'type' => 'secondary'],
                ],
                'admin' => [
                    ['label' => 'Manage Appointments', 'route' => '/admin/appointments', 'icon' => '📅', 'type' => 'primary'],
                ],
            ],
            'book_appointment' => [
                'client' => [
                    ['label' => 'Book Appointment', 'route' => '/appointments/book', 'icon' => '📅', 'type' => 'primary'],
                ],
                'guest' => [
                    ['label' => 'Register to Book', 'route' => '/register', 'icon' => '📝', 'type' => 'primary'],
                    ['label' => 'Login', 'route' => '/login', 'icon' => '🔐', 'type' => 'secondary'],
                ],
            ],
            'view_profile' => [
                'client' => [
                    ['label' => 'View Profile', 'route' => '/profile', 'icon' => '👤', 'type' => 'primary'],
                    ['label' => 'Edit Profile', 'route' => '/profile/edit', 'icon' => '✏️', 'type' => 'secondary'],
                ],
                'admin' => [
                    ['label' => 'View Profile', 'route' => '/admin/profile', 'icon' => '👤', 'type' => 'primary'],
                ],
                'cashier' => [
                    ['label' => 'View Profile', 'route' => '/cashier/profile', 'icon' => '👤', 'type' => 'primary'],
                ],
            ],
            'edit_profile' => [
                'client' => [
                    ['label' => 'Edit Profile', 'route' => '/profile/edit', 'icon' => '✏️', 'type' => 'primary'],
                ],
            ],
            'view_payments' => [
                'client' => [
                    ['label' => 'View Payments', 'route' => '/payments', 'icon' => '💳', 'type' => 'primary'],
                ],
                'cashier' => [
                    ['label' => 'Payment Dashboard', 'route' => '/cashier/payments', 'icon' => '💳', 'type' => 'primary'],
                ],
                'admin' => [
                    ['label' => 'View All Payments', 'route' => '/admin/payments', 'icon' => '💳', 'type' => 'primary'],
                ],
            ],
            'view_pending_payments' => [
                'cashier' => [
                    ['label' => 'Pending Payments', 'route' => '/cashier/payments?status=pending', 'icon' => '⏳', 'type' => 'primary'],
                ],
                'admin' => [
                    ['label' => 'Pending Payments', 'route' => '/admin/payments?status=pending', 'icon' => '⏳', 'type' => 'primary'],
                ],
            ],
            'view_refunds' => [
                'client' => [
                    ['label' => 'View Refunds', 'route' => '/refunds', 'icon' => '💸', 'type' => 'primary'],
                ],
                'cashier' => [
                    ['label' => 'Refund Queue', 'route' => '/cashier/refunds', 'icon' => '💸', 'type' => 'primary'],
                ],
                'admin' => [
                    ['label' => 'Manage Refunds', 'route' => '/admin/refunds', 'icon' => '💸', 'type' => 'primary'],
                ],
            ],
            'view_pending_refunds' => [
                'cashier' => [
                    ['label' => 'Process Refunds', 'route' => '/cashier/refunds?status=approved', 'icon' => '💸', 'type' => 'primary'],
                ],
                'admin' => [
                    ['label' => 'Pending Refunds', 'route' => '/admin/refunds?status=pending', 'icon' => '⏳', 'type' => 'primary'],
                ],
            ],
            'view_pending_appointments' => [
                'admin' => [
                    ['label' => 'Review Appointments', 'route' => '/admin/appointments?status=pending', 'icon' => '📋', 'type' => 'primary'],
                ],
            ],
            'view_services' => [
                'guest' => [
                    ['label' => 'Browse Services', 'route' => '/services', 'icon' => '📋', 'type' => 'primary'],
                ],
                'client' => [
                    ['label' => 'View Services', 'route' => '/services', 'icon' => '📋', 'type' => 'primary'],
                    ['label' => 'Book Now', 'route' => '/appointments/book', 'icon' => '📅', 'type' => 'secondary'],
                ],
            ],
            'view_analytics' => [
                'admin' => [
                    ['label' => 'View Analytics', 'route' => '/admin/analytics', 'icon' => '📊', 'type' => 'primary'],
                ],
            ],
            'manage_users' => [
                'admin' => [
                    ['label' => 'Manage Users', 'route' => '/admin/users', 'icon' => '👥', 'type' => 'primary'],
                ],
            ],
            'shift_report' => [
                'cashier' => [
                    ['label' => 'View Shift Report', 'route' => '/cashier/reports/shift', 'icon' => '📊', 'type' => 'primary'],
                ],
            ],
            'system_status' => [
                'admin' => [
                    ['label' => 'System Dashboard', 'route' => '/admin/system', 'icon' => '🔧', 'type' => 'primary'],
                ],
            ],
            'help' => [
                'guest' => [
                    ['label' => 'FAQ', 'route' => '/faq', 'icon' => '❓', 'type' => 'secondary'],
                    ['label' => 'Contact Us', 'route' => '/contact', 'icon' => '📞', 'type' => 'secondary'],
                ],
                'client' => [
                    ['label' => 'Help Center', 'route' => '/help', 'icon' => '❓', 'type' => 'secondary'],
                ],
            ],
            'request_refund' => [
                'client' => [
                    ['label' => 'Request Refund', 'route' => '/refunds/request', 'icon' => '💸', 'type' => 'primary'],
                ],
            ],
        ];

        // Get buttons for this intent and role
        if (isset($intentButtons[$intent][$role])) {
            $buttons = $intentButtons[$intent][$role];
        } elseif (isset($intentButtons[$intent]['client']) && in_array($role, ['admin', 'cashier', 'staff'])) {
            // Staff roles can also use client-level navigation for personal items
            // but don't show them by default
        }

        return $buttons;
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
     * Build response for contact information
     */
    private function buildContactInfoResponse(array $roleInfo, string $language = 'english'): array
    {
        $businessInfo = $this->getBusinessInfo();

        if ($language === 'filipino') {
            $response = "**📞 Contact Information**\n\n";
            $response .= "**Company:** {$businessInfo['company_name']}\n\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "Pwede niyo kami kontakin sa mga oras ng opisina para sa mga tanong o appointment inquiries.";
        } else {
            $response = "**📞 Contact Information**\n\n";
            $response .= "**Company:** {$businessInfo['company_name']}\n\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "Feel free to contact us during business hours for any inquiries or to schedule an appointment.";
        }

        return [
            'response' => $response,
            'business_info' => $businessInfo,
            'has_data' => true,
        ];
    }

    /**
     * Build response for location/address information
     */
    private function buildLocationInfoResponse(array $roleInfo, string $language = 'english'): array
    {
        $businessInfo = $this->getBusinessInfo();

        if ($language === 'filipino') {
            $response = "**📍 Office Location**\n\n";
            $response .= "**{$businessInfo['company_name']}**\n\n";
            $response .= "**Address:**\n{$businessInfo['address']}\n\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "Bisitahin niyo kami sa aming opisina o mag-book ng appointment online.";
        } else {
            $response = "**📍 Office Location**\n\n";
            $response .= "**{$businessInfo['company_name']}**\n\n";
            $response .= "**Address:**\n{$businessInfo['address']}\n\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "Visit us at our office or book an appointment online for your convenience.";
        }

        return [
            'response' => $response,
            'business_info' => $businessInfo,
            'has_data' => true,
        ];
    }

    /**
     * Build response for about business/attorney information
     */
    private function buildAboutBusinessResponse(array $roleInfo, string $language = 'english'): array
    {
        $businessInfo = $this->getBusinessInfo();
        $services = implode(', ', $businessInfo['specialties'] ?? []);

        if ($language === 'filipino') {
            $response = "**⚖️ Tungkol sa {$businessInfo['company_name']}**\n\n";
            $response .= "Kami ay isang propesyonal na legal services firm na nagbibigay ng mga serbisyong notaryo at legal consultation.\n\n";
            $response .= "**Mga Serbisyo Namin:**\n";
            foreach ($businessInfo['specialties'] as $specialty) {
                $response .= "• {$specialty}\n";
            }
            $response .= "\n**📍 Location:** {$businessInfo['address']}\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "Para sa legal na tulong, pwede kayong mag-book ng appointment online o tumawag sa aming opisina.";
        } else {
            $response = "**⚖️ About {$businessInfo['company_name']}**\n\n";
            $response .= "We are a professional legal services firm providing notary services and legal consultation.\n\n";
            $response .= "**Our Services:**\n";
            foreach ($businessInfo['specialties'] as $specialty) {
                $response .= "• {$specialty}\n";
            }
            $response .= "\n**📍 Location:** {$businessInfo['address']}\n";
            $response .= "**📱 Phone:** {$businessInfo['phone']}\n";
            $response .= "**✉️ Email:** {$businessInfo['email']}\n\n";
            $response .= "For legal assistance, you can book an appointment online or call our office directly.";
        }

        return [
            'response' => $response,
            'business_info' => $businessInfo,
            'has_data' => true,
        ];
    }

    /**
     * Get business information (centralized)
     */
    private function getBusinessInfo(): array
    {
        return [
            'company_name' => 'Peejayy De Guzman Legal',
            'email' => 'peejaydeguzmanlegal@gmail.com',
            'phone' => '09765075274',
            'address' => '233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro',
            'type' => 'Notary Services & Legal Consultation',
            'specialties' => [
                'Notary Services',
                'Legal Consultations', 
                'Document Review',
                'Contract Drafting',
                'Court Representation',
                'Legal Opinions',
                'Case Evaluations'
            ]
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
     * Explains the chatbot's role and capabilities
     */
    private function buildHelpResponse(array $roleInfo, string $language = 'english'): array
    {
        $role = $roleInfo['primary_role'];

        if ($language === 'filipino') {
            $intro = "👋 **Ako ang AI assistant niyo para sa appointment booking system na ito.**\n\n";
            $intro .= "**Ang role ko ay:**\n";
            $intro .= "✓ Sagutin ang mga tanong niyo\n";
            $intro .= "✓ Magbigay ng information tungkol sa system\n";
            $intro .= "✓ I-guide kayo kung paano gamitin ang mga features\n";
            $intro .= "✓ I-explain ang mga processes at requirements\n\n";
            $intro .= "**Hindi ko po magagawa:**\n";
            $intro .= "✗ Mag-approve, cancel, o modify ng kahit ano\n";
            $intro .= "✗ Mag-process ng payments o refunds\n";
            $intro .= "✗ Mag-execute ng system commands\n\n";
            
            $helpByRole = [
                'client' => $intro . "📅 **Mga Appointment**\n• \"Ipakita ang appointments ko\"\n• \"Ano na status ng appointment ko?\"\n• \"Paano mag-cancel?\"\n• \"Paano mag-reschedule?\"\n\n💳 **Payments at Refunds**\n• \"Check ang bayad ko\"\n• \"Paano mag-request ng refund?\"\n• \"Nasaan na ang refund ko?\"\n\n📋 **Mga Services**\n• \"Ano ang mga services niyo?\"\n• \"Magkano ang notary service?\"\n• \"Kelan kayo available?\"\n\nMag-type lang po ng tanong niyo!",
                'admin' => $intro . "📅 **Appointment Information**\n• \"Ipakita ang pending appointments\"\n• \"Ilang appointments ngayon?\"\n• \"Status ng appointments\"\n\n💰 **Financial Information**\n• \"Ipakita pending refunds\"\n• \"Magkano ang collections ngayon?\"\n\n📊 **System Information**\n• \"System status\"\n• \"Analytics overview\"\n• \"User counts\"\n\nTandaan: Para mag-approve o mag-process, gamitin ang Admin Dashboard.",
                'cashier' => $intro . "💳 **Payment Information**\n• \"Ipakita pending payments\"\n• \"Magkano ang collections ngayon?\"\n\n💰 **Refund Information**\n• \"Ipakita pending refunds\"\n• \"Approved refunds list\"\n\n📊 **Reports**\n• \"Shift report info\"\n• \"Mga transactions ngayon\"\n\nTandaan: Para mag-process ng payments, gamitin ang Cashier Dashboard.",
                'guest' => $intro . "📋 **Information**\n• \"Ano ang mga services niyo?\"\n• \"Magkano po?\"\n• \"Ano ang business hours niyo?\"\n• \"Paano mag-book ng appointment?\"\n\n🔐 **Getting Started**\n• \"Paano mag-register?\"\n• \"Paano mag-login?\"\n\n**Para ma-access ang ibang features:**\nPaki-**register** o **login** muna po!",
            ];
        } else {
            $intro = "👋 **I'm your AI assistant for this appointment booking system.**\n\n";
            $intro .= "**My role is to:**\n";
            $intro .= "✓ Answer your questions\n";
            $intro .= "✓ Provide system information\n";
            $intro .= "✓ Guide you through features\n";
            $intro .= "✓ Explain processes and requirements\n\n";
            $intro .= "**I cannot:**\n";
            $intro .= "✗ Approve, cancel, or modify anything\n";
            $intro .= "✗ Process payments or refunds\n";
            $intro .= "✗ Execute system commands\n\n";
            
            $helpByRole = [
                'client' => $intro . "📅 **Appointments**\n• \"Show my appointments\"\n• \"What's my appointment status?\"\n• \"How do I cancel?\"\n• \"How do I reschedule?\"\n\n💳 **Payments & Refunds**\n• \"Check my payment\"\n• \"How do I request a refund?\"\n• \"Where is my refund?\"\n\n📋 **Services**\n• \"What services do you offer?\"\n• \"How much is notary service?\"\n• \"When are you available?\"\n\nJust type your question naturally!",
                'admin' => $intro . "📅 **Appointment Information**\n• \"Show pending appointments\"\n• \"How many appointments today?\"\n• \"Appointments status overview\"\n\n💰 **Financial Information**\n• \"Show pending refunds\"\n• \"Today's collections\"\n\n📊 **System Information**\n• \"System status\"\n• \"Analytics overview\"\n• \"User counts\"\n\nRemember: To approve or process items, use the Admin Dashboard.",
                'cashier' => $intro . "💳 **Payment Information**\n• \"Show pending payments\"\n• \"Today's collections\"\n\n💰 **Refund Information**\n• \"Show pending refunds\"\n• \"Approved refunds list\"\n\n📊 **Reports**\n• \"Shift report info\"\n• \"Today's transactions\"\n\nRemember: To process payments, use the Cashier Dashboard.",
                'guest' => $intro . "📋 **Information**\n• \"What services do you offer?\"\n• \"How much does it cost?\"\n• \"What are your business hours?\"\n• \"How do I book an appointment?\"\n\n🔐 **Getting Started**\n• \"How do I register?\"\n• \"How do I log in?\"\n\n**To access more features:**\nPlease **register** or **log in** first!",
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
     * Provides a helpful response without listing all commands
     */
    private function buildGeneralResponse(array $context): array
    {
        $message = $context['message'] ?? $context['user_message'] ?? 'your question';
        $roleInfo = $context['role_info'] ?? [];
        $role = $roleInfo['primary_role'] ?? 'guest';
        $userId = $context['user_id'] ?? null;
        $language = $context['language'] ?? 'english';

        // IMPORTANT: For general/unclear questions, return a response that signals
        // the controller to use LLM instead of just asking for clarification
        // This ensures the chatbot gives SMART answers instead of generic prompts

        return [
            'response' => "Processing your question with AI...", // Placeholder - will be replaced by LLM
            'requires_clarification' => false, // Don't ask for clarification, use LLM
            'should_use_llm' => true, // Flag for controller to generate LLM response
            'fallback_reason' => 'general_question',
            'meta' => [
                'source' => 'fallback',
                'intent' => 'general_question',
                'note' => 'Delegating to LLM for intelligent response',
            ],
        ];
    }

    /**
     * Build error response
     * Provides helpful error messages without exposing system details
     */
    private function buildErrorResponse(string $error, string $language = 'english'): array
    {
        // Log the actual error for debugging (never expose to user)
        Log::error('ChatbotSmartResponseBuilder error', ['error' => $error]);
        
        if ($language === 'filipino') {
            return [
                'response' => "Pasensya na, nagkaroon ng problema habang pinoproseso ang request niyo. Pakisubukan ulit o makipag-ugnayan sa support kung magpatuloy ang problema.\n\nPwede niyo rin subukan:\n• I-rephrase ang tanong niyo\n• Gumamit ng mas simpleng commands\n• I-type ang 'tulong' para sa available options",
                'error' => 'processing_error',
                'success' => false,
                'has_data' => false,
                'meta' => [
                    'source' => 'error_handler',
                    'recoverable' => true,
                ],
            ];
        }
        
        return [
            'response' => "I encountered an issue while processing your request. Please try again or contact support if the problem persists.\n\nYou can also try:\n• Rephrasing your question\n• Using simpler commands\n• Typing 'help' for available options",
            'error' => 'processing_error',
            'success' => false,
            'has_data' => false,
            'meta' => [
                'source' => 'error_handler',
                'recoverable' => true,
            ],
        ];
    }
    
    /**
     * Build clarification request response
     * Used when the chatbot needs more information to fulfill a request
     * 
     * @param string $intent Detected intent
     * @param array $missingInfo What information is missing
     * @param string $language Response language
     * @return array Clarification response
     */
    public function buildClarificationResponse(
        string $intent,
        array $missingInfo = [],
        string $language = 'english'
    ): array {
        $clarificationQuestions = [];
        
        // Define clarification questions based on intent and missing info
        $questions = [
            'cancel_appointment' => [
                'appointment_id' => [
                    'en' => "Which appointment would you like to cancel? Please provide the appointment ID or describe which one (e.g., 'my appointment on Monday').",
                    'tl' => "Aling appointment po ang gusto niyong i-cancel? Pakibigay po ang appointment ID o i-describe kung alin (hal. 'appointment ko sa Monday').",
                ],
            ],
            'reschedule_appointment' => [
                'appointment_id' => [
                    'en' => "Which appointment would you like to reschedule?",
                    'tl' => "Aling appointment po ang gusto niyong i-reschedule?",
                ],
                'date' => [
                    'en' => "What new date would you prefer for the appointment?",
                    'tl' => "Anong bagong date po ang gusto niyo para sa appointment?",
                ],
            ],
            'check_appointment_status' => [
                'appointment_id' => [
                    'en' => "Which appointment's status would you like to check? You can provide the ID or describe it.",
                    'tl' => "Aling appointment po ang gusto niyong i-check ang status? Pwede niyo ibigay ang ID o i-describe.",
                ],
            ],
            'service_pricing' => [
                'service' => [
                    'en' => "Which service would you like to know the pricing for?",
                    'tl' => "Aling service po ang gusto niyong malaman ang presyo?",
                ],
            ],
            'book_appointment' => [
                'service' => [
                    'en' => "What service would you like to book?",
                    'tl' => "Anong service po ang gusto niyong i-book?",
                ],
                'date' => [
                    'en' => "What date would you prefer for your appointment?",
                    'tl' => "Anong date po ang gusto niyo para sa appointment?",
                ],
            ],
        ];
        
        $langKey = $language === 'filipino' ? 'tl' : 'en';
        
        // Build clarification questions for missing info
        if (isset($questions[$intent])) {
            foreach ($missingInfo as $field) {
                if (isset($questions[$intent][$field][$langKey])) {
                    $clarificationQuestions[] = $questions[$intent][$field][$langKey];
                }
            }
        }
        
        // Default clarification if no specific questions
        if (empty($clarificationQuestions)) {
            $clarificationQuestions[] = $language === 'filipino'
                ? "Pakiklarify po kung pwede para matulungan ko kayo ng mas mabuti."
                : "Could you please provide more details so I can better assist you?";
        }
        
        $response = implode("\n\n", $clarificationQuestions);
        
        return [
            'response' => $response,
            'requires_clarification' => true,
            'missing_info' => $missingInfo,
            'original_intent' => $intent,
            'has_data' => false,
            'meta' => [
                'source' => 'clarification',
                'awaiting_response' => true,
            ],
        ];
    }
    
    /**
     * Build data unavailable response
     * Used when required data is not accessible
     * 
     * @param string $dataType Type of data that's unavailable
     * @param string $language Response language
     * @return array Response indicating data unavailability
     */
    public function buildDataUnavailableResponse(string $dataType, string $language = 'english'): array
    {
        $responses = [
            'appointment' => [
                'en' => "I don't have access to appointment information at the moment. Please check the Appointments section in your dashboard or try again later.",
                'tl' => "Wala po akong access sa appointment information ngayon. Pakitingnan po ang Appointments section sa dashboard niyo o subukan ulit mamaya.",
            ],
            'payment' => [
                'en' => "Payment details are not available to me right now. Please visit the Payments section in your dashboard for accurate information.",
                'tl' => "Hindi po available sa akin ang payment details ngayon. Pumunta po sa Payments section sa dashboard niyo para sa tumpak na information.",
            ],
            'refund' => [
                'en' => "Refund information is not accessible at the moment. Please check your refund status in the dashboard or contact support.",
                'tl' => "Hindi po accessible ang refund information ngayon. Pakitingnan po ang refund status niyo sa dashboard o makipag-ugnayan sa support.",
            ],
            'user' => [
                'en' => "I don't have access to user account details. Please check your profile settings directly.",
                'tl' => "Wala po akong access sa user account details. Pakitingnan po directly ang profile settings niyo.",
            ],
            'service' => [
                'en' => "Service information is currently unavailable. Please visit our Services page for the most up-to-date information.",
                'tl' => "Hindi po available ang service information ngayon. Pumunta po sa Services page para sa updated information.",
            ],
            'general' => [
                'en' => "I don't have access to that information in the system right now. Please contact support or check the relevant section in your dashboard.",
                'tl' => "Wala po akong access sa information na iyan sa system ngayon. Makipag-ugnayan po sa support o tingnan ang relevant section sa dashboard niyo.",
            ],
        ];
        
        $langKey = $language === 'filipino' ? 'tl' : 'en';
        $responseData = $responses[$dataType] ?? $responses['general'];
        
        return [
            'response' => $responseData[$langKey],
            'data_unavailable' => true,
            'data_type' => $dataType,
            'has_data' => false,
            'meta' => [
                'source' => 'data_unavailable',
                'reason' => 'system_data_not_accessible',
            ],
        ];
    }

    /**
     * Build role restriction response
     * Used when a user tries to access features outside their role
     * 
     * @param string $requestedFeature The feature being requested
     * @param string $currentRole User's current role
     * @param array $allowedRoles Roles that can access this feature
     * @param string $language Response language
     * @return array Response with guidance
     */
    public function buildRoleRestrictionResponse(
        string $requestedFeature,
        string $currentRole,
        array $allowedRoles,
        string $language = 'english'
    ): array {
        $roleNames = array_map(fn($r) => ucfirst($r), $allowedRoles);
        $roleList = count($roleNames) > 1 
            ? implode(', ', array_slice($roleNames, 0, -1)) . ' or ' . end($roleNames)
            : $roleNames[0] ?? 'Admin';

        if ($language === 'filipino') {
            $messages = [
                'view_all_appointments' => "Ang pagtingin sa lahat ng appointments ay para sa {$roleList} accounts lang po. Bilang " . ucfirst($currentRole) . ", pwede niyo po tingnan ang sarili niyong appointments sa pamamagitan ng pag-tanong ng 'Ipakita ang mga appointment ko'.",
                'approve_appointment' => "Ang pag-approve ng appointments ay kailangan ng {$roleList} privileges. Bilang " . ucfirst($currentRole) . ", pwede niyo i-check ang status ng appointment niyo.",
                'decline_appointment' => "Ang pag-decline ng appointments ay kailangan ng {$roleList} privileges. Pwede niyo i-cancel ang sarili niyong appointment kung kailangan.",
                'approve_refund' => "Ang refund approvals ay handled ng {$roleList}. Pwede kayong mag-request ng refund at i-track ang status nito.",
                'process_payment' => "Ang payment processing ay restricted sa {$roleList}. Pwede niyo i-check ang payment status niyo o magbayad sa payment portal.",
                'view_analytics' => "Ang analytics at reports ay para sa {$roleList} lang. Pwede ko kayo tulungan sa personal appointment at payment information niyo.",
                'manage_users' => "Ang user management ay para sa {$roleList} accounts lang. Pwede ko kayo tulungan i-update ang sarili niyong profile.",
                'default' => "Ang feature na ito ay restricted sa {$roleList} accounts. Ang role niyo ngayon (" . ucfirst($currentRole) . ") ay walang access dito. May iba pa ba akong maitutulong sa inyo?",
            ];
        } else {
            $messages = [
                'view_all_appointments' => "Viewing all appointments is restricted to {$roleList} accounts. As a " . ucfirst($currentRole) . ", you can view your own appointments by asking 'Show my appointments'.",
                'approve_appointment' => "Approving appointments requires {$roleList} privileges. As a " . ucfirst($currentRole) . ", you can check your appointment status instead.",
                'decline_appointment' => "Declining appointments requires {$roleList} privileges. You can cancel your own appointments if needed.",
                'approve_refund' => "Refund approvals are handled by {$roleList}. You can request a refund and track its status.",
                'process_payment' => "Payment processing is restricted to {$roleList}. You can check your payment status or make payments through the payment portal.",
                'view_analytics' => "Analytics and reports are only available to {$roleList}. I can help you with your personal appointment and payment information instead.",
                'manage_users' => "User management is an {$roleList}-only feature. I can help you update your own profile information.",
                'default' => "This feature is restricted to {$roleList} accounts. Your current role (" . ucfirst($currentRole) . ") doesn't have access to this functionality. Is there something else I can help you with?",
            ];
        }

        $message = $messages[$requestedFeature] ?? $messages['default'];

        return [
            'response' => $message,
            'role_restricted' => true,
            'required_roles' => $allowedRoles,
            'current_role' => $currentRole,
            'has_data' => false,
        ];
    }

    /**
     * Build transparency response when data is unavailable
     * 
     * @param string $dataType Type of data that's unavailable
     * @param string $language Response language
     * @return array Response with transparency message
     */
    public function buildTransparencyResponse(string $dataType, string $language = 'english'): array
    {
        if ($language === 'filipino') {
            $responses = [
                'appointment' => "Hindi ko po ma-access ang appointment information ngayon. Pakitingnan po ang Appointments section sa dashboard niyo o makipag-ugnayan sa support para sa tulong.",
                'payment' => "Hindi ko po makuha ang payment details ngayon. Paki-visit po ang Payments section o makipag-ugnayan sa cashier para sa accurate information.",
                'refund' => "Hindi available sa akin ang refund information ngayon. Pakitingnan po ang refund status niyo sa dashboard o makipag-ugnayan sa administrator.",
                'user' => "Hindi ko po ma-access ang user account details. Pakitingnan po ang profile settings niyo o makipag-ugnayan sa support.",
                'service' => "Hindi available ngayon ang service information. Paki-visit po ang Services page namin para sa updated information.",
                'schedule' => "Hindi ko po ma-access ang schedule data ngayon. Pakitingnan po ang booking calendar para sa available slots.",
                'general' => "Wala po akong access sa information na iyon sa system. Makipag-ugnayan po sa support o tingnan ang relevant section sa dashboard niyo.",
            ];
        } else {
            $responses = [
                'appointment' => "I don't have access to appointment information at the moment. Please check the Appointments section in your dashboard or contact support for assistance.",
                'payment' => "I cannot retrieve payment details right now. Please visit the Payments section or contact the cashier for accurate information.",
                'refund' => "Refund information is not available to me at this time. Please check your refund status in the dashboard or contact an administrator.",
                'user' => "I don't have access to user account details. Please check your profile settings or contact support.",
                'service' => "Service information is currently unavailable. Please visit our Services page for the most up-to-date information.",
                'schedule' => "I cannot access schedule data at the moment. Please check the booking calendar for available slots.",
                'general' => "I don't have access to that information in the system. Please contact support or check the relevant section in your dashboard.",
            ];
        }

        return [
            'response' => $responses[$dataType] ?? $responses['general'],
            'data_unavailable' => true,
            'data_type' => $dataType,
            'has_data' => false,
        ];
    }

    /**
     * Build out of scope response
     * Provides a friendly response without listing all capabilities
     * 
     * @param string $language Response language
     * @return array Response explaining scope limitations
     */
    public function buildOutOfScopeResponse(string $language = 'english'): array
    {
        if ($language === 'filipino') {
            $responses = [
                "Pasensya na, pero ang tanong na iyan ay labas sa aking kakayahan. Ako po ay para lamang sa appointment booking system na ito. May maitutulong ba ako tungkol sa appointments o services niyo?",
                "Hindi ko po masagot iyan dahil hindi iyan sakop ng aking trabaho. Nandito po ako para tulungan kayo sa mga appointments, services, at account dito sa sistema. Ano po ang kailangan niyo?",
                "Pasensya na po, limitado lang po ang aking kakayahan sa appointment system na ito. Kung may tanong po kayo tungkol sa booking o services, masaya po akong tumulong!",
            ];
            $response = $responses[array_rand($responses)];
        } else {
            $responses = [
                "I appreciate your question, but that's outside the scope of what I'm designed to help with. I'm your assistant for this appointment booking system. Is there anything I can help you with regarding appointments or services?",
                "I'm sorry, but I can only assist with matters related to this appointment system. If you have questions about booking, services, or your account, I'd be happy to help!",
                "That's not something I'm able to help with, as my expertise is limited to this booking system. Feel free to ask me about appointments, services, or payments instead!",
                "I wish I could help with that, but it's outside my capabilities. I specialize in appointment booking assistance. What can I help you with regarding your appointments today?",
            ];
            $response = $responses[array_rand($responses)];
        }

        return [
            'response' => $response,
            'out_of_scope' => true,
            'has_data' => false,
        ];
    }

    /**
     * Build action guidance response
     * Used when user asks the bot to perform an action it cannot do
     * 
     * @param string $action The action requested
     * @param string $language Response language
     * @return array Response with guidance on how to perform the action
     */
    public function buildActionGuidanceResponse(string $action, string $language = 'english'): array
    {
        if ($language === 'filipino') {
            $guidance = [
                'approve_appointment' => "Hindi ko po ma-approve ang appointments directly, pero pwede ko kayong i-guide sa process. Para mag-approve ng appointments:\n\n1. Pumunta sa **Admin Dashboard**\n2. I-click ang **Pending Appointments**\n3. I-review ang appointment details\n4. I-click ang **Approve** o **Decline**\n\nGusto niyo bang ipakita ko ang pending appointments na kailangan i-review?",
                'process_payment' => "Hindi ko po ma-process ang payments para sa inyo, pero ito ang paraan:\n\n1. Pumunta sa **Payments** section\n2. Piliin ang appointment na babayaran\n3. Piliin ang payment method\n4. Kumpletuhin ang transaction\n\nKailangan niyo ba ng tulong sa pag-intindi ng payment process?",
                'cancel_appointment' => "Hindi ko po ma-cancel ang appointments directly. Para mag-cancel ng appointment:\n\n1. Pumunta sa **My Appointments**\n2. Hanapin ang appointment na gusto niyo i-cancel\n3. I-click ang **Cancel Appointment**\n4. I-confirm ang cancellation\n\nGusto niyo bang makita ang upcoming appointments niyo?",
                'default' => "Ako po ay designed para mag-assist, inform, at guide - pero hindi po ako pwedeng mag-perform ng actions para sa inyo. Ito ay para sa security at accuracy ng system.\n\nPwede ko po:\n✓ I-explain kung paano gawin ang isang bagay\n✓ Ipakita ang relevant information\n✓ I-guide kayo sa mga processes\n✓ Sagutin ang mga tanong niyo\n\nAno po ang gusto niyong malaman?",
            ];
        } else {
            $guidance = [
                'approve_appointment' => "I can't approve appointments directly, but I can guide you through the process. To approve appointments:\n\n1. Go to the **Admin Dashboard**\n2. Navigate to **Pending Appointments**\n3. Review the appointment details\n4. Click **Approve** or **Decline**\n\nWould you like me to show you the pending appointments that need review?",
                'process_payment' => "I'm unable to process payments on your behalf, but here's how you can do it:\n\n1. Go to the **Payments** section\n2. Select the appointment to pay for\n3. Choose your payment method\n4. Complete the transaction\n\nNeed help understanding the payment process?",
                'cancel_appointment' => "I can't cancel appointments directly. To cancel an appointment:\n\n1. Go to **My Appointments**\n2. Find the appointment you want to cancel\n3. Click **Cancel Appointment**\n4. Confirm your cancellation\n\nWould you like to see your upcoming appointments?",
                'default' => "I'm designed to assist, inform, and guide - but I cannot perform actions on your behalf. This ensures security and accuracy in the system.\n\nI can:\n✓ Explain how to do something\n✓ Show you relevant information\n✓ Guide you through processes\n✓ Answer your questions\n\nWhat would you like to know?",
            ];
        }

        $response = $guidance[$action] ?? $guidance['default'];

        return [
            'response' => $response,
            'action_guidance' => true,
            'requested_action' => $action,
            'has_data' => false,
        ];
    }

    /**
     * Build inappropriate content response
     * 
     * @param string $language Response language
     * @return array Response handling inappropriate content
     */
    public function buildInappropriateContentResponse(string $language = 'english'): array
    {
        if ($language === 'filipino') {
            $responses = [
                "Nandito po ako para tumulong sa mga system-related questions. Panatilihin natin na may respeto at professional ang ating usapan. Paano ko po kayo matutulungan sa appointments, services, o payments?",
                "Naiintindihan ko po na maaaring frustrated kayo, pero hindi ko po masagot ang inappropriate language. Masaya po akong tumulong kung mayroon kayong tanong tungkol sa aming services, appointments, o account niyo.",
            ];
        } else {
            $responses = [
                "I'm here to help with system-related questions. Let's keep our conversation respectful and professional. How can I assist you with appointments, services, or payments?",
                "I understand you may be frustrated, but I'm unable to respond to inappropriate language. I'm happy to help if you have questions about our services, appointments, or your account.",
            ];
        }

        return [
            'response' => $responses[array_rand($responses)],
            'content_filtered' => true,
            'has_data' => false,
        ];
    }

    /**
     * Build comprehensive error handling response
     * 
     * @param string $errorType Type of error
     * @param string $context Additional context
     * @param string $language Response language
     * @return array Response with error handling guidance
     */
    public function buildErrorHandlingResponse(string $errorType, string $context = '', string $language = 'english'): array
    {
        if ($language === 'filipino') {
            $responses = [
                'database_error' => [
                    'response' => "Nagkakaroon ako ng problema sa pag-access ng system data ngayon. Maaaring temporary issue ito.",
                    'suggestions' => [
                        'Subukan i-refresh ang page',
                        'Maghintay ng ilang sandali at subukan ulit',
                        'Makipag-ugnayan sa support kung magpatuloy ang problema',
                    ],
                    'next_steps' => 'Kung kailangan niyo ng immediate assistance, makipag-ugnayan po direkta sa support team namin.',
                ],
                'authentication_error' => [
                    'response' => "Mukhang may problema sa session niyo. Maaaring kailangan niyong mag-log in ulit.",
                    'suggestions' => [
                        'Subukan mag-logout at mag-login ulit',
                        'I-clear ang browser cache niyo',
                        'Gamitin ang login page para mag-authenticate ulit',
                    ],
                    'next_steps' => 'Pagkatapos mag-login, magkakaroon kayo ng full access sa account features niyo.',
                ],
                'general' => [
                    'response' => "May nangyaring problema habang prinoprocess ang request niyo.",
                    'suggestions' => [
                        'Subukan ulit sa ilang sandali',
                        'I-rephrase ang tanong niyo',
                        'Makipag-ugnayan sa support para sa tulong',
                    ],
                    'next_steps' => 'Nandito ako para tumulong - sabihin niyo kung paano ko kayo matutulungan.',
                ],
            ];
        } else {
            $responses = [
                'database_error' => [
                    'response' => "I'm having trouble accessing the system data right now. This could be a temporary issue.",
                    'suggestions' => [
                        'Try refreshing the page',
                        'Wait a moment and try again',
                        'Contact support if the issue persists',
                    ],
                    'next_steps' => 'If you need immediate assistance, please contact our support team directly.',
                ],
                'authentication_error' => [
                    'response' => "There seems to be an issue with your session. You may need to log in again.",
                    'suggestions' => [
                        'Try logging out and back in',
                        'Clear your browser cache',
                        'Use the login page to re-authenticate',
                    ],
                    'next_steps' => 'After logging in, you\'ll have full access to your account features.',
                ],
                'permission_error' => [
                    'response' => "You don't have permission to access this feature with your current account type.",
                    'suggestions' => [
                        'Check if you\'re logged into the correct account',
                        'Contact an administrator for access',
                        'Review the feature requirements',
                    ],
                    'next_steps' => 'I can help you with features available to your account type.',
                ],
                'validation_error' => [
                    'response' => "The information provided doesn't seem to be in the correct format.",
                    'suggestions' => [
                        'Double-check the information you entered',
                        'Make sure all required fields are filled',
                        'Try using a different format (e.g., date format)',
                    ],
                    'next_steps' => 'Let me know what you\'re trying to do, and I\'ll guide you through it.',
                ],
                'not_found' => [
                    'response' => "I couldn't find what you're looking for in the system.",
                    'suggestions' => [
                        'Verify the ID or reference number',
                        'Check if the item exists in your account',
                        'Try searching with different criteria',
                    ],
                    'next_steps' => 'Would you like me to help you search for something else?',
                ],
                'general' => [
                    'response' => "Something went wrong while processing your request.",
                    'suggestions' => [
                        'Try again in a moment',
                        'Rephrase your question',
                        'Contact support for assistance',
                    ],
                    'next_steps' => 'I\'m here to help - let me know if there\'s another way I can assist you.',
                ],
            ];
        }

        $error = $responses[$errorType] ?? $responses['general'];
        
        $formattedResponse = $error['response'] . "\n\n";
        if (!empty($error['suggestions'])) {
            $formattedResponse .= "**Suggestions:**\n";
            foreach ($error['suggestions'] as $suggestion) {
                $formattedResponse .= "• {$suggestion}\n";
            }
        }
        if (!empty($error['next_steps'])) {
            $formattedResponse .= "\n" . $error['next_steps'];
        }

        return [
            'response' => $formattedResponse,
            'error_type' => $errorType,
            'suggestions' => $error['suggestions'] ?? [],
            'has_data' => false,
        ];
    }
}
