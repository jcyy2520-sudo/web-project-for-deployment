<?php

namespace Tests\Unit;

use App\Services\ChatbotLoadManagerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotLoadManagerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('chatbot_unified.load.enabled', true);
        config()->set('chatbot_unified.load.max_active_requests', 2);
        config()->set('chatbot_unified.load.warning_threshold', 1);
        config()->set('chatbot_unified.load.degraded_threshold', 2);
        config()->set('chatbot_unified.load.wait_poll_ms', 0);
        config()->set('chatbot_unified.load.wait_ms.high', 0);
        config()->set('chatbot_unified.load.wait_ms.normal', 0);
        config()->set('chatbot_unified.load.wait_ms.low', 0);
        config()->set('chatbot_unified.load.retry_after_seconds', 4);
        config()->set('chatbot_unified.load.stale_request_seconds', 60);
    }

    public function test_load_manager_enters_degraded_mode_before_hard_rejecting(): void
    {
        $service = app(ChatbotLoadManagerService::class);

        $first = $service->admit([
            'role' => 'client',
            'message' => 'Show my appointment status',
            'streaming' => false,
        ]);
        $second = $service->admit([
            'role' => 'client',
            'message' => 'Show my payment status',
            'streaming' => false,
        ]);

        $this->assertTrue($first['admitted']);
        $this->assertSame('normal', $first['mode']);
        $this->assertTrue($second['admitted']);
        $this->assertSame('degraded', $second['mode']);

        $service->release($first['token']);
        $service->release($second['token']);
    }

    public function test_load_manager_rejects_requests_after_capacity_is_full_and_recovers_after_release(): void
    {
        $service = app(ChatbotLoadManagerService::class);

        $first = $service->admit([
            'role' => 'admin',
            'message' => 'urgent dashboard issue',
            'streaming' => false,
        ]);
        $second = $service->admit([
            'role' => 'client',
            'message' => 'Need help with appointment',
            'streaming' => false,
        ]);
        $third = $service->admit([
            'role' => 'guest',
            'message' => 'hello',
            'streaming' => false,
        ]);

        $this->assertTrue($first['admitted']);
        $this->assertTrue($second['admitted']);
        $this->assertFalse($third['admitted']);
        $this->assertSame('busy', $third['mode']);
        $this->assertSame('overloaded', $third['snapshot']['state']);

        $service->release($second['token']);

        $recovered = $service->admit([
            'role' => 'guest',
            'message' => 'hello again',
            'streaming' => false,
        ]);

        $this->assertTrue($recovered['admitted']);

        $service->release($first['token']);
        $service->release($recovered['token']);
    }
}