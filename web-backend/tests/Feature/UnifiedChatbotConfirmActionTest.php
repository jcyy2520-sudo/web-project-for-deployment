<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AgentReasoningService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\UnifiedChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class UnifiedChatbotConfirmActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_action_endpoint_executes_agent_pending_confirmation_with_key(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $confirmationKey = 'agent_confirm_user_' . $user->id . '_pending';

        Cache::put($confirmationKey, [
            'tool' => 'book_appointment',
            'arguments' => [
                'service_id' => 'Affidavit',
                'date' => '2026-05-09',
                'time' => '08:00',
            ],
            'created_at' => now()->toIso8601String(),
        ], 300);

        $this->app->instance(UnifiedChatbotService::class, Mockery::mock(UnifiedChatbotService::class));
        $this->app->instance(ChatbotFeedbackService::class, Mockery::mock(ChatbotFeedbackService::class));
        $this->app->instance(ChatbotRealTimeDataService::class, Mockery::mock(ChatbotRealTimeDataService::class));

        $roleService = Mockery::mock(ChatbotRoleAwarenessService::class);
        $roleService->shouldReceive('detectUserRole')
            ->once()
            ->with($user->id)
            ->andReturn([
                'primary_role' => 'client',
                'display_name' => 'Client',
            ]);
        $this->app->instance(ChatbotRoleAwarenessService::class, $roleService);

        $agentReasoning = Mockery::mock(AgentReasoningService::class);
        $agentReasoning->shouldReceive('reason')
            ->once()
            ->with(
                'yes',
                '',
                [],
                $user->id,
                'client',
                Mockery::on(function ($pending): bool {
                    return ($pending['tool'] ?? null) === 'book_appointment'
                        && ($pending['arguments']['service_id'] ?? null) === 'Affidavit'
                        && ($pending['arguments']['time'] ?? null) === '08:00';
                }),
                'user_' . $user->id
            )
            ->andReturn([
                'response' => 'Appointment booked successfully.',
                'tool_calls' => [],
                'action_buttons' => [],
                'confirmed_action' => true,
            ]);
        $this->app->instance(AgentReasoningService::class, $agentReasoning);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Session-ID', 'session-confirm-test')
            ->postJson('/api/chatbot/confirm-action', [
                'confirmation_key' => $confirmationKey,
                'decision' => 'confirm',
                'conversation_id' => 'chat-confirmation-test',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'conversation_id' => 'chat-confirmation-test',
                'user_message' => 'yes',
                'ai_response' => 'Appointment booked successfully.',
            ])
            ->assertJsonPath('meta.source', 'agent_confirmation')
            ->assertJsonPath('meta.role', 'client');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => 'chat-confirmation-test',
            'user_id' => $user->id,
            'message' => 'yes',
            'role' => 'user',
            'source' => 'user',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => 'chat-confirmation-test',
            'user_id' => $user->id,
            'message' => 'Appointment booked successfully.',
            'role' => 'assistant',
            'source' => 'agent_confirmation',
        ]);

        $this->assertFalse(Cache::has($confirmationKey));
    }
}