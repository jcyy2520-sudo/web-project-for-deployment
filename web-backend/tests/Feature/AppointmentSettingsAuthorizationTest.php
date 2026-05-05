<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentSettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        AppointmentSettings::create([
            'daily_booking_limit_per_user' => 3,
            'is_active' => true,
            'description' => 'Authorization test settings',
        ]);
    }

    /** @test */
    public function guest_cannot_view_booking_limits()
    {
        $today = now()->format('Y-m-d');

        $this->getJson("/api/appointment-settings/user-limit/{$this->user->id}/{$today}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function user_cannot_view_another_users_booking_limits()
    {
        $today = now()->format('Y-m-d');

        $this->actingAs($this->user)
            ->getJson("/api/appointment-settings/user-limit/{$this->otherUser->id}/{$today}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function admin_can_view_another_users_booking_limits()
    {
        $today = now()->format('Y-m-d');

        Appointment::forceCreate([
            'user_id' => $this->otherUser->id,
            'type' => 'consultation',
            'service_type' => 'Legal Consultation',
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->getJson("/api/appointment-settings/user-limit/{$this->otherUser->id}/{$today}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.used', 1)
            ->assertJsonPath('data.remaining', 2);
    }
}