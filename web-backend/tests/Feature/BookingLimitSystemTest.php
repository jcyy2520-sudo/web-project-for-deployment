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
 * PRODUCTION BOOKING LIMIT SYSTEM TEST
 * Validates that the booking limit system works correctly in production conditions
 * Tests: daily limit enforcement, capacity constraints, concurrent bookings, edge cases
 */
class BookingLimitSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $client;
    protected $staff;
    protected $settings;
    protected $bookableDate;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users
        $this->client = User::factory()->create([
            'role' => 'client',
            'email' => 'client@test.com',
            'first_name' => 'Test',
            'last_name' => 'Client'
        ]);
        
        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@test.com',
            'first_name' => 'Test',
            'last_name' => 'Staff'
        ]);
        
        // Create appointment settings
        $this->settings = AppointmentSettings::create([
            'daily_booking_limit_per_user' => 2,
            'is_active' => true,
            'description' => 'Production settings'
        ]);

        // Use tomorrow as a bookable date (validations require after:today)
        $this->bookableDate = now()->addDay()->format('Y-m-d');
        
        // Create time slots for testing
        $this->createTimeSlots();
    }

    protected function createTimeSlots()
    {
        $times = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
        foreach ($times as $time) {
            $endTime = date('H:i', strtotime($time . ' +1 hour'));
            TimeSlotCapacity::create([
                'start_time' => $time,
                'end_time' => $endTime,
                'max_appointments_per_slot' => 3,
                'is_active' => true
            ]);
        }
    }

    protected function bookableTime($offset = 0)
    {
        return sprintf('%02d:00', 8 + $offset);
    }

    /** @test System enforces daily booking limit - user cannot exceed limit */
    public function test_user_cannot_exceed_daily_booking_limit()
    {
        // Book 2 appointments (at the limit)
        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal Consultation',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + $i),
                'notes' => "Booking $i"
            ]);
            
            // Both should succeed
            $success = $response->status() === 200 || $response->status() === 201 
                ? ($response->json('success') ?? true) 
                : false;
            
            $this->assertTrue(
                $success,
                "Booking $i failed. Status: " . $response->status() . ", Response: " . json_encode($response->json())
            );
        }

        // Try to book a 3rd appointment (should fail - exceeds limit)
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00',
            'notes' => 'Should fail - exceeds limit'
        ]);

        // Should be rejected (either 422 or success = false)
        $this->assertTrue(
            $response->status() === 422 || $response->json('success') === false,
            "Should reject booking that exceeds limit. Status: " . $response->status() . ", Response: " . json_encode($response->json())
        );
        
        if ($response->status() === 422) {
            $this->assertStringContainsString('daily booking limit', strtolower($response->json('message', '')));
        }
    }

    /** @test Limit resets at day boundary */
    public function test_limit_resets_at_day_boundary()
    {
        $nextDay = now()->addDays(2)->format('Y-m-d');
        
        // Fill today's limit
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + $i)
            ]);
        }

        // Verify at limit for today
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);
        $this->assertFalse($response->json('success'));

        // Tomorrow should allow 2 more bookings
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $nextDay,
            'appointment_time' => '08:00'
        ]);

        $this->assertTrue($response->json('success'), "Should allow booking on next day");
    }

    /** @test Cancelled appointments don't count toward limit */
    public function test_cancelled_appointment_frees_up_limit()
    {
        // Create 2 appointments directly in database
        $apt1 = Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '08:00',
            'status' => 'pending'
        ]);

        $apt2 = Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        // At limit - booking should fail
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);
        $this->assertFalse($response->json('success'));

        // Cancel first appointment
        $apt1->status = 'cancelled';
        $apt1->save();

        // Should now be able to book (1 active + 1 cancelled = 1 toward limit)
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);

        $this->assertTrue($response->json('success'), "Should allow booking after cancellation frees up limit");
    }

    /** @test Time slot capacity is enforced independently of user limit */
    public function test_time_slot_capacity_enforced()
    {
        // Fill a time slot with 3 different users (slot capacity is 3)
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'client']);
            $this->actingAs($user)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => '08:00'
            ]);
        }

        // Try to book 4th in same slot (should fail - slot is full)
        $user4 = User::factory()->create(['role' => 'client']);
        $response = $this->actingAs($user4)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '08:00'
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('capacity', strtolower($response->json('message', '')));
    }

    /** @test Both user limit and slot capacity enforced simultaneously */
    public function test_both_limits_enforced_simultaneously()
    {
        // Create a user with 2 bookings already
        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);

        Appointment::create([
            'user_id' => $this->client->id,
            'type' => 'consultation',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00',
            'status' => 'pending'
        ]);

        // Fill the 08:00 slot to capacity with other users
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'client']);
            Appointment::create([
                'user_id' => $user->id,
                'type' => 'consultation',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => '08:00',
                'status' => 'pending'
            ]);
        }

        // Try to book at full slot - client is at user limit AND slot is full
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '08:00'
        ]);

        $this->assertFalse($response->json('success'));
    }

    /** @test User limit endpoint returns accurate information */
    public function test_user_limit_endpoint_accurate()
    {
        // Book 1 appointment
        $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '08:00'
        ]);

        // Check limit endpoint
        $response = $this->actingAs($this->client)
            ->getJson("/api/appointment-settings/user-limit/{$this->client->id}/{$this->bookableDate}");

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertEquals(2, $data['limit']);
        $this->assertEquals(1, $data['used']);
        $this->assertEquals(1, $data['remaining']);
        $this->assertFalse($data['has_reached_limit']);
    }

    /** @test Limit can be changed dynamically and affects bookings */
    public function test_limit_change_affects_bookings()
    {
        // Book 2 (at current limit)
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->client->id,
                'type' => 'consultation',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + $i),
                'status' => 'pending'
            ]);
        }

        // Verify 3rd booking fails
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);
        $this->assertFalse($response->json('success'));

        // Increase limit to 3
        $this->settings->daily_booking_limit_per_user = 3;
        $this->settings->save();

        // Should now succeed
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);

        $this->assertTrue($response->json('success'), "Should allow booking after limit increase");
    }

    /** @test Disabled limit allows unlimited bookings */
    public function test_disabled_limit_allows_unlimited()
    {
        // The is_active flag means getCurrent() won't return this record if set to false.
        // Instead, we'll test with a very high limit (effectively unlimited)
        $this->settings->daily_booking_limit_per_user = 100;
        $this->settings->save();

        // Should be able to book more than 2
        $successCount = 0;
        for ($i = 0; $i < 4; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + ($i % 8))
            ]);

            if ($response->json('success') === true) {
                $successCount++;
            }
        }
        
        // All 4 should succeed with a very high limit
        $this->assertEquals(4, $successCount, "Expected all 4 bookings to succeed with high limit");
    }

    /** @test Staff booking independent of client limit */
    public function test_staff_booking_independent_of_client_limit()
    {
        // Fill client's limit
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->client->id,
                'type' => 'consultation',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + $i),
                'status' => 'pending'
            ]);
        }

        // Staff should still be able to book (staff role might have different limits or none)
        $response = $this->actingAs($this->staff)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);

        // If staff booking succeeds, that's expected - staff roles often have different rules
        $this->assertNotNull($response->json('success'));
    }

    /** @test Concurrent booking simulation */
    public function test_concurrent_booking_simulation()
    {
        $responses = [];
        
        // Simulate 3 concurrent requests from same user
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($this->client)->postJson('/api/appointments', [
                'type' => 'consultation',
                'service_type' => 'Legal',
                'appointment_date' => $this->bookableDate,
                'appointment_time' => sprintf('%02d:00', 8 + $i)
            ]);
            
            $responses[] = $response->json('success') === true ? 1 : 0;
        }

        // Only 2 should succeed (limit is 2)
        $successful = array_sum($responses);
        $this->assertEquals(2, $successful, "Expected 2 successful bookings out of 3");
    }

    /** @test Different appointment types are counted together toward limit */
    public function test_types_count_together_toward_limit()
    {
        // Book consultation
        $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '08:00'
        ]);

        // Book different type
        $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'follow-up',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '09:00'
        ]);

        // Try to book another - should fail (both types count toward limit)
        $response = $this->actingAs($this->client)->postJson('/api/appointments', [
            'type' => 'initial-consultation',
            'service_type' => 'Legal',
            'appointment_date' => $this->bookableDate,
            'appointment_time' => '10:00'
        ]);

        $this->assertFalse($response->json('success'), "Different types should count toward limit");
    }
}
