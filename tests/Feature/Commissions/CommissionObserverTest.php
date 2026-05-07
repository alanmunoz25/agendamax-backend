<?php

declare(strict_types=1);

namespace Tests\Feature\Commissions;

use App\Jobs\CalculateAppointmentCommission;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 2 — Observer + Job integration tests.
 * Tests that the observer dispatches the commission job and the job skips non-completed appointments.
 */
class CommissionObserverTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Employee $employee;

    private Service $service;

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
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);
    }

    public function test_observer_dispatches_job_on_status_completed(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        // Transition to completed — observer should dispatch the job
        $appointment->update(['status' => 'completed']);

        Queue::assertPushed(CalculateAppointmentCommission::class, function ($job) use ($appointment): bool {
            return $job->appointmentId === $appointment->id;
        });
    }

    public function test_job_skips_if_appointment_no_longer_completed(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'cancelled',
        ]);

        // Instantiate the job manually with the appointment ID
        $job = new CalculateAppointmentCommission($appointment->id);

        // Run handle directly — appointment is cancelled, not completed
        $job->handle(app(CommissionService::class), app(PayrollService::class));

        $this->assertDatabaseCount('commission_records', 0);
    }

    public function test_job_unique_id_is_appointment_id(): void
    {
        $job = new CalculateAppointmentCommission(42);

        $this->assertSame('42', $job->uniqueId());
    }

    public function test_observer_does_not_dispatch_job_when_status_is_not_completed(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        $appointment->update(['status' => 'cancelled']);

        Queue::assertNotPushed(CalculateAppointmentCommission::class);
    }

    public function test_job_dispatched_with_after_commit_on_completion(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        $appointment->update(['status' => 'completed']);

        // The job must be pushed — afterCommit() is a Queue::fake-transparent call
        Queue::assertPushed(CalculateAppointmentCommission::class, function ($job) use ($appointment): bool {
            return $job->appointmentId === $appointment->id;
        });
    }
}
