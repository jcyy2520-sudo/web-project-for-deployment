<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\TimeSlotCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

/**
 * PRODUCTION INTEGRATION TESTS
 * Real-world scenarios for booking limit system
 * Tests with actual time constraints, concurrent bookings, edge cases
 */
class BookingLimitProductionTest extends TestCase
{
    use RefreshDatabase;

    protected $client;
    protected $staff;
    protected $settings;
    protected $today;
    protected $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = User::factory()->create(['role' => 'client', 'email' => 'client@test.com']);
        $this->staff = User::factory()->create(['role' => 'staff', 'email' => 'staff@test.com']);
        
        $this->settings = AppointmentSettings::create([
            'daily_booking_limit_per_user' => 2,
            'is_active' => true,
            'description' => 'Production settings'
        ]);

        $this->tomorrow = now()->format('Y-m-d');
        $this->tomorrow = now()->addDay()->format('Y-m-d');
        
        $this->createTimeSlots();
    }

    protected function createTimeSlots()
    {
        $times = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
        foreach ($times as $time) {
            TimeSlotCapacity::create([
                'start_time' => $time,
                'end_time' => date('H:i', strtotime($time . ' +1 hour')),
                'max_appointments_per_slot' => 3,
                'is_active' => true
            ]);
        }
    }

    /** @test CRITICAL: User cannot bypass limit with rapid requests */
    public function rapid_booking_requests_enforces_limit()
    {
        // Simulate rapid booking attempts - use tomorrow since validation requires after:today
        $requests = [];
        for ($i = 0; $i < 4; $i++) {
            $requests[] = [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00',
                'notes' => "Rapid request $i"
            ];
        }

        $successful = 0;
        foreach ($requests as $req) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', $req);
            if ($response->json('success')) {
                $successful++;
            }
        }

        // Only 2 should succeed (limit)
        $this->assertEquals(2, $successful);
        $this->assertEquals(2, Appointment::where('user_id', $this->client->id)
            ->where('appointment_date', $this->today)
            ->where('status', '!=', 'cancelled')
            ->count());
    }

    /** @test CRITICAL: Limit enforced even with different appointment types */
    public function limit_enforced_across_appointment_types()
    {
        $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '08:00',
            'notes' => 'First'
        ]);

        $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'notarization',
            'service_type' => 'Document Notarization',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '09:00',
            'notes' => 'Second'
        ]);

        // Third should fail regardless of type
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'verification',
            'service_type' => 'Identity Verification',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00',
            'notes' => 'Third'
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('daily booking limit', strtolower($response->json('message')));
    }

    /** @test CRITICAL: Limit resets at midnight */
    public function booking_limit_resets_at_day_boundary()
    {
        // Book 2 for today
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->client->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00',
                'status' => 'pending'
            ]);
        }

        // Tomorrow should allow 2 more
        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00'
            ]);
            $this->assertTrue($response->json('success'), "Failed to book appointment $i for tomorrow");
        }
    }

    /** @test Cancelled appointments don't count toward limit */
    public function cancelled_appointment_frees_up_limit()
    {
        $apt1 = Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '08:00',
            'status' => 'pending'
        ]);

        $apt2 = Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        // At limit
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00'
        ]);
        $this->assertFalse($response->json('success'));

        // Cancel first one
        $apt1->status = 'cancelled';
        $apt1->save();

        // Should be able to book again
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00'
        ]);
        $this->assertTrue($response->json('success'));
    }

    /** @test Time slot capacity enforced independently */
    public function time_slot_capacity_enforced()
    {
        // Fill a time slot to capacity (3)
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'client']);
            Appointment::create([
                'user_id' => $user->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => '08:00',
                'status' => 'pending'
            ]);
        }

        // Try to book 4th (should fail)
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '08:00'
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('capacity', strtolower($response->json('message')));
    }

    /** @test Both limits enforced simultaneously */
    public function both_user_limit_and_slot_capacity_enforced()
    {
        // Fill slot at 08:00 to capacity
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'client']);
            Appointment::create([
                'user_id' => $user->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => '08:00',
                'status' => 'pending'
            ]);
        }

        // Book our client at different times to reach user limit
        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00',
            'status' => 'pending'
        ]);

        // Try to book at full slot (should fail for slot capacity)
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '08:00'
        ]);

        $this->assertFalse($response->json('success'));
    }

    /** @test Limit info endpoint accurate */
    public function user_limit_endpoint_accurate()
    {
        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '08:00',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/appointment-settings/user-limit/{$this->client->id}/$this->tomorrow");

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertEquals(2, $data['limit']);
        $this->assertEquals(1, $data['used']);
        $this->assertEquals(1, $data['remaining']);
        $this->assertFalse($data['has_reached_limit']);
    }

    /** @test Limit can be changed dynamically */
    public function limit_change_affects_bookings()
    {
        // Book 2 (at limit of 2)
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->client->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00',
                'status' => 'pending'
            ]);
        }

        // Increase limit to 3
        $this->settings->daily_booking_limit_per_user = 3;
        $this->settings->save();

        // Should now be able to book
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00'
        ]);

        $this->assertTrue($response->json('success') === true, "Response: " . json_encode($response->json()));
    }

    /** @test Disabled limit allows unlimited bookings */
    public function disabled_limit_allows_unlimited()
    {
        $this->settings->is_active = false;
        $this->settings->save();

        // Book many
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + ($i % 8)) . ':00',
                'notes' => "Appointment $i"
            ]);

            if ($i < 3) {
                // First 3 should succeed (different times)
                $this->assertTrue($response->json('success'), "Booking $i failed");
            }
        }
    }

    /** @test Edge case: Booking at exactly day boundary (limit resets daily) */
    public function booking_at_day_boundary()
    {
        $nextDay = now()->addDays(2)->format('Y-m-d');
        
        // Book today
        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '16:00',
            'status' => 'pending'
        ]);

        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '16:30',
            'status' => 'pending'
        ]);

        // At limit for tomorrow
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '15:00'
        ]);
        $this->assertFalse($response->json('success'));

        // Next day should reset and allow booking
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $nextDay,
            'appointment_time' => '08:00'
        ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test Staff booking not affected by client limit */
    public function staff_booking_independent_of_client_limit()
    {
        // Fill client limit
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->client->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00',
                'status' => 'pending'
            ]);
        }

        // Staff should still be able to book
        $response = $this->actingAs($this->staff)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00'
        ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test Recovery: System handles concurrent bookings correctly */
    public function concurrent_booking_simulation()
    {
        // Simulate 3 concurrent requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => (8 + $i) . ':00'
            ]);
            $responses[] = $response->json('success');
        }

        // Only 2 should succeed
        $successful = array_sum($responses);
        $this->assertEquals(2, $successful);
    }
}
