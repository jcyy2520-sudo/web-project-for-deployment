<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
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
     * PayMongo API base URL
     */
    private string $apiBaseUrl = 'https://api.paymongo.com/v1';

    /**
     * Get the secret key for server-side API calls
     */
    private function getSecretKey(): string
    {
        return config('paymongo.secret_key');
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
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|max:255',
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

            $paymentAmount = (float) $request->payment_amount;
            $discountAmount = (float) ($request->discount_amount ?? 0);
            $totalAmount = max(0, $paymentAmount - $discountAmount);

            // PayMongo expects amounts in centavos (smallest currency unit)
            $amountInCentavos = (int) round($totalAmount * 100);

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

            // Get the frontend URL for redirect
            $frontendUrl = config('app.url', 'http://localhost:5173');
            // Try to determine the correct frontend URL
            $successUrl = $request->input('success_url', $frontendUrl . '/cashier?payment=success&appointment_id=' . $appointmentId);
            $cancelUrl = $request->input('cancel_url', $frontendUrl . '/cashier?payment=cancelled&appointment_id=' . $appointmentId);

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
                            'discount_type' => $request->discount_type ?? '',
                            'original_amount' => (string) $paymentAmount,
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
                ->post($this->apiBaseUrl . '/checkout_sessions', $checkoutData);

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
                'amount' => $totalAmount,
                'message' => 'Checkout session created successfully'
            ]);

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
            if ($sessionStatus === 'active' && !empty($payments)) {
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
        try {
            $payload = $request->all();
            $eventType = $payload['data']['attributes']['type'] ?? '';

            Log::info('PayMongo webhook received', [
                'type' => $eventType,
                'id' => $payload['data']['id'] ?? 'unknown',
            ]);

            // Handle checkout session payment events
            if (in_array($eventType, ['checkout_session.payment.paid', 'payment.paid'])) {
                $this->handlePaymentPaid($payload);
            }

            // Always return 200 to acknowledge receipt
            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('PayMongo webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // Still return 200 to prevent PayMongo from retrying
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Handle payment.paid webhook event
     */
    private function handlePaymentPaid(array $payload)
    {
        $resourceData = $payload['data']['attributes']['data'] ?? [];
        $metadata = $resourceData['attributes']['metadata'] ?? [];

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
        if ($appointment->paymongo_checkout_id) {
            $session = $this->retrieveCheckoutSession($appointment->paymongo_checkout_id);
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

            // Extract payment details from checkout session or payment data
            $metadata = [];
            $paymongoPaymentId = null;

            if ($session) {
                $metadata = $session['attributes']['metadata'] ?? [];
                $payments = $session['attributes']['payments'] ?? [];
                if (!empty($payments)) {
                    $paymongoPaymentId = $payments[0]['id'] ?? null;
                }
            }

            if ($paymentData) {
                $paymongoPaymentId = $paymongoPaymentId ?? ($paymentData['id'] ?? null);
                $metadata = array_merge($metadata, $paymentData['attributes']['metadata'] ?? []);
            }

            $originalAmount = (float) ($metadata['original_amount'] ?? $appointment->service->price ?? 0);
            $discountAmount = (float) ($metadata['discount_amount'] ?? 0);
            $discountType = $metadata['discount_type'] ?? '';
            $cashierId = $metadata['cashier_id'] ?? null;
            $totalPaid = max(0, $originalAmount - $discountAmount);

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
            $appointment->payment_amount = $totalPaid;
            $appointment->processed_by = $cashierId;
            $appointment->status = 'completed';
            $appointment->completed_by = $cashierId;
            $appointment->paymongo_payment_id = $paymongoPaymentId;
            $appointment->paymongo_status = 'paid';
            $appointment->save();

            // Create a Payment record for consistency
            try {
                $existingPayment = Payment::where('appointment_id', $appointment->id)->first();
                if (!$existingPayment) {
                    $newPayment = Payment::create([
                        'appointment_id' => $appointment->id,
                        'recorded_by' => $cashierId,
                        'service_price' => $appointment->service->price ?? $originalAmount,
                        'amount_paid' => $totalPaid,
                        'discount_amount' => $discountAmount,
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
                "Online payment of ₱" . number_format($totalPaid, 2) . " completed via PayMongo for " .
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

            Log::info("PayMongo payment completed for appointment #{$appointment->id}, amount: ₱" . number_format($totalPaid, 2));

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
                ->get($this->apiBaseUrl . '/checkout_sessions/' . $checkoutId);

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
                ->post($this->apiBaseUrl . '/checkout_sessions/' . $appointment->paymongo_checkout_id . '/expire');

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
}
