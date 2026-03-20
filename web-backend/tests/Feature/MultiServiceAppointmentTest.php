<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\TimeSlotCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiServiceAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'profile_completed' => true,
            'role' => 'client'
        ]);
        
        AppointmentSettings::create([
            'daily_booking_limit_per_user' => 10,
            'is_active' => true,
        ]);

        TimeSlotCapacity::create([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'max_appointments_per_slot' => 5,
            'is_active' => true
        ]);
    }

    /** @test */
    public function user_can_book_multiple_services_in_one_appointment()
    {
        $service1 = Service::create(['name' => 'Service A', 'price' => 500, 'duration' => 30, 'is_active' => true]);
        $service2 = Service::create(['name' => 'Service B', 'price' => 1000, 'duration' => 60, 'is_active' => true]);

        $today = now()->addDays(1)->format('Y-m-d');
        if (now()->addDays(1)->isWeekend()) {
            $today = now()->addDays(3)->format('Y-m-d'); // Skip weekend
        }

        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_ids' => [$service1->id, $service2->id],
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'notes' => 'Multi-service booking'
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $appointment = Appointment::first()->refresh();
        $this->assertEquals(1500, $appointment->payment_amount);
        $this->assertEquals(1500, $appointment->original_price);
        
        $this->assertDatabaseHas('appointment_service', [
            'appointment_id' => $appointment->id,
            'service_id' => $service1->id,
            'price_at_booking' => 500
        ]);

        $this->assertDatabaseHas('appointment_service', [
            'appointment_id' => $appointment->id,
            'service_id' => $service2->id,
            'price_at_booking' => 1000
        ]);

        $appointment = Appointment::with('services')->find($appointment->id);
        $this->assertCount(2, $appointment->services);
        
        $this->assertDatabaseHas('appointment_service', [
            'appointment_id' => $appointment->id,
            'service_id' => $service1->id,
            'price_at_booking' => 500
        ]);

        $this->assertDatabaseHas('appointment_service', [
            'appointment_id' => $appointment->id,
            'service_id' => $service2->id,
            'price_at_booking' => 1000
        ]);
    }
}
