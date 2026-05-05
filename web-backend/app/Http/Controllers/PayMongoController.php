<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\DiscountRate;
use App\Models\ActionLog;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayMongoController extends Controller
{
    /**
     * Get the secret key for server-side API calls
     */
    private function getSecretKey(): string
    {
        return config('paymongo.secret_key');
    }

    private function getWebhookSecret(): ?string
    {
        return config('paymongo.webhook_secret');
    }

    private function getApiBaseUrl(): string
    {
        return config('paymongo.api_base_url', 'https://api.paymongo.com/v1');
    }

    private function getFrontendUrl(): string
    {
        return rtrim(config('paymongo.frontend_url', config('app.url', 'http://localhost:5173')), '/');
    }

    private function getWebhookToleranceSeconds(): int
    {
        return max(0, (int) config('paymongo.webhook_tolerance_seconds', 300));
    }

    /**
     * Create a PayMongo Checkout Session for an appointment
     *
     * The cashier triggers this when selecting "Online Payment".
     * It creates a hosted checkout page on PayMongo where the client
     * can pay via card, GCash, GrabPay, etc.
     */
    public function createCheckoutSession(Request $request, $appointmentId)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:1',
            'discount_type' => 'nullable|string|max:255',
            'discount_proof' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::lockForUpdate()
                ->with(['user', 'service'])
                ->findOrFail($appointmentId);

            // Verify appointment is approved
            if ($appointment->status !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Only approved appointments can be processed for payment',
                    'success' => false
                ], 422);
            }

            // Prevent double payment
            if ($appointment->payment_status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'message' => 'This appointment has already been paid',
                    'success' => false
                ], 422);
            }

            if (in_array($appointment->payment_status, ['partial', 'partially_paid'], true) || $this->getPriorPaidTotal($appointment) > 0.01) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Online checkout currently supports only unpaid appointments with no prior installments',
                    'success' => false
                ], 422);
            }

            // Check if there's already an active checkout session
            if ($appointment->paymongo_checkout_id && $appointment->paymongo_status === 'active') {
                // Return existing checkout URL if still active
                $existingSession = $this->retrieveCheckoutSession($appointment->paymongo_checkout_id);
                if ($existingSession && ($existingSession['attributes']['status'] ?? '') === 'active') {
                    DB::rollBack();
                    return response()->json([
                        'success' => true,
                        'checkout_url' => $existingSession['attributes']['checkout_url'],
                        'checkout_id' => $appointment->paymongo_checkout_id,
                        'message' => 'Existing checkout session is still active'
                    ]);
                }
            }

            $servicePrice = round((float) ($appointment->service->price ?? 0), 2);

            if ($servicePrice <= 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This appointment has no billable service price configured',
                    'success' => false
                ], 422);
            }

            [$discountAmount, $discountType] = $this->resolveCheckoutDiscount($request, $servicePrice);
            $expectedAmount = round(max(0, $servicePrice - $discountAmount), 2);
            $requestedAmount = round((float) $request->payment_amount, 2);

            if (abs($requestedAmount - $expectedAmount) > 0.01) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Checkout amount does not match the server-calculated payable amount',
                    'success' => false,
                    'expected_amount' => $expectedAmount,
                ], 422);
            }

            // PayMongo expects amounts in centavos (smallest currency unit)
            $amountInCentavos = (int) round($expectedAmount * 100);

            if ($amountInCentavos < 100) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment amount must be at least ₱1.00',
                    'success' => false
                ], 422);
            }

            // Build line items for the checkout session
            $serviceName = $appointment->service->name ?? 'Legal Service';
            $clientName = trim(($appointment->user->first_name ?? '') . ' ' . ($appointment->user->last_name ?? ''));

            $frontendUrl = $this->getFrontendUrl();
            $successUrl = $frontendUrl . '/cashier?payment=success&appointment_id=' . $appointmentId;
            $cancelUrl = $frontendUrl . '/cashier?payment=cancelled&appointment_id=' . $appointmentId;

            $description = "Appointment #{$appointmentId} - {$serviceName} for {$clientName}";
            if ($discountAmount > 0) {
                $description .= " (Discount: ₱" . number_format($discountAmount, 2) . " - {$request->discount_type})";
            }

            // Create checkout session via PayMongo API
            $checkoutData = [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'cancel_url' => $cancelUrl,
                        'success_url' => $successUrl,
                        'description' => $description,
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => $amountInCentavos,
                                'name' => $serviceName,
                                'quantity' => 1,
                                'description' => "Appointment #{$appointmentId} for {$clientName}",
                            ]
                        ],
                        'payment_method_types' => config('paymongo.payment_method_types', [
                            'card', 'gcash', 'grab_pay', 'paymaya'
                        ]),
                        'metadata' => [
                            'appointment_id' => (string) $appointmentId,
                            'client_name' => $clientName,
                            'client_email' => $appointment->user->email ?? '',
                            'service_name' => $serviceName,
                            'cashier_id' => (string) $request->user()->id,
                            'discount_amount' => (string) $discountAmount,
                            'discount_type' => $discountType,
                            'discount_proof' => (string) ($request->discount_proof ?? ''),
                            'service_price' => (string) $servicePrice,
                            'charge_amount' => (string) $expectedAmount,
                        ],
                    ]
                ]
            ];

            // If client has an email, pre-fill billing info
            if ($appointment->user && $appointment->user->email) {
                $checkoutData['data']['attributes']['billing'] = [
                    'name' => $clientName,
                    'email' => $appointment->user->email,
                    'phone' => $appointment->user->phone ?? '',
                ];
            }

            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->timeout(30)
                ->post($this->getApiBaseUrl() . '/checkout_sessions', $checkoutData);

            if (!$response->successful()) {
                DB::rollBack();
                $errorBody = $response->json();
                $errorMessage = $errorBody['errors'][0]['detail'] ?? 'Failed to create checkout session';
                Log::error('PayMongo checkout creation failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'appointment_id' => $appointmentId,
                ]);
                return response()->json([
                    'message' => $errorMessage,
                    'success' => false
                ], 500);
            }

            $checkoutSession = $response->json('data');
            $checkoutId = $checkoutSession['id'];
            $checkoutUrl = $checkoutSession['attributes']['checkout_url'];

            // Store checkout info on the appointment
            $appointment->paymongo_checkout_id = $checkoutId;
            $appointment->paymongo_checkout_url = $checkoutUrl;
            $appointment->paymongo_status = 'active';
            $appointment->payment_type = 'online';
            $appointment->discount_amount = $discountAmount;
            $appointment->discount_type = $discountType;
            $appointment->save();

            // Log the action
            ActionLog::log(
                'paymongo_checkout_created',
                "Created PayMongo checkout session for appointment #{$appointmentId} - {$serviceName} - ₱" . number_format($totalAmount, 2),
                'Appointment',
                $appointment->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'checkout_id' => $checkoutId,
                'amount' => $expectedAmount,
                'message' => 'Checkout session created successfully'
            ]);

        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayMongo checkout creation error: ' . $e->getMessage(), [
                'appointment_id' => $appointmentId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => config('app.debug')
                    ? 'Failed to create checkout session: ' . $e->getMessage()
                    : 'Failed to create checkout session',
                'success' => false
            ], 500);
        }
    }

    /**
     * Check payment status for an appointment
     *
     * The frontend polls this endpoint after redirecting the client
     * to PayMongo checkout, to detect when payment completes.
     */
    public function checkPaymentStatus($appointmentId)
    {
        try {
            $appointment = Appointment::with(['user', 'service'])->findOrFail($appointmentId);

            // If already paid, return immediately
            if ($appointment->payment_status === 'paid') {
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment already completed',
                    'appointment' => $appointment,
                ]);
            }

            // If no checkout session exists
            if (!$appointment->paymongo_checkout_id) {
                return response()->json([
                    'success' => true,
                    'status' => 'no_session',
                    'message' => 'No checkout session found for this appointment'
                ]);
            }

            // Query PayMongo for the checkout session status
            $session = $this->retrieveCheckoutSession($appointment->paymongo_checkout_id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Could not retrieve checkout session from PayMongo'
                ], 500);
            }

            $sessionStatus = $session['attributes']['status'] ?? 'unknown';
            $payments = $session['attributes']['payments'] ?? [];

            // Update local status
            $appointment->paymongo_status = $sessionStatus;
            $appointment->save();

            // If payment completed, process it
            if ($this->hasSuccessfulPayment($session)) {
                // Payment was made - process it
                $this->completePaymentFromCheckout($appointment, $session);

                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment completed successfully',
                    'receipt' => $this->buildReceipt($appointment),
                ]);
            }

            if ($sessionStatus === 'expired') {
                return response()->json([
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'Checkout session has expired'
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $sessionStatus,
                'message' => 'Payment is still pending',
                'checkout_url' => $appointment->paymongo_checkout_url,
            ]);

        } catch (\Exception $e) {
            Log::error('PayMongo status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => config('app.debug')
                    ? 'Error checking payment status: ' . $e->getMessage()
                    : 'Error checking payment status'
            ], 500);
        }
    }

    /**
     * Handle PayMongo webhook events
     *
     * This is a public endpoint that receives POST requests from PayMongo
     * when payment events occur (e.g., successful payment).
     */
    public function handleWebhook(Request $request)
    {
        $rawPayload = $request->getContent();

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

            if (!$this->verifyWebhookSignature($request, $rawPayload, $payload)) {
                Log::warning('PayMongo webhook rejected due to invalid signature');
                return response()->json(['success' => false, 'message' => 'Invalid webhook signature'], 401);
            }

            $eventType = $payload['data']['attributes']['type'] ?? '';
            $reservation = $this->reserveWebhookEvent($payload);

            if ($reservation['already_processed'] ?? false) {
                return response()->json(['success' => true, 'duplicate' => true], 200);
            }

            Log::info('PayMongo webhook received', [
                'type' => $eventType,
                'id' => $payload['data']['id'] ?? 'unknown',
            ]);

            // Handle checkout session payment events
            if (in_array($eventType, ['checkout_session.payment.paid', 'payment.paid'])) {
                $this->handlePaymentPaid($payload);
            }

            if (in_array($eventType, ['payment.refunded', 'payment.refund.updated'], true)) {
                Log::info('PayMongo refund webhook received', ['type' => $eventType]);
            }

            if (!empty($reservation['event_id'])) {
                $this->markWebhookProcessed($reservation['event_id']);
            }

            // Always return 200 to acknowledge receipt
            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            $payload = $payload ?? [];
            $eventId = $payload['data']['id'] ?? null;
            if ($eventId) {
                $this->markWebhookFailed($eventId, $e->getMessage());
            }
            Log::error('PayMongo webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Handle payment.paid webhook event
     */
    private function handlePaymentPaid(array $payload)
    {
        $resourceData = $payload['data']['attributes']['data'] ?? [];
        $metadata = $resourceData['attributes']['metadata'] ?? [];
        $resourceType = $resourceData['type'] ?? null;

        // Try to find appointment by metadata
        $appointmentId = $metadata['appointment_id'] ?? null;

        if (!$appointmentId) {
            // Try to find by checkout session ID from the payment
            $checkoutSessionId = $resourceData['attributes']['checkout_session_id'] ?? null;
            if ($checkoutSessionId) {
                $appointment = Appointment::where('paymongo_checkout_id', $checkoutSessionId)->first();
                if ($appointment) {
                    $appointmentId = $appointment->id;
                }
            }
        }

        if (!$appointmentId) {
            Log::warning('PayMongo webhook: Could not determine appointment ID from payload', [
                'metadata' => $metadata,
            ]);
            return;
        }

        $appointment = Appointment::with(['user', 'service'])->find($appointmentId);
        if (!$appointment) {
            Log::warning("PayMongo webhook: Appointment #{$appointmentId} not found");
            return;
        }

        // Skip if already paid
        if ($appointment->payment_status === 'paid') {
            Log::info("PayMongo webhook: Appointment #{$appointmentId} already paid, skipping");
            return;
        }

        // Retrieve the full checkout session to get accurate payment details
        $session = null;
        if ($resourceType === 'checkout_session') {
            $session = $resourceData;
        } elseif ($appointment->paymongo_checkout_id) {
            $session = $this->retrieveCheckoutSession($appointment->paymongo_checkout_id);
        }

        if (!$session) {
            Log::warning("PayMongo webhook: checkout session unavailable for appointment #{$appointmentId}");
            return;
        }

        $this->completePaymentFromCheckout($appointment, $session, $resourceData);
    }

    /**
     * Complete payment processing from a PayMongo checkout session
     */
    private function completePaymentFromCheckout(Appointment $appointment, ?array $session, ?array $paymentData = null)
    {
        // Prevent double processing
        if ($appointment->payment_status === 'paid') {
            return;
        }

        try {
            DB::beginTransaction();

            // Re-fetch with lock to prevent race conditions
            $appointment = Appointment::lockForUpdate()->with(['user', 'service'])->find($appointment->id);
            if (!$appointment || $appointment->payment_status === 'paid') {
                DB::rollBack();
                return;
            }

            if (!$session || !$this->hasSuccessfulPayment($session)) {
                DB::rollBack();
                Log::warning('PayMongo payment completion skipped because checkout session has no successful payment', [
                    'appointment_id' => $appointment->id,
                ]);
                return;
            }

            $successfulPayment = $this->extractSuccessfulPayment($session);
            $paymentAttributes = $successfulPayment['attributes'] ?? [];
            $metadata = $session['attributes']['metadata'] ?? [];
            if ($paymentData) {
                $metadata = array_merge($metadata, $paymentData['attributes']['metadata'] ?? []);
            }

            $paymongoPaymentId = $successfulPayment['id'] ?? ($paymentData['id'] ?? null);
            $servicePrice = round((float) ($appointment->service->price ?? ($metadata['service_price'] ?? 0)), 2);
            $discountAmount = round((float) ($appointment->discount_amount ?? ($metadata['discount_amount'] ?? 0)), 2);
            $discountType = $appointment->discount_type ?? ($metadata['discount_type'] ?? '');
            $cashierId = isset($metadata['cashier_id']) ? (int) $metadata['cashier_id'] : null;
            $paidAmount = round(((float) ($paymentAttributes['amount'] ?? 0)) / 100, 2);

            if ($paidAmount <= 0) {
                DB::rollBack();
                Log::warning('PayMongo payment completion skipped because provider amount is invalid', [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $paymongoPaymentId,
                ]);
                return;
            }

            $providerMethod = $session['attributes']['payment_method_used']
                ?? $paymentAttributes['source']['type']
                ?? null;
            $paymentMethodId = $this->resolvePaymentMethodId($providerMethod);

            // Update appointment
            $appointment->update([
                'discount_amount' => $discountAmount,
                'discount_type' => $discountType,
                'payment_type' => 'online',
                'payment_date' => now(),
                'payment_notes' => 'Paid via PayMongo online checkout',
                'completed_at' => now(),
            ]);

            $appointment->payment_status = 'paid';
            $appointment->payment_amount = $paidAmount;
            $appointment->balance_remaining = 0;
            $appointment->processed_by = $cashierId;
            $appointment->status = 'completed';
            $appointment->completed_by = $cashierId;
            $appointment->paymongo_payment_id = $paymongoPaymentId;
            $appointment->paymongo_status = 'paid';
            $appointment->save();

            // Create a Payment record for consistency
            try {
                $existingPayment = Payment::where('appointment_id', $appointment->id)
                    ->where('notes', 'like', '%' . ($paymongoPaymentId ?? 'NO_ID') . '%')
                    ->first();
                if (!$existingPayment) {
                    $newPayment = Payment::create([
                        'appointment_id' => $appointment->id,
                        'recorded_by' => $cashierId,
                        'service_price' => $servicePrice,
                        'amount_paid' => $paidAmount,
                        'discount_amount' => $discountAmount,
                        'payment_method_id' => $paymentMethodId,
                        'payment_date' => now(),
                        'notes' => 'Paid via PayMongo online checkout (PayMongo ID: ' . ($paymongoPaymentId ?? 'N/A') . ')',
                    ]);
                    $newPayment->shortfall = 0;
                    $newPayment->payment_status = 'paid';
                    $newPayment->save();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to create Payment record during PayMongo completion: ' . $e->getMessage());
            }

            // Log the action
            ActionLog::log(
                'paymongo_payment_completed',
                "Online payment of ₱" . number_format($paidAmount, 2) . " completed via PayMongo for " .
                    ($appointment->user ? "{$appointment->user->first_name} {$appointment->user->last_name}" : "Appointment #{$appointment->id}") .
                    " - " . ($appointment->service->name ?? 'N/A'),
                'Appointment',
                $appointment->id
            );

            // Send payment notification to client
            $this->sendPaymentNotification($appointment, $cashierId);

            // Invalidate dashboard caches
            \Illuminate\Support\Facades\Cache::forget('cashier_dashboard_stats_daily');
            \Illuminate\Support\Facades\Cache::forget('cashier_dashboard_stats_weekly');
            \Illuminate\Support\Facades\Cache::forget('cashier_dashboard_stats_monthly');
            \Illuminate\Support\Facades\Cache::forget('cashier_dashboard_stats_yearly');

            DB::commit();

            // Broadcast appointment update
            try {
                $appointment->refresh();
                $appointment->load(['user', 'service']);
                event(new \App\Events\AppointmentUpdated($appointment));
            } catch (\Exception $e) {
                Log::debug('Failed to broadcast PayMongo payment event: ' . $e->getMessage());
            }

            Log::info("PayMongo payment completed for appointment #{$appointment->id}, amount: ₱" . number_format($paidAmount, 2));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayMongo payment completion error: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Retrieve a checkout session from PayMongo API
     */
    private function retrieveCheckoutSession(string $checkoutId): ?array
    {
        try {
            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->timeout(15)
                ->get($this->getApiBaseUrl() . '/checkout_sessions/' . $checkoutId);

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('PayMongo: Failed to retrieve checkout session', [
                'checkout_id' => $checkoutId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('PayMongo: Error retrieving checkout session: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build a receipt response from an appointment
     */
    private function buildReceipt(Appointment $appointment): array
    {
        $appointment->load(['user', 'service']);
        $paymentAmount = (float) ($appointment->payment_amount ?? 0);
        $discount = (float) ($appointment->discount_amount ?? 0);

        return [
            'id' => $appointment->id,
            'date' => now()->toISOString(),
            'clientName' => trim(($appointment->user->first_name ?? '') . ' ' . ($appointment->user->last_name ?? '')),
            'clientEmail' => $appointment->user->email ?? '',
            'service' => $appointment->service->name ?? 'N/A',
            'appointmentDate' => $appointment->appointment_date,
            'subtotal' => $paymentAmount + $discount,
            'discount' => $discount,
            'discountType' => $appointment->discount_type ?? '',
            'totalPaid' => $paymentAmount,
            'paymentMethod' => 'Online Payment (PayMongo)',
        ];
    }

    /**
     * Send payment notification to client
     */
    private function sendPaymentNotification(Appointment $appointment, ?int $cashierId)
    {
        try {
            $appointmentDate = Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
            $serviceType = $appointment->service->name ?? 'N/A';

            $messageText = "✓ Your online payment has been received!\n\n";
            $messageText .= "📅 Date: " . $appointmentDate . "\n";
            $messageText .= "📋 Service: " . $serviceType . "\n";
            $messageText .= "💰 Amount Paid: ₱" . number_format($appointment->payment_amount, 2) . "\n";
            $messageText .= "💳 Payment Method: Online (PayMongo)\n";

            if ($appointment->discount_amount > 0) {
                $messageText .= "🎫 Discount: ₱" . number_format($appointment->discount_amount, 2) . " ({$appointment->discount_type})\n";
            }

            $messageText .= "\nThank you for your payment. Your receipt has been generated.";

            Message::create([
                'sender_id' => $cashierId ?? 1,
                'receiver_id' => $appointment->user_id,
                'message' => $messageText,
                'read' => false,
                'type' => 'payment_processed'
            ]);

            // Create in-app notification
            if (class_exists(\App\Services\NotificationService::class)) {
                \App\Services\NotificationService::paymentProcessed($appointment, $appointment->payment_amount);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send PayMongo payment notification: ' . $e->getMessage());
        }
    }

    /**
     * Expire/cancel a checkout session
     * Called when cashier wants to cancel an active online payment
     */
    public function expireCheckoutSession(Request $request, $appointmentId)
    {
        try {
            $appointment = Appointment::findOrFail($appointmentId);

            if (!$appointment->paymongo_checkout_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active checkout session found'
                ], 404);
            }

            // Expire the checkout session on PayMongo
            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->timeout(15)
                ->post($this->getApiBaseUrl() . '/checkout_sessions/' . $appointment->paymongo_checkout_id . '/expire');

            // Update local status regardless
            $appointment->paymongo_status = 'expired';
            $appointment->save();

            ActionLog::log(
                'paymongo_checkout_expired',
                "Expired PayMongo checkout session for appointment #{$appointmentId}",
                'Appointment',
                $appointment->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Checkout session cancelled'
            ]);

        } catch (\Exception $e) {
            Log::error('PayMongo expire error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel checkout session'
            ], 500);
        }
    }

    private function resolveCheckoutDiscount(Request $request, float $servicePrice): array
    {
        if (!$request->filled('discount_type')) {
            return [0.0, ''];
        }

        $discountType = $this->normalizeDiscountTypeKey($request->input('discount_type'));
        $discountRate = DiscountRate::getByType($discountType);

        if (!$discountRate) {
            throw new \InvalidArgumentException('Unknown or inactive discount type: ' . $request->input('discount_type'));
        }

        $discountAmount = round(($servicePrice * (float) $discountRate->discount_percentage) / 100, 2);

        return [
            $discountAmount,
            ucfirst(str_replace('_', ' ', $discountType)) . " ({$discountRate->discount_percentage}%)",
        ];
    }

    private function normalizeDiscountTypeKey(?string $discountType): string
    {
        $discountKey = str_replace([' ', '-'], '_', strtolower((string) $discountType));

        return match ($discountKey) {
            'pwd', '20%_pwd_discount' => 'pwd',
            'senior', 'senior_citizen', '20%_senior_discount' => 'senior_citizen',
            'student', '10%_student_discount' => 'student',
            default => $discountKey,
        };
    }

    private function getPriorPaidTotal(Appointment $appointment): float
    {
        $paymentTotal = round((float) Payment::where('appointment_id', $appointment->id)->sum('amount_paid'), 2);
        $appointmentTotal = round((float) ($appointment->payment_amount ?? 0), 2);

        return max($paymentTotal, $appointmentTotal);
    }

    private function hasSuccessfulPayment(array $session): bool
    {
        return $this->extractSuccessfulPayment($session) !== null;
    }

    private function extractSuccessfulPayment(array $session): ?array
    {
        foreach (($session['attributes']['payments'] ?? []) as $payment) {
            if (($payment['attributes']['status'] ?? null) === 'paid') {
                return $payment;
            }
        }

        return null;
    }

    private function resolvePaymentMethodId(?string $providerMethod): int
    {
        $normalized = strtolower((string) $providerMethod);

        $definition = match (true) {
            $normalized === 'card' => ['slug' => 'card', 'name' => 'Card', 'description' => 'Credit/Debit card payment'],
            $normalized === 'gcash',
            $normalized === 'grab_pay',
            $normalized === 'paymaya',
            $normalized === 'qrph',
            $normalized === 'dob',
            $normalized === 'dob_ubp',
            str_starts_with($normalized, 'brankas'),
            $normalized === 'billease' => ['slug' => 'online_gateway', 'name' => 'Online Gateway', 'description' => 'Hosted online checkout payment'],
            default => ['slug' => 'online_gateway', 'name' => 'Online Gateway', 'description' => 'Hosted online checkout payment'],
        };

        return PaymentMethod::firstOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'description' => $definition['description'],
            ]
        )->id;
    }

    private function verifyWebhookSignature(Request $request, string $rawPayload, ?array $payload = null): bool
    {
        $secret = $this->getWebhookSecret();
        if (!$secret) {
            Log::error('PayMongo webhook secret is not configured');
            return false;
        }

        $parsed = $this->parseSignatureHeader($request->header('Paymongo-Signature'));
        if (!$parsed || empty($parsed['timestamp'])) {
            return false;
        }

        $tolerance = $this->getWebhookToleranceSeconds();
        if ($tolerance > 0 && abs(time() - (int) $parsed['timestamp']) > $tolerance) {
            Log::warning('PayMongo webhook rejected because timestamp is outside tolerance', [
                'timestamp' => $parsed['timestamp'],
            ]);
            return false;
        }

        $signatureBase = $parsed['timestamp'] . '.' . $rawPayload;
        $computed = hash_hmac('sha256', $signatureBase, $secret);
        $isLive = (bool) ($payload['data']['attributes']['livemode'] ?? false);
        $expected = $isLive ? ($parsed['live'] ?? null) : ($parsed['test'] ?? null);

        return !empty($expected) && hash_equals($expected, $computed);
    }

    private function parseSignatureHeader(?string $header): ?array
    {
        if (!$header) {
            return null;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        return [
            'timestamp' => $parts['t'] ?? null,
            'test' => $parts['te'] ?? null,
            'live' => $parts['li'] ?? null,
        ];
    }

    private function reserveWebhookEvent(array $payload): array
    {
        $eventId = $payload['data']['id'] ?? null;
        if (!$eventId) {
            return ['already_processed' => false, 'event_id' => null];
        }

        $existing = DB::table('paymongo_webhook_events')->where('event_id', $eventId)->first();
        if ($existing) {
            DB::table('paymongo_webhook_events')
                ->where('event_id', $eventId)
                ->update([
                    'attempts' => (int) $existing->attempts + 1,
                    'updated_at' => now(),
                ]);

            return [
                'already_processed' => $existing->processed_at !== null,
                'event_id' => $eventId,
            ];
        }

        DB::table('paymongo_webhook_events')->insert([
            'event_id' => $eventId,
            'event_type' => $payload['data']['attributes']['type'] ?? null,
            'livemode' => (bool) ($payload['data']['attributes']['livemode'] ?? false),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'received_at' => now(),
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['already_processed' => false, 'event_id' => $eventId];
    }

    private function markWebhookProcessed(string $eventId): void
    {
        DB::table('paymongo_webhook_events')
            ->where('event_id', $eventId)
            ->update([
                'processed_at' => now(),
                'processing_error' => null,
                'updated_at' => now(),
            ]);
    }

    private function markWebhookFailed(string $eventId, string $error): void
    {
        DB::table('paymongo_webhook_events')
            ->where('event_id', $eventId)
            ->update([
                'processing_error' => mb_substr($error, 0, 65535),
                'updated_at' => now(),
            ]);
    }
}
