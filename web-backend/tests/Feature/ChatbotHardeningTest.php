<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatbotLoadManagerService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\UnifiedChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatbotHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Cache::flush();
    }

    public function test_guest_history_and_conversations_are_backend_restricted(): void
    {
        $this->getJson('/api/chatbot/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('guest_restricted', true)
            ->assertJsonPath('data', []);

        $this->getJson('/api/chatbot/conversations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('guest_restricted', true)
            ->assertJsonPath('data', []);

        $this->getJson('/api/chatbot/conversations/example-conversation')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('guest_restricted', true)
            ->assertJsonPath('data', []);
    }

    public function test_guest_new_conversation_search_and_delete_features_are_blocked(): void
    {
        $this->postJson('/api/chatbot/conversations/new', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Please log in to start a new conversation.');

        $this->postJson('/api/chatbot/search-knowledge', ['query' => 'services'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Please log in to use chatbot search.');

        $this->deleteJson('/api/chatbot/clear-history', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Please log in to clear chat history.');

        $this->deleteJson('/api/chatbot/conversations/example-conversation')
            ->assertForbidden()
            ->assertJsonPath('message', 'Please log in to delete conversations.');
    }

    public function test_overloaded_send_message_returns_graceful_busy_response(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->mock(UnifiedChatbotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('processMessage');
        });

        $this->mock(ChatbotRoleAwarenessService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('detectUserRole');
        });

        $loadManager = \Mockery::mock(ChatbotLoadManagerService::class);
        $loadManager->shouldReceive('admit')
            ->once()
            ->andReturn([
                'admitted' => false,
                'mode' => 'busy',
                'token' => null,
                'snapshot' => [
                    'state' => 'overloaded',
                    'retry_after_seconds' => 5,
                ],
            ]);
        $this->app->instance(ChatbotLoadManagerService::class, $loadManager);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/chatbot/v2/send-message', [
                'message' => 'I need help with my payment status',
                'conversation_id' => 'overload-check',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.source', 'load_shed_busy')
            ->assertJsonPath('meta.role', 'client')
            ->assertJsonPath('meta.retry_after_seconds', 5)
            ->assertJsonPath('meta.load_state', 'overloaded');
    }

    public function test_public_status_endpoint_hides_internal_provider_details(): void
    {
        $this->mock(UnifiedChatbotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getHealthStatus')
                ->once()
                ->andReturn([
                    'llm' => [
                        'gemini' => true,
                        'github_gpt5' => true,
                        'available_provider' => 'gemini',
                    ],
                    'embeddings' => true,
                    'knowledge_base_indexed' => 123,
                ]);
        });

        $loadManager = \Mockery::mock(ChatbotLoadManagerService::class);
        $loadManager->shouldReceive('publicStatus')
            ->once()
            ->andReturn([
                'status' => 'operational',
                'load' => 'normal',
                'retry_after_seconds' => 5,
            ]);
        $this->app->instance(ChatbotLoadManagerService::class, $loadManager);

        $response = $this->getJson('/api/chatbot/status');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'operational')
            ->assertJsonPath('data.chatbot', 'operational')
            ->assertJsonPath('data.load', 'normal')
            ->assertJsonMissingPath('services')
            ->assertJsonMissingPath('data.llm');
    }
}