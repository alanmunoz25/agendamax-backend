<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthRegisterClientPivotTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'invitation_code' => 'TESTINVITE',
            'status' => 'active',
        ]);
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '8091234567',
        ], $overrides);
    }

    public function test_register_with_invitation_code_and_multi_business_flag_creates_pivot_not_business_id(): void
    {
        Config::set('agendamax.client_multi_business', true);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'invitation_code' => 'TESTINVITE',
        ]));

        $response->assertStatus(201);

        $userId = $response->json('user.id');

        // In multi-business mode, users.primary_business_id stays null
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'primary_business_id' => null,
            'role' => 'client',
        ]);

        // Pivot row must have been created
        $this->assertDatabaseHas('user_business', [
            'user_id' => $userId,
            'business_id' => $this->business->id,
            'status' => 'active',
        ]);
    }

    public function test_register_without_invitation_code_and_multi_business_flag_sets_lead_role(): void
    {
        Config::set('agendamax.client_multi_business', true);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(201);

        $userId = $response->json('user.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'primary_business_id' => null,
            'role' => 'lead',
        ]);

        $this->assertDatabaseMissing('user_business', [
            'user_id' => $userId,
        ]);
    }

    public function test_register_with_invitation_code_and_multi_business_flag_false_uses_legacy_behavior(): void
    {
        Config::set('agendamax.client_multi_business', false);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'invitation_code' => 'TESTINVITE',
        ]));

        $response->assertStatus(201);

        $userId = $response->json('user.id');

        // Legacy mode: primary_business_id is set on the user row
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'primary_business_id' => $this->business->id,
            'role' => 'client',
        ]);

        // No pivot row should be created in legacy mode
        $this->assertDatabaseMissing('user_business', [
            'user_id' => $userId,
        ]);
    }

    public function test_register_without_invitation_code_and_multi_business_flag_false_uses_legacy_behavior(): void
    {
        Config::set('agendamax.client_multi_business', false);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(201);

        $userId = $response->json('user.id');

        // Legacy mode without invitation code: primary_business_id null, role lead (no business context)
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'primary_business_id' => null,
            'role' => 'lead',
        ]);
    }
}
