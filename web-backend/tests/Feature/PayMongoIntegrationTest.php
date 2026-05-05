<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayMongoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_rejects_mismatched_server_amount(): void
    {
        $cashier = User::factory()->create(['role' => 'staff']);
        $appointment = $this->createApprovedAppointment();

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->postJson("/api/cashier/paymongo/checkout/{$appointment->id}", [
                'payment_amount' => 90,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('expected_amount', 100);
    }

    public function test_cashier_payment_persists_payment_method_id(): void
    {
        $cashier = User::factory()->create(['role' => 'staff']);
        $appointment = $this->createApprovedAppointment();

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->postJson("/api/cashier/appointments/{$appointment->id}/process-payment", [
                'payment_amount' => 100,
                'payment_type' => 'cash',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $payment = Payment::query()->with('paymentMethod')->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->payment_method_id);
        $this->assertSame('cash', $payment->paymentMethod->slug);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        Config::set('paymongo.webhook_secret', 'whsec_test_secret');

        $appointment = $this->createApprovedAppointment();
        $appointment->paymongo_checkout_id = 'cs_test_secure';
        $appointment->save();

        $payload = $this->checkoutPaidEventPayload($appointment, false);

        $response = $this->call(
            'POST',
            '/api/paymongo/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => 't=1234567890,te=invalid-signature,li=',
            ],
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );

        $response->assertStatus(401);
        $this->assertSame('unpaid', $appointment->fresh()->payment_status);
    }

    public function test_webhook_processes_paid_event_once_and_creates_payment_row(): void
    {
        Config::set('paymongo.webhook_secret', 'whsec_test_secret');

        $appointment = $this->createApprovedAppointment();
        $appointment->paymongo_checkout_id = 'cs_test_secure';
        $appointment->save();

        $payload = $this->checkoutPaidEventPayload($appointment, false);
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = $this->signPayMongoPayload($rawPayload, 'whsec_test_secret', false);

        $firstResponse = $this->call(
            'POST',
            '/api/paymongo/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => $signature,
            ],
            $rawPayload
        );

        $firstResponse->assertOk()->assertJsonPath('success', true);

        $appointment->refresh();
        $this->assertSame('paid', $appointment->payment_status);
        $this->assertSame('pay_test_paid_1', $appointment->paymongo_payment_id);
        $this->assertSame(1, Payment::count());
        $this->assertSame('card', Payment::query()->with('paymentMethod')->first()->paymentMethod->slug);

        $duplicateResponse = $this->call(
            'POST',
            '/api/paymongo/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => $signature,
            ],
            $rawPayload
        );

        $duplicateResponse->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, Payment::count());
    }

    public function test_online_refund_completion_calls_paymongo_and_stores_provider_refund_id(): void
    {
        Config::set('paymongo.secret_key', 'sk_test_refund_secret');
        Http::fake([
            'https://api.paymongo.com/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_test_123',
                    'type' => 'refund',
                    'attributes' => [
                        'status' => 'succeeded',
                        'payment_id' => 'pay_test_paid_1',
                    ],
                ],
            ], 200),
        ]);
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create(['role' => 'client']);
        $appointment = $this->createApprovedAppointment($requester);
        $appointment->payment_status = 'paid';
        $appointment->payment_type = 'online';
        $appointment->payment_amount = 100;
        $appointment->paymongo_payment_id = 'pay_test_paid_1';
        $appointment->processed_by = $admin->id;
        $appointment->status = 'completed';
        $appointment->save();

        $refund = Refund::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $requester->id,
            'refund_amount' => 100,
            'original_amount' => 100,
            'reason' => 'requested_by_customer',
            'description' => 'Client requested refund',
            'refund_method' => 'original_method',
            'is_partial' => false,
        ]);
        $refund->status = 'approved';
        $refund->approved_by = $admin->id;
        $refund->approved_at = now();
        $refund->save();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/refunds/{$refund->id}/complete", []);

        $response->assertOk()->assertJsonPath('success', true);

        $refund->refresh();
        $appointment->refresh();

        $this->assertSame('completed', $refund->status);
        $this->assertSame('ref_test_123', $refund->transaction_id);
        $this->assertSame('refunded', $appointment->payment_status);

        Http::assertSent(function ($request) use ($appointment) {
            $data = $request->data();

            return $request->url() === 'https://api.paymongo.com/v1/refunds'
                && ($data['data']['attributes']['payment_id'] ?? null) === $appointment->paymongo_payment_id
                && ($data['data']['attributes']['amount'] ?? null) === 10000;
        });
    }

    private function createApprovedAppointment(?User $client = null): Appointment
    {
        $client ??= User::factory()->create(['role' => 'client']);
        $service = Service::create([
            'name' => 'Consultation ' . uniqid(),
            'price' => 100,
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'user_id' => $client->id,
            'type' => 'consultation',
            'service_id' => $service->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '09:00',
            'purpose' => 'Payment integration test',
        ]);

        $appointment->status = 'approved';
        $appointment->payment_status = 'unpaid';
        $appointment->save();

        return $appointment->fresh(['service', 'user']);
    }

    private function checkoutPaidEventPayload(Appointment $appointment, bool $livemode): array
    {
        return [
            'data' => [
                'id' => 'evt_test_checkout_paid',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => $livemode,
                    'data' => [
                        'id' => $appointment->paymongo_checkout_id,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'status' => 'active',
                            'payment_method_used' => 'card',
                            'metadata' => [
                                'appointment_id' => (string) $appointment->id,
                                'cashier_id' => '1',
                                'service_price' => '100',
                                'discount_amount' => '0',
                                'discount_type' => '',
                            ],
                            'payments' => [
                                [
                                    'id' => 'pay_test_paid_1',
                                    'type' => 'payment',
                                    'attributes' => [
                                        'status' => 'paid',
                                        'amount' => 10000,
                                        'source' => [
                                            'type' => 'card',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function signPayMongoPayload(string $rawPayload, string $secret, bool $livemode): string
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $secret);

        return $livemode
            ? "t={$timestamp},te=,li={$signature}"
            : "t={$timestamp},te={$signature},li=";
    }
}
