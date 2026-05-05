<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_verification_codes_are_hashed_at_rest(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register-step1', [
            'username' => 'securitycheck',
            'email' => 'security@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertOk();

        $verificationCode = VerificationCode::query()->sole();

        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($verificationCode): bool {
            $this->assertNotSame($mail->verificationCode, $verificationCode->code);
            $this->assertTrue(Hash::check($mail->verificationCode, $verificationCode->code));

            return true;
        });
    }

    public function test_password_reset_codes_are_hashed_at_rest_and_still_verify(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password/send-code', [
            'email' => $user->email,
        ]);

        $response->assertOk();

        $resetCode = PasswordResetCode::query()->sole();
        $issuedCode = null;

        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($resetCode, &$issuedCode): bool {
            $issuedCode = $mail->code;

            $this->assertNotSame($mail->code, $resetCode->code);
            $this->assertTrue(Hash::check($mail->code, $resetCode->code));

            return true;
        });

        $this->assertNotNull($issuedCode);

        $this->postJson('/api/forgot-password/verify-code', [
            'email' => $user->email,
            'code' => $issuedCode,
        ])->assertOk()->assertJsonPath('verified', true);
    }

    public function test_password_reset_send_code_limit_is_scoped_to_email_on_shared_ip(): void
    {
        Mail::fake();

        $firstUser = User::factory()->create([
            'email' => 'first-reset@example.com',
        ]);

        $secondUser = User::factory()->create([
            'email' => 'second-reset@example.com',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->postJson('/api/forgot-password/send-code', [
                    'email' => $firstUser->email,
                ])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/forgot-password/send-code', [
                'email' => $firstUser->email,
            ])
            ->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/forgot-password/send-code', [
                'email' => $secondUser->email,
            ])
            ->assertOk();

        Mail::assertQueued(PasswordResetCodeMail::class, 6);
    }

    public function test_login_locks_account_after_repeated_invalid_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
            'account_status' => 'active',
            'verification_method' => 'email',
            'password_login_enabled' => true,
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'WrongPassword123',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123',
        ])->assertStatus(429);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123',
        ])->assertStatus(429);
    }
}