<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\SendEmailNotification;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailNotificationService $service;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EmailNotificationService;

        // Create test data
        $business = Business::factory()->create([
            'name' => 'Test Business',
            'email' => 'business@test.com',
            'phone' => '555-0123',
            'address' => '123 Main St',
        ]);

        $client = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'client@test.com',
            'business_id' => $business->id,
            'role' => 'client',
        ]);

        $employeeUser = User::factory()->create([
            'name' => 'Jane Smith',
            'business_id' => $business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $business->id,
            'user_id' => $employeeUser->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Haircut',
            'duration' => 60,
            'price' => 50.00,
        ]);

        $this->appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addDay(),
            'scheduled_until' => now()->addDay()->addHour(),
            'status' => 'confirmed',
        ]);
    }

    public function test_send_template_dispatches_job_with_valid_template(): void
    {
        Queue::fake();

        $this->service->sendTemplate(
            'test@example.com',
            'appointment_confirmation',
            ['test' => 'data']
        );

        Queue::assertPushed(SendEmailNotification::class, function ($job) {
            return $job->to === 'test@example.com'
                && $job->templateName === 'appointment_confirmation'
                && $job->data === ['test' => 'data'];
        });
    }

    public function test_send_template_throws_exception_for_invalid_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email template: invalid_template');

        $this->service->sendTemplate(
            'test@example.com',
            'invalid_template',
            []
        );
    }

    public function test_send_appointment_confirmation_dispatches_job(): void
    {
        Queue::fake();

        $this->service->sendAppointmentConfirmation($this->appointment);

        Queue::assertPushed(SendEmailNotification::class, function ($job) {
            return $job->to === $this->appointment->client->email
                && $job->templateName === 'appointment_confirmation'
                && isset($job->data['client_name'])
                && $job->data['client_name'] === 'John Doe';
        });
    }

    public function test_send_appointment_reminder_dispatches_job(): void
    {
        Queue::fake();

        $this->service->sendAppointmentReminder($this->appointment);

        Queue::assertPushed(SendEmailNotification::class, function ($job) {
            return $job->to === $this->appointment->client->email
                && $job->templateName === 'appointment_reminder';
        });
    }

    public function test_send_appointment_cancelled_dispatches_job(): void
    {
        Queue::fake();

        $this->service->sendAppointmentCancelled($this->appointment);

        Queue::assertPushed(SendEmailNotification::class, function ($job) {
            return $job->to === $this->appointment->client->email
                && $job->templateName === 'appointment_cancelled';
        });
    }

    public function test_send_appointment_rescheduled_dispatches_job(): void
    {
        Queue::fake();

        $oldScheduledAt = 'January 1, 2025 at 10:00 AM';
        $this->service->sendAppointmentRescheduled($this->appointment, $oldScheduledAt);

        Queue::assertPushed(SendEmailNotification::class, function ($job) use ($oldScheduledAt) {
            return $job->to === $this->appointment->client->email
                && $job->templateName === 'appointment_rescheduled'
                && $job->data['old_scheduled_at'] === $oldScheduledAt;
        });
    }

    public function test_build_appointment_data_returns_correct_structure(): void
    {
        Queue::fake();

        $this->service->sendAppointmentConfirmation($this->appointment);

        Queue::assertPushed(SendEmailNotification::class, function ($job) {
            $data = $job->data;

            return isset($data['client_name'])
                && isset($data['client_email'])
                && isset($data['business_name'])
                && isset($data['business_email'])
                && isset($data['business_phone'])
                && isset($data['business_address'])
                && isset($data['service_name'])
                && isset($data['service_duration'])
                && isset($data['service_price'])
                && isset($data['employee_name'])
                && isset($data['scheduled_at'])
                && isset($data['scheduled_until'])
                && isset($data['appointment_id']);
        });
    }

    public function test_get_available_templates_returns_array(): void
    {
        $templates = $this->service->getAvailableTemplates();

        $this->assertIsArray($templates);
        $this->assertContains('appointment_confirmation', $templates);
        $this->assertContains('appointment_reminder', $templates);
        $this->assertContains('appointment_cancelled', $templates);
        $this->assertContains('appointment_rescheduled', $templates);
        $this->assertContains('enrollment_confirmation', $templates);
        $this->assertContains('payment_received', $templates);
        $this->assertCount(6, $templates);
    }
}
