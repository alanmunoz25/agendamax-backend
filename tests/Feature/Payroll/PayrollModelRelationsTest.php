<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Service;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 1 — verify model relationships return expected instances.
 */
class PayrollModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_rule_belongs_to_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $rule = CommissionRule::factory()->forEmployee($employee)->create();

        $this->assertInstanceOf(Employee::class, $rule->employee);
        $this->assertSame($employee->id, $rule->employee->id);
    }

    public function test_commission_rule_belongs_to_service(): void
    {
        $business = Business::factory()->create();
        $service = Service::factory()->create(['business_id' => $business->id]);
        $rule = CommissionRule::factory()->forService($service)->create();

        $this->assertInstanceOf(Service::class, $rule->service);
        $this->assertSame($service->id, $rule->service->id);
    }

    public function test_commission_record_belongs_to_appointment(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertInstanceOf(Appointment::class, $record->appointment);
        $this->assertSame($appointment->id, $record->appointment->id);
    }

    public function test_commission_record_belongs_to_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertInstanceOf(Employee::class, $record->employee);
        $this->assertSame($employee->id, $record->employee->id);
    }

    public function test_commission_record_belongs_to_payroll_period(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->inPeriod($period)->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertInstanceOf(PayrollPeriod::class, $record->payrollPeriod);
        $this->assertSame($period->id, $record->payrollPeriod->id);
    }

    public function test_payroll_period_has_many_payroll_records(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);

        PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertCount(1, $period->payrollRecords);
        $this->assertInstanceOf(PayrollRecord::class, $period->payrollRecords->first());
    }

    public function test_payroll_record_belongs_to_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();

        $record = PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertInstanceOf(Employee::class, $record->employee);
        $this->assertSame($employee->id, $record->employee->id);
    }

    public function test_payroll_record_belongs_to_period(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();

        $record = PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertInstanceOf(PayrollPeriod::class, $record->period);
        $this->assertSame($period->id, $record->period->id);
    }

    public function test_payroll_record_has_approver_relation(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $admin = User::factory()->create(['business_id' => $business->id]);

        $record = PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);
        // Bypass fillable: test setup forces the approved_by relation for assertion.
        $record->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        $this->assertInstanceOf(User::class, $record->approver);
        $this->assertSame($admin->id, $record->approver->id);
    }

    public function test_tip_belongs_to_appointment(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);
        $tip = Tip::factory()->forAppointment($appointment)->create([
            'employee_id' => $employee->id,
        ]);

        $this->assertInstanceOf(Appointment::class, $tip->appointment);
        $this->assertSame($appointment->id, $tip->appointment->id);
    }

    public function test_tip_belongs_to_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);
        $tip = Tip::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertInstanceOf(Employee::class, $tip->employee);
        $this->assertSame($employee->id, $tip->employee->id);
    }

    public function test_payroll_adjustment_belongs_to_period(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $adjustment = PayrollAdjustment::factory()->forPeriod($period)->create();

        $this->assertInstanceOf(PayrollPeriod::class, $adjustment->period);
        $this->assertSame($period->id, $adjustment->period->id);
    }

    public function test_payroll_adjustment_belongs_to_employee(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $admin = User::factory()->create(['business_id' => $business->id]);

        $adjustment = PayrollAdjustment::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'created_by' => $admin->id,
        ]);

        $this->assertInstanceOf(Employee::class, $adjustment->employee);
        $this->assertSame($employee->id, $adjustment->employee->id);
    }

    public function test_employee_has_many_commission_rules(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);

        CommissionRule::factory()->forEmployee($employee)->count(3)->create();

        $this->assertCount(3, $employee->commissionRules);
    }

    public function test_employee_has_many_tips(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);

        Tip::factory()->count(2)->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertCount(2, $employee->tips);
    }

    public function test_appointment_has_many_commission_records(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertCount(1, $appointment->commissionRecords);
        $this->assertInstanceOf(CommissionRecord::class, $appointment->commissionRecords->first());
    }

    public function test_payroll_adjustment_signed_amount_credit(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $adjustment = PayrollAdjustment::factory()->credit(50.0)->forPeriod($period)->create();

        $this->assertSame('50.00', $adjustment->signedAmount());
    }

    public function test_payroll_adjustment_signed_amount_debit(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $adjustment = PayrollAdjustment::factory()->debit(30.0)->forPeriod($period)->create();

        $this->assertSame('-30.00', $adjustment->signedAmount());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 2 additions — Pito fix 5a
    // ─────────────────────────────────────────────────────────────────────

    public function test_commission_record_belongs_to_appointment_service(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertNotNull($record->appointmentService);
        $this->assertSame($appointmentServiceId, $record->appointmentService->id);
    }

    public function test_commission_record_belongs_to_commission_rule(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $rule = CommissionRule::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'commission_rule_id' => $rule->id,
        ]);

        $this->assertInstanceOf(CommissionRule::class, $record->commissionRule);
        $this->assertSame($rule->id, $record->commissionRule->id);
    }

    public function test_commission_record_belongs_to_service(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = CommissionRecord::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertInstanceOf(Service::class, $record->service);
        $this->assertSame($service->id, $record->service->id);
    }

    public function test_payroll_period_has_many_commission_records(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CommissionRecord::factory()->inPeriod($period)->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        $this->assertCount(1, $period->commissionRecords);
        $this->assertInstanceOf(CommissionRecord::class, $period->commissionRecords->first());
    }

    public function test_payroll_period_has_many_tips(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);

        Tip::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
        ]);

        $this->assertCount(1, $period->tips);
        $this->assertInstanceOf(Tip::class, $period->tips->first());
    }

    public function test_payroll_period_has_many_adjustments(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();

        PayrollAdjustment::factory()->forPeriod($period)->create();

        $this->assertCount(1, $period->adjustments);
        $this->assertInstanceOf(PayrollAdjustment::class, $period->adjustments->first());
    }
}
