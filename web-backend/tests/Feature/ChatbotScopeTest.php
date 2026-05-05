<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatbotRealTimeDataService;
use App\Services\ChatbotSecurityService;
use App\Services\DynamicSystemPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_contains_scope_limitations(): void
    {
        // 1. Create a guest user context
        $userContext = [
            'role' => 'guest',
            'is_authenticated' => false,
            'user' => null,
        ];

        // 2. Instantiate the DynamicSystemPromptService
        $dataService = $this->app->make(ChatbotRealTimeDataService::class);
        $securityService = $this->app->make(ChatbotSecurityService::class);
        $promptService = new DynamicSystemPromptService($dataService, $securityService);

        // 3. Build a system prompt
        $prompt = $promptService->build($userContext);

        // 4. Assert that the prompt contains the new scope rules
        $this->assertStringContainsString('SCOPE & CAPABILITIES', $prompt);
        $this->assertStringContainsString('SCOPE LIMITATION', $prompt);
        $this->assertStringContainsString('STRICT REFUSAL', $prompt);
        $this->assertStringContainsString('AMBIGUITY', $prompt);
        $this->assertStringContainsString('FOCUS', $prompt);
        $this->assertStringContainsString('NO HALLUCINATION', $prompt);

        // 5. Assert specific refusal messages
        $this->assertStringContainsString((string) config('chatbot_unified.safety.refusal_message'), $prompt);
        $this->assertStringContainsString("I don’t have enough information about that within the system.", $prompt);
    }
}
