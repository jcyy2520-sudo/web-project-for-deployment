<?php

namespace Tests\Feature;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ActionLogCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_write_actions_are_auto_logged_for_user_admin_and_cashier_roles(): void
    {
        Cache::flush();

        $users = [
            User::factory()->create(['role' => 'client']),
            User::factory()->create(['role' => 'admin']),
            User::factory()->create(['role' => 'staff']),
        ];

        foreach ($users as $index => $user) {
            $position = match ($index) {
                0 => 'top-left',
                1 => 'top-right',
                default => 'bottom-left',
            };

            $response = $this
                ->actingAs($user, 'sanctum')
                ->postJson('/api/chatbot/position', ['position' => $position]);

            $response->assertOk()->assertJsonPath('success', true);

            $log = ActionLog::where('user_id', $user->id)->latest('id')->first();

            $this->assertNotNull($log);
            $this->assertSame('auto_chatbot_position_store', $log->action);
            $this->assertSame('success', $log->status);
            $this->assertSame('ChatbotPosition', $log->model_type);
            $this->assertSame('POST', $log->metadata['http_method'] ?? null);
            $this->assertSame('api/chatbot/position', $log->metadata['path'] ?? null);
            $this->assertTrue((bool) ($log->metadata['auto_recorded'] ?? false));
        }
    }

    public function test_action_log_views_show_recorded_entries_for_user_admin_and_cashier(): void
    {
        Cache::flush();

        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'staff']);

        $this->actingAs($client, 'sanctum')->postJson('/api/chatbot/position', ['position' => 'top-left'])->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/chatbot/position', ['position' => 'top-right'])->assertOk();
        $this->actingAs($cashier, 'sanctum')->postJson('/api/chatbot/position', ['position' => 'bottom-left'])->assertOk();

        $clientLogs = $this
            ->actingAs($client, 'sanctum')
            ->getJson('/api/action-logs/my/logs');

        $clientLogs->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $clientLogs->json('data'));
        $this->assertSame('auto_chatbot_position_store', $clientLogs->json('data.0.action'));

        $cashierLogs = $this
            ->actingAs($cashier, 'sanctum')
            ->getJson('/api/cashier/action-logs?type=cashier');

        $cashierLogs->assertOk();
        $this->assertCount(1, $cashierLogs->json('data'));
        $this->assertSame('auto_chatbot_position_store', $cashierLogs->json('data.0.action'));

        $adminLogs = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/action-logs');

        $adminLogs->assertOk()->assertJsonPath('success', true);
        $this->assertCount(3, $adminLogs->json('data'));
    }

    public function test_explicit_controller_logs_are_not_duplicated_by_fallback_logger(): void
    {
        Cache::flush();

        $user = User::factory()->create(['role' => 'client']);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/profile/update', [
                'first_name' => 'Updated',
                'last_name' => 'User',
                'username' => 'updated-user',
                'email' => 'updated-user@example.test',
                'phone' => '09123456789',
                'address' => 'Updated Address',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, ActionLog::where('user_id', $user->id)->count());
        $this->assertSame(1, ActionLog::where('user_id', $user->id)->where('action', 'update_profile')->count());
        $this->assertSame(0, ActionLog::where('user_id', $user->id)->where('action', 'auto_profile_update')->count());
    }
}