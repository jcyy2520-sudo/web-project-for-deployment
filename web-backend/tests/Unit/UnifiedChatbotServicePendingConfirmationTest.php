<?php

namespace Tests\Unit;

use App\Services\AgentReasoningService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicKnowledgeFeedService;
use App\Services\DynamicSystemPromptService;
use App\Services\LLMService;
use App\Services\UnifiedChatbotService;
use App\Services\VectorEmbeddingService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class UnifiedChatbotServicePendingConfirmationTest extends TestCase
{
    public function test_explicit_confirmation_reply_uses_fast_path_before_security_checks(): void
    {
        config(['chatbot_unified.features.agent_mode' => true]);

        Cache::put('agent_confirm_guest_session_alpha_pending', [
            'tool' => 'book_appointment',
            'arguments' => [
                'service_id' => 'Affidavit',
                'date' => '2026-05-09',
                'time' => '08:00',
            ],
            'created_at' => now()->toIso8601String(),
        ], 300);

        $feedbackService = Mockery::mock(ChatbotFeedbackService::class);
        $feedbackService->shouldReceive('logInteraction')
            ->once()
            ->andReturn(11);

        $securityService = Mockery::mock(ChatbotSecurityService::class);
        $securityService->shouldNotReceive('runSecurityChecks');
        $securityService->shouldNotReceive('createRoleAssertion');

        $agentReasoning = Mockery::mock(AgentReasoningService::class);
        $agentReasoning->shouldReceive('reason')
            ->once()
            ->with(
                'confirm',
                '',
                [],
                null,
                'guest',
                Mockery::on(function ($pending): bool {
                    return ($pending['tool'] ?? null) === 'book_appointment'
                        && ($pending['arguments']['service_id'] ?? null) === 'Affidavit';
                }),
                'guest_session_alpha'
            )
            ->andReturn([
                'response' => 'Appointment booked successfully.',
                'tool_calls' => [],
                'provider' => 'agent',
            ]);

        $service = new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            $feedbackService,
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            $securityService,
            null,
            null,
            null,
            null,
            $agentReasoning
        );

        $result = $service->processMessage(
            'confirm',
            null,
            'pending-confirm-fast-path',
            [
                'actor_key' => 'guest_session_alpha',
                'ip_address' => '127.0.0.1',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertSame('llm', $result['source']);
        $this->assertSame('guest', $result['meta']['role']);
        $this->assertSame('Appointment booked successfully.', $result['response']);
        $this->assertFalse(Cache::has('agent_confirm_guest_session_alpha_pending'));
    }
}