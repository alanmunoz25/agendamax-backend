<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

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
 * AgendaMax Payroll Phase 3.1 — T-3.1.9: Input validation for addAdjustment and markPaid.
 * Covers: M-02 (invalid type), M-03 (non-positive amount), M-04 (invalid payment method).
 */
class PayrollValidationTest extends TestCase
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
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);
    }

    // -------------------------------------------------------------------------
    // addAdjustment — type validation (M-02)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_throws_on_invalid_type_uppercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Invalid adjustment type 'CREDIT'/");

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'CREDIT',
            10.00,
            'Should fail',
            $this->adminUser
        );
    }

    /** @test */
    public function test_add_adjustment_throws_on_typo_in_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Invalid adjustment type 'creit'/");

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'creit',
            10.00,
            'Should fail',
            $this->adminUser
        );
    }

    // -------------------------------------------------------------------------
    // addAdjustment — amount validation (M-03)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_throws_on_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Adjustment amount must be positive/');

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            0,
            'Should fail',
            $this->adminUser
        );
    }

    /** @test */
    public function test_add_adjustment_throws_on_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Adjustment amount must be positive/');

        $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            -100.00,
            'Should fail',
            $this->adminUser
        );
    }

    // -------------------------------------------------------------------------
    // addAdjustment — happy paths
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_accepts_valid_credit(): void
    {
        $adj = $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'credit',
            10.50,
            'Valid credit',
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollAdjustment::class, $adj);
        $this->assertSame('credit', $adj->type);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adj->id,
            'type' => 'credit',
            'amount' => 10.50,
        ]);
    }

    /** @test */
    public function test_add_adjustment_accepts_valid_debit(): void
    {
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'adjustments_total' => '0.00',
            'gross_total' => '100.00',
        ]);

        $adj = $this->service->addAdjustment(
            $this->period,
            $this->employee,
            'debit',
            10.50,
            'Valid debit',
            $this->adminUser
        );

        $this->assertSame('debit', $adj->type);
        // signedAmount() must return negative string for debit.
        $this->assertSame('-10.50', $adj->signedAmount());

        $record->refresh();
        $this->assertSame('-10.50', $record->adjustments_total);
    }

    // -------------------------------------------------------------------------
    // markPaid — payment_method validation (M-04)
    // -------------------------------------------------------------------------

    /** @test */
    public function test_mark_paid_throws_on_unknown_payment_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Invalid payment method 'wire_transfer'/");

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->service->markPaid($record, $this->adminUser, [
            'payment_method' => 'wire_transfer',
        ]);
    }

    /** @test */
    public function test_mark_paid_throws_on_typo_payment_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Invalid payment method 'transferr'/");

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->service->markPaid($record, $this->adminUser, [
            'payment_method' => 'transferr',
        ]);
    }

    /** @test */
    public function test_mark_paid_accepts_each_valid_payment_method(): void
    {
        foreach (PayrollService::PAYMENT_METHODS as $method) {
            // Create a fresh approved record for each method to avoid state conflicts.
            $record = PayrollRecord::factory()->approved()->create([
                'business_id' => $this->business->id,
                'payroll_period_id' => $this->period->id,
                'employee_id' => Employee::factory()->create([
                    'business_id' => $this->business->id,
                    'is_active' => true,
                ])->id,
            ]);

            $this->service->markPaid($record, $this->adminUser, [
                'payment_method' => $method,
            ]);

            $this->assertDatabaseHas('payroll_records', [
                'id' => $record->id,
                'status' => 'paid',
                'payment_method' => $method,
            ]);
        }
    }
}
