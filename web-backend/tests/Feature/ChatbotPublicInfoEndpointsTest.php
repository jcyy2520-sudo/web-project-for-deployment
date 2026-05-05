<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdvancedContentModerationService;
use App\Services\ChatbotFeedbackService;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotRoleAwarenessService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicKnowledgeFeedService;
use App\Services\DynamicSystemPromptService;
use App\Services\LLMService;
use App\Services\StreamingLLMService;
use App\Services\SystemInfoProvider;
use App\Services\UnifiedChatbotService;
use App\Services\VectorEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatbotPublicInfoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Cache::flush();
    }

    #[DataProvider('sendMessageEndpointCases')]
    public function test_send_message_endpoints_answer_public_system_info_without_private_leaks(string $path, string $role): void
    {
        [$user, $userId] = $this->createUserContext($role);
        $this->bindPublicInfoDependencies($role, $userId, false);

        $request = $user
            ? $this->actingAs($user, 'sanctum')
            : $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $response = $request
            ->withHeader('X-Session-ID', 'send-public-info-' . md5($path . '-' . $role))
            ->postJson($path, [
                'message' => 'What is the system about?',
                'conversation_id' => 'send_public_info_' . md5($path . '_' . $role),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.source', 'public_system_info_fast_path')
            ->assertJsonPath('meta.role', $role);

        $aiResponse = (string) $response->json('ai_response');

        $this->assertStringContainsString('Appointment Management & Legal Services System', $aiResponse, "Failed endpoint: {$path} role: {$role}");
        $this->assertStringContainsString('IT Student Developer', $aiResponse, "Failed endpoint: {$path} role: {$role}");
        $this->assertStringNotContainsString('999', $aiResponse, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('555', $aiResponse, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('total_users', $aiResponse, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('total_appointments', $aiResponse, "Private metrics leaked on {$path} for {$role}");
    }

    #[DataProvider('streamEndpointCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_stream_endpoints_answer_public_system_info_without_private_leaks(string $path, string $role): void
    {
        [$user, $userId] = $this->createUserContext($role);
        $this->bindPublicInfoDependencies($role, $userId, true);

        $request = $user
            ? $this->actingAs($user, 'sanctum')
            : $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $response = $request
            ->withHeaders([
                'Accept' => 'text/event-stream',
                'X-Session-ID' => 'stream-public-info-' . md5($path . '-' . $role),
            ])
            ->post($path, [
                'message' => 'What is the system about?',
                'conversation_id' => 'stream_public_info_' . md5($path . '_' . $role),
            ]);

        $response->assertOk();

        ob_start();
        ob_start();
        $response->baseResponse->sendContent();
        $innerBuffer = (string) ob_get_clean();
        $outerBuffer = (string) ob_get_clean();
        $streamedContent = $outerBuffer . $innerBuffer;

        $this->assertStringContainsString('event: complete', $streamedContent, "Missing complete event on {$path} for {$role}");
        $this->assertStringContainsString('public_system_info_fast_path', $streamedContent, "Missing public fast path on {$path} for {$role}");
        $this->assertStringContainsString('Appointment Management & Legal Services System', $streamedContent, "Missing system info on {$path} for {$role}");
        $this->assertStringContainsString('IT Student Developer', $streamedContent, "Missing developer info on {$path} for {$role}");
        $this->assertStringContainsString('"role":"' . $role . '"', $streamedContent, "Missing role metadata on {$path} for {$role}");
        $this->assertStringNotContainsString('999', $streamedContent, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('555', $streamedContent, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('total_users', $streamedContent, "Private metrics leaked on {$path} for {$role}");
        $this->assertStringNotContainsString('total_appointments', $streamedContent, "Private metrics leaked on {$path} for {$role}");
    }

    public static function sendMessageEndpointCases(): array
    {
        return [
            'v2 guest send' => ['/api/chatbot/v2/send-message', 'guest'],
            'v2 client send' => ['/api/chatbot/v2/send-message', 'client'],
            'v2 staff send' => ['/api/chatbot/v2/send-message', 'staff'],
            'v2 cashier send' => ['/api/chatbot/v2/send-message', 'cashier'],
            'v2 admin send' => ['/api/chatbot/v2/send-message', 'admin'],
            'legacy guest send' => ['/api/chatbot/send-message', 'guest'],
            'legacy client send' => ['/api/chatbot/send-message', 'client'],
            'legacy staff send' => ['/api/chatbot/send-message', 'staff'],
            'legacy cashier send' => ['/api/chatbot/send-message', 'cashier'],
            'legacy admin send' => ['/api/chatbot/send-message', 'admin'],
        ];
    }

    public static function streamEndpointCases(): array
    {
        return [
            'v2 guest stream' => ['/api/chatbot/v2/stream', 'guest'],
            'v2 client stream' => ['/api/chatbot/v2/stream', 'client'],
            'v2 staff stream' => ['/api/chatbot/v2/stream', 'staff'],
            'v2 cashier stream' => ['/api/chatbot/v2/stream', 'cashier'],
            'v2 admin stream' => ['/api/chatbot/v2/stream', 'admin'],
            'legacy guest stream' => ['/api/chatbot/stream', 'guest'],
            'legacy client stream' => ['/api/chatbot/stream', 'client'],
            'legacy staff stream' => ['/api/chatbot/stream', 'staff'],
            'legacy cashier stream' => ['/api/chatbot/stream', 'cashier'],
            'legacy admin stream' => ['/api/chatbot/stream', 'admin'],
        ];
    }

    private function createUserContext(string $role): array
    {
        if ($role === 'guest') {
            return [null, null];
        }

        $user = User::factory()->create();

        if ($role === 'cashier') {
            $user->role = 'client';
            $user->save();

            Role::firstOrCreate(['name' => 'cashier']);
            $user->assignRole('cashier');

            return [$user, $user->id];
        }

        $user->role = $role;
        $user->save();

        return [$user, $user->id];
    }

    private function bindPublicInfoDependencies(string $role, ?int $expectedUserId, bool $withStreaming): void
    {
        $displayName = ucfirst($role);
        $systemInfoPayload = $this->publicSystemInfoPayload();

        $feedbackService = Mockery::mock(ChatbotFeedbackService::class);
        $feedbackService->shouldReceive('logInteraction')
            ->once()
            ->andReturn(5001);
        $this->app->instance(ChatbotFeedbackService::class, $feedbackService);

        $dataService = Mockery::mock(ChatbotRealTimeDataService::class);
        $this->app->instance(ChatbotRealTimeDataService::class, $dataService);

        $roleAwarenessService = Mockery::mock(ChatbotRoleAwarenessService::class);
        $roleAwarenessService->shouldReceive('detectUserRole')
            ->once()
            ->with($expectedUserId)
            ->andReturn([
                'primary_role' => $role,
                'display_name' => $displayName,
                'pending_items' => [],
            ]);
        $roleAwarenessService->shouldReceive('getContextualSuggestions')
            ->once()
            ->withArgs(function ($resolvedRole, $userMessage, $response) use ($role) {
                return $resolvedRole === $role
                    && is_string($userMessage)
                    && is_string($response);
            })
            ->andReturn([]);
        $this->app->instance(ChatbotRoleAwarenessService::class, $roleAwarenessService);

        $securityService = Mockery::mock(ChatbotSecurityService::class);
        $securityService->shouldReceive('runSecurityChecks')
            ->once()
            ->andReturn(['passed' => true]);
        if ($withStreaming) {
            $securityService->shouldNotReceive('createRoleAssertion');
        } else {
            $securityService->shouldReceive('createRoleAssertion')
                ->once()
                ->andReturn('role-assertion');
        }
        $this->app->instance(ChatbotSecurityService::class, $securityService);

        $moderationService = Mockery::mock(AdvancedContentModerationService::class);
        $moderationService->shouldReceive('checkContentSafety')
            ->once()
            ->with('What is the system about?')
            ->andReturn([
                'safe' => true,
                'reasons' => [],
                'violation_type' => null,
            ]);
        $this->app->instance(AdvancedContentModerationService::class, $moderationService);

        $systemInfoProvider = Mockery::mock(SystemInfoProvider::class);
        $systemInfoProvider->shouldReceive('getSystemInfo')
            ->once()
            ->with('standard')
            ->andReturn($systemInfoPayload);
        $this->app->instance(SystemInfoProvider::class, $systemInfoProvider);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldNotReceive('generateResponse');

        $streamingService = null;
        if ($withStreaming) {
            $streamingService = Mockery::mock(StreamingLLMService::class);
            $streamingService->shouldNotReceive('streamResponse');
        }

        $chatbotService = new UnifiedChatbotService(
            $llmService,
            Mockery::mock(VectorEmbeddingService::class),
            $dataService,
            $feedbackService,
            Mockery::mock(DynamicSystemPromptService::class),
            Mockery::mock(DynamicKnowledgeFeedService::class),
            $securityService,
            $streamingService
        );

        $this->app->instance(UnifiedChatbotService::class, $chatbotService);
    }

    private function publicSystemInfoPayload(): array
    {
        return [
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
            'status' => [
                'current_metrics' => [
                    'total_users' => 999,
                    'total_appointments' => 555,
                ],
            ],
        ];
    }
}