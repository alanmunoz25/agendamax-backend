<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_creates_code_entry(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200)
            ->assertJson(['message' => 'Si el email existe, recibirás un código en breve.']);

        $this->assertDatabaseHas('password_reset_codes', ['email' => $user->email]);
    }

    public function test_forgot_password_dispatches_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200);

        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Si el email existe, recibirás un código en breve.']);

        $this->assertDatabaseMissing('password_reset_codes', ['email' => 'nonexistent@example.com']);
    }

    public function test_forgot_password_rate_limits_excessive_requests(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        // 3 allowed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
                ->assertStatus(200);
        }

        // 4th request should be rate limited
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(429);
    }

    public function test_reset_password_with_valid_code_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $code = '123456';

        $this->seedResetCode($user->email, $code);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200)
            ->assertJson(['message' => 'Contraseña actualizada. Inicia sesión.']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_reset_password_with_invalid_code_returns_422(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->seedResetCode($user->email, '123456');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Código inválido o expirado.']);
    }

    public function test_reset_password_with_expired_code_returns_422(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $code = '123456';

        $this->seedResetCode($user->email, $code, Carbon::now()->subMinute());

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Código inválido o expirado.']);
    }

    public function test_reset_password_revokes_existing_sanctum_tokens(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $user->createToken('mobile-app');
        $user->createToken('mobile-app-2');

        $code = '123456';
        $this->seedResetCode($user->email, $code);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_reset_password_locks_after_5_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->seedResetCode($user->email, '123456');

        // 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/reset-password', [
                'email' => $user->email,
                'code' => '000000',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])->assertStatus(422);
        }

        // 6th attempt should be locked (attempts >= 5)
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(429)
            ->assertJson(['message' => 'Demasiados intentos. Solicita un nuevo código.']);
    }

    /**
     * Seed a password reset code entry for testing.
     */
    private function seedResetCode(string $email, string $plainCode, ?Carbon $expiresAt = null): void
    {
        \Illuminate\Support\Facades\DB::table('password_reset_codes')->upsert([
            'email' => $email,
            'code' => Hash::make($plainCode),
            'expires_at' => ($expiresAt ?? Carbon::now()->addMinutes(15))->toDateTimeString(),
            'attempts' => 0,
            'created_at' => Carbon::now()->toDateTimeString(),
        ], ['email'], ['code', 'expires_at', 'attempts', 'created_at']);
    }
}
