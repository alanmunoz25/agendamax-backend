<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ensures that flipping the `agendamax.use_business_context` feature flag
 * does NOT change any user-facing behaviour.
 *
 * Every assertion block is run twice:
 *   - flag = false (legacy path, the current default)
 *   - flag = true  (new BusinessContext path)
 *
 * Both must produce identical results.
 */
class BackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private User $employee;

    private User $client;

    private Service $service;

    private ServiceCategory $category;

    private Employee $employeeModel;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['name' => 'Test Biz', 'status' => 'active']);

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employeeModel = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->employee = $employeeUser;

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $this->category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair',
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'price' => 50,
            'duration' => 30,
            'is_active' => true,
        ]);

        $this->employeeModel->services()->attach($this->service->id);

        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employeeModel->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }

        $this->appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employeeModel->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'scheduled_until' => now()->addDay()->setTime(10, 30),
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        // Always clear context between test runs.
        BusinessContext::clear();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Set the feature flag and reset BusinessContext for a clean slate.
     */
    private function withFlag(bool $enabled): void
    {
        config(['agendamax.use_business_context' => $enabled]);
        BusinessContext::clear();
    }

    // ── API: GET /api/v1/auth/user ────────────────────────────────────────

    public function test_auth_user_response_identical_with_flag_false_for_admin(): void
    {
        $this->withFlag(false);
        Sanctum::actingAs($this->admin);
        $responseFlagOff = $this->getJson('/api/v1/auth/user')->assertOk();

        $this->withFlag(true);
        Sanctum::actingAs($this->admin);
        $responseFlagOn = $this->getJson('/api/v1/auth/user')->assertOk();

        $this->assertEquals(
            $responseFlagOff->json('data.id'),
            $responseFlagOn->json('data.id'),
        );
    }

    public function test_auth_user_response_identical_with_flag_false_for_client(): void
    {
        $this->withFlag(false);
        Sanctum::actingAs($this->client);
        $responseFlagOff = $this->getJson('/api/v1/auth/user')->assertOk();

        $this->withFlag(true);
        Sanctum::actingAs($this->client);
        $responseFlagOn = $this->getJson('/api/v1/auth/user')->assertOk();

        $this->assertEquals(
            $responseFlagOff->json('data.id'),
            $responseFlagOn->json('data.id'),
        );
    }

    // ── API: GET /api/v1/appointments (client) ────────────────────────────

    public function test_api_appointments_index_count_identical_for_client(): void
    {
        $this->withFlag(false);
        Sanctum::actingAs($this->client);
        $countFlagOff = count($this->getJson('/api/v1/appointments')->assertOk()->json('data') ?? []);

        $this->withFlag(true);
        BusinessContext::set($this->business->id);
        Sanctum::actingAs($this->client);
        $countFlagOn = count($this->getJson('/api/v1/appointments')->assertOk()->json('data') ?? []);

        $this->assertEquals($countFlagOff, $countFlagOn);
    }

    public function test_api_appointments_index_count_identical_for_admin(): void
    {
        $this->withFlag(false);
        Sanctum::actingAs($this->admin);
        $countFlagOff = count($this->getJson('/api/v1/appointments')->assertOk()->json('data') ?? []);

        $this->withFlag(true);
        Sanctum::actingAs($this->admin);
        $countFlagOn = count($this->getJson('/api/v1/appointments')->assertOk()->json('data') ?? []);

        $this->assertEquals($countFlagOff, $countFlagOn);
    }

    // ── Web: Admin appointments (Inertia) ────────────────────────────────

    public function test_web_appointments_index_ok_for_admin_flag_off(): void
    {
        $this->withFlag(false);
        $response = $this->actingAs($this->admin)->get('/appointments');
        $response->assertOk();
    }

    public function test_web_appointments_index_ok_for_admin_flag_on(): void
    {
        $this->withFlag(true);
        $response = $this->actingAs($this->admin)->get('/appointments');
        $response->assertOk();
    }

    // ── Web: Admin services (Inertia) ────────────────────────────────────

    public function test_web_services_index_ok_for_admin_flag_off(): void
    {
        $this->withFlag(false);
        $response = $this->actingAs($this->admin)->get('/services');
        $response->assertOk();
    }

    public function test_web_services_index_ok_for_admin_flag_on(): void
    {
        $this->withFlag(true);
        $response = $this->actingAs($this->admin)->get('/services');
        $response->assertOk();
    }

    // ── Web: Admin employees (Inertia) ───────────────────────────────────

    public function test_web_employees_index_ok_for_admin_flag_off(): void
    {
        $this->withFlag(false);
        $response = $this->actingAs($this->admin)->get('/employees');
        $response->assertOk();
    }

    public function test_web_employees_index_ok_for_admin_flag_on(): void
    {
        $this->withFlag(true);
        $response = $this->actingAs($this->admin)->get('/employees');
        $response->assertOk();
    }

    // ── Scope parity — flag off vs flag on produce same record sets ───────

    public function test_scope_produces_same_appointment_ids_flag_off_vs_on(): void
    {
        // Create a second business + appointment that should NEVER appear.
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create(['business_id' => $otherBusiness->id, 'role' => 'business_admin']);
        $otherEmployee = Employee::factory()->create(['business_id' => $otherBusiness->id, 'user_id' => $otherAdmin->id]);
        $otherService = Service::factory()->create(['business_id' => $otherBusiness->id]);
        $otherClient = User::factory()->create(['business_id' => $otherBusiness->id, 'role' => 'client']);
        Appointment::factory()->create([
            'business_id' => $otherBusiness->id,
            'service_id' => $otherService->id,
            'employee_id' => $otherEmployee->id,
            'client_id' => $otherClient->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'scheduled_until' => now()->addDay()->setTime(11, 30),
        ]);

        // Flag OFF — legacy path.
        $this->withFlag(false);
        $this->actingAs($this->admin);
        $idsFlagOff = Appointment::pluck('id')->sort()->values()->toArray();

        // Flag ON — new context path.
        $this->withFlag(true);
        BusinessContext::set($this->business->id);
        $this->actingAs($this->admin);
        $idsFlagOn = Appointment::pluck('id')->sort()->values()->toArray();

        $this->assertEquals($idsFlagOff, $idsFlagOn, 'Appointment IDs should be identical regardless of flag.');
    }

    public function test_scope_produces_same_service_ids_flag_off_vs_on(): void
    {
        $otherBusiness = Business::factory()->create();
        Service::factory()->create(['business_id' => $otherBusiness->id]);

        $this->withFlag(false);
        $this->actingAs($this->admin);
        $idsFlagOff = Service::pluck('id')->sort()->values()->toArray();

        $this->withFlag(true);
        BusinessContext::set($this->business->id);
        $this->actingAs($this->admin);
        $idsFlagOn = Service::pluck('id')->sort()->values()->toArray();

        $this->assertEquals($idsFlagOff, $idsFlagOn);
    }

    public function test_scope_produces_same_employee_ids_flag_off_vs_on(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherUser = User::factory()->create(['business_id' => $otherBusiness->id]);
        Employee::factory()->create(['business_id' => $otherBusiness->id, 'user_id' => $otherUser->id]);

        $this->withFlag(false);
        $this->actingAs($this->admin);
        $idsFlagOff = Employee::pluck('id')->sort()->values()->toArray();

        $this->withFlag(true);
        BusinessContext::set($this->business->id);
        $this->actingAs($this->admin);
        $idsFlagOn = Employee::pluck('id')->sort()->values()->toArray();

        $this->assertEquals($idsFlagOff, $idsFlagOn);
    }
}
