<?php

namespace Tests\Feature;

use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\UnifiedChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatbotGuestLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Cache::flush();

        $this->instance(ChatbotFeedbackService::class, \Mockery::mock(ChatbotFeedbackService::class));
        $this->instance(ChatbotRealTimeDataService::class, \Mockery::mock(ChatbotRealTimeDataService::class));
    }

    public function test_guest_limit_resets_after_cooldown_expiry(): void
    {
        $sessionId = 'guest-limit-reset-session';
        $guestLimitKey = $this->guestLimitKey($sessionId);

        Cache::put($guestLimitKey, [
            'count' => 5,
            'cooldown_until' => now()->subMinute()->timestamp,
        ], now()->addHours(6));

        $this->mockChatbotServices(null, 'guest', 'Guest');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Session-ID', $sessionId)
            ->postJson('/api/chatbot/v2/send-message', [
                'message' => 'Hello after cooldown',
                'conversation_id' => 'guest-reset-conversation',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.role', 'guest')
            ->assertJsonPath('meta.guest_limit.count', 1)
            ->assertJsonPath('meta.guest_limit.remaining', 4)
            ->assertJsonPath('meta.guest_limit.is_limited', false)
            ->assertJsonPath('meta.guest_limit.cooldown_until', null);

        $this->assertSame([
            'count' => 1,
            'cooldown_until' => null,
        ], Cache::get($guestLimitKey));
    }

    public function test_authenticated_users_are_not_affected_by_guest_limit_cache(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'client']);
        $sessionId = 'authenticated-role-session';
        $guestLimitKey = $this->guestLimitKey($sessionId);
        $existingGuestState = [
            'count' => 5,
            'cooldown_until' => now()->addHours(5)->timestamp,
        ];

        Cache::put($guestLimitKey, $existingGuestState, now()->addHours(6));

        $this->mockChatbotServices($user->id, 'client', 'Client');

        $response = $this
            ->actingAs($user, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Session-ID', $sessionId)
            ->postJson('/api/chatbot/v2/send-message', [
                'message' => 'Authenticated message',
                'conversation_id' => 'authenticated-conversation',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.role', 'client');

        $this->assertNull($response->json('meta.guest_limit'));
        $this->assertSame($existingGuestState, Cache::get($guestLimitKey));
    }

    private function guestLimitKey(string $sessionId, string $ipAddress = '127.0.0.1'): string
    {
        return 'guest_chat_limit_' . md5($ipAddress . ':' . $sessionId);
    }

    private function mockChatbotServices(?int $expectedUserId, string $role, string $displayName): void
    {
        $this->mock(UnifiedChatbotService::class, function (MockInterface $mock) use ($expectedUserId, $role): void {
            $mock->shouldReceive('processMessage')
                ->once()
                ->withArgs(function ($message, $userId, $conversationId, $context) use ($expectedUserId) {
                    return is_string($message)
                        && $userId === $expectedUserId
                        && is_string($conversationId)
                        && is_array($context);
                })
                ->andReturn([
                    'response' => 'Mock chatbot response',
                    'source' => 'llm',
                    'meta' => [
                        'role' => $role,
                        'detected_language' => 'en',
                    ],
                ]);
        });

        $this->mock(ChatbotRoleAwarenessService::class, function (MockInterface $mock) use ($expectedUserId, $role, $displayName): void {
            $mock->shouldReceive('detectUserRole')
                ->once()
                ->with($expectedUserId)
                ->andReturn([
                    'primary_role' => $role,
                    'display_name' => $displayName,
                    'pending_items' => [],
                ]);

            $mock->shouldReceive('getContextualSuggestions')
                ->once()
                ->withArgs(function ($resolvedRole, $userMessage, $response) use ($role) {
                    return $resolvedRole === $role
                        && is_string($userMessage)
                        && is_string($response);
                })
                ->andReturn([]);
        });
    }
}