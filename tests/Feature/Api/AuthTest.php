<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        // Without invitation code in multi-business mode, user becomes a lead
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'phone', 'role', 'business_id', 'avatar_url'],
                'token',
            ])
            ->assertJson([
                'user' => [
                    'name' => 'Test Client',
                    'email' => 'client@example.com',
                    'business_id' => null,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
        ]);
    }

    public function test_user_can_register_with_invitation_code(): void
    {
        // In multi-business mode (default), business_id on user stays null; pivot row is created
        $business = Business::factory()->create([
            'invitation_code' => 'TESTCODE',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'TESTCODE',
        ]);

        $response->assertStatus(201);

        $userId = $response->json('user.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'client@example.com',
            'role' => 'client',
        ]);

        $this->assertDatabaseHas('user_business', [
            'user_id' => $userId,
            'business_id' => $business->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_register_with_invitation_code_legacy_mode(): void
    {
        Config::set('agendamax.client_multi_business', false);

        $business = Business::factory()->create([
            'invitation_code' => 'TESTCODE',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'TESTCODE',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user' => [
                    'business_id' => $business->id,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'primary_business_id' => $business->id,
        ]);
    }

    public function test_user_can_register_with_business_id(): void
    {
        // business_id param only works in legacy mode; in multi-business mode it still works
        // but business_id on user row stays null (pivot is created via invitation_code only).
        // Test legacy mode behavior explicitly.
        Config::set('agendamax.client_multi_business', false);

        $business = Business::factory()->create();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'business_id' => $business->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user' => [
                    'business_id' => $business->id,
                ],
            ]);
    }

    public function test_registration_rejects_invalid_invitation_code(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_rejects_inactive_business_invitation_code(): void
    {
        Business::factory()->inactive()->create([
            'invitation_code' => 'INACTIVE',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'INACTIVE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_rejects_both_invitation_code_and_business_id(): void
    {
        $business = Business::factory()->create([
            'invitation_code' => 'TESTCODE',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'TESTCODE',
            'business_id' => $business->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_allows_duplicate_email_across_businesses_in_legacy_mode(): void
    {
        Config::set('agendamax.client_multi_business', false);

        $businessA = Business::factory()->create(['invitation_code' => 'CODEA']);
        $businessB = Business::factory()->create(['invitation_code' => 'CODEB']);

        // Register with business A
        $this->postJson('/api/v1/auth/register', [
            'name' => 'User A',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'CODEA',
        ])->assertStatus(201);

        // Register with business B (same email, different business) — allowed in legacy mode
        $this->postJson('/api/v1/auth/register', [
            'name' => 'User B',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'CODEB',
        ])->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_registration_rejects_duplicate_email_in_multi_business_mode(): void
    {
        // In multi-business mode, email is globally unique
        $businessA = Business::factory()->create(['invitation_code' => 'CODEA']);
        $businessB = Business::factory()->create(['invitation_code' => 'CODEB']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'User A',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'CODEA',
        ])->assertStatus(201);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'User B',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'CODEB',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_duplicate_email_within_same_business_in_legacy_mode(): void
    {
        Config::set('agendamax.client_multi_business', false);

        $business = Business::factory()->create(['invitation_code' => 'TESTCODE']);

        // First registration
        $this->postJson('/api/v1/auth/register', [
            'name' => 'User A',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'TESTCODE',
        ])->assertStatus(201);

        // Second registration with same email and business
        $this->postJson('/api/v1/auth/register', [
            'name' => 'User B',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'TESTCODE',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_without_business_still_works(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Business User',
            'email' => 'nobiz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user' => [
                    'business_id' => null,
                ],
            ]);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com', 'business_id' => null]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role', 'business_id', 'avatar_url'],
                'token',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout successful']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_get_their_details(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'business_id' => $business->id,
        ]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'role', 'business_id', 'avatar_url', 'business'],
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'business_id' => $business->id,
                ],
            ]);
    }

    public function test_user_can_update_push_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/push-token', [
                'push_token' => 'test-fcm-token-123',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Push token updated successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'push_token' => 'test-fcm-token-123',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/auth/user');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/logout');
        $response->assertStatus(401);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
