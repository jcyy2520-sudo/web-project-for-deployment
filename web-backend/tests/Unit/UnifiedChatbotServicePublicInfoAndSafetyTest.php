<?php

namespace Tests\Unit;

use App\Services\AdvancedContentModerationService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicKnowledgeFeedService;
use App\Services\DynamicSystemPromptService;
use App\Services\LLMService;
use App\Services\StreamingLLMService;
use App\Services\SystemInfoProvider;
use App\Services\UnifiedChatbotService;
use App\Services\VectorEmbeddingService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UnifiedChatbotServicePublicInfoAndSafetyTest extends TestCase
{
    public function test_process_message_uses_public_system_info_fast_path_before_llm(): void
    {
        $provider = Mockery::mock(SystemInfoProvider::class);
        $provider->shouldReceive('getSystemInfo')
            ->once()
            ->with('standard')
            ->andReturn([
                'system' => [
                    'name' => 'Appointment Management & Legal Services System',
                    'purpose' => 'a platform for booking legal-service appointments and managing related workflows',
                ],
                'developer' => [
                    'name' => 'IT Student Developer',
                    'education' => [
                        'school' => 'Mindoro State University - Bongabong Campus',
                        'program' => 'Bachelor of Science in Information Technology',
                    ],
                ],
                'features' => [],
            ]);
        $this->app->instance(SystemInfoProvider::class, $provider);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldNotReceive('generateResponse');

        $feedbackService = Mockery::mock(ChatbotFeedbackService::class);
        $feedbackService->shouldReceive('logInteraction')
            ->once()
            ->andReturn(73);

        $securityService = Mockery::mock(ChatbotSecurityService::class);
        $securityService->shouldReceive('runSecurityChecks')
            ->once()
            ->andReturn(['passed' => true]);
        $securityService->shouldReceive('createRoleAssertion')
            ->once()
            ->andReturn('role-assertion');

        $service = new UnifiedChatbotService(
            $llmService,
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            $feedbackService,
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            $securityService
        );

        $result = $service->processMessage(
            'Who developed this system?',
            null,
            'public-system-fast-path-conversation',
            ['ip_address' => '127.0.0.1']
        );

        $this->assertTrue($result['success']);
        $this->assertSame('public_system_info_fast_path', $result['source']);
        $this->assertSame('guest', $result['meta']['role']);
        $this->assertSame('73', $result['meta']['interaction_id']);
        $this->assertStringContainsString('IT Student Developer', $result['response']);
    }

    public function test_public_system_info_fast_path_is_available_to_all_roles_without_private_metrics(): void
    {
        $roles = ['guest', 'client', 'staff', 'cashier', 'admin'];

        $provider = Mockery::mock(SystemInfoProvider::class);
        $provider->shouldReceive('getSystemInfo')
            ->times(count($roles))
            ->with('standard')
            ->andReturn([
                'system' => [
                    'name' => 'Appointment Management & Legal Services System',
                    'purpose' => 'a platform for booking legal-service appointments and managing related workflows',
                ],
                'developer' => [
                    'name' => 'IT Student Developer',
                    'education' => [
                        'school' => 'Mindoro State University - Bongabong Campus',
                        'program' => 'Bachelor of Science in Information Technology',
                    ],
                ],
                'features' => [
                    'appointment_system' => ['description' => 'Complete appointment lifecycle management'],
                ],
                'status' => [
                    'current_metrics' => [
                        'total_users' => 999,
                        'total_appointments' => 555,
                    ],
                ],
            ]);
        $this->app->instance(SystemInfoProvider::class, $provider);

        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('tryPublicSystemInfoFastPath');
        $method->setAccessible(true);

        foreach ($roles as $role) {
            $result = $method->invoke($service, 'What is this system and who developed it?', $role);

            $this->assertIsArray($result);
            $this->assertStringContainsString('Appointment Management & Legal Services System', $result['response']);
            $this->assertStringContainsString('IT Student Developer', $result['response']);
            $this->assertStringNotContainsString('999', $result['response']);
            $this->assertStringNotContainsString('555', $result['response']);
            $this->assertStringNotContainsString('total_users', $result['response']);
            $this->assertStringNotContainsString('total_appointments', $result['response']);
        }
    }

    public function test_public_system_info_fast_path_ignores_sensitive_operational_queries(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('tryPublicSystemInfoFastPath');
        $method->setAccessible(true);

        $userCountQuery = $method->invoke($service, 'How many users are in the system?', 'guest');
        $revenueQuery = $method->invoke($service, 'What is the total revenue of the system?', 'cashier');

        $this->assertNull($userCountQuery);
        $this->assertNull($revenueQuery);
    }

    public function test_public_system_info_fast_path_returns_safe_summary_for_cashier(): void
    {
        $provider = Mockery::mock(SystemInfoProvider::class);
        $provider->shouldReceive('getSystemInfo')
            ->once()
            ->with('standard')
            ->andReturn([
                'system' => [
                    'name' => 'Appointment Management & Legal Services System',
                    'purpose' => 'a platform for booking legal-service appointments and managing related workflows',
                ],
                'developer' => [
                    'name' => 'IT Student Developer',
                    'education' => [
                        'school' => 'Mindoro State University - Bongabong Campus',
                        'program' => 'Bachelor of Science in Information Technology',
                    ],
                ],
                'features' => [
                    'appointment_system' => ['description' => 'Complete appointment lifecycle management'],
                    'ai_chatbot' => ['description' => 'Intelligent conversational assistant'],
                ],
            ]);
        $this->app->instance(SystemInfoProvider::class, $provider);

        $service = $this->makeService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('tryPublicSystemInfoFastPath');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'Tell me about the system and its developer.', 'cashier');

        $this->assertIsArray($result);
        $this->assertStringContainsString('Appointment Management & Legal Services System', $result['response']);
        $this->assertStringContainsString('IT Student Developer', $result['response']);
        $this->assertStringContainsString('Mindoro State University - Bongabong Campus', $result['response']);
        $this->assertStringContainsString('appointment lifecycle management', strtolower($result['response']));
        $this->assertSame(['system_info'], $result['context_used']);
    }

    public function test_public_system_security_fast_path_uses_documented_features_only(): void
    {
        $provider = Mockery::mock(SystemInfoProvider::class);
        $provider->shouldReceive('getSystemInfo')
            ->once()
            ->with('standard')
            ->andReturn([
                'features' => [
                    'security_features' => [
                        'description' => 'Protection and access control',
                        'features' => [
                            'Role-based access control (RBAC)',
                            'User authentication and authorization',
                            'Session management',
                            'Activity logging',
                            'Secure data transmission (HTTPS)',
                        ],
                    ],
                ],
            ]);
        $this->app->instance(SystemInfoProvider::class, $provider);

        $service = $this->makeService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('tryPublicSystemInfoFastPath');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'Pano ko malaman kung safe ba talaga data ko dito?', 'client');

        $this->assertIsArray($result);
        $this->assertStringContainsString('Role-based access control (RBAC)', $result['response']);
        $this->assertStringContainsString('Secure data transmission (HTTPS)', $result['response']);
        $this->assertStringContainsString('I can confirm only these documented protections', $result['response']);
        $this->assertStringNotContainsString('licensed payment gateways', strtolower($result['response']));
        $this->assertStringNotContainsString('regular updates', strtolower($result['response']));
        $this->assertSame(['system_info_security'], $result['context_used']);
    }

    public function test_perform_safety_check_refuses_directed_bad_behavior(): void
    {
        $moderationService = Mockery::mock(AdvancedContentModerationService::class);
        $moderationService->shouldReceive('checkContentSafety')
            ->once()
            ->with('you are a stupid useless bot')
            ->andReturn([
                'safe' => true,
                'violation_type' => 'harassment',
                'reasons' => ['harassment'],
            ]);
        $moderationService->shouldReceive('getSafeResponse')
            ->once()
            ->with('harassment')
            ->andReturn("Let's keep our conversation positive and constructive. I'm here to help, but I need respect from both sides. How can I assist you today?");
        $this->app->instance(AdvancedContentModerationService::class, $moderationService);

        $service = $this->makeService();

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('performSafetyCheck');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'you are a stupid useless bot');

        $this->assertFalse($result['safe']);
        $this->assertSame('harassment', $result['reason']);
        $this->assertStringContainsString('respect', strtolower($result['response']));
    }

    public function test_process_message_streaming_uses_public_system_info_fast_path_before_streaming_llm(): void
    {
        $provider = Mockery::mock(SystemInfoProvider::class);
        $provider->shouldReceive('getSystemInfo')
            ->once()
            ->with('standard')
            ->andReturn([
                'system' => [
                    'name' => 'Appointment Management & Legal Services System',
                    'purpose' => 'a platform for booking legal-service appointments and managing related workflows',
                ],
                'developer' => [
                    'name' => 'IT Student Developer',
                    'education' => [
                        'school' => 'Mindoro State University - Bongabong Campus',
                        'program' => 'Bachelor of Science in Information Technology',
                    ],
                ],
                'features' => [],
            ]);
        $this->app->instance(SystemInfoProvider::class, $provider);

        $moderationService = Mockery::mock(AdvancedContentModerationService::class);
        $moderationService->shouldReceive('checkContentSafety')
            ->once()
            ->with('Who developed this system?')
            ->andReturn([
                'safe' => true,
                'reasons' => [],
                'violation_type' => null,
            ]);
        $this->app->instance(AdvancedContentModerationService::class, $moderationService);

        $streamingService = Mockery::mock(StreamingLLMService::class);
        $streamingService->shouldNotReceive('streamResponse');

        $feedbackService = Mockery::mock(ChatbotFeedbackService::class);
        $feedbackService->shouldReceive('logInteraction')
            ->once()
            ->andReturn(91);

        $securityService = Mockery::mock(ChatbotSecurityService::class);
        $securityService->shouldReceive('runSecurityChecks')
            ->once()
            ->andReturn(['passed' => true]);

        $service = new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            $feedbackService,
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            $securityService,
            $streamingService
        );

        $tokens = [];
        $completed = [];

        $result = $service->processMessageStreaming(
            'Who developed this system?',
            null,
            'public-system-streaming-conversation',
            function ($token) use (&$tokens): void {
                $tokens[] = $token;
            },
            function ($finalResult) use (&$completed): void {
                $completed[] = $finalResult;
            },
            ['ip_address' => '127.0.0.1']
        );

        $this->assertSame('public_system_info_fast_path', $result['source']);
        $this->assertCount(1, $tokens);
        $this->assertStringContainsString('IT Student Developer', $tokens[0]);
        $this->assertCount(1, $completed);
        $this->assertSame('public_system_info_fast_path', $completed[0]['source']);
    }

    private function makeService(): UnifiedChatbotService
    {
        return new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ChatbotFeedbackService::class),
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            Mockery::mock(ChatbotSecurityService::class)
        );
    }
}