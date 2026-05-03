<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Exceptions\Payroll\PeriodNotOpenException;
use App\Exceptions\Payroll\RecordAlreadyFinalizedException;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.3: addAdjustment hardening post-approval.
 * Covers: null record, draft record (credit and debit), approved/paid/voided rejection, closed period rejection.
 */
class PayrollAdjustmentFinalizedTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    private Employee $employee;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);

        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $this->period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);
    }

    /** @test */
    public function test_add_adjustment_creates_record_when_no_record_exists(): void
    {
        // No PayrollRecord exists for this employee in this period.
        $this->assertDatabaseMissing('payroll_records', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);

        $adj = $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            50.00,
            'Spot bonus',
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollAdjustment::class, $adj);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adj->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'type' => 'credit',
            'amount' => 50.00,
        ]);

        // No PayrollRecord should have been created as a side effect.
        $this->assertDatabaseMissing('payroll_records', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function test_add_adjustment_recalcs_draft_record_with_bcmath_precision(): void
    {
        // Draft record with a known gross_total.
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'adjustments_total' => '0.00',
            'gross_total' => '100.00',
        ]);

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            0.10,
            'Precision credit',
            $this->adminUser
        );

        $record->refresh();

        // BCMath must produce exact decimal strings — no float drift.
        $this->assertSame('0.10', $record->adjustments_total);
        $this->assertSame('100.10', $record->gross_total);
    }

    /** @test */
    public function test_add_adjustment_recalcs_draft_record_with_debit(): void
    {
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'adjustments_total' => '0.00',
            'gross_total' => '99.00',
        ]);

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'debit',
            0.50,
            'Late penalty',
            $this->adminUser
        );

        $record->refresh();

        $this->assertSame('-0.50', $record->adjustments_total);
        $this->assertSame('98.50', $record->gross_total);
    }

    /** @test */
    public function test_add_adjustment_throws_when_record_is_approved(): void
    {
        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '500.00',
        ]);

        $this->expectException(RecordAlreadyFinalizedException::class);

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            100.00,
            'Should not persist',
            $this->adminUser
        );

        // Transaction rolled back — no adjustment should exist in DB.
        $this->assertDatabaseMissing('payroll_adjustments', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);

        // Record must remain unchanged.
        $record->refresh();
        $this->assertSame('approved', $record->status);
        $this->assertSame('500.00', $record->gross_total);
    }

    /** @test */
    public function test_add_adjustment_throws_when_record_is_paid(): void
    {
        PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '800.00',
        ]);

        $this->expectException(RecordAlreadyFinalizedException::class);

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'debit',
            50.00,
            'Should not persist',
            $this->adminUser
        );

        $this->assertDatabaseMissing('payroll_adjustments', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function test_add_adjustment_throws_when_record_is_voided(): void
    {
        PayrollRecord::factory()->voided()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->expectException(RecordAlreadyFinalizedException::class);

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            10.00,
            'Should not persist',
            $this->adminUser
        );

        $this->assertDatabaseMissing('payroll_adjustments', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function test_add_adjustment_throws_when_period_is_closed(): void
    {
        $closedPeriod = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
        ]);

        $this->expectException(PeriodNotOpenException::class);

        $this->service->addAdjustment(
            $closedPeriod,
            $this->employee,
            'credit',
            100.00,
            'Should fail on closed period',
            $this->adminUser
        );
    }
}
