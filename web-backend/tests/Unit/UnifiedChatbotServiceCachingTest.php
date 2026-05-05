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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UnifiedChatbotServiceCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gather_real_time_data_keeps_user_appointment_cache_warm_for_read_only_turns(): void
    {
        Cache::spy();

        $dataService = Mockery::mock(ChatbotRealTimeDataService::class);
        $dataService->shouldReceive('getBusinessInfo')->once()->andReturn([]);
        $dataService->shouldReceive('getAvailableServices')->once()->andReturn([]);
        $dataService->shouldReceive('getBusinessHours')->once()->andReturn([]);
        $dataService->shouldReceive('getUserAppointments')->once()->with(41, null, 8)->andReturn([]);
        $dataService->shouldReceive('getUserPayments')->once()->with(41, null, 8)->andReturn([]);

        $service = new UnifiedChatbotService(
            Mockery::mock(LLMService::class),
            Mockery::mock(VectorEmbeddingService::class),
            $dataService,
            Mockery::mock(ChatbotFeedbackService::class),
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            Mockery::mock(ChatbotSecurityService::class)
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('gatherRealTimeData');
        $method->setAccessible(true);

        $result = $method->invoke($service, 41, 'client', 'Show me my appointments today.');

        $this->assertArrayHasKey('user_appointments', $result);
        $this->assertArrayHasKey('user_payments', $result);
        Cache::shouldNotHaveReceived('forget', ["chatbot_appointments_user_41_all"]);
        Cache::shouldNotHaveReceived('forget', ["chatbot_appointments_user_41_pending"]);
        Cache::shouldNotHaveReceived('forget', ["chatbot_appointments_user_41_approved"]);
        Cache::shouldNotHaveReceived('forget', ["chatbot_appointments_user_41_completed"]);
        Cache::shouldNotHaveReceived('forget', ["chatbot_appointments_user_41_cancelled"]);
        Cache::shouldNotHaveReceived('forget', ["chatbot_booking_limit_41"]);
    }
}