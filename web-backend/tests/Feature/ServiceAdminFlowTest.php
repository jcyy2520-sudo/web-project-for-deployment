<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ServiceAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_service_crud_updates_public_and_admin_views_immediately(): void
    {
        Cache::flush();

        $admin = User::factory()->create([
            'role' => 'admin',
            'profile_completed' => true,
        ]);

        Service::create([
            'name' => 'Existing Service',
            'description' => 'Original',
            'price' => 100,
            'duration' => 30,
            'is_active' => true,
        ]);

        // Prime caches before mutating services.
        $this->getJson('/api/services')->assertOk();
        $this->getJson('/api/public/init')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services/stats')->assertOk();

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/services', [
            'name' => 'Express Consultation',
            'description' => 'Fast-track service',
            'price' => 250,
            'duration' => 45,
            'public_requirements' => ['Valid ID'],
        ]);

        $createResponse->assertCreated()->assertJsonPath('success', true);
        $serviceId = $createResponse->json('data.id');

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['name' => 'Express Consultation']);

        $this->getJson('/api/public/init')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services/stats')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/services/{$serviceId}", [
            'name' => 'Express Consultation Plus',
            'description' => 'Updated service',
            'price' => 300,
            'duration' => 60,
            'is_active' => true,
            'public_requirements' => ['Valid ID', 'Supporting document'],
        ])->assertOk()->assertJsonPath('success', true);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation Plus'])
            ->assertJsonMissing(['name' => 'Express Consultation']);

        $this->getJson('/api/public/init')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation Plus'])
            ->assertJsonMissing(['name' => 'Express Consultation']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Express Consultation Plus']);

        $this->getJson('/api/public/init')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Express Consultation Plus']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services/archived/list')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation Plus']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/services/{$serviceId}/restore")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation Plus']);

        $this->getJson('/api/public/init')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Express Consultation Plus']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/services/{$serviceId}/permanent")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Express Consultation Plus']);

        $this->getJson('/api/public/init')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Express Consultation Plus']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Express Consultation Plus']);
    }
}