<?php

namespace Tests\Unit;

use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicSystemPromptService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class DynamicSystemPromptServiceBehaviorTest extends TestCase
{
    public function test_language_section_prefers_clarification_for_ambiguous_taglish_messages(): void
    {
        $service = $this->makeService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildLanguageSection');
        $method->setAccessible(true);

        $section = $method->invoke($service, 'taglish');

        $this->assertStringContainsString('Match user in **Taglish**.', $section);
        $this->assertStringContainsString('ONE focused clarification question', $section);
    }

    public function test_cashier_role_sections_allow_public_system_information(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);

        $roleMethod = $reflection->getMethod('buildRoleAndCapabilitySection');
        $roleMethod->setAccessible(true);
        $roleSection = $roleMethod->invoke($service, 'cashier');

        $detailedRoleMethod = $reflection->getMethod('buildRoleSection');
        $detailedRoleMethod->setAccessible(true);
        $detailedRoleSection = $detailedRoleMethod->invoke($service, 'cashier', null);

        $this->assertStringContainsString('public system/developer information', $roleSection);
        $this->assertStringContainsString('public system/developer information', $detailedRoleSection);
    }

    public function test_client_workflow_requires_grounded_service_list_for_booking(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);

        $method = $reflection->getMethod('buildWorkflowSection');
        $method->setAccessible(true);

        $section = $method->invoke($service, 'client');

        $this->assertStringContainsString('call `get_available_services`', $section);
        $this->assertStringContainsString('EXACT returned list', $section);
        $this->assertStringContainsString('NEVER invent, trim, or summarize the service catalog from memory', $section);
    }

    private function makeService(): DynamicSystemPromptService
    {
        return new DynamicSystemPromptService(
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ChatbotSecurityService::class)
        );
    }
}