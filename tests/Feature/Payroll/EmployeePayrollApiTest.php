<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP tests for Employee Payroll API endpoints (Fase 4 — PASO 6).
 */
class EmployeePayrollApiTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $employeeUser;

    private Employee $employee;

    private PayrollPeriod $openPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'is_active' => true,
        ]);

        $this->openPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    /** @test */
    public function test_non_employee_user_returns_403(): void
    {
        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/current')
            ->assertForbidden();

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/history')
            ->assertForbidden();
    }

    // ── /current ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_current_requires_authentication(): void
    {
        $this->getJson('/api/v1/employee/payroll/current')->assertUnauthorized();
    }

    /** @test */
    public function test_current_returns_null_when_no_open_period(): void
    {
        $this->openPeriod->forceFill(['status' => 'closed'])->save();

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/current');

        $response->assertOk();
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta.has_current_period', false);
    }

    /** @test */
    public function test_current_returns_null_data_when_no_record_yet(): void
    {
        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/current');

        $response->assertOk();
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta.has_current_period', true);
        $response->assertJsonPath('meta.has_record', false);
    }

    /** @test */
    public function test_current_returns_record_data_when_record_exists(): void
    {
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'base_salary_snapshot' => '1500.00',
            'gross_total' => '1870.00',
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/current');

        $response->assertOk();
        $response->assertJsonPath('meta.has_current_period', true);
        $response->assertJsonStructure([
            'data' => [
                'id', 'period', 'status', 'base_salary_snapshot',
                'commissions_total', 'tips_total', 'gross_total',
                'is_negative', 'commissions', 'tips', 'adjustments',
            ],
        ]);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.is_negative', false);
    }

    /** @test */
    public function test_current_sets_is_negative_true_for_negative_gross(): void
    {
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '-200.00',
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/current');

        $response->assertOk();
        $response->assertJsonPath('data.is_negative', true);
        $response->assertJsonPath('data.gross_total', '-200.00');
    }

    // ── /history ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/employee/payroll/history')->assertUnauthorized();
    }

    /** @test */
    public function test_history_returns_paginated_closed_period_records(): void
    {
        $closedPeriod = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
        ]);

        PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $closedPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/history');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'period', 'status', 'gross_total', 'is_negative']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', 1);
    }

    /** @test */
    public function test_history_does_not_include_open_period_records(): void
    {
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/history');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 0);
    }

    /** @test */
    public function test_history_respects_per_page_param(): void
    {
        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/history?per_page=5');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 5);
    }

    // ── /periods/{id} ────────────────────────────────────────────────────────

    /** @test */
    public function test_period_detail_returns_record_for_employee(): void
    {
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/periods/{$this->openPeriod->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $record->id);
    }

    /** @test */
    public function test_period_detail_returns_404_when_no_record(): void
    {
        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/periods/{$this->openPeriod->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function test_period_detail_returns_404_for_another_employees_period(): void
    {
        $anotherUser = User::factory()->create(['business_id' => $this->business->id, 'role' => 'employee']);
        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherUser->id,
        ]);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $anotherEmployee->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/periods/{$this->openPeriod->id}");

        $response->assertNotFound();
    }

    // ── /adjustments ─────────────────────────────────────────────────────────

    /** @test */
    public function test_adjustments_returns_paginated_list(): void
    {
        $adjustment = PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'credit',
            'amount' => '100.00',
            'reason' => 'Bono especial',
            'created_by' => User::factory()->create(['business_id' => $this->business->id])->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/adjustments');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonStructure([
            'data' => [['id', 'type', 'amount', 'signed_amount', 'reason', 'is_compensation']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    /** @test */
    public function test_adjustments_can_be_filtered_by_period(): void
    {
        $otherPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $adminUser = User::factory()->create(['business_id' => $this->business->id]);

        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'credit',
            'amount' => '100.00',
            'reason' => 'Bono',
            'created_by' => $adminUser->id,
        ]);

        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $otherPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '50.00',
            'reason' => 'Descuento',
            'created_by' => $adminUser->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/adjustments?period_id={$this->openPeriod->id}");

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
    }

    /** @test */
    public function test_adjustments_marks_compensation_correctly(): void
    {
        $adminUser = User::factory()->create(['business_id' => $this->business->id]);

        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '500.00',
            'reason' => 'Void compensation: payroll_record #42',
            'created_by' => $adminUser->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/adjustments');

        $response->assertOk();
        $response->assertJsonPath('data.0.is_compensation', true);
        $response->assertJsonPath('data.0.signed_amount', '-500.00');
    }

    /** @test */
    public function test_period_detail_includes_compensation_debit_with_is_compensation_flag(): void
    {
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $adminUser = User::factory()->create(['business_id' => $this->business->id]);

        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '480.00',
            'reason' => 'Void compensation: payroll_record #37',
            'created_by' => $adminUser->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/periods/{$this->openPeriod->id}");

        $response->assertOk();
        $response->assertJsonPath('data.adjustments.0.is_compensation', true);
        $response->assertJsonPath('data.adjustments.0.signed_amount', '-480.00');
    }

    /** @test */
    public function test_period_detail_returns_404_when_period_belongs_to_different_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherPeriod = PayrollPeriod::factory()->forBusiness($otherBusiness)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson("/api/v1/employee/payroll/periods/{$otherPeriod->id}")
            ->assertNotFound();
    }

    /** @test */
    public function test_adjustments_does_not_expose_other_employees_data(): void
    {
        $anotherUser = User::factory()->create(['business_id' => $this->business->id, 'role' => 'employee']);
        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherUser->id,
        ]);

        $adminUser = User::factory()->create(['business_id' => $this->business->id]);

        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $anotherEmployee->id,
            'type' => 'credit',
            'amount' => '200.00',
            'reason' => 'Bono de otro empleado',
            'created_by' => $adminUser->id,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/adjustments');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 0);
    }
}
