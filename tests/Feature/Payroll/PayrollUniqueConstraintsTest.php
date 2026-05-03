<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 1 — verify unique constraints are enforced at the DB level.
 */
class PayrollUniqueConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_records_unique_per_appointment_service_line_and_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        // Insert a pivot row directly
        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $baseRow = [
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'commission_rule_id' => null,
            'payroll_period_id' => null,
            'service_price_snapshot' => 50.00,
            'rule_type_snapshot' => 'percentage',
            'rule_value_snapshot' => 10.00,
            'commission_amount' => 5.00,
            'status' => 'pending',
            'generated_at' => now(),
            'locked_at' => null,
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('commission_records')->insert($baseRow);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('commission_records')->insert($baseRow);
    }

    public function test_payroll_records_unique_per_period_and_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();

        $baseRow = [
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'base_salary_snapshot' => 0,
            'commissions_total' => 0,
            'tips_total' => 0,
            'adjustments_total' => 0,
            'gross_total' => 0,
            'status' => 'draft',
            'approved_at' => null,
            'approved_by' => null,
            'paid_at' => null,
            'paid_by' => null,
            'payment_method' => null,
            'payment_reference' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'snapshot_payload' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('payroll_records')->insert($baseRow);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('payroll_records')->insert($baseRow);
    }

    public function test_payroll_periods_unique_per_business_and_date_range(): void
    {
        $business = Business::factory()->create();

        $baseRow = [
            'business_id' => $business->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('payroll_periods')->insert($baseRow);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('payroll_periods')->insert($baseRow);
    }
}
