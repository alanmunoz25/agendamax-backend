<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Service;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.2 BCMath precision tests.
 *
 * Verifies that all monetary arithmetic in PayrollService uses BCMath (scale 2)
 * and never introduces IEEE-754 float drift. Every monetary assertion is a string
 * comparison — the decimal:2 cast on PayrollRecord returns strings, so any drift
 * would surface as a failing assertSame.
 */
class PayrollPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);
        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();

        $this->period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);
    }

    /** @test */
    public function test_one_hundred_commissions_of_one_cent_sum_exactly_one_dollar(): void
    {
        // IEEE-754 proof: sum 100 floats of 0.01 in PHP without BCMath produces ~1.0000000000000007.
        // BCMath with scale 2 must return exactly '1.00'.
        $employee = $this->createEmployeeWithSalary(0);

        $this->createCommissions($employee, 100, '0.01');

        $records = $this->service->generateRecords($this->period, $this->adminUser);

        $this->assertCount(1, $records);
        $record = $records->first();

        $this->assertSame('1.00', $record->commissions_total);
        $this->assertSame('1.00', $record->gross_total);
    }

    /** @test */
    public function test_summing_thirty_three_thirty_three_thrice_with_extra_cent_yields_one_hundred(): void
    {
        // 33.33 + 33.33 + 33.34 = 100.00 exactly.
        // In float arithmetic the result can drift to 99.99999999999... or 100.00000000001.
        $employee = $this->createEmployeeWithSalary(0);

        $this->createCommissions($employee, 1, '33.33');
        $this->createCommissions($employee, 1, '33.33');
        $this->createCommissions($employee, 1, '33.34');

        $records = $this->service->generateRecords($this->period, $this->adminUser);

        $this->assertCount(1, $records);
        $record = $records->first();

        $this->assertSame('100.00', $record->commissions_total);
        $this->assertSame('100.00', $record->gross_total);
    }

    /** @test */
    public function test_adjustment_with_decimal_amount_does_not_drift_after_recalc(): void
    {
        // Adding 0.10 ten times must yield exactly 1.00 over the base, not ~1.00000000001.
        $employee = $this->createEmployeeWithSalary(0);

        // Seed a draft record at 100.00
        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'adjustments_total' => '0.00',
            'gross_total' => '100.00',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->service->addAdjustment(
                $this->period,
                $employee,
                'credit',
                0.10,
                "Micro-credit iteration {$i}",
                $this->adminUser
            );
        }

        $record->refresh();
        $this->assertSame('1.00', $record->adjustments_total);
        $this->assertSame('101.00', $record->gross_total);
    }

    /** @test */
    public function test_negative_adjustment_via_debit_preserves_precision(): void
    {
        $employee = $this->createEmployeeWithSalary(0);

        $record = PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'adjustments_total' => '0.00',
            'gross_total' => '10.00',
        ]);

        $this->service->addAdjustment(
            $this->period,
            $employee,
            'debit',
            0.05,
            'Small deduction',
            $this->adminUser
        );

        $record->refresh();
        $this->assertSame('-0.05', $record->adjustments_total);
        $this->assertSame('9.95', $record->gross_total);
    }

    /** @test */
    public function test_signed_amount_returns_string_for_credit_and_debit(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);

        $employee = $this->createEmployeeWithSalary(0);

        $credit = PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'credit',
            'amount' => 12.34,
            'created_by' => $this->adminUser->id,
        ]);

        $debit = PayrollAdjustment::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'debit',
            'amount' => 12.34,
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertSame('12.34', $credit->signedAmount());
        $this->assertSame('-12.34', $debit->signedAmount());

        // Confirm no precision loss after round-trip through DB.
        $credit->refresh();
        $debit->refresh();
        $this->assertSame('12.34', $credit->signedAmount());
        $this->assertSame('-12.34', $debit->signedAmount());
    }

    /** @test */
    public function test_generate_records_handles_base_salary_with_decimals(): void
    {
        $employee = $this->createEmployeeWithSalary(1234.56);

        // No commissions, tips, or adjustments — only base salary.
        $records = $this->service->generateRecords($this->period, $this->adminUser);

        $this->assertCount(1, $records);
        $record = $records->first();

        $this->assertSame('1234.56', $record->base_salary_snapshot);
        $this->assertSame('0.00', $record->commissions_total);
        $this->assertSame('0.00', $record->tips_total);
        $this->assertSame('0.00', $record->adjustments_total);
        $this->assertSame('1234.56', $record->gross_total);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an active employee with the given base salary attached to $this->business.
     */
    private function createEmployeeWithSalary(float|int|string $salary): Employee
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        return Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => $salary,
        ]);
    }

    /**
     * Create N commission records with the given amount string for the employee.
     * Inserts directly via DB to avoid the CommissionRecord factory creating unrelated
     * Business/Employee/Appointment objects per iteration (which would exhaust unique slug space).
     * Sets created_at inside the period so generateRecords picks them up.
     */
    private function createCommissions(Employee $employee, int $count, string $amount): void
    {
        $service = Service::factory()->create(['business_id' => $this->business->id]);

        for ($i = 0; $i < $count; $i++) {
            // Each iteration needs a fresh appointment so (appointment_id, service_id) stays unique.
            $appointment = Appointment::factory()->create([
                'business_id' => $this->business->id,
                'employee_id' => $employee->id,
            ]);

            $appointmentServiceId = DB::table('appointment_services')->insertGetId([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            // Insert directly to avoid the factory generating extra Business records per row.
            DB::table('commission_records')->insert([
                'business_id' => $this->business->id,
                'employee_id' => $employee->id,
                'appointment_id' => $appointment->id,
                'appointment_service_id' => $appointmentServiceId,
                'service_id' => $service->id,
                'commission_rule_id' => null,
                'service_price_snapshot' => '0.00',
                'rule_type_snapshot' => 'percentage',
                'rule_value_snapshot' => '10.00',
                'commission_amount' => $amount,
                'status' => 'pending',
                'payroll_period_id' => null,
                'generated_at' => now()->toDateTimeString(),
                'created_at' => Carbon::parse('2026-05-15')->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
        }
    }
}
