<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Support\BusinessContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrossBusinessAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessContext::clear();
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    /**
     * Helper: insert a user_business pivot row.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function enrollUser(User $user, Business $business, string $status = 'active', array $overrides = []): void
    {
        DB::table('user_business')->insert(array_merge([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role_in_business' => 'client',
            'status' => $status,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Helper: create an appointment belonging to a specific business and client.
     */
    private function makeAppointment(Business $business, User $client, string $status = 'pending'): Appointment
    {
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        return Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'status' => $status,
        ]);
    }

    public function test_cross_business_scope_returns_appointments_grouped_by_business(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $businessA = Business::factory()->create(['status' => 'active']);
        $businessB = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $businessA);
        $this->enrollUser($client, $businessB);

        $this->makeAppointment($businessA, $client);
        $this->makeAppointment($businessA, $client);
        $this->makeAppointment($businessB, $client);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['business', 'appointments', 'total_count', 'is_blocked'],
                ],
                'meta' => ['total_businesses', 'total_appointments', 'active_enrollments', 'blocked_enrollments'],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $businessIds = collect($data)->pluck('business.id')->toArray();
        $this->assertContains($businessA->id, $businessIds);
        $this->assertContains($businessB->id, $businessIds);

        $groupA = collect($data)->firstWhere('business.id', $businessA->id);
        $this->assertCount(2, $groupA['appointments']);
        $this->assertEquals(2, $groupA['total_count']);

        $groupB = collect($data)->firstWhere('business.id', $businessB->id);
        $this->assertCount(1, $groupB['appointments']);

        $this->assertEquals(2, $response->json('meta.total_businesses'));
        $this->assertEquals(3, $response->json('meta.total_appointments'));
    }

    public function test_blocked_client_sees_history_with_is_blocked_flag(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $businessA = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $businessA, 'blocked', [
            'blocked_at' => now()->subDays(2),
        ]);

        $this->makeAppointment($businessA, $client, 'completed');

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_blocked']);
        $this->assertCount(1, $data[0]['appointments']);
    }

    public function test_left_business_not_included(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $businessA = Business::factory()->create(['status' => 'active']);
        $businessB = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $businessA, 'active');
        $this->enrollUser($client, $businessB, 'left');

        $this->makeAppointment($businessA, $client);
        $this->makeAppointment($businessB, $client);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);

        $businessIds = collect($data)->pluck('business.id')->toArray();
        $this->assertContains($businessA->id, $businessIds);
        $this->assertNotContains($businessB->id, $businessIds);
    }

    public function test_scope_business_without_header_returns_422(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);

        BusinessContext::clear();

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=business');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'X-Business-Id header required for business scope');
    }

    public function test_scope_business_with_valid_header_returns_paginated_classic(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $business = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $business);
        $this->makeAppointment($business, $client);
        $this->makeAppointment($business, $client);

        Sanctum::actingAs($client);

        $response = $this->getJson(
            '/api/v1/client/appointments?scope=business',
            ['X-Business-Id' => (string) $business->id]
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'business_id', 'client_id', 'status', 'scheduled_at'],
                ],
                'meta',
                'links',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_filter_by_status(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $business = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $business);

        $this->makeAppointment($business, $client, 'completed');
        $this->makeAppointment($business, $client, 'pending');
        $this->makeAppointment($business, $client, 'pending');

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all&status=completed');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);

        $group = $data[0];
        $this->assertCount(1, $group['appointments']);
        $this->assertEquals('completed', $group['appointments'][0]['status']);
    }

    public function test_filter_by_from_to_date(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $business = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($client, $business);

        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        // Appointment within range
        Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'scheduled_at' => Carbon::parse('2025-06-15 10:00:00'),
            'scheduled_until' => Carbon::parse('2025-06-15 11:00:00'),
        ]);

        // Appointment outside range (before)
        Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'scheduled_at' => Carbon::parse('2025-01-01 10:00:00'),
            'scheduled_until' => Carbon::parse('2025-01-01 11:00:00'),
        ]);

        // Appointment outside range (after)
        Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'scheduled_at' => Carbon::parse('2025-12-01 10:00:00'),
            'scheduled_until' => Carbon::parse('2025-12-01 11:00:00'),
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all&from=2025-06-01&to=2025-06-30');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['total_count']);
    }

    public function test_cross_tenant_defense(): void
    {
        $clientA = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $clientB = User::factory()->create(['role' => 'client', 'business_id' => null]);
        $business = Business::factory()->create(['status' => 'active']);

        $this->enrollUser($clientA, $business);
        $this->enrollUser($clientB, $business);

        $this->makeAppointment($business, $clientA);

        // Client B is logged in and should see only their own appointments
        Sanctum::actingAs($clientB);

        $response = $this->getJson('/api/v1/client/appointments?scope=all');

        $response->assertStatus(200);

        $data = $response->json('data');
        // clientB has one group (business) but 0 appointments
        $this->assertCount(1, $data);
        $this->assertCount(0, $data[0]['appointments']);
        $this->assertEquals(0, $data[0]['total_count']);
    }

    public function test_client_with_no_enrollments_returns_empty(): void
    {
        $client = User::factory()->create(['role' => 'client', 'business_id' => null]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/client/appointments?scope=all');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total_businesses', 0)
            ->assertJsonPath('meta.total_appointments', 0);
    }
}
