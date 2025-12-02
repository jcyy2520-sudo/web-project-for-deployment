<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SIMPLE BOOKING LIMIT TEST
 * Direct test: Does the limit actually work?
 */
class BookingLimitTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'client']);
        $this->tomorrow = now()->addDay()->format('Y-m-d');
        
        // Create settings with limit of 2
        AppointmentSettings::create([
            'daily_booking_limit_per_user' => 2,
            'is_active' => true
        ]);
    }

    /** @test Booking limit actually rejects 3rd booking */
    public function test_booking_limit_rejects_third_appointment()
    {
        // DIRECTLY test via the model/logic, not API
        $userHasReached = \App\Models\AppointmentSettings::userHasReachedDailyLimit(
            $this->user->id, 
            $this->tomorrow
        );
        
        // With 0 bookings, should NOT be at limit
        $this->assertFalse($userHasReached, "Should not be at limit with 0 bookings");
        
        // Create 1 appointment
        Appointment::create([
            'user_id' => $this->user->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '09:00',
            'status' => 'pending'
        ]);
        
        // With 1 booking, should NOT be at limit
        $userHasReached = \App\Models\AppointmentSettings::userHasReachedDailyLimit(
            $this->user->id, 
            $this->tomorrow
        );
        $this->assertFalse($userHasReached, "Should not be at limit with 1 booking out of 2");
        
        // Create 2nd appointment
        Appointment::create([
            'user_id' => $this->user->id,
            'type' => 'consultation',
            'appointment_date' => $this->tomorrow,
            'appointment_time' => '10:00',
            'status' => 'pending'
        ]);
        
        // With 2 bookings, SHOULD be at limit
        $userHasReached = \App\Models\AppointmentSettings::userHasReachedDailyLimit(
            $this->user->id, 
            $this->tomorrow
        );
        $this->assertTrue($userHasReached, "Should be at limit with 2 bookings");
        
        // Cancelled doesn't count
        $apt = Appointment::first();
        $apt->status = 'cancelled';
        $apt->save();
        
        // After cancellation, should NOT be at limit
        $userHasReached = \App\Models\AppointmentSettings::userHasReachedDailyLimit(
            $this->user->id, 
            $this->tomorrow
        );
        $this->assertFalse($userHasReached, "Should not be at limit after cancellation");
    }

    /** @test Limit resets per day */
    public function test_limit_resets_per_day()
    {
        $day1 = now()->addDay()->format('Y-m-d');
        $day2 = now()->addDays(2)->format('Y-m-d');
        
        // Create 2 on day 1
        for ($i = 0; $i < 2; $i++) {
            Appointment::create([
                'user_id' => $this->user->id,
                'type' => 'consultation',
                'appointment_date' => $day1,
                'appointment_time' => sprintf('%02d:00', 9 + $i),
                'status' => 'pending'
            ]);
        }
        
        // Day 1: at limit
        $limit1 = \App\Models\AppointmentSettings::userHasReachedDailyLimit($this->user->id, $day1);
        $this->assertTrue($limit1, "Should be at limit on day 1");
        
        // Day 2: NOT at limit (fresh day)
        $limit2 = \App\Models\AppointmentSettings::userHasReachedDailyLimit($this->user->id, $day2);
        $this->assertFalse($limit2, "Should NOT be at limit on day 2 (fresh day)");
    }

    /** @test Disabled limit allows unlimited */
    public function test_disabled_limit_allows_unlimited()
    {
        // Set to a very high limit (effectively unlimited)
        $settings = AppointmentSettings::first();
        $settings->daily_booking_limit_per_user = 100;
        $settings->save();
        
        // Create many bookings
        for ($i = 0; $i < 10; $i++) {
            Appointment::create([
                'user_id' => $this->user->id,
                'type' => 'consultation',
                'appointment_date' => $this->tomorrow,
                'appointment_time' => sprintf('%02d:00', (8 + $i) % 24),
                'status' => 'pending'
            ]);
        }
        
        // With high limit, should still not be "at limit"
        $userHasReached = \App\Models\AppointmentSettings::userHasReachedDailyLimit(
            $this->user->id, 
            $this->tomorrow
        );
        
        $this->assertFalse($userHasReached, "Should not be at limit with 10 bookings / 100 limit");
    }
}
