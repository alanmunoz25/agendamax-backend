<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Exceptions\Payroll\NoOpenPeriodForCompensationException;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.5 void compensation tests.
 *
 * Covers Decision #6: voiding a paid PayrollRecord must create a compensation debit
 * in the next open period. Voids from draft or approved do not generate compensation.
 */
class PayrollVoidCompensationTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);

        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a paid PayrollRecord in a closed period for the shared employee.
     * The period ends before $nextStartsOn so a subsequent open period can be attached.
     *
     * @return array{record: PayrollRecord, period: PayrollPeriod}
     */
    private function createPaidRecord(string $grossTotal = '500.00'): array
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
        ]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => $grossTotal,
        ]);
        // Bypass fillable: test setup forces record into paid state.
        $record->forceFill([
            'status' => 'paid',
            'approved_at' => now()->subDays(2),
            'approved_by' => $this->adminUser->id,
            'paid_at' => now()->subDay(),
            'paid_by' => $this->adminUser->id,
            'payment_method' => 'cash',
        ])->save();

        return ['record' => $record, 'period' => $period];
    }

    /**
     * Create an open period that starts after January 2026 (after the paid record's period).
     */
    private function createNextOpenPeriod(): PayrollPeriod
    {
        return PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-28',
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy path — compensation debit created
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_paid_record_creates_compensation_debit_in_next_open_period(): void
    {
        ['record' => $record] = $this->createPaidRecord('500.00');
        $nextPeriod = $this->createNextOpenPeriod();

        $this->service->void($record, $this->adminUser, 'Payment was erroneous');

        $record->refresh();
        $this->assertEquals('voided', $record->status);

        $this->assertDatabaseHas('payroll_adjustments', [
            'business_id' => $this->business->id,
            'payroll_period_id' => $nextPeriod->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '500.00',
            'created_by' => $this->adminUser->id,
        ]);

        $adjustment = PayrollAdjustment::withoutGlobalScopes()
            ->where('payroll_period_id', $nextPeriod->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals('-500.00', $adjustment->signedAmount());
    }

    /** @test */
    public function test_void_paid_record_recalcs_draft_record_in_next_period(): void
    {
        ['record' => $record] = $this->createPaidRecord('500.00');
        $nextPeriod = $this->createNextOpenPeriod();

        // Create a draft record for the same employee in the next period.
        $draftRecord = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $nextPeriod->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '300.00',
            'adjustments_total' => '0.00',
        ]);
        // Bypass fillable: force to draft status explicitly.
        $draftRecord->forceFill(['status' => 'draft'])->save();

        $this->service->void($record, $this->adminUser, 'Error in January payroll');

        $draftRecord->refresh();
        $this->assertEquals('-500.00', $draftRecord->adjustments_total);
        $this->assertEquals('-200.00', $draftRecord->gross_total);
    }

    // -------------------------------------------------------------------------
    // Error path — no open period
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_paid_record_throws_when_no_open_period_exists(): void
    {
        $this->expectException(NoOpenPeriodForCompensationException::class);

        ['record' => $record] = $this->createPaidRecord('500.00');

        // No next open period created — void must fail.
        $this->service->void($record, $this->adminUser, 'Test void');

        // The record must remain paid (transaction rolled back).
        $record->refresh();
        $this->assertEquals('paid', $record->status);

        $this->assertDatabaseMissing('payroll_adjustments', [
            'employee_id' => $this->employee->id,
            'type' => 'debit',
        ]);
    }

    /** @test */
    public function test_void_paid_record_rolls_back_entirely_when_no_open_period(): void
    {
        ['record' => $record] = $this->createPaidRecord('500.00');

        try {
            $this->service->void($record, $this->adminUser, 'Should roll back');
        } catch (NoOpenPeriodForCompensationException) {
            // Expected — verify record is still paid and no adjustment was created.
        }

        $record->refresh();
        $this->assertEquals('paid', $record->status, 'Record must remain paid when void is rolled back');

        $this->assertDatabaseMissing('payroll_adjustments', [
            'employee_id' => $this->employee->id,
            'type' => 'debit',
        ]);
    }

    // -------------------------------------------------------------------------
    // Commission integrity — commissions stay paid after void
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_paid_record_does_not_alter_commissions(): void
    {
        ['record' => $record, 'period' => $period] = $this->createPaidRecord('500.00');
        $this->createNextOpenPeriod();

        // Create a paid commission linked to the period and employee.
        $commission = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_period_id' => $period->id,
        ]);
        // Bypass fillable: force commission to paid status.
        $commission->forceFill([
            'status' => 'paid',
            'locked_at' => now()->subDay(),
            'paid_at' => now(),
        ])->save();

        $this->service->void($record, $this->adminUser, 'Void with paid commissions');

        $commission->refresh();
        $this->assertEquals('paid', $commission->status, 'Commission must remain paid after voiding a paid record');
    }

    // -------------------------------------------------------------------------
    // No compensation for non-paid voids
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_approved_record_does_not_create_compensation(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
        ]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '400.00',
        ]);
        // Bypass fillable: force to approved state.
        $record->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->adminUser->id,
        ])->save();

        $this->service->void($record, $this->adminUser, 'Approved but incorrect');

        $record->refresh();
        $this->assertEquals('voided', $record->status);

        $this->assertDatabaseMissing('payroll_adjustments', [
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'reason' => "Void compensation: payroll_record #{$record->id}",
        ]);
    }

    /** @test */
    public function test_void_draft_record_does_not_create_compensation(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
        ]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '200.00',
        ]);
        // Factory default is draft status.

        $this->service->void($record, $this->adminUser, 'Draft mistake');

        $record->refresh();
        $this->assertEquals('voided', $record->status);

        $this->assertDatabaseMissing('payroll_adjustments', [
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'reason' => "Void compensation: payroll_record #{$record->id}",
        ]);
    }

    // -------------------------------------------------------------------------
    // Period selection — nearest open period wins
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_paid_record_picks_nearest_open_period_when_multiple_exist(): void
    {
        ['record' => $record] = $this->createPaidRecord('600.00');

        // Period B — starts February 2026 (nearest after January 2026).
        $periodB = PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-28',
        ]);

        // Period C — starts March 2026 (further out).
        $periodC = PayrollPeriod::factory()->forBusiness($this->business)->open()->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
        ]);

        $this->service->void($record, $this->adminUser, 'Pick nearest period');

        // Compensation must go to period B (the nearest open period).
        $this->assertDatabaseHas('payroll_adjustments', [
            'payroll_period_id' => $periodB->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
            'amount' => '600.00',
        ]);

        // Period C must not have any adjustment for this void.
        $this->assertDatabaseMissing('payroll_adjustments', [
            'payroll_period_id' => $periodC->id,
            'employee_id' => $this->employee->id,
            'type' => 'debit',
        ]);
    }
}
