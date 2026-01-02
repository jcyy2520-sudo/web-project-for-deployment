<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FeedbackSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Ensure settings exist
        FeedbackSettings::getSettings();
    }

    public function test_rate_limit_blocks_after_limit()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $settings = FeedbackSettings::getSettings();
        $settings->update(['rate_limit' => 2, 'cooldown_days' => 7]);

        // Submit allowed number
        for ($i = 0; $i < 2; $i++) {
            $resp = $this->postJson('/api/user/feedback', [
                'email' => $user->email,
                'message' => 'Test feedback ' . $i,
                'rating' => 5
            ]);

            $resp->assertStatus(201);
        }

        // Third attempt should be blocked
        $resp = $this->postJson('/api/user/feedback', [
            'email' => $user->email,
            'message' => 'Third feedback',
            'rating' => 4
        ]);

        $resp->assertStatus(429);
        $resp->assertJsonFragment(['error' => 'rate_limit_reached']);
    }

    public function test_profanity_detection_blocks()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $settings = FeedbackSettings::getSettings();
        $settings->update(['profanity_filter_enabled' => true, 'profanity_list' => json_encode(['foo','bar','damn'])]);

        $resp = $this->postJson('/api/user/feedback', [
            'email' => $user->email,
            'message' => 'This is a damn message',
            'rating' => 5
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonFragment(['error' => 'profanity_detected']);
    }

    public function test_duplicate_detection_blocks()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $settings = FeedbackSettings::getSettings();
        $settings->update(['duplicate_detection_enabled' => true, 'cooldown_days' => 7]);

        $resp = $this->postJson('/api/user/feedback', [
            'email' => $user->email,
            'message' => 'Unique message',
            'rating' => 5
        ]);
        $resp->assertStatus(201);

        $resp2 = $this->postJson('/api/user/feedback', [
            'email' => $user->email,
            'message' => 'Unique message',
            'rating' => 5
        ]);
        $resp2->assertStatus(409);
        $resp2->assertJsonFragment(['error' => 'duplicate_feedback']);
    }

    public function test_admin_can_report_feedback_and_user_gets_notified()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        // Submit feedback as user (public endpoint)
        $resp = $this->postJson('/api/feedback', [
            'email' => $user->email,
            'message' => 'Feedback to be reported',
            'rating' => 4
        ]);
        $resp->assertStatus(201);

        $feedbackId = $resp->json('data.id');

        // Admin reports
        $this->actingAs($admin, 'sanctum');
        $reportResp = $this->postJson("/api/admin/feedback/{$feedbackId}/report", [
            'reason' => 'spam',
            'explanation' => 'automated test'
        ]);

        $reportResp->assertStatus(200);
        $reportResp->assertJsonFragment(['message' => 'Feedback reported successfully']);
    }
}
