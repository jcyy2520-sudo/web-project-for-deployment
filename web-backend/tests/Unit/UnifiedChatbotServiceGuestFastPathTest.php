<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicKnowledgeFeedService;
use App\Services\DynamicSystemPromptService;
use App\Services\LLMService;
use App\Services\UnifiedChatbotService;
use App\Services\VectorEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UnifiedChatbotServiceGuestFastPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_public_service_question_skips_llm_pipeline(): void
    {
        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldNotReceive('generateResponse');

        $embeddingService = Mockery::mock(VectorEmbeddingService::class);

        $dataService = Mockery::mock(ChatbotRealTimeDataService::class);
        $dataService->shouldReceive('getAvailableServices')
            ->once()
            ->andReturn([
                ['name' => 'Notarial Service', 'price' => 150],
                ['name' => 'Legal Consultation', 'price' => 1200],
            ]);

        $feedbackService = Mockery::mock(ChatbotFeedbackService::class);
        $feedbackService->shouldReceive('logInteraction')
            ->once()
            ->andReturn(42);

        $promptService = Mockery::mock(DynamicSystemPromptService::class);
        $knowledgeFeedService = Mockery::mock(DynamicKnowledgeFeedService::class);

        $securityService = Mockery::mock(ChatbotSecurityService::class);
        $securityService->shouldReceive('runSecurityChecks')
            ->once()
            ->andReturn(['passed' => true]);
        $securityService->shouldReceive('createRoleAssertion')
            ->once()
            ->andReturn('role-assertion');

        $service = new UnifiedChatbotService(
            $llmService,
            $embeddingService,
            $dataService,
            $feedbackService,
            $promptService,
            $knowledgeFeedService,
            $securityService,
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );

        $result = $service->processMessage(
            'What services do you offer and how much do they cost?',
            null,
            'guest-fast-path-conversation',
            ['ip_address' => '127.0.0.1']
        );

        $this->assertTrue($result['success']);
        $this->assertSame('guest_public_fast_path', $result['source']);
        $this->assertSame('guest', $result['meta']['role']);
        $this->assertSame('42', $result['meta']['interaction_id']);
        $this->assertStringContainsString('Notarial Service', $result['response']);
        $this->assertStringContainsString('Legal Consultation', $result['response']);
        $this->assertStringContainsString('register or log in first', $result['response']);
    }

    public function test_guest_history_is_scoped_by_session_and_conversation(): void
    {
        $firstMessage = new ChatMessage([
            'session_id' => 'guest-session-a',
            'conversation_id' => 'guest-history-conversation',
            'message' => 'First guest question',
            'role' => 'user',
            'source' => 'user',
        ]);
        $firstMessage->created_at = now()->subSeconds(2);
        $firstMessage->save();

        $secondMessage = new ChatMessage([
            'session_id' => 'guest-session-a',
            'conversation_id' => 'guest-history-conversation',
            'message' => 'First guest answer',
            'role' => 'assistant',
            'source' => 'llm',
        ]);
        $secondMessage->created_at = now()->subSecond();
        $secondMessage->save();

        $ignoredMessage = new ChatMessage([
            'session_id' => 'guest-session-b',
            'conversation_id' => 'guest-history-conversation',
            'message' => 'Other guest message',
            'role' => 'user',
            'source' => 'user',
        ]);
        $ignoredMessage->created_at = now();
        $ignoredMessage->save();

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
        $method = $reflection->getMethod('getConversationHistory');
        $method->setAccessible(true);

        $history = $method->invoke($service, null, 'guest-history-conversation', 'guest-session-a');

        $this->assertSame([
            ['role' => 'user', 'content' => 'First guest question'],
            ['role' => 'assistant', 'content' => 'First guest answer'],
        ], $history);
    }
}