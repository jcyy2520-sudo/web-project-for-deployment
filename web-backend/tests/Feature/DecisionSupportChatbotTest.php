<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActionPermissionService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicSystemPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('Get AI-powered insights on a specific customer\'s history and risk profile', $prompt);
        $this->assertStringContainsString('Predict upcoming busy days to prepare for high traffic', $prompt);
        $this->assertStringContainsString('Assess the no-show risk for a specific appointment', $prompt);
        $this->assertStringContainsString('View demand forecasts and no-show patterns to understand operational trends', $prompt);
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
    }
}
