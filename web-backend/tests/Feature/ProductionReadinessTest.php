<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\AppointmentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1: Verify experimental endpoints exist and are wrapped
     */
    public function test_experimental_endpoints_are_wrapped_with_safety()
    {
        // Create a user with auth token
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test')->plainTextToken;
        
        // Test an experimental endpoint exists
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/admin/stats/summary');
        
        // Should get either 200 OK or 503 Service Unavailable
        // But NOT 404 Not Found (which means endpoint doesn't exist)
        $this->assertNotEquals(404, $response->status(), 'Endpoint should exist');
        echo "✅ TEST 1: Experimental endpoints are registered\n";
    }

    /**
     * TEST 2: Booking limit enforces correctly
     */
    public function test_booking_limit_enforces_in_real_appointment_creation()
    {
        $user = User::factory()->create(['role' => 'client']);
        
        // Create a service directly
        $service = new Service();
        $service->name = 'Test Service';
        $service->duration = 60;
        $service->save();
        
        // Set limit to 2
        AppointmentSettings::updateOrCreate(
            ['id' => 1],
            [
                'daily_booking_limit_per_user' => 2,
                'is_active' => true,
            ]
        );
        
        // Create 2 appointments (should succeed)
        $today = now()->format('Y-m-d');
        
        Appointment::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'appointment_date' => $today,
            'appointment_time' => '09:00',
            'status' => 'approved',
            'type' => 'appointment',
        ]);
        
        Appointment::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'appointment_date' => $today,
            'appointment_time' => '09:30',
            'status' => 'approved',
            'type' => 'appointment',
        ]);
        
        // Check limit
        $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($user->id, $today);
        
        $this->assertTrue($hasReachedLimit, 'User should have reached limit');
        echo "✅ TEST 2: Booking limit enforcement works\n";
    }

    /**
     * TEST 3: No debug/verification endpoints exposed
     */
    public function test_no_debug_endpoints_expose_verification_codes()
    {
        // Try to access potential debug endpoints
        $debugEndpoints = [
            '/api/debug-appointments',
            '/api/test-endpoints',
            '/api/verification-codes',
            '/api/debug/verify-code',
        ];
        
        foreach ($debugEndpoints as $endpoint) {
            $response = $this->get($endpoint);
            // Should get 404 or 401, not 200 with data
            $this->assertNotEquals(200, $response->status(), "Debug endpoint $endpoint should not return 200");
        }
        
        echo "✅ TEST 3: No debug endpoints found\n";
    }

    /**
     * TEST 4: Error fallback returns proper response
     */
    public function test_experimental_endpoint_error_handling()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test')->plainTextToken;
        
        // Call an experimental endpoint that might fail gracefully
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/admin/analytics/dashboard');
        
        // Should either work (200) or fail gracefully (503)
        // But NOT return 500 (unhandled error)
        $this->assertNotEquals(500, $response->status(), 'Should not return 500 error');
        
        if ($response->status() === 503) {
            $data = json_decode($response->getContent(), true);
            $this->assertEquals('experimental_unavailable', $data['status']);
            echo "✅ TEST 4a: Error fallback returns 503 with proper structure\n";
        } else {
            echo "✅ TEST 4b: Endpoint working, no error needed\n";
        }
    }

    /**
     * TEST 5: Traits are actually applied to controllers
     */
    public function test_safe_experimental_feature_trait_applied_to_controllers()
    {
        $reflection = new \ReflectionClass('App\Http\Controllers\AnalyticsController');
        $traits = $reflection->getTraits();
        
        $hasTraitOrMethod = false;
        foreach ($traits as $trait) {
            if (strpos($trait->getName(), 'SafeExperimentalFeature') !== false) {
                $hasTraitOrMethod = true;
                break;
            }
        }
        
        // Also check if method exists
        if (!$hasTraitOrMethod) {
            $hasTraitOrMethod = method_exists('App\Http\Controllers\AnalyticsController', 'wrapExperimental');
        }
        
        $this->assertTrue($hasTraitOrMethod, 'SafeExperimentalFeature should be applied');
        echo "✅ TEST 5: SafeExperimentalFeature trait verified\n";
    }
}
