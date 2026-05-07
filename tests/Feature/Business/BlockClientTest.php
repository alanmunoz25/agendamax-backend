<?php

declare(strict_types=1);

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockClientTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;

    private Business $businessB;

    private User $adminA;

    private User $adminB;

    private User $employee;

    private User $client;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessA = Business::factory()->create(['name' => 'Business A']);
        $this->businessB = Business::factory()->create(['name' => 'Business B']);

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $this->adminA = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->businessA->id,
        ]);

        $this->adminB = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->businessB->id,
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->businessA->id,
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->businessA->id,
        ]);

        // Enroll the client as an active member of business A.
        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->businessA->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Block endpoint
    // -------------------------------------------------------------------------

    public function test_business_admin_of_a_can_block_client_in_a(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", [
                'reason' => 'Repeated no-shows without notice',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Client blocked successfully.')
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.user_id', $this->client->id)
            ->assertJsonPath('data.business_id', $this->businessA->id);

        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->businessA->id,
            'status' => 'blocked',
            'blocked_reason' => 'Repeated no-shows without notice',
        ]);
    }

    public function test_business_admin_of_a_cannot_block_client_in_b(): void
    {
        // Enroll the client in business B as well.
        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->businessB->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessB->id}/clients/{$this->client->id}/block", [
                'reason' => 'Attempting cross-business block',
            ]);

        $response->assertForbidden();
    }

    public function test_super_admin_can_block_client_in_any_business(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", [
                'reason' => 'Super admin initiated block action',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Client blocked successfully.');
    }

    public function test_employee_cannot_block_client(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", [
                'reason' => 'Employee trying to block',
            ]);

        $response->assertForbidden();
    }

    public function test_client_cannot_block_another_client(): void
    {
        $otherClient = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->businessA->id,
        ]);

        DB::table('user_business')->insert([
            'user_id' => $otherClient->id,
            'business_id' => $this->businessA->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$otherClient->id}/block", [
                'reason' => 'Client trying to block another',
            ]);

        $response->assertForbidden();
    }

    public function test_block_with_reason_shorter_than_10_chars_returns_422(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", [
                'reason' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_block_without_reason_returns_422(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_blocking_already_blocked_client_is_idempotent_and_preserves_original_reason(): void
    {
        // Block the client with an initial reason.
        DB::table('user_business')
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->businessA->id)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_by_user_id' => $this->adminA->id,
                'blocked_reason' => 'Original reason that must be kept',
                'updated_at' => now(),
            ]);

        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/block", [
                'reason' => 'Trying to overwrite the original reason',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Client is already blocked.');

        // Original reason must remain untouched.
        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->businessA->id,
            'blocked_reason' => 'Original reason that must be kept',
        ]);
    }

    public function test_blocking_client_not_enrolled_returns_404(): void
    {
        $unenrolledClient = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->businessA->id,
        ]);

        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$unenrolledClient->id}/block", [
                'reason' => 'Client is not in this business',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Client is not enrolled in this business.');
    }

    // -------------------------------------------------------------------------
    // Unblock endpoint
    // -------------------------------------------------------------------------

    public function test_can_unblock_a_blocked_client(): void
    {
        DB::table('user_business')
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->businessA->id)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_by_user_id' => $this->adminA->id,
                'blocked_reason' => 'Reason to unblock later',
                'updated_at' => now(),
            ]);

        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/unblock");

        $response->assertOk()
            ->assertJsonPath('message', 'Client unblocked successfully.')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.blocked_at', null)
            ->assertJsonPath('data.blocked_reason', null);

        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->businessA->id,
            'status' => 'active',
            'blocked_reason' => null,
        ]);
    }

    public function test_unblocking_a_non_blocked_client_returns_404(): void
    {
        // Client is still active (not blocked).
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/businesses/{$this->businessA->id}/clients/{$this->client->id}/unblock");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Client is not blocked in this business.');
    }
}
