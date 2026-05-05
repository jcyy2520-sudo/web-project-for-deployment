<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\AgentReasoningService;
use App\Services\AgentToolRegistry;
use App\Services\ChatbotSecurityService;
use App\Services\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class AgentReasoningServiceSingleTurnBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_client_booking_requires_confirmation_before_execution(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('chatbot_booking_decisions')->andReturnSelf();

        $serviceRecord = Service::create([
            'name' => 'Notarization Service',
            'description' => 'Official document notarization and certification',
            'price' => 500,
            'duration' => 30,
            'is_active' => true,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $toolRegistry = Mockery::mock(AgentToolRegistry::class);
        $securityService = Mockery::mock(ChatbotSecurityService::class);

        $toolRegistry->shouldReceive('getNativeToolDefinitions')
            ->once()
            ->with('client')
            ->andReturn([['name' => 'book_appointment']]);
        $toolRegistry->shouldReceive('toolExists')->once()->with('book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('canRoleUseTool')->once()->with('client', 'book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('isDestructiveTool')->once()->with('book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('validateBookingSlot')
            ->once()
            ->with(Mockery::on(function ($args) {
                return ($args['service_id'] ?? null) === 'NOTARIZATION SERVICE'
                    && ($args['date'] ?? null) === '2026-05-04'
                    && ($args['time'] ?? null) === '09:00';
            }), 41)
            ->andReturn(['valid' => true, 'message' => 'Ready to book.']);
        $toolRegistry->shouldReceive('resolveServiceIdsPublic')
            ->once()
            ->with('NOTARIZATION SERVICE')
            ->andReturn([$serviceRecord->id]);
        $toolRegistry->shouldNotReceive('executeTool');

        $llmService->shouldReceive('generateResponse')
            ->once()
            ->andReturn([
                'success' => true,
                'response' => '',
                'tool_calls' => [[
                    'id' => 'toolu_123',
                    'name' => 'book_appointment',
                    'input' => [
                        'service_id' => 'NOTARIZATION SERVICE',
                        'date' => '2026-05-04',
                        'time' => '09:00',
                    ],
                ]],
                'provider' => 'anthropic',
                'model' => 'claude',
                'tokens_used' => 123,
            ]);

        $service = new AgentReasoningService($llmService, $toolRegistry, $securityService);

        $result = $service->reason(
            'BOOK NOTARIZATION SERVICE ON MAY 4 2026 AT 9 AM',
            'system prompt',
            [],
            41,
            'client'
        );

        $this->assertTrue($result['requires_confirmation'] ?? false);
        $this->assertNotEmpty($result['confirmation_key'] ?? null);
        $this->assertSame('book_appointment', $result['pending_tool'] ?? null);
        $this->assertSame([], $result['tool_calls']);
        $this->assertStringContainsString('Please confirm your appointment', $result['response']);
        $this->assertStringContainsString('Notarization Service', $result['response']);
        $this->assertStringNotContainsString('Appointment booked successfully', $result['response']);
        $this->assertStringNotContainsString('Appointment #', $result['response']);

        Log::shouldHaveReceived('info')
            ->withArgs(function (...$args) {
                $message = $args[0] ?? null;
                $context = $args[1] ?? [];

                return $message === 'AgentReasoning: Booking decision analyzed'
                    && ($context['decision'] ?? null) === 'confirm'
                    && ($context['reason_code'] ?? null) === 'complete_clear_request'
                    && ($context['message_signature'] ?? null) !== 'BOOK NOTARIZATION SERVICE ON MAY 4 2026 AT 9 AM'
                    && strlen($context['message_signature'] ?? '') === 16
                    && !array_key_exists('user_message', $context)
                    && ($context['normalized_booking_fields']['service'] ?? null) === 'NOTARIZATION SERVICE'
                    && ($context['normalized_booking_fields']['time'] ?? null) === '09:00';
            })
            ->once();
    }

    public function test_incomplete_booking_request_returns_clarification_instead_of_executing(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('chatbot_booking_decisions')->andReturnSelf();

        $llmService = Mockery::mock(LLMService::class);
        $toolRegistry = Mockery::mock(AgentToolRegistry::class);
        $securityService = Mockery::mock(ChatbotSecurityService::class);

        $toolRegistry->shouldReceive('getNativeToolDefinitions')
            ->once()
            ->with('client')
            ->andReturn([['name' => 'book_appointment']]);
        $toolRegistry->shouldReceive('toolExists')->once()->with('book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('canRoleUseTool')->once()->with('client', 'book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('isDestructiveTool')->once()->with('book_appointment')->andReturn(true);
        $toolRegistry->shouldReceive('validateBookingSlot')
            ->once()
            ->with(Mockery::type('array'), 41)
            ->andReturn(['valid' => false, 'error' => 'Service IDs, date, and time are required.']);
        $toolRegistry->shouldNotReceive('executeTool');

        $llmService->shouldReceive('generateResponse')
            ->twice()
            ->andReturn(
                [
                    'success' => true,
                    'response' => '',
                    'tool_calls' => [[
                        'id' => 'toolu_456',
                        'name' => 'book_appointment',
                        'input' => [
                            'service_id' => 'DOCUMENT SIGNING',
                            'date' => '2026-05-04',
                        ],
                    ]],
                    'provider' => 'anthropic',
                ],
                [
                    'success' => true,
                    'response' => 'Please confirm the exact time you want for the appointment so I can finish the booking.',
                    'tool_calls' => [],
                    'provider' => 'anthropic',
                ]
            );

        $service = new AgentReasoningService($llmService, $toolRegistry, $securityService);

        $result = $service->reason(
            'Book document signing on May 4, 2026',
            'system prompt',
            [],
            41,
            'client'
        );

        $this->assertArrayNotHasKey('requires_confirmation', $result);
        $this->assertSame([], $result['tool_calls']);
        $this->assertStringContainsString('exact time', $result['response']);

        Log::shouldHaveReceived('info')
            ->withArgs(function (...$args) {
                $message = $args[0] ?? null;
                $context = $args[1] ?? [];

                return $message === 'AgentReasoning: Booking decision analyzed'
                    && ($context['decision'] ?? null) === 'clarify'
                    && ($context['reason_code'] ?? null) === 'missing_required_fields'
                    && ($context['missing_fields'] ?? null) === ['time']
                    && ($context['normalized_booking_fields']['service'] ?? null) === 'DOCUMENT SIGNING'
                    && ($context['normalized_booking_fields']['date'] ?? null) === '2026-05-04';
            })
            ->once();
    }

    public function test_booking_success_response_hides_internal_appointment_number(): void
    {
        $llmService = Mockery::mock(LLMService::class);
        $toolRegistry = Mockery::mock(AgentToolRegistry::class);
        $securityService = Mockery::mock(ChatbotSecurityService::class);

        $service = new AgentReasoningService($llmService, $toolRegistry, $securityService);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatToolResultDirectly');
        $method->setAccessible(true);

        $response = $method->invoke($service, 'book_appointment', [
            'success' => true,
            'data' => [
                'appointment_id' => 329,
                'date_formatted' => 'Apr 30, 2026',
                'day' => 'Thursday',
                'time_formatted' => '10:00 AM',
                'services' => [
                    ['name' => 'Affidavit', 'price_formatted' => '₱500.00'],
                ],
                'total_price_formatted' => '₱500.00',
                'remaining_bookings_today' => 1,
                'daily_limit' => 2,
            ],
        ], []);

        $this->assertStringContainsString('Appointment booked successfully!', $response);
        $this->assertStringContainsString('Affidavit', $response);
        $this->assertStringNotContainsString('Appointment #', $response);
        $this->assertStringNotContainsString('329', $response);
    }

    public function test_guest_pending_confirmations_are_scoped_by_actor_key(): void
    {
        $service = new AgentReasoningService(
            Mockery::mock(LLMService::class),
            Mockery::mock(AgentToolRegistry::class),
            Mockery::mock(ChatbotSecurityService::class)
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('storePendingToolCall');
        $method->setAccessible(true);

        $alphaCacheKey = $method->invoke(
            $service,
            null,
            'book_appointment',
            ['date' => '2026-05-04'],
            'guest_session_alpha'
        );

        $betaCacheKey = $method->invoke(
            $service,
            null,
            'book_appointment',
            ['date' => '2026-05-05'],
            'guest_session_beta'
        );

        $this->assertNotSame($alphaCacheKey, $betaCacheKey);

        $alphaPending = AgentReasoningService::getPendingConfirmation('guest_session_alpha');
        $betaPending = AgentReasoningService::getPendingConfirmation('guest_session_beta');

        $this->assertSame('book_appointment', $alphaPending['tool']);
        $this->assertSame('2026-05-04', $alphaPending['arguments']['date']);
        $this->assertSame('book_appointment', $betaPending['tool']);
        $this->assertSame('2026-05-05', $betaPending['arguments']['date']);
        $this->assertNull(AgentReasoningService::getPendingConfirmation('guest_session_alpha'));
    }

    public function test_xml_like_tool_call_is_parsed_and_cleaned(): void
    {
        $llmService = Mockery::mock(LLMService::class);
        $toolRegistry = Mockery::mock(AgentToolRegistry::class);
        $securityService = Mockery::mock(ChatbotSecurityService::class);

        $toolRegistry->shouldReceive('toolExists')
            ->once()
            ->with('get_available_slots')
            ->andReturn(true);

        $service = new AgentReasoningService($llmService, $toolRegistry, $securityService);

        $reflection = new ReflectionClass($service);

        $parseMethod = $reflection->getMethod('parseToolCall');
        $parseMethod->setAccessible(true);

        $cleanMethod = $reflection->getMethod('cleanResponse');
        $cleanMethod->setAccessible(true);

        $response = "I will check the next available slots for you.\n\n<get_available_slots>\n<parameter=service_id>Document Review</parameter>\n<parameter=date>2026-05-04</parameter>\n<parameter=limit>5</parameter>\n</get_available_slots>";

        $parsed = $parseMethod->invoke($service, $response);
        $cleaned = $cleanMethod->invoke($service, $response);

        $this->assertSame('get_available_slots', $parsed['tool']);
        $this->assertSame('Document Review', $parsed['arguments']['service_id']);
        $this->assertSame('2026-05-04', $parsed['arguments']['date']);
        $this->assertSame('5', $parsed['arguments']['limit']);
        $this->assertSame('I will check the next available slots for you.', $cleaned);
        $this->assertStringNotContainsString('<get_available_slots>', $cleaned);
    }

    public function test_booking_clarification_instruction_requires_exact_service_list_from_tool(): void
    {
        $service = new AgentReasoningService(
            Mockery::mock(LLMService::class),
            Mockery::mock(AgentToolRegistry::class),
            Mockery::mock(ChatbotSecurityService::class)
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildBookingClarificationInstruction');
        $method->setAccessible(true);

        $instruction = $method->invoke($service, 'Service is required.');

        $this->assertStringContainsString('call get_available_services first', $instruction);
        $this->assertStringContainsString('present the exact returned service list', $instruction);
        $this->assertStringContainsString('instead of relying on memory or a partial summary', $instruction);
    }
}