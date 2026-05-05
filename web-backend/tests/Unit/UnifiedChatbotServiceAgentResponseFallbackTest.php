<?php

namespace Tests\Unit;

use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicKnowledgeFeedService;
use App\Services\DynamicSystemPromptService;
use App\Services\LLMService;
use App\Services\UnifiedChatbotService;
use App\Services\VectorEmbeddingService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UnifiedChatbotServiceAgentResponseFallbackTest extends TestCase
{
    public function test_internal_tool_name_leak_is_replaced_with_service_list_response(): void
    {
        $service = new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ChatbotFeedbackService::class),
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            Mockery::mock(ChatbotSecurityService::class)
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeAgentVisibleResponse');
        $method->setAccessible(true);

        $normalized = $method->invoke($service, 'get_available_services', [[
            'tool' => 'get_available_services',
            'result' => [
                'success' => true,
                'data' => [
                    ['name' => 'Notarization Service', 'price' => 500],
                    ['name' => 'Document Review', 'price' => 2000],
                ],
                'count' => 2,
            ],
        ]]);

        $this->assertStringContainsString('Notarization Service', $normalized);
        $this->assertStringContainsString('Document Review', $normalized);
        $this->assertStringContainsString('continue with the booking', $normalized);
        $this->assertStringNotContainsString('get_available_services', $normalized);
    }

    public function test_validate_and_clean_response_strips_xml_like_tool_blocks(): void
    {
        $service = new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ChatbotFeedbackService::class),
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            Mockery::mock(ChatbotSecurityService::class)
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateAndCleanResponse');
        $method->setAccessible(true);

        $cleaned = $method->invoke(
            $service,
            "<get_available_slots>\n<parameter=service_id>Document Review</parameter>\n<parameter=date>2026-05-04</parameter>\n<parameter=limit>5</parameter>\n</get_available_slots>"
        );

        $this->assertSame(
            "I apologize, but I couldn't generate a proper response. Could you please rephrase your question?",
            $cleaned
        );
    }
}