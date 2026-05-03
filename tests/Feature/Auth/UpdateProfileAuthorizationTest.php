<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BLOCK bonus: Unauthenticated requests to update profile must be rejected.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->patchJson('/api/v1/auth/user', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(401);
    }

    /**
     * BLOCK bonus: Authenticated user can update their own profile.
     */
    public function test_authenticated_user_can_update_own_profile(): void
    {
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'client',
            'business_id' => $business->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/auth/user', [
                'name' => 'Updated Name',
                'phone' => '+1234567890',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }
}
