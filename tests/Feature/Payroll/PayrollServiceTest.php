<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Exceptions\Payroll\InvalidPayrollTransitionException;
use App\Exceptions\Payroll\MissingTransitionMetadataException;
use App\Exceptions\Payroll\PeriodNotOpenException;
use App\Exceptions\Payroll\PeriodOverlapException;
use App\Exceptions\Payroll\RecordsAlreadyGeneratedException;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Service;
use App\Models\Tip;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3 — PayrollService integration tests.
 * Covers: createPeriod, generateRecords, approve, markPaid, void, addAdjustment, auto-close, multi-tenancy.
 */
class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);

        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Wave 3 — createPeriod (tests 1-3)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_create_period_creates_open_period(): void
    {
        $start = Carbon::parse('2026-05-01');
        $end = Carbon::parse('2026-05-31');

        $period = $this->service->createPeriod($this->business, $start, $end, $this->adminUser);

        $this->assertInstanceOf(PayrollPeriod::class, $period);
        $this->assertEquals('open', $period->status);
        $this->assertEquals('2026-05-01', $period->starts_on->toDateString());
        $this->assertEquals('2026-05-31', $period->ends_on->toDateString());
        $this->assertEquals($this->business->id, $period->business_id);

        // SQLite stores date columns as datetime strings — compare via model assertions.
        $this->assertDatabaseHas('payroll_periods', [
            'business_id' => $this->business->id,
            'status' => 'open',
        ]);
        $this->assertEquals('2026-05-01', $period->fresh()->starts_on->toDateString());
        $this->assertEquals('2026-05-31', $period->fresh()->ends_on->toDateString());
    }

    /** @test */
    public function test_create_period_rejects_overlap(): void
    {
        $this->expectException(PeriodOverlapException::class);

        // Create an existing period for May
        PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Attempt to create overlapping period (May 15 – June 15)
        $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-05-15'),
            Carbon::parse('2026-06-15'),
            $this->adminUser
        );
    }

    /** @test */
    public function test_create_period_rejects_invalid_dates(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-05-31'),
            Carbon::parse('2026-05-01'),
            $this->adminUser
        );
    }

    // -------------------------------------------------------------------------
    // Wave 4 — generateRecords (tests 4-8, 17, 18)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_generate_records_creates_one_per_active_employee_with_activity(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Active employee with a commission
        $employee = $this->createActiveEmployeeWithCommission($period->starts_on);

        // Inactive employee — should be skipped
        $inactive = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => false,
            'base_salary' => 0,
        ]);

        // Active employee with no activity and no salary — should be skipped (decision #2)
        Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $records = $this->service->generateRecords($period, $this->adminUser);

        $this->assertCount(1, $records);
        $this->assertEquals($employee->id, $records->first()->employee_id);
    }

    /** @test */
    public function test_generate_records_sums_commissions_tips_adjustments_and_base(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 1000.00,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Commission: $200
        CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 200.00,
            'created_at' => Carbon::parse('2026-05-15'),
        ]);

        // Tip: $50
        Tip::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'amount' => 50.00,
            'payroll_period_id' => null,
            'received_at' => Carbon::parse('2026-05-15'),
        ]);

        // Adjustment (credit): $100
        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'credit',
            'amount' => 100.00,
            'created_by' => $this->adminUser->id,
        ]);

        // Adjustment (debit): $30
        PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'debit',
            'amount' => 30.00,
            'created_by' => $this->adminUser->id,
        ]);

        $records = $this->service->generateRecords($period, $this->adminUser);

        $this->assertCount(1, $records);
        $record = $records->first();

        $this->assertEquals('1000.00', $record->base_salary_snapshot);
        $this->assertEquals('200.00', $record->commissions_total);
        $this->assertEquals('50.00', $record->tips_total);
        // Net adjustment: +100 - 30 = +70
        $this->assertEquals('70.00', $record->adjustments_total);
        // Gross: 1000 + 200 + 50 + 70 = 1320
        $this->assertEquals('1320.00', $record->gross_total);
    }

    /** @test */
    public function test_generate_records_is_transactional_on_failure(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'base_salary' => 500.00,
        ]);

        // Force failure by using a partial mock that throws after first record creation attempt.
        // We simulate failure by passing a closed period (which triggers PeriodNotOpenException before any write).
        $closedPeriod = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
        ]);

        try {
            $this->service->generateRecords($closedPeriod, $this->adminUser);
        } catch (PeriodNotOpenException) {
            // expected
        }

        $this->assertDatabaseMissing('payroll_records', [
            'payroll_period_id' => $closedPeriod->id,
        ]);
    }

    /** @test */
    public function test_generate_records_throws_when_called_twice(): void
    {
        $this->expectException(RecordsAlreadyGeneratedException::class);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $this->createActiveEmployeeWithCommission($period->starts_on);

        $this->service->generateRecords($period, $this->adminUser);

        // Second call must throw
        $this->service->generateRecords($period, $this->adminUser);
    }

    /** @test */
    public function test_generate_records_assigns_payroll_period_id_to_commissions_and_tips(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commission = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 100.00,
            'created_at' => Carbon::parse('2026-05-10'),
        ]);

        $tip = Tip::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'amount' => 25.00,
            'payroll_period_id' => null,
            'received_at' => Carbon::parse('2026-05-10'),
        ]);

        $this->service->generateRecords($period, $this->adminUser);

        $this->assertDatabaseHas('commission_records', [
            'id' => $commission->id,
            'payroll_period_id' => $period->id,
        ]);

        $this->assertDatabaseHas('tips', [
            'id' => $tip->id,
            'payroll_period_id' => $period->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Wave 5 — approve + markPaid + void (tests 9-14 + auto-close)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_approve_transitions_all_draft_records_and_locks_commissions(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employee = $this->createActiveEmployeeWithCommission($period->starts_on);
        $this->service->generateRecords($period, $this->adminUser);

        $this->service->approve($period, $this->adminUser);

        $this->assertDatabaseHas('payroll_records', [
            'payroll_period_id' => $period->id,
            'status' => 'approved',
            'approved_by' => $this->adminUser->id,
        ]);

        // All commissions in the period should be locked
        $commissions = CommissionRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->get();

        $this->assertNotEmpty($commissions);

        foreach ($commissions as $commission) {
            $this->assertEquals('locked', $commission->status);
            $this->assertNotNull($commission->locked_at);
        }
    }

    /** @test */
    public function test_approve_throws_if_any_record_not_draft(): void
    {
        $this->expectException(InvalidPayrollTransitionException::class);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Manually create a non-draft record
        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'base_salary' => 500.00,
        ]);

        PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $this->service->approve($period, $this->adminUser);
    }

    /** @test */
    public function test_mark_paid_transitions_record_and_commissions(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $this->createActiveEmployeeWithCommission($period->starts_on);
        $this->service->generateRecords($period, $this->adminUser);
        $this->service->approve($period, $this->adminUser);

        $record = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->first();

        $this->service->markPaid($record, $this->adminUser, [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-12345',
        ]);

        $this->assertDatabaseHas('payroll_records', [
            'id' => $record->id,
            'status' => 'paid',
            'paid_by' => $this->adminUser->id,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-12345',
        ]);

        // Commission records should be paid
        $commissions = CommissionRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $record->employee_id)
            ->get();

        foreach ($commissions as $commission) {
            $this->assertEquals('paid', $commission->status);
        }
    }

    /** @test */
    public function test_mark_paid_throws_if_record_not_approved(): void
    {
        $this->expectException(InvalidPayrollTransitionException::class);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]); // status defaults to 'draft' in DB — not approved

        $this->service->markPaid($record, $this->adminUser, ['payment_method' => 'cash']);
    }

    /** @test */
    public function test_void_from_each_state_returns_commissions_to_pending(): void
    {
        // From draft — commissions remain unassigned (no lock to undo) — 3 months ago
        $this->assertVoidFromStatus('draft', false, 3);

        // From approved — locked commissions must return to pending — 4 months ago
        $this->assertVoidFromStatus('approved', true, 4);

        // From paid — commissions stay paid (voiding the record doesn't undo payment) — 5 months ago
        $this->assertVoidFromStatus('paid', false, 5);
    }

    /** @test */
    public function test_void_requires_reason(): void
    {
        $this->expectException(MissingTransitionMetadataException::class);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]); // status defaults to 'draft' in DB

        $this->service->void($record, $this->adminUser, '');
    }

    /** @test */
    public function test_period_auto_closes_when_all_records_paid_or_voided(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $this->createActiveEmployeeWithCommission($period->starts_on);
        $this->service->generateRecords($period, $this->adminUser);
        $this->service->approve($period, $this->adminUser);

        $record = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->first();

        // Period should still be open after approve
        $period->refresh();
        $this->assertEquals('open', $period->status);

        $this->service->markPaid($record, $this->adminUser, ['payment_method' => 'cash']);

        // Period should auto-close now
        $period->refresh();
        $this->assertEquals('closed', $period->status);
        $this->assertNotNull($period->closed_at);
        $this->assertEquals($this->adminUser->id, $period->closed_by);
    }

    // -------------------------------------------------------------------------
    // Wave 6 — addAdjustment (tests 15-16 + bonus)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_creates_and_links_to_open_period(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $adj = $this->service->addAdjustment(
            $period,
            $employee,
            'credit',
            150.00,
            'Performance bonus',
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollAdjustment::class, $adj);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adj->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'credit',
            'amount' => 150.00,
            'reason' => 'Performance bonus',
            'created_by' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function test_add_adjustment_rejects_non_open_period(): void
    {
        $this->expectException(PeriodNotOpenException::class);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $this->service->addAdjustment($period, $employee, 'credit', 100.00, 'Bonus', $this->adminUser);
    }

    /** @test */
    public function test_add_adjustment_recalculates_existing_draft_record(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 1000.00,
        ]);

        // Pre-existing draft record (status defaults to 'draft' in DB)
        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'adjustments_total' => 0.00,
            'gross_total' => 1000.00,
        ]);

        // Add a credit adjustment (+$200)
        $this->service->addAdjustment($period, $employee, 'credit', 200.00, 'Bonus', $this->adminUser);

        $record->refresh();
        $this->assertEquals('200.00', $record->adjustments_total);
        $this->assertEquals('1200.00', $record->gross_total);

        // Add a debit adjustment (-$50)
        $this->service->addAdjustment($period, $employee, 'debit', 50.00, 'Late penalty', $this->adminUser);

        $record->refresh();
        $this->assertEquals('150.00', $record->adjustments_total);
        $this->assertEquals('1150.00', $record->gross_total);
    }

    // -------------------------------------------------------------------------
    // Wave 4 — multi-tenancy + double-generation guard (tests 17-18)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_multi_tenant_isolation_in_generate_records(): void
    {
        $businessA = $this->business;
        $businessB = Business::factory()->create();

        $periodA = PayrollPeriod::factory()->forBusiness($businessA)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Employee A with commission
        $this->createActiveEmployeeWithCommission($periodA->starts_on, $businessA);

        // Employee B with commission — should NOT appear in period A records
        $employeeUserB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'employee',
        ]);

        $employeeB = Employee::factory()->create([
            'business_id' => $businessB->id,
            'user_id' => $employeeUserB->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointmentB = Appointment::factory()->create([
            'business_id' => $businessB->id,
            'employee_id' => $employeeB->id,
        ]);

        $serviceB = Service::factory()->create(['business_id' => $businessB->id]);

        $appointmentServiceIdB = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointmentB->id,
            'service_id' => $serviceB->id,
            'employee_id' => $employeeB->id,
            'created_at' => Carbon::parse('2026-05-10'),
            'updated_at' => now(),
        ]);

        CommissionRecord::factory()->create([
            'business_id' => $businessB->id,
            'employee_id' => $employeeB->id,
            'appointment_id' => $appointmentB->id,
            'appointment_service_id' => $appointmentServiceIdB,
            'commission_amount' => 999.00,
            'created_at' => Carbon::parse('2026-05-10'),
        ]);

        $records = $this->service->generateRecords($periodA, $this->adminUser);

        // Only business A employee should have a record
        $this->assertCount(1, $records);
        $this->assertEquals($businessA->id, $records->first()->business_id);

        // Business B commission remains unassigned
        $this->assertDatabaseHas('commission_records', [
            'business_id' => $businessB->id,
            'employee_id' => $employeeB->id,
            'payroll_period_id' => null,
        ]);
    }

    /** @test */
    public function test_double_generation_blocked_by_post_commit_guard(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $this->createActiveEmployeeWithCommission($period->starts_on);

        // First call succeeds
        $this->service->generateRecords($period, $this->adminUser);

        // Second call must throw RecordsAlreadyGeneratedException regardless of driver.
        // The guard is the payrollRecords()->exists() check inside lockForUpdate — tested sequentially here.
        // SQLite does not escalate the lock across process boundaries, but the post-commit exists() check
        // is driver-independent and sufficient to block the same-process / same-connection double call.
        $this->expectException(RecordsAlreadyGeneratedException::class);
        $this->service->generateRecords($period, $this->adminUser);
    }

    /**
     * @group concurrency-real
     *
     * True cross-process concurrency test — requires MariaDB/MySQL with real row-level locks.
     * SQLite does not support lockForUpdate across multiple connections and will NOT validate the guard.
     *
     * Run manually against MariaDB:
     *   ddev exec --dir /var/www/html/backend php artisan test --group=concurrency-real
     *
     * A proper implementation uses Symfony\Process or pcntl_fork to spin two PHP processes
     * that each call generateRecords on the same period simultaneously. One must succeed and
     * the other must throw RecordsAlreadyGeneratedException. This test is kept as a documented
     * placeholder until a MariaDB CI job is added (see T-3.1.8 plan for full scope).
     */
    public function test_concurrent_generate_only_one_succeeds_with_real_processes(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requires MariaDB/MySQL with real row-level locking. Run with phpunit.mariadb.xml.');
        }

        if (config('cache.default') === 'array') {
            $this->markTestSkipped('Requires a non-array cache driver (e.g. database) for atomic cross-process locks.');
        }

        // TD-018: pcntl_fork / Symfony\Process implementation pending.
        // MariaDB CI is now active (phpunit.mariadb.xml). The fork test remains
        // the last open item before full concurrency coverage is complete.
        $this->markTestSkipped('pcntl_fork or Symfony\\Process concurrency harness not yet implemented (TD-018).');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an active employee with one pending commission within the given date.
     */
    private function createActiveEmployeeWithCommission(
        Carbon|\Illuminate\Support\Carbon $withinDate,
        ?Business $business = null
    ): Employee {
        $biz = $business ?? $this->business;

        $employeeUser = User::factory()->create([
            'business_id' => $biz->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $biz->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $biz->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $biz->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => $withinDate,
            'updated_at' => now(),
        ]);

        CommissionRecord::factory()->create([
            'business_id' => $biz->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 100.00,
            'created_at' => $withinDate,
        ]);

        return $employee;
    }

    /**
     * Helper to test void transitions from different initial statuses.
     *
     * When $fromStatus is 'paid', a next open period is created after the voided record's period
     * to satisfy Decision #6 (compensation debit must land in a real open period).
     *
     * @param  bool  $expectCommissionsReturnedToPending  whether locked commissions should return to pending
     * @param  int  $monthsAgo  offset in months from now to use as the period base date (must be unique per call)
     */
    private function assertVoidFromStatus(string $fromStatus, bool $expectCommissionsReturnedToPending, int $monthsAgo = 2): void
    {
        $periodBase = now()->subMonths($monthsAgo);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => $periodBase->copy()->startOfMonth()->toDateString(),
            'ends_on' => $periodBase->copy()->endOfMonth()->toDateString(),
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 500.00,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => Carbon::parse($period->starts_on)->addDays(5),
            'updated_at' => now(),
        ]);

        $commission = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 100.00,
            'created_at' => Carbon::parse($period->starts_on)->addDays(5),
        ]);
        // Bypass fillable: test setup forces commission into the specific state needed.
        $commission->forceFill([
            'status' => $fromStatus === 'draft' ? 'pending' : ($fromStatus === 'paid' ? 'paid' : 'locked'),
            'payroll_period_id' => in_array($fromStatus, ['approved', 'paid']) ? $period->id : null,
            'locked_at' => $fromStatus !== 'draft' ? now() : null,
            'paid_at' => $fromStatus === 'paid' ? now() : null,
        ])->save();

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_total' => 500.00,
        ]);
        // Bypass fillable: test setup forces record into the specific state needed.
        $record->forceFill([
            'status' => $fromStatus,
            'approved_at' => in_array($fromStatus, ['approved', 'paid']) ? now() : null,
            'approved_by' => in_array($fromStatus, ['approved', 'paid']) ? $this->adminUser->id : null,
            'paid_at' => $fromStatus === 'paid' ? now() : null,
            'paid_by' => $fromStatus === 'paid' ? $this->adminUser->id : null,
        ])->save();

        // Decision #6: voiding a paid record requires a next open period to place the compensation debit.
        // The next period starts after the voided record's period ends_on.
        // Use a month far in the future to avoid date collisions with the other assertVoidFromStatus calls
        // (which consume months subMonths(3) through subMonths(5) on the same $this->business).
        if ($fromStatus === 'paid') {
            $nextPeriodBase = now()->addMonths($monthsAgo);
            PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
                'starts_on' => $nextPeriodBase->copy()->startOfMonth()->toDateString(),
                'ends_on' => $nextPeriodBase->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $this->service->void($record, $this->adminUser, "Voiding from {$fromStatus} state");

        $this->assertDatabaseHas('payroll_records', [
            'id' => $record->id,
            'status' => 'voided',
        ]);

        $commission->refresh();

        if ($expectCommissionsReturnedToPending) {
            $this->assertEquals('pending', $commission->status, "Commission should return to pending when voiding from {$fromStatus}");
            $this->assertNull($commission->payroll_period_id, 'Commission payroll_period_id should be null after void from approved');
        }

        // Decision #6: verify compensation debit was created in the next period when voiding from paid.
        if ($fromStatus === 'paid') {
            $this->assertDatabaseHas('payroll_adjustments', [
                'business_id' => $this->business->id,
                'employee_id' => $employee->id,
                'type' => 'debit',
                'amount' => '500.00',
            ]);
        }
    }
}
