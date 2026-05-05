<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\ActionPermissionService;
use App\Services\AgentToolRegistry;
use App\Services\ChatbotRealTimeDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentToolRegistryServiceAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_signing_phrase_resolves_to_notarization_service(): void
    {
        $notarization = Service::create([
            'name' => 'Notarization Service',
            'description' => 'Official document notarization and certification',
            'price' => 500,
            'duration' => 30,
            'is_active' => true,
        ]);

        Service::create([
            'name' => 'Document Review',
            'description' => 'Detailed review of legal documents',
            'price' => 2000,
            'duration' => 90,
            'is_active' => true,
        ]);

        $registry = new AgentToolRegistry(
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ActionPermissionService::class)
        );

        $resolvedIds = $registry->resolveServiceIdsPublic('document signing');

        $this->assertSame([$notarization->id], $resolvedIds);
    }

    public function test_service_resolution_accepts_all_caps_existing_service_name(): void
    {
        $consultation = Service::create([
            'name' => 'Legal Consultation',
            'description' => 'General legal advice and consultation',
            'price' => 1200,
            'duration' => 60,
            'is_active' => true,
        ]);

        $registry = new AgentToolRegistry(
            Mockery::mock(ChatbotRealTimeDataService::class),
            Mockery::mock(ActionPermissionService::class)
        );

        $resolvedIds = $registry->resolveServiceIdsPublic('LEGAL CONSULTATION');

        $this->assertSame([$consultation->id], $resolvedIds);
    }
}