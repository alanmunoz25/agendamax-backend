<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Tests for Sanctum token expiration and the refresh endpoint.
 *
 * Verifies that tokens older than 30 days are rejected and that
 * the /api/v1/auth/refresh endpoint rotates a valid token correctly.
 */
class ApiTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_expiration_config_is_30_days(): void
    {
        $expirationMinutes = config('sanctum.expiration');

        $this->assertNotNull($expirationMinutes, 'sanctum.expiration must not be null (tokens would never expire).');
        $this->assertEquals(60 * 24 * 30, $expirationMinutes, 'sanctum.expiration must be 43200 minutes (30 days).');
    }

    public function test_expired_token_returns_401(): void
    {
        $user = User::factory()->create();

        // Create a token and manually backdate its created_at to 31 days ago.
        $token = $user->createToken('mobile-app');
        PersonalAccessToken::where('id', $token->accessToken->id)
            ->update(['created_at' => now()->subDays(31)]);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    public function test_valid_token_allows_access(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('mobile-app');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/user');

        $response->assertOk();
    }

    public function test_refresh_endpoint_revokes_old_token_and_issues_new_one(): void
    {
        $user = User::factory()->create();

        $oldToken = $user->createToken('mobile-app');

        $response = $this->withToken($oldToken->plainTextToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk();
        $response->assertJsonStructure(['message', 'token']);

        $newPlainToken = $response->json('token');
        $this->assertNotEquals($oldToken->plainTextToken, $newPlainToken);

        // Old token must be revoked.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);

        // New token must be usable.
        $userResponse = $this->withToken($newPlainToken)
            ->getJson('/api/v1/auth/user');

        $userResponse->assertOk();
    }

    public function test_refresh_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_old_token_is_deleted_from_database_after_refresh(): void
    {
        $user = User::factory()->create();

        $oldToken = $user->createToken('mobile-app');
        $oldTokenId = $oldToken->accessToken->id;

        $this->withToken($oldToken->plainTextToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        // Old token record must be gone from DB — cannot be used again.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldTokenId,
        ]);
    }
}
