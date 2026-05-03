<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP tests for Payroll web controllers (Fase 4 — PASO 1).
 */
class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
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
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /** @test */
    public function test_index_renders_payroll_periods_page(): void
    {
        PayrollPeriod::factory()->forBusiness($this->business)->create();

        $response = $this->actingAs($this->admin)->get('/payroll/periods');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Index')
            ->has('periods')
            ->has('summary')
            ->has('filters')
        );
    }

    /** @test */
    public function test_index_requires_authentication(): void
    {
        $this->get('/payroll/periods')->assertRedirect('/login');
    }

    /** @test */
    public function test_index_returns_empty_periods_for_new_business(): void
    {
        $response = $this->actingAs($this->admin)->get('/payroll/periods');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('periods.data', 0)
            ->where('summary.open_count', 0)
        );
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    /** @test */
    public function test_store_creates_payroll_period(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/periods', [
            'start' => '2026-05-01',
            'end' => '2026-05-31',
        ]);

        $response->assertRedirect('/payroll/periods');

        $this->assertDatabaseCount('payroll_periods', 1);
        $this->assertDatabaseHas('payroll_periods', [
            'business_id' => $this->business->id,
            'status' => 'open',
        ]);
    }

    /** @test */
    public function test_store_validates_end_after_start(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/periods', [
            'start' => '2026-05-31',
            'end' => '2026-05-01',
        ]);

        $response->assertSessionHasErrors('end');
        $this->assertDatabaseCount('payroll_periods', 0);
    }

    /** @test */
    public function test_store_returns_error_on_overlapping_period(): void
    {
        PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->post('/payroll/periods', [
            'start' => '2026-05-15',
            'end' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors('start');
        $this->assertDatabaseCount('payroll_periods', 1);
    }

    /** @test */
    public function test_store_requires_start_and_end(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/periods', []);

        $response->assertSessionHasErrors(['start', 'end']);
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function test_show_renders_period_detail_page(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Show')
            ->has('period')
            ->has('records')
            ->has('period_summary')
            ->has('can')
            ->has('employees')
        );
    }

    /** @test */
    public function test_show_includes_next_open_period_when_available(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        $nextPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('next_open_period.id', $nextPeriod->id)
        );
    }

    /** @test */
    public function test_show_next_open_period_is_null_when_none_exists(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('next_open_period', null)
        );
    }

    /** @test */
    public function test_show_can_flags_based_on_period_state(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.generate', true)
            ->where('can.approve_all', false)
            ->where('can.add_adjustment', true)
        );
    }

    // ── Generate ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_generate_creates_records_for_active_employees(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => Carbon::now()->startOfMonth()->toDateString(),
            'ends_on' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // Employee with base salary so they're not skipped
        $this->employee->forceFill(['base_salary' => 1000])->save();

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/generate");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payroll_records', [
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function test_generate_returns_info_when_records_already_exist(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/generate");

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_approve_approves_all_draft_records(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payroll_records', [
            'id' => $record->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function test_approve_returns_error_when_records_not_all_draft(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $anotherEmployeeUser = User::factory()->create(['business_id' => $this->business->id, 'role' => 'employee']);
        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherEmployeeUser->id,
        ]);

        PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $anotherEmployee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHasErrors('period');
    }

    // ── MarkPaid ──────────────────────────────────────────────────────────────

    /** @test */
    public function test_mark_paid_transitions_approved_record_to_paid(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/mark-paid", [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payroll_records', [
            'id' => $record->id,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);
    }

    /** @test */
    public function test_mark_paid_requires_payment_method(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/mark-paid", []);

        $response->assertSessionHasErrors('payment_method');
    }

    /** @test */
    public function test_mark_paid_rejects_invalid_payment_method(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/mark-paid", [
            'payment_method' => 'bitcoin',
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    /** @test */
    public function test_mark_paid_returns_error_for_non_approved_record(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/mark-paid", [
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('record');
    }

    // ── Void ─────────────────────────────────────────────────────────────────

    /** @test */
    public function test_void_transitions_draft_record_to_voided(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/void", [
            'reason' => 'Anulado por error de carga de datos.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payroll_records', [
            'id' => $record->id,
            'status' => 'voided',
        ]);
    }

    /** @test */
    public function test_void_requires_reason_with_at_least_10_chars(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/void", [
            'reason' => 'corto',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    /** @test */
    public function test_void_paid_record_without_next_period_returns_error(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        $record = PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '1000.00',
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/void", [
            'reason' => 'Pago registrado incorrectamente.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('record');
    }

    /** @test */
    public function test_void_paid_record_creates_compensation_in_next_period(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        $nextPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $record = PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '1500.00',
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/records/{$record->id}/void", [
            'reason' => 'Error en el cálculo del pago de nómina.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_records', ['id' => $record->id, 'status' => 'voided']);
        $this->assertDatabaseHas('payroll_adjustments', [
            'payroll_period_id' => $nextPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
        ]);
    }

    // ── Add Adjustment ────────────────────────────────────────────────────────

    /** @test */
    public function test_add_adjustment_creates_credit_adjustment(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/adjustments", [
            'employee_id' => $this->employee->id,
            'type' => 'credit',
            'amount' => '200.00',
            'reason' => 'Bono de productividad',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payroll_adjustments', [
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'type' => 'credit',
        ]);
    }

    /** @test */
    public function test_add_adjustment_requires_all_fields(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/adjustments", []);

        $response->assertSessionHasErrors(['employee_id', 'type', 'amount', 'reason']);
    }

    /** @test */
    public function test_add_adjustment_rejects_negative_amount(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/adjustments", [
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '-100',
            'reason' => 'Razón del ajuste',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    /** @test */
    public function test_add_adjustment_returns_error_for_finalized_record(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/payroll/periods/{$period->id}/adjustments", [
            'employee_id' => $this->employee->id,
            'type' => 'credit',
            'amount' => '100',
            'reason' => 'Intento de ajuste después de pago',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('employee_id');
    }

    // ── Employee ──────────────────────────────────────────────────────────────

    /** @test */
    public function test_employee_page_renders_record_detail(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Employee')
            ->has('period')
            ->has('record')
            ->has('transitions')
        );
    }

    /** @test */
    public function test_employee_page_returns_404_when_no_record(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->get("/payroll/periods/{$period->id}/employees/{$this->employee->id}");

        $response->assertNotFound();
    }
}
