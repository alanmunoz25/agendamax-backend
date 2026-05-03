<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'phone' => '809-000-0000',
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/auth/user', [
                'name' => 'Updated Name',
                'phone' => '809-111-2222',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '809-111-2222')
            ->assertJsonPath('message', 'Perfil actualizado.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '809-111-2222',
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->patchJson('/api/v1/auth/user', ['name' => 'Test'])
            ->assertStatus(401);
    }

    public function test_email_field_is_ignored_in_update_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/user', [
                'name' => 'New Name',
                'email' => 'hacked@example.com',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'original@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => 'hacked@example.com',
        ]);
    }

    public function test_password_field_is_ignored_in_update_payload(): void
    {
        $originalPassword = 'original-secret-password';

        $user = User::factory()->create([
            'password' => $originalPassword,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/user', [
                'name' => 'New Name',
                'password' => 'new-hacked-password',
            ])
            ->assertStatus(200);

        $user->refresh();
        $this->assertTrue(
            Hash::check($originalPassword, $user->password),
            'Password should remain unchanged after profile update.'
        );
    }

    public function test_validation_rejects_invalid_phone_too_long(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/user', [
                'phone' => str_repeat('1', 21),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_response_returns_fresh_user_data(): void
    {
        $user = User::factory()->create(['name' => 'Before Update']);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/auth/user', [
                'name' => 'After Update',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'After Update');
    }
}
