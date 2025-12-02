<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\TimeSlotCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentLimitTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $settings;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => bcrypt('password')
        ]);

        // Create appointment settings
        $this->settings = AppointmentSettings::create([
            'daily_booking_limit_per_user' => 2,
            'is_active' => true,
            'description' => 'Test settings'
        ]);

        // Create time slot capacities
        $this->createTimeSlots();
    }

    protected function createTimeSlots()
    {
        $slots = [
            ['08:00', '08:30', 3],
            ['08:30', '09:00', 3],
            ['09:00', '09:30', 3],
            ['09:30', '10:00', 3],
            ['10:00', '10:30', 3],
            ['10:30', '11:00', 3],
            ['11:00', '11:30', 3],
            ['11:30', '12:00', 3],
            ['13:00', '13:30', 3],
            ['13:30', '14:00', 3],
            ['14:00', '14:30', 3],
            ['14:30', '15:00', 3],
            ['15:00', '15:30', 3],
            ['15:30', '16:00', 3],
            ['16:00', '16:30', 3],
            ['16:30', '17:00', 3],
        ];

        foreach ($slots as [$start, $end, $capacity]) {
            TimeSlotCapacity::create([
                'start_time' => $start,
                'end_time' => $end,
                'day_of_week' => null,
                'max_appointments_per_slot' => $capacity,
                'is_active' => true
            ]);
        }
    }

    /** @test */
    public function user_can_book_within_daily_limit()
    {
        $today = now()->format('Y-m-d');
        
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'notes' => 'Test appointment'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('appointments', [
            'user_id' => $this->user->id,
            'appointment_date' => $today,
            'appointment_time' => '09:00'
        ]);
    }

    /** @test */
    public function user_can_book_second_appointment_on_same_day()
    {
        $today = now()->format('Y-m-d');
        
        // First appointment
        $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'notes' => 'First'
        ]);

        // Second appointment
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '10:00',
            'notes' => 'Second'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(2, Appointment::where('user_id', $this->user->id)
            ->where('appointment_date', $today)
            ->where('status', '!=', 'cancelled')
            ->count());
    }

    /** @test */
    public function user_cannot_exceed_daily_booking_limit()
    {
        $today = now()->format('Y-m-d');
        
        // Book two appointments (at limit)
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->user)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => (9 + $i) . ':00',
                'notes' => "Appointment " . ($i + 1)
            ]);
        }

        // Try to book third (should fail)
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '11:00',
            'notes' => 'Third'
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('daily booking limit', strtolower($response->json('message')));
        $this->assertEquals(422, $response->status());
    }

    /** @test */
    public function user_limit_is_per_day()
    {
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');
        
        // Book 2 for today
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->user)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => (9 + $i) . ':00',
                'notes' => "Today " . ($i + 1)
            ]);
        }

        // Should be able to book 2 for tomorrow
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $tomorrow,
            'appointment_time' => '09:00',
            'notes' => 'Tomorrow'
        ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function cancelled_appointments_dont_count_toward_limit()
    {
        $today = now()->format('Y-m-d');
        
        // Book 2 appointments
        $apt1 = Appointment::create([
            'user_id' => $this->user->id,
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        $apt2 = Appointment::create([
            'user_id' => $this->user->id,
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '10:00',
            'status' => 'pending'
        ]);

        // Cancel first one
        $apt1->status = 'cancelled';
        $apt1->save();

        // Should be able to book another
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '11:00',
            'notes' => 'Third'
        ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function time_slot_cannot_exceed_capacity()
    {
        $today = now()->format('Y-m-d');
        
        // Create 3 appointments at 09:00 (capacity is 3)
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            Appointment::create([
                'user_id' => $user->id,
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => '09:00',
                'status' => 'pending'
            ]);
        }

        // Try to book 4th at same time (should fail)
        $response = $this->actingAs($this->user)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'notes' => 'Should fail'
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('capacity', strtolower($response->json('message')));
        $this->assertEquals(422, $response->status());
    }

    /** @test */
    public function get_user_limit_endpoint_returns_correct_info()
    {
        $today = now()->format('Y-m-d');
        
        // Book one appointment
        Appointment::create([
            'user_id' => $this->user->id,
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/appointment-settings/user-limit/{$this->user->id}/$today");

        $response->assertSuccessful();
        $data = $response->json('data');
        $this->assertEquals(2, $data['limit']);
        $this->assertEquals(1, $data['used']);
        $this->assertEquals(1, $data['remaining']);
        $this->assertFalse($data['has_reached_limit']);
    }

    /** @test */
    public function get_user_limit_shows_reached_when_at_limit()
    {
        $today = now()->format('Y-m-d');
        
        // Book two appointments (at limit)
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->user->id,
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => (9 + $i) . ':00',
                'status' => 'pending'
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson("/api/appointment-settings/user-limit/{$this->user->id}/$today");

        $response->assertSuccessful();
        $data = $response->json('data');
        $this->assertEquals(2, $data['limit']);
        $this->assertEquals(2, $data['used']);
        $this->assertEquals(0, $data['remaining']);
        $this->assertTrue($data['has_reached_limit']);
    }

    /** @test */
    public function apply_all_time_slot_capacities()
    {
        // Delete existing capacities
        TimeSlotCapacity::truncate();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/slot-capacities/apply-all', [
                'max_appointments_per_slot' => 5
            ]);

        $response->assertSuccessful();
        $data = $response->json('data');
        $this->assertEquals(16, $data['total']);

        // Verify all slots were created with correct capacity
        $slots = TimeSlotCapacity::all();
        $this->assertEquals(16, $slots->count());
        foreach ($slots as $slot) {
            $this->assertEquals(5, $slot->max_appointments_per_slot);
        }
    }

    /** @test */
    public function limit_is_disabled_when_inactive()
    {
        $this->settings->is_active = false;
        $this->settings->save();

        $today = now()->format('Y-m-d');
        
        // Should be able to book unlimited
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->user)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => (9 + ($i % 8)) . ':00',
                'notes' => "Appointment " . ($i + 1)
            ]);

            $this->assertTrue($response->json('success'));
        }
    }

    /** @test */
    public function limit_updates_when_setting_changes()
    {
        $today = now()->format('Y-m-d');
        
        // Book with limit of 2
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->user)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $today,
                'appointment_time' => (9 + $i) . ':00'
            ]);
        }

        // Verify limit reached
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/appointment-settings/user-limit/{$this->user->id}/$today");
        $this->assertTrue($response1->json('data.has_reached_limit'));

        // Increase limit to 3
        $this->settings->daily_booking_limit_per_user = 3;
        $this->settings->save();

        // Verify limit not reached anymore
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/appointment-settings/user-limit/{$this->user->id}/$today");
        $this->assertFalse($response2->json('data.has_reached_limit'));
        $this->assertEquals(1, $response2->json('data.remaining'));
    }
}
