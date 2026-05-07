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
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Track D — Commission generation end-to-end tests.
 * Verifies the full flow: complete appointment → CommissionRecord created →
 * admin generates payroll → CommissionRecord assigned to open period.
 */
class CommissionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Employee $employee;

    private Service $service;

    private User $admin;

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
            'base_salary' => 1000.00,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => 500.00,
        ]);

        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
            'priority' => 1,
        ]);
    }

    /** @test */
    public function test_completing_appointment_dispatches_commission_job(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        $appointment->update(['status' => 'completed']);

        Queue::assertPushed(CalculateAppointmentCommission::class, function ($job) use ($appointment): bool {
            return $job->appointmentId === $appointment->id;
        });
    }

    /** @test */
    public function test_commission_record_created_when_appointment_completed(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        // Insert a service line so CommissionService can compute commissions
        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run the job synchronously (bypassing the queue)
        $appointment->update(['status' => 'completed']);
        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        $this->assertDatabaseHas('commission_records', [
            'appointment_id' => $appointment->id,
            'employee_id' => $this->employee->id,
            'business_id' => $this->business->id,
            'status' => 'pending',
        ]);

        // payroll_period_id is NULL until payroll is generated
        $this->assertDatabaseHas('commission_records', [
            'appointment_id' => $appointment->id,
            'payroll_period_id' => null,
        ]);
    }

    /** @test */
    public function test_commission_record_assigned_to_period_when_payroll_generated(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Complete appointment and run commission job
        $appointment->update(['status' => 'completed']);
        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        // Verify commission record exists without a period
        $this->assertDatabaseCount('commission_records', 1);

        // Create an open payroll period that covers today
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => Carbon::now()->startOfMonth()->toDateString(),
            'ends_on' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // Admin generates payroll — this assigns the CommissionRecord to the period
        $payrollService = app(PayrollService::class);
        $payrollService->generateRecords($period, $this->admin);

        // CommissionRecord must now be linked to the period
        $this->assertDatabaseHas('commission_records', [
            'appointment_id' => $appointment->id,
            'payroll_period_id' => $period->id,
        ]);

        // PayrollRecord must have the commission total
        $commissionRecord = CommissionRecord::first();
        $this->assertDatabaseHas('payroll_records', [
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'commissions_total' => $commissionRecord->commission_amount,
        ]);
    }

    /** @test */
    public function test_job_skips_non_completed_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'cancelled',
        ]);

        $job = new CalculateAppointmentCommission($appointment->id);
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        $this->assertDatabaseCount('commission_records', 0);
    }

    /** @test */
    public function test_commission_record_is_idempotent_on_double_run(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'completed',
        ]);

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commissionService = app(CommissionService::class);

        // Run twice — must produce only 1 record (firstOrCreate idempotency)
        $commissionService->generateForAppointment($appointment);
        $commissionService->generateForAppointment($appointment);

        $this->assertDatabaseCount('commission_records', 1);
    }
}
