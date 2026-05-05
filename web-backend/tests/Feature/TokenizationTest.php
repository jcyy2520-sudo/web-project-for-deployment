<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AccessToken;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class TokenizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_generated_for_new_user()
    {
        $user = User::factory()->create();
        
        $this->assertNotNull($user->uuid);
        $this->assertTrue(str_contains($user->uuid, '-'));
    }

    public function test_generate_password_reset_token()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'password_reset',
            3600
        );

        $this->assertArrayHasKey('token', $tokenData);
        $this->assertArrayHasKey('uuid', $tokenData);
        $this->assertArrayHasKey('expires_at', $tokenData);
        $this->assertArrayHasKey('url', $tokenData);
        $this->assertArrayHasKey('secure_url', $tokenData);
    }

    public function test_verify_token_success()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'password_reset',
            3600
        );

        $result = TokenService::verifyToken($tokenData['token'], 'password_reset');
        
        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertEquals('password_reset', $result['purpose']);
    }

    public function test_verify_token_by_uuid()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'email_verification',
            86400
        );

        $result = TokenService::verifyTokenByUuid(
            $tokenData['uuid'],
            $tokenData['token']
        );
        
        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_expired_token_fails()
    {
        $user = User::factory()->create();
        
        $accessToken = AccessToken::create([
            'token_uuid' => \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'token_hash' => hash('sha256', 'test-token'),
            'purpose' => 'test',
            'expires_at' => now()->subHour()
        ]);

        $result = TokenService::verifyToken('test-token');
        
        $this->assertNull($result);
    }

    public function test_revoke_token()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'password_reset',
            3600
        );

        TokenService::revokeToken($tokenData['token']);
        
        $result = TokenService::verifyToken($tokenData['token']);
        $this->assertNull($result);
    }

    public function test_revoke_all_user_tokens()
    {
        $user = User::factory()->create();
        
        $token1 = TokenService::generateTokenizedUrl($user->id, 'password_reset', 3600);
        $token2 = TokenService::generateTokenizedUrl($user->id, 'email_verification', 86400);

        TokenService::revokeAllUserTokens($user->id);
        
        $result1 = TokenService::verifyToken($token1['token']);
        $result2 = TokenService::verifyToken($token2['token']);
        
        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    public function test_wrong_purpose_fails()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'password_reset',
            3600
        );

        $result = TokenService::verifyToken($tokenData['token'], 'email_verification');
        
        $this->assertNull($result);
    }

    public function test_get_secure_user_data()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('secret')
        ]);
        
        $secureData = TokenService::getSecureUserData($user);
        
        $this->assertArrayHasKey('uuid', $secureData);
        $this->assertArrayHasKey('username', $secureData);
        $this->assertArrayHasKey('display_name', $secureData);
        $this->assertArrayNotHasKey('email', $secureData);
        $this->assertArrayNotHasKey('password', $secureData);
    }

    public function test_password_reset_endpoint()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com'
        ]);

        $response = $this->postJson('/api/password-reset-request', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath(
            'message',
            'If this email is registered, a password reset link has been sent.'
        );
    }

    public function test_verify_email_endpoint()
    {
        $user = User::factory()->create();
        
        $tokenData = TokenService::generateTokenizedUrl(
            $user->id,
            'email_verification',
            86400
        );

        $response = $this->getJson(
            "/api/verify-email/{$tokenData['uuid']}?token={$tokenData['token']}"
        );

        $response->assertStatus(200);
        
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_share_link_generation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->postJson('/api/generate-share-token/appointment/123');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'share_token',
            'share_url',
            'expires_at',
            'uuid'
        ]);

        $shareUrl = (string) $response->json('share_url');
        $this->assertStringContainsString('/api/shared-resource/', $shareUrl);
        $this->assertStringContainsString('token=', $shareUrl);

        $accessToken = AccessToken::query()->sole();
        $this->assertSame('share_appointment', $accessToken->purpose);
        $this->assertSame('appointment', data_get($accessToken->metadata, 'resource_type'));
        $this->assertSame('123', (string) data_get($accessToken->metadata, 'resource_id'));
        $this->assertSame((string) $user->uuid, (string) data_get($accessToken->metadata, 'created_by'));
    }
}
