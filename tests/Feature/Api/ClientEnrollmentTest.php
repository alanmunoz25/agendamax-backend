<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'invitation_code' => 'BELLA2024',
            'status' => 'active',
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'business_id' => null,
        ]);
    }

    public function test_enroll_happy_path_creates_pivot_row_and_returns_201(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'BELLA2024',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'logo_url',
                    'is_blocked',
                    'pivot' => ['status', 'joined_at'],
                ],
            ])
            ->assertJsonPath('message', 'Enrollment successful')
            ->assertJsonPath('data.id', $this->business->id)
            ->assertJsonPath('data.is_blocked', false);

        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'status' => 'active',
        ]);
    }

    public function test_enroll_is_idempotent_and_returns_200_already_enrolled(): void
    {
        Sanctum::actingAs($this->client);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'BELLA2024',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Already enrolled');
    }

    public function test_enroll_reactivates_left_membership_and_returns_201(): void
    {
        Sanctum::actingAs($this->client);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'left',
            'joined_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'BELLA2024',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Enrollment successful');

        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'status' => 'active',
        ]);
    }

    public function test_blocked_client_cannot_reenroll_and_gets_403(): void
    {
        Sanctum::actingAs($this->client);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now()->subDays(10),
            'blocked_at' => now()->subDays(5),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'BELLA2024',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You are blocked from this business and cannot re-enroll.');
    }

    public function test_enroll_with_invalid_invitation_code_returns_422(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'INVALID99',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.invitation_code.0', 'The invitation code is invalid.');
    }

    public function test_enroll_with_inactive_business_returns_422(): void
    {
        Sanctum::actingAs($this->client);

        $inactiveBusiness = Business::factory()->inactive()->create([
            'invitation_code' => 'CLOSED01',
        ]);

        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'CLOSED01',
        ]);

        $response->assertStatus(422);
    }

    public function test_enroll_with_business_slug_works(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/client/businesses', [
            'business_slug' => $this->business->slug,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Enrollment successful');
    }

    public function test_enroll_requires_exactly_one_field(): void
    {
        Sanctum::actingAs($this->client);

        // Neither field provided
        $response = $this->postJson('/api/v1/client/businesses', []);
        $response->assertStatus(422);

        // Both fields provided
        $response = $this->postJson('/api/v1/client/businesses', [
            'invitation_code' => 'BELLA2024',
            'business_slug' => $this->business->slug,
        ]);
        $response->assertStatus(422);
    }

    public function test_list_enrolled_excludes_left_status(): void
    {
        Sanctum::actingAs($this->client);

        $activeBusiness = Business::factory()->create(['status' => 'active']);
        $leftBusiness = Business::factory()->create(['status' => 'active']);

        DB::table('user_business')->insert([
            [
                'user_id' => $this->client->id,
                'business_id' => $activeBusiness->id,
                'role_in_business' => 'client',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->client->id,
                'business_id' => $leftBusiness->id,
                'role_in_business' => 'client',
                'status' => 'left',
                'joined_at' => now()->subDays(10),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
        ]);

        $response = $this->getJson('/api/v1/client/businesses');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($activeBusiness->id, $ids);
        $this->assertNotContains($leftBusiness->id, $ids);
    }

    public function test_list_enrolled_includes_blocked_with_is_blocked_true(): void
    {
        Sanctum::actingAs($this->client);

        $blockedBusiness = Business::factory()->create(['status' => 'active']);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $blockedBusiness->id,
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now()->subDays(5),
            'blocked_at' => now()->subDays(2),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/client/businesses');

        $response->assertStatus(200);

        $blocked = collect($response->json('data'))
            ->firstWhere('id', $blockedBusiness->id);

        $this->assertNotNull($blocked);
        $this->assertTrue($blocked['is_blocked']);
        $this->assertEquals('blocked', $blocked['pivot']['status']);
    }

    public function test_unenroll_happy_path_sets_status_left_and_returns_200(): void
    {
        Sanctum::actingAs($this->client);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/client/businesses/{$this->business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'You have left the business successfully.');

        $this->assertDatabaseHas('user_business', [
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'status' => 'left',
        ]);
    }

    public function test_unenroll_from_business_not_enrolled_in_returns_404(): void
    {
        Sanctum::actingAs($this->client);

        $otherBusiness = Business::factory()->create();

        $response = $this->deleteJson("/api/v1/client/businesses/{$otherBusiness->id}");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Enrollment not found.');
    }

    public function test_historical_appointments_persist_after_unenroll(): void
    {
        Sanctum::actingAs($this->client);

        $employee = Employee::factory()->create(['business_id' => $this->business->id]);
        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/client/businesses/{$this->business->id}");

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_throttle_blocks_after_thirty_requests(): void
    {
        Sanctum::actingAs($this->client);

        // Make 30 requests — all should pass (the 30th is the last allowed)
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/client/businesses');
        }

        // The 31st request should be throttled
        $response = $this->getJson('/api/v1/client/businesses');
        $response->assertStatus(429);
    }

    public function test_unauthenticated_user_cannot_access_enrollment_endpoints(): void
    {
        $response = $this->getJson('/api/v1/client/businesses');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/client/businesses', ['invitation_code' => 'BELLA2024']);
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/v1/client/businesses/{$this->business->id}");
        $response->assertStatus(401);
    }
}
