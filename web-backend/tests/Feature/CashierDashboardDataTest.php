<?php

namespace Tests\Feature;

use App\Models\ActionLog;
use App\Models\Appointment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\RefundApprovedMail;
use App\Mail\ReceiptMail;
use Tests\TestCase;

class CashierDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_include_active_services_and_multi_service_sales(): void
    {
        Cache::flush();

        $cashier = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);

        $consultation = Service::create(['name' => 'Consultation', 'price' => 100, 'is_active' => true]);
        $legalOpinion = Service::create(['name' => 'Legal Opinion', 'price' => 200, 'is_active' => true]);
        $affidavit = Service::create(['name' => 'Affidavit', 'price' => 150, 'is_active' => true]);
        $notarization = Service::create(['name' => 'Notarization', 'price' => 80, 'is_active' => true]);

        $multiServiceAppointment = Appointment::create([
            'user_id' => $client->id,
            'type' => 'consultation',
            'service_id' => $consultation->id,
            'service_type' => 'Consultation, Legal Opinion',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00:00',
        ]);
        $multiServiceAppointment->status = 'completed';
        $multiServiceAppointment->payment_status = 'paid';
        $multiServiceAppointment->payment_amount = 300;
        $multiServiceAppointment->payment_date = now();
        $multiServiceAppointment->processed_by = $cashier->id;
        $multiServiceAppointment->save();
        $multiServiceAppointment->services()->sync([
            $consultation->id => ['price_at_booking' => 100],
            $legalOpinion->id => ['price_at_booking' => 200],
        ]);

        $legacyAppointment = Appointment::create([
            'user_id' => $client->id,
            'type' => 'affidavit',
            'service_id' => $affidavit->id,
            'service_type' => 'Affidavit',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00:00',
        ]);
        $legacyAppointment->status = 'completed';
        $legacyAppointment->payment_status = 'paid';
        $legacyAppointment->payment_amount = 150;
        $legacyAppointment->payment_date = now();
        $legacyAppointment->processed_by = $cashier->id;
        $legacyAppointment->save();

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/dashboard-stats?timeframe=monthly');

        $response->assertOk()->assertJsonPath('success', true);

        $distribution = collect($response->json('salesByService'))->keyBy('label');

        $this->assertSame(1, $distribution->get('Consultation')['value'] ?? null);
        $this->assertSame(1, $distribution->get('Legal Opinion')['value'] ?? null);
        $this->assertSame(1, $distribution->get('Affidavit')['value'] ?? null);
        $this->assertSame(0, $distribution->get('Notarization')['value'] ?? null);
    }

    public function test_dashboard_stats_are_scoped_to_the_current_cashier(): void
    {
        Cache::flush();

        $cashierA = User::factory()->create(['role' => 'staff']);
        $cashierB = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);
        $service = Service::create(['name' => 'Scoped Revenue Service', 'price' => 200, 'is_active' => true]);

        $cashierAAppointment = $this->createPaidAppointment($client->id, $service->id, $cashierA->id);
        $cashierAAppointment->payment_amount = 180;
        $cashierAAppointment->save();

        $cashierBAppointment = $this->createPaidAppointment($client->id, $service->id, $cashierB->id);
        $cashierBAppointment->payment_amount = 320;
        $cashierBAppointment->payment_date = now()->copy()->subDay();
        $cashierBAppointment->save();

        $cashierAResponse = $this
            ->actingAs($cashierA, 'sanctum')
            ->getJson('/api/cashier/dashboard-stats?timeframe=monthly');

        $cashierAResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.totalRevenue', 180)
            ->assertJsonPath('stats.totalSales', 1)
            ->assertJsonPath('stats.todayRevenue', 180)
            ->assertJsonPath('stats.todaySales', 1);

        $cashierBResponse = $this
            ->actingAs($cashierB, 'sanctum')
            ->getJson('/api/cashier/dashboard-stats?timeframe=monthly');

        $cashierBResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.totalRevenue', 320)
            ->assertJsonPath('stats.totalSales', 1)
            ->assertJsonPath('stats.todayRevenue', 0)
            ->assertJsonPath('stats.todaySales', 0);
    }

    public function test_calendar_endpoint_defaults_to_pending_and_approved_appointments(): void
    {
        Cache::flush();

        $cashier = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);
        $service = Service::create(['name' => 'Calendar Service', 'price' => 120, 'is_active' => true]);

        $this->createCalendarAppointment($client->id, $service->id, 'pending', now()->day(6)->toDateString(), '09:00:00');
        $this->createCalendarAppointment($client->id, $service->id, 'approved', now()->day(12)->toDateString(), '10:00:00');
        $this->createCalendarAppointment($client->id, $service->id, 'completed', now()->day(18)->toDateString(), '11:00:00');

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/calendar/appointments?month=' . now()->month . '&year=' . now()->year);

        $response->assertOk()->assertJsonPath('success', true);

        $appointments = collect($response->json('appointments'));

        $this->assertCount(2, $appointments);
        $this->assertSame(['approved', 'pending'], $appointments->pluck('status')->sort()->values()->all());
    }

    public function test_cashier_refunds_endpoint_returns_all_refund_requests(): void
    {
        Cache::flush();

        $cashier = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);
        $service = Service::create(['name' => 'Refund Service', 'price' => 150, 'is_active' => true]);
        $appointment = $this->createPaidAppointment($client->id, $service->id, $cashier->id);

        $pending = Refund::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $client->id,
            'refund_amount' => 25,
            'original_amount' => 150,
            'reason' => 'customer_request',
            'description' => 'Pending refund',
            'is_partial' => true,
        ]);
        $pending->status = 'pending';
        $pending->save();

        $approved = Refund::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $client->id,
            'refund_amount' => 50,
            'original_amount' => 150,
            'reason' => 'customer_request',
            'description' => 'Approved refund',
            'is_partial' => true,
        ]);
        $approved->status = 'approved';
        $approved->save();

        $completed = Refund::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $client->id,
            'refund_amount' => 75,
            'original_amount' => 150,
            'reason' => 'customer_request',
            'description' => 'Completed refund',
            'is_partial' => false,
        ]);
        $completed->status = 'completed';
        $completed->save();

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/refunds');

        $response->assertOk();

        $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();
        $this->assertSame(['approved', 'completed', 'pending'], $statuses);
    }

    public function test_receipt_email_endpoint_queues_receipt_mail(): void
    {
        Cache::flush();
        Mail::fake();

        $cashier = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);
        $service = Service::create(['name' => 'Receipt Service', 'price' => 180, 'is_active' => true]);
        $appointment = $this->createPaidAppointment($client->id, $service->id, $cashier->id);

        $response = $this
            ->actingAs($cashier, 'sanctum')
            ->postJson("/api/cashier/appointments/{$appointment->id}/email-receipt");

        $response->assertOk()->assertJsonPath('success', true);

        Mail::assertQueued(ReceiptMail::class, function (ReceiptMail $mail) use ($client): bool {
            return $mail->hasTo($client->email);
        });
    }

    public function test_refund_approval_queues_approved_mail(): void
    {
        Cache::flush();
        Mail::fake();

        $cashier = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $service = Service::create(['name' => 'Refund Approval Service', 'price' => 200, 'is_active' => true]);
        $appointment = $this->createPaidAppointment($client->id, $service->id, $cashier->id);

        $refund = Refund::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $client->id,
            'refund_amount' => 80,
            'original_amount' => 200,
            'reason' => 'customer_request',
            'description' => 'Refund awaiting approval',
            'is_partial' => true,
        ]);
        $refund->status = 'pending';
        $refund->save();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/refunds/{$refund->id}/approve", [
                'refund_method' => 'cash',
                'approval_notes' => 'Approved for cashier processing.',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        Mail::assertQueued(RefundApprovedMail::class, function (RefundApprovedMail $mail) use ($client): bool {
            return $mail->hasTo($client->email);
        });
    }

    public function test_cashier_and_client_cannot_message_each_other_but_admin_can_message_cashier(): void
    {
        Cache::flush();

        $cashier = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $cashierToClient = $this
            ->actingAs($cashier, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $client->id,
                'message' => 'This should be blocked.',
            ]);

        $cashierToClient->assertStatus(403)->assertJsonPath('success', false);

        $clientToCashier = $this
            ->actingAs($client, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $cashier->id,
                'message' => 'This should also be blocked.',
            ]);

        $clientToCashier->assertStatus(403)->assertJsonPath('success', false);

        $adminToCashier = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $cashier->id,
                'message' => 'Admin to cashier is allowed.',
            ]);

        $adminToCashier->assertOk()->assertJsonPath('success', true);

        $blockedConversation = $this
            ->actingAs($client, 'sanctum')
            ->getJson("/api/messages/conversation/user/{$cashier->id}");

        $blockedConversation->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_cashier_action_logs_keep_admin_logs_and_my_logs_separate(): void
    {
        Cache::flush();

        $cashier = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($cashier, 'sanctum');
        ActionLog::log('cashier_action', 'Cashier-only log entry', 'User', $cashier->id);

        $this->actingAs($admin, 'sanctum');
        ActionLog::log('admin_action', 'Admin-only log entry', 'User', $admin->id);

        $cashierLogsResponse = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/action-logs?type=cashier');

        $cashierLogsResponse->assertOk();
        $cashierLogs = collect($cashierLogsResponse->json('data'));
        $this->assertCount(1, $cashierLogs);
        $this->assertSame('cashier_action', $cashierLogs->first()['action']);
        $this->assertSame($cashier->id, $cashierLogs->first()['user_id']);

        $adminLogsResponse = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/action-logs?type=admin');

        $adminLogsResponse->assertOk();
        $adminLogs = collect($adminLogsResponse->json('data'));
        $this->assertCount(1, $adminLogs);
        $this->assertSame('admin_action', $adminLogs->first()['action']);
        $this->assertSame($admin->id, $adminLogs->first()['user_id']);
    }

    private function createCalendarAppointment(int $userId, int $serviceId, string $status, string $date, string $time): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $userId,
            'type' => 'consultation',
            'service_id' => $serviceId,
            'service_type' => 'Calendar Service',
            'appointment_date' => $date,
            'appointment_time' => $time,
        ]);

        $appointment->status = $status;
        $appointment->payment_status = $status === 'completed' ? 'paid' : 'unpaid';
        $appointment->payment_amount = 120;
        $appointment->payment_date = $status === 'completed' ? now() : null;
        $appointment->save();

        return $appointment;
    }

    private function createPaidAppointment(int $userId, int $serviceId, int $cashierId): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $userId,
            'type' => 'consultation',
            'service_id' => $serviceId,
            'service_type' => 'Paid Service',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '13:00:00',
        ]);

        $appointment->status = 'completed';
        $appointment->payment_status = 'paid';
        $appointment->payment_amount = 180;
        $appointment->payment_date = now();
        $appointment->processed_by = $cashierId;
        $appointment->save();

        return $appointment;
    }
}