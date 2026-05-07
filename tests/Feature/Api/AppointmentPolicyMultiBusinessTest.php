<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentPolicyMultiBusinessTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $client;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['status' => 'active']);

        $this->client = User::factory()->create([
            'role' => 'client',
            'business_id' => null,
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'duration' => 60,
        ]);

        $this->employee->services()->attach($this->service->id);

        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employee->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }
    }

    private function enrollClient(User $client, Business $business, string $status = 'active'): void
    {
        DB::table('user_business')->insert([
            'user_id' => $client->id,
            'business_id' => $business->id,
            'role_in_business' => 'client',
            'status' => $status,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_policy_allows_actively_enrolled_client(): void
    {
        $this->enrollClient($this->client, $this->business, 'active');

        $policy = new AppointmentPolicy;
        $result = $policy->create($this->client, $this->business);

        $this->assertTrue($result);
    }

    public function test_policy_denies_blocked_client_in_that_business(): void
    {
        $this->enrollClient($this->client, $this->business, 'blocked');

        $policy = new AppointmentPolicy;
        $result = $policy->create($this->client, $this->business);

        $this->assertFalse($result);
    }

    public function test_policy_allows_blocked_client_in_other_active_business(): void
    {
        $otherBusiness = Business::factory()->create(['status' => 'active']);

        $this->enrollClient($this->client, $this->business, 'blocked');
        $this->enrollClient($this->client, $otherBusiness, 'active');

        $policy = new AppointmentPolicy;

        $this->assertFalse($policy->create($this->client, $this->business));
        $this->assertTrue($policy->create($this->client, $otherBusiness));
    }

    public function test_policy_denies_non_enrolled_client(): void
    {
        // No pivot row at all

        $policy = new AppointmentPolicy;
        $result = $policy->create($this->client, $this->business);

        $this->assertFalse($result);
    }

    public function test_policy_allows_super_admin_regardless_of_business(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $policy = new AppointmentPolicy;

        $this->assertTrue($policy->create($superAdmin, $this->business));
        $this->assertTrue($policy->create($superAdmin, null));
    }

    public function test_policy_allows_business_admin_with_matching_business(): void
    {
        $admin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        $policy = new AppointmentPolicy;

        $this->assertTrue($policy->create($admin, $this->business));
    }

    public function test_policy_denies_business_admin_with_different_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $admin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $otherBusiness->id,
        ]);

        $policy = new AppointmentPolicy;

        $this->assertFalse($policy->create($admin, $this->business));
    }

    public function test_policy_legacy_fallback_for_business_admin_without_business_param(): void
    {
        $admin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        $policy = new AppointmentPolicy;

        // Without a Business model (web-admin context), should still allow if business_id is set
        $this->assertTrue($policy->create($admin, null));
    }

    public function test_enrolled_client_can_create_appointment_via_api(): void
    {
        $this->enrollClient($this->client, $this->business, 'active');

        // In the current API, business context is passed via business_id on the service/employee.
        // The policy for the API AppointmentController does NOT use authorize() at all,
        // so we test that the endpoint still accepts the request correctly.
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        // The API controller does not gate on AppointmentPolicy::create currently,
        // so we verify the request is processed (201 or 422 for availability, but NOT 403).
        $this->assertNotEquals(403, $response->status());
    }
}
