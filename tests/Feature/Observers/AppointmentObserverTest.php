<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentObserverTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $client;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

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

    public function test_confirmation_email_queued_when_appointment_created(): void
    {
        Queue::fake();

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
        ]);

        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, function ($job) {
            return $job->templateName === 'appointment_confirmation';
        });
    }

    public function test_loyalty_stamp_created_when_appointment_completed(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseCount('stamps', 0);

        // Update to completed
        $appointment->update(['status' => 'completed']);

        $this->assertDatabaseCount('stamps', 1);

        $stamp = Stamp::first();
        $this->assertEquals($appointment->id, $stamp->appointment_id);
        $this->assertEquals($this->client->id, $stamp->client_id);
        $this->assertEquals($this->business->id, $stamp->business_id);
    }

    public function test_cancellation_email_queued_when_status_changed_to_cancelled(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        // Clear queue from creation
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);

        // Update to cancelled
        $appointment->update(['status' => 'cancelled']);

        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, function ($job) {
            return $job->templateName === 'appointment_cancelled';
        });
    }

    public function test_rescheduled_email_queued_when_scheduled_at_changes(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_until' => now()->addDay()->addHour(),
        ]);

        // Clear queue from creation
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);

        // Reschedule
        $appointment->update([
            'scheduled_at' => now()->addDays(2),
            'scheduled_until' => now()->addDays(2)->addHour(),
        ]);

        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, function ($job) {
            return $job->templateName === 'appointment_rescheduled';
        });
    }

    public function test_cancellation_email_queued_when_appointment_deleted(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        // Clear queue from creation
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);

        // Delete appointment
        $appointment->delete();

        // Should queue cancellation email
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 2);
    }

    public function test_no_cancellation_email_when_already_cancelled_appointment_deleted(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'cancelled',
        ]);

        // Should have confirmation email only (cancelled is set on creation, not a status change)
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);

        // Delete already cancelled appointment
        $appointment->delete();

        // Should NOT queue another cancellation email
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);
    }

    public function test_no_stamp_created_when_status_changes_but_not_to_completed(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'pending',
        ]);

        // Update to confirmed (not completed)
        $appointment->update(['status' => 'confirmed']);

        $this->assertDatabaseCount('stamps', 0);
    }

    public function test_observer_handles_multiple_status_changes_correctly(): void
    {
        Queue::fake();

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'pending',
        ]);

        // Confirm
        $appointment->update(['status' => 'confirmed']);

        // Complete
        $appointment->update(['status' => 'completed']);

        // Verify stamp was created
        $this->assertDatabaseCount('stamps', 1);

        // Verify only confirmation email was sent (no status change email for pending->confirmed)
        Queue::assertPushed(\App\Jobs\SendEmailNotification::class, 1);
    }
}
