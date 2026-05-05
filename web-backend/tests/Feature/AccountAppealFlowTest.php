<?php

namespace Tests\Feature;

use App\Mail\AccountActionMail;
use App\Mail\AppealResolvedMail;
use App\Models\AccountAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountAppealFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_submit_and_resolve_an_appeal_through_the_full_flow(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'profile_completed' => true,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'profile_completed' => true,
            'account_status' => 'active',
            'account_status_reason' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$client->id}/account-action", [
                'action' => 'blocked',
                'reason' => 'This account was blocked after a policy review found repeated violations.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $appeal = AccountAppeal::query()->firstOrFail();

        $this->assertSame($client->id, $appeal->user_id);
        $this->assertSame($admin->id, $appeal->acted_by);
        $this->assertSame('pending', $appeal->status);

        $client->refresh();
        $this->assertFalse($client->is_active);
        $this->assertSame('blocked', $client->account_status);

        Mail::assertQueued(AccountActionMail::class);

        $this->getJson("/api/appeals/verify/{$appeal->token}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.already_submitted', false)
            ->assertJsonPath('data.user_name', $appeal->user_name)
            ->assertJsonPath('data.action_type', 'blocked');

        $this->postJson("/api/appeals/submit/{$appeal->token}", [
            'appeal_category' => 'technical_issue',
            'appeal_message' => 'This block was triggered by an account issue that has already been corrected.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $appeal->refresh();

        $this->assertSame('technical_issue', $appeal->appeal_category);
        $this->assertSame('This block was triggered by an account issue that has already been corrected.', $appeal->appeal_message);
        $this->assertNotNull($appeal->appeal_submitted_at);

        $this->getJson("/api/appeals/verify/{$appeal->token}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.already_submitted', true)
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/appeals')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $appeal->id)
            ->assertJsonPath('data.0.acted_by', $admin->id)
            ->assertJsonPath('data.0.status', 'pending');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/appeals/{$appeal->id}/resolve", [
                'status' => 'approved',
                'admin_response' => 'We verified the issue and restored the account access.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.resolved_by', $admin->id);

        $appeal->refresh();
        $client->refresh();

        $this->assertSame('approved', $appeal->status);
        $this->assertSame($admin->id, $appeal->resolved_by);
        $this->assertNotNull($appeal->resolved_at);
        $this->assertTrue($client->is_active);
        $this->assertSame('active', $client->account_status);
        $this->assertNull($client->account_status_reason);

        Mail::assertQueued(AppealResolvedMail::class);
    }
}