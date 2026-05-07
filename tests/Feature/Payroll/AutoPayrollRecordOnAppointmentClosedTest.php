<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Jobs\CalculateAppointmentCommission;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\PosTicket;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #6 Sprint 6 — Auto-generate PayrollRecord when appointment is closed + billed.
 *
 * Tests that when a POS ticket is created for an appointment (completed + paid),
 * the CommissionRecord gets assigned to an open PayrollPeriod immediately and a
 * PayrollRecord is created or updated for the employee — without any manual admin step.
 */
class AutoPayrollRecordOnAppointmentClosedTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Employee $employee;

    private Service $service;

    private User $cashier;

    private CommissionRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => '0.00',
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => '1000.00',
        ]);

        // Global commission rule: 10% for all employees/services in this business.
        $this->rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
            'priority' => 1,
        ]);

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);
    }

    /**
     * Build a completed appointment with a linked POS ticket so it is considered "paid".
     */
    private function makeCompletedPaidAppointment(): Appointment
    {
        // Create ticket first (status = paid by default in PosTicket)
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'cashier_id' => $this->cashier->id,
            'total' => '1000.00',
        ]);

        return Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'completed',
            'final_price' => '1000.00',
            'ticket_id' => $ticket->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Test 1: completed + paid → CommissionRecord assigned to period + PayrollRecord created
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_completed_and_paid_appointment_creates_payroll_record_immediately(): void
    {
        $appointment = $this->makeCompletedPaidAppointment();

        // Attach service line to appointment so CommissionService can process it.
        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        // CommissionRecord must exist and have a payroll_period_id
        $commissionRecord = CommissionRecord::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($commissionRecord, 'CommissionRecord should have been created');
        $this->assertNotNull($commissionRecord->payroll_period_id, 'CommissionRecord should be assigned to a period');

        // PayrollRecord must exist with commission_total > 0
        $payrollRecord = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->where('payroll_period_id', $commissionRecord->payroll_period_id)
            ->first();

        $this->assertNotNull($payrollRecord, 'PayrollRecord should have been created automatically');
        $this->assertGreaterThan(0, (float) $payrollRecord->commissions_total, 'commissions_total must be > 0');
        $this->assertEquals('draft', $payrollRecord->status);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Test 2: completed WITHOUT payment → CommissionRecord created but period NOT assigned
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_completed_without_payment_leaves_payroll_period_null(): void
    {
        // Appointment without a POS ticket (ticket_id = null)
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'completed',
            'final_price' => '1000.00',
            'ticket_id' => null,
        ]);

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        // CommissionRecord created but payroll_period_id stays null
        $commissionRecord = CommissionRecord::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->first();

        $this->assertNotNull($commissionRecord, 'CommissionRecord should exist');
        $this->assertNull($commissionRecord->payroll_period_id, 'Period should remain null until payment');

        // No PayrollRecord created
        $this->assertEquals(0, PayrollRecord::withoutGlobalScopes()->count(), 'No PayrollRecord until paid');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Test 3: no existing PayrollPeriod → one is auto-created
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_auto_creates_payroll_period_when_none_exists(): void
    {
        // Verify no periods exist initially
        $this->assertEquals(0, PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $this->business->id)->count());

        $appointment = $this->makeCompletedPaidAppointment();

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        // A period must have been auto-created for this business
        $periodCount = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('status', 'open')
            ->count();

        $this->assertEquals(1, $periodCount, 'Auto-created period must exist');

        // PayrollRecord must also exist
        $this->assertEquals(1, PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Test 4: 2 appointments from same employee in same period → 1 PayrollRecord with summed commissions
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_two_paid_appointments_same_employee_aggregate_into_one_payroll_record(): void
    {
        $appointment1 = $this->makeCompletedPaidAppointment();
        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment1->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appointment2 = $this->makeCompletedPaidAppointment();
        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment2->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commissionService = app(CommissionService::class);
        $payrollService = app(PayrollService::class);

        // Process first appointment
        $job1 = new CalculateAppointmentCommission($appointment1->id);
        $job1->handle($commissionService, $payrollService);

        // Process second appointment
        $job2 = new CalculateAppointmentCommission($appointment2->id);
        $job2->handle($commissionService, $payrollService);

        // Only 1 PayrollRecord should exist for this employee
        $payrollRecords = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->get();

        $this->assertCount(1, $payrollRecords, 'Should have exactly 1 PayrollRecord for this employee');

        // commissions_total must equal 10% of 1000 * 2 = 200.00
        $this->assertEquals('200.00', $payrollRecords->first()->commissions_total);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Test 5: idempotency — running the job twice for the same appointment does not duplicate
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_running_job_twice_for_same_appointment_is_idempotent(): void
    {
        $appointment = $this->makeCompletedPaidAppointment();

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commissionService = app(CommissionService::class);
        $payrollService = app(PayrollService::class);

        $job = new CalculateAppointmentCommission($appointment->id);

        // First run
        $job->handle($commissionService, $payrollService);

        // Second run (simulating queue retry)
        $job->handle($commissionService, $payrollService);

        // Only 1 CommissionRecord (firstOrCreate is idempotent)
        $this->assertEquals(1, CommissionRecord::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)->count(), 'CommissionRecord must not be duplicated');

        // Only 1 PayrollRecord
        $this->assertEquals(1, PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)->count(), 'PayrollRecord must not be duplicated');

        // commissions_total must equal 10% of 1000 = 100.00 (not doubled)
        $payrollRecord = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertEquals('100.00', $payrollRecord->commissions_total);
    }
}
