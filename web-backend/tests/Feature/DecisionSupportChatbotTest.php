<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use App\Services\AgentToolRegistry;
use App\Services\ActionPermissionService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicSystemPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DecisionSupportChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_has_decision_support_capabilities_in_prompt()
    {
        // 1. Create a cashier user
        $cashier = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'cashier']);
        $cashier->assignRole('cashier');

        // 2. Instantiate the DynamicSystemPromptService
        $dataService = $this->app->make(ChatbotRealTimeDataService::class);
        $securityService = $this->app->make(ChatbotSecurityService::class);
        $promptService = new DynamicSystemPromptService($dataService, $securityService);

        $userContext = [
            'role' => 'cashier',
            'is_authenticated' => true,
            'user' => $cashier->toArray(),
        ];

        // 3. Build a system prompt for the cashier user
        $prompt = $promptService->build($userContext);

        // 4. Assert that the prompt contains the new decision support capabilities
        $this->assertStringContainsString('Read-only Q&A for collections, revenue summaries, shift reports, pending payments, refund queues, and public system/developer information.', $prompt);
        $this->assertStringContainsString('Ask about cashier revenue summaries and collections', $prompt);
        $this->assertStringContainsString('Ask about approved refunds waiting for processing', $prompt);
        $this->assertStringContainsString('Ask about predicted busy days and no-show risks', $prompt);
        $this->assertStringContainsString('cashier revenue summaries and collections', $prompt);
        $this->assertStringContainsString('approved refunds waiting for processing', $prompt);
        $this->assertStringContainsString('public system/developer information', $prompt);
    }

    public function test_cashier_has_permission_to_use_decision_support_tools()
    {
        // 1. Instantiate the ActionPermissionService
        $permissionService = new ActionPermissionService();

        // 2. Assert that the cashier role can use the new decision support tools
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'get_risk_assessment'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'get_customer_insights'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'predict_busy_days'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'predict_no_show'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'cashier_get_revenue_summary'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'cashier_get_shift_report'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'cashier_get_pending_payments'));
        $this->assertTrue($permissionService->canUseAgentTool('cashier', 'cashier_get_refund_queue'));

        $this->assertFalse($permissionService->canUseAgentTool('client', 'cashier_get_revenue_summary'));
        $this->assertFalse($permissionService->canUseAgentTool('guest', 'cashier_get_pending_payments'));
        $this->assertFalse($permissionService->canUseAgentTool('admin', 'cashier_get_refund_queue'));
    }

    public function test_cashier_financial_tools_return_cashier_dashboard_data(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $client = User::factory()->create();

        $service = Service::create([
            'name' => 'Revenue Test Service',
            'price' => 250,
            'is_active' => true,
        ]);

        $paidAppointment = Appointment::create([
            'user_id' => $client->id,
            'type' => 'revenue-test',
            'service_id' => $service->id,
            'service_type' => 'Revenue Test Service',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00:00',
        ]);
        $paidAppointment->status = 'completed';
        $paidAppointment->payment_status = 'paid';
        $paidAppointment->payment_amount = 250;
        $paidAppointment->payment_date = now();
        $paidAppointment->processed_by = $cashier->id;
        $paidAppointment->payment_type = 'cash';
        $paidAppointment->save();

        $pendingAppointment = Appointment::create([
            'user_id' => $client->id,
            'type' => 'pending-payment-test',
            'service_id' => $service->id,
            'service_type' => 'Revenue Test Service',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00:00',
        ]);
        $pendingAppointment->status = 'approved';
        $pendingAppointment->payment_status = 'pending';
        $pendingAppointment->payment_amount = 250;
        $pendingAppointment->save();

        $approvedRefund = Refund::create([
            'appointment_id' => $paidAppointment->id,
            'requested_by' => $client->id,
            'refund_amount' => 50,
            'original_amount' => 250,
            'reason' => 'customer_request',
            'description' => 'Approved refund waiting for processing',
            'is_partial' => true,
        ]);
        $approvedRefund->status = 'approved';
        $approvedRefund->approved_at = now();
        $approvedRefund->save();

        $toolRegistry = $this->app->make(AgentToolRegistry::class);

        $revenueSummary = $toolRegistry->executeTool('cashier_get_revenue_summary', ['timeframe' => 'monthly'], $cashier->id, 'cashier');
        $this->assertTrue($revenueSummary['success']);
        $this->assertSame('monthly', $revenueSummary['data']['timeframe']);
        $this->assertSame(250.0, $revenueSummary['data']['total_revenue']);
        $this->assertSame(1, $revenueSummary['data']['total_sales']);

        $pendingPayments = $toolRegistry->executeTool('cashier_get_pending_payments', ['limit' => 10], $cashier->id, 'cashier');
        $this->assertTrue($pendingPayments['success']);
        $this->assertSame(1, $pendingPayments['count']);
        $this->assertSame($pendingAppointment->id, $pendingPayments['data'][0]['appointment_id']);
        $this->assertSame(trim($client->first_name . ' ' . $client->last_name), $pendingPayments['data'][0]['client_name']);

        $refundQueue = $toolRegistry->executeTool('cashier_get_refund_queue', ['status' => 'approved', 'limit' => 10], $cashier->id, 'cashier');
        $this->assertTrue($refundQueue['success']);
        $this->assertSame(1, $refundQueue['count']);
        $this->assertSame($approvedRefund->id, $refundQueue['data'][0]['id']);

        $shiftReport = $toolRegistry->executeTool('cashier_get_shift_report', ['date' => now()->toDateString()], $cashier->id, 'cashier');
        $this->assertTrue($shiftReport['success']);
        $this->assertSame(250.0, $shiftReport['data']['total_revenue']);
        $this->assertSame(1, $shiftReport['data']['total_sales']);
    }
}
