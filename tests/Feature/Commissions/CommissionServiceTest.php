<?php

declare(strict_types=1);

namespace Tests\Feature\Commissions;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 2 — Commission Engine unit tests.
 * Tests the cascade resolution, discount proration, idempotency, and multi-tenant isolation.
 */
class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CommissionService::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Rule cascade tests
    // ─────────────────────────────────────────────────────────────────────

    public function test_cascade_employee_plus_service_wins(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        // Level 1: employee + service (most specific)
        $ruleLevel1 = CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'type' => 'percentage',
            'value' => 15.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 2: employee only
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 3: service only
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => $service->id,
            'type' => 'percentage',
            'value' => 8.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 4: global default
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'fixed',
            'value' => 5.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->service->resolveRuleFor($employee, $service);

        $this->assertNotNull($resolved);
        $this->assertSame($ruleLevel1->id, $resolved->id);
    }

    public function test_cascade_employee_only_when_no_specific(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        // Level 2: employee only (most specific available)
        $ruleLevel2 = CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 3: service only
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => $service->id,
            'type' => 'percentage',
            'value' => 8.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 4: global default
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'fixed',
            'value' => 5.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->service->resolveRuleFor($employee, $service);

        $this->assertNotNull($resolved);
        $this->assertSame($ruleLevel2->id, $resolved->id);
    }

    public function test_cascade_service_only_when_no_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        // Level 3: service only (most specific available)
        $ruleLevel3 = CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => $service->id,
            'type' => 'percentage',
            'value' => 8.00,
            'priority' => 5,
            'is_active' => true,
        ]);
        // Level 4: global default
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'fixed',
            'value' => 5.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->service->resolveRuleFor($employee, $service);

        $this->assertNotNull($resolved);
        $this->assertSame($ruleLevel3->id, $resolved->id);
    }

    public function test_cascade_default_when_nothing_matches(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);

        // Level 4 only: global default
        $ruleLevel4 = CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'fixed',
            'value' => 5.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->service->resolveRuleFor($employee, $service);

        $this->assertNotNull($resolved);
        $this->assertSame($ruleLevel4->id, $resolved->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Idempotency test
    // ─────────────────────────────────────────────────────────────────────

    public function test_idempotent_when_completing_twice(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);

        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'status' => 'completed',
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call twice — should produce the same 1 record, not 2
        $this->service->generateForAppointment($appointment);
        $this->service->generateForAppointment($appointment);

        $this->assertDatabaseCount('commission_records', 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-tenant isolation test
    // ─────────────────────────────────────────────────────────────────────

    public function test_multi_tenant_isolation(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $employeeA = Employee::factory()->create(['business_id' => $businessA->id]);
        $serviceA = Service::factory()->create(['business_id' => $businessA->id]);

        // Rule only exists in business B — should NOT match employee from business A
        CommissionRule::factory()->create([
            'business_id' => $businessB->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $resolved = $this->service->resolveRuleFor($employeeA, $serviceA);

        $this->assertNull($resolved);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Discount proration test (Opción A)
    // ─────────────────────────────────────────────────────────────────────

    public function test_discount_prorates_proportionally_across_lines(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);

        $serviceA = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);
        $serviceB = Service::factory()->create(['business_id' => $business->id, 'price' => 200]);
        $serviceC = Service::factory()->create(['business_id' => $business->id, 'price' => 300]);

        // Global rule: 100% commission (so commission_amount = snapshot)
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 100.00,
            'is_active' => true,
        ]);

        // Appointment with 16.67% discount: catalog $600, final $500
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $serviceA->id,
            'status' => 'completed',
            'final_price' => 500.00,
        ]);

        DB::table('appointment_services')->insert([
            ['appointment_id' => $appointment->id, 'service_id' => $serviceA->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceB->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceC->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $records = $this->service->generateForAppointment($appointment);

        $this->assertCount(3, $records);

        // Compare in integer cents to avoid float imprecision — must be exactly $500.00
        $totalSnapshotInCents = $records->sum(fn ($r) => (int) round($r->service_price_snapshot * 100));
        $this->assertSame(50000, $totalSnapshotInCents);
    }

    public function test_discount_prorate_no_residual_lost(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);

        $serviceA = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);
        $serviceB = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);
        $serviceC = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);

        // Global rule: 100% commission so commission_amount === snapshot
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 100.00,
            'is_active' => true,
        ]);

        // Catalog total $300, final_price $100 → factor = 1/3 (each line $33.33 without adjustment → sum $99.99)
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $serviceA->id,
            'status' => 'completed',
            'final_price' => 100.00,
        ]);

        DB::table('appointment_services')->insert([
            ['appointment_id' => $appointment->id, 'service_id' => $serviceA->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceB->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceC->id, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $records = $this->service->generateForAppointment($appointment);

        $this->assertCount(3, $records);

        // Must be exactly $100.00 in cents — no residual lost due to rounding
        $totalCents = $records->sum(fn ($r) => (int) round($r->service_price_snapshot * 100));
        $this->assertSame(10000, $totalCents);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-service multi-employee test
    // ─────────────────────────────────────────────────────────────────────

    public function test_multi_service_multi_employee_creates_one_record_per_line(): void
    {
        $business = Business::factory()->create();
        $employee1 = Employee::factory()->create(['business_id' => $business->id]);
        $employee2 = Employee::factory()->create(['business_id' => $business->id]);

        $serviceA = Service::factory()->create(['business_id' => $business->id, 'price' => 100]);
        $serviceB = Service::factory()->create(['business_id' => $business->id, 'price' => 150]);
        $serviceC = Service::factory()->create(['business_id' => $business->id, 'price' => 200]);

        // Global rule for all
        CommissionRule::factory()->create([
            'business_id' => $business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee1->id,
            'service_id' => $serviceA->id,
            'status' => 'completed',
        ]);

        // 3 service lines: employee1 handles A+B, employee2 handles C
        DB::table('appointment_services')->insert([
            ['appointment_id' => $appointment->id, 'service_id' => $serviceA->id, 'employee_id' => $employee1->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceB->id, 'employee_id' => $employee1->id, 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'service_id' => $serviceC->id, 'employee_id' => $employee2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $records = $this->service->generateForAppointment($appointment);

        // 3 lines → 3 commission records
        $this->assertCount(3, $records);
        $this->assertDatabaseCount('commission_records', 3);
    }
}
