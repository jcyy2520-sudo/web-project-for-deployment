<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\ActionPermissionService;
use App\Services\AgentToolRegistry;
use App\Services\ChatbotRealTimeDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AgentToolRegistryBookingExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_booking_still_creates_an_appointment(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create([
            'phone' => '09123456789',
            'address' => 'Test Address',
            'profile_completed' => true,
        ]);

        $service = Service::create([
            'name' => 'Notarization Service',
            'description' => 'Official document notarization and certification',
            'price' => 500,
            'duration' => 30,
            'is_active' => true,
        ]);

        $bookingDate = Carbon::now()->addWeeks(2);
        if ($bookingDate->isWeekend()) {
            $bookingDate = $bookingDate->next(Carbon::MONDAY);
        }

        $permissionService = Mockery::mock(ActionPermissionService::class);
        $permissionService->shouldReceive('canUseAgentTool')
            ->once()
            ->with('client', 'book_appointment')
            ->andReturn(true);

        $registry = new AgentToolRegistry(
            Mockery::mock(ChatbotRealTimeDataService::class),
            $permissionService
        );

        $result = $registry->executeTool(
            'book_appointment',
            [
                'service_id' => $service->name,
                'date' => $bookingDate->format('Y-m-d'),
                'time' => '09:00',
            ],
            $user->id,
            'client'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('pending', $result['data']['status']);
        $this->assertStringContainsString('Appointment booked successfully!', $result['message']);
        $this->assertStringNotContainsString('appointment ID', $result['message']);
        $this->assertStringNotContainsString('#' . $result['data']['appointment_id'], $result['message']);
        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'appointment_date' => $bookingDate->format('Y-m-d'),
            'appointment_time' => '09:00',
            'status' => 'pending',
        ]);

        $appointment = Appointment::first();
        $this->assertNotNull($appointment);
        $this->assertSame([$service->id], $appointment->services()->pluck('services.id')->all());
    }
}