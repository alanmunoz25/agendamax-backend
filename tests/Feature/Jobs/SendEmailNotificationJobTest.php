<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SendEmailNotification;
use App\Mail\AppointmentConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendEmailNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_email_successfully(): void
    {
        Mail::fake();

        $data = [
            'client_name' => 'John Doe',
            'business_name' => 'Test Business',
            'service_name' => 'Haircut',
            'employee_name' => 'Jane Smith',
            'scheduled_at' => 'January 15, 2025 at 10:00 AM',
            'service_duration' => 60,
            'service_price' => 50.00,
            'appointment_id' => 1,
        ];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            $data
        );

        $job->handle();

        Mail::assertSent(AppointmentConfirmation::class, function ($mail) use ($data) {
            return $mail->hasTo('test@example.com')
                && $mail->data === $data;
        });
    }

    public function test_job_logs_success(): void
    {
        Mail::fake();
        Log::spy();

        $data = ['client_name' => 'John Doe', 'appointment_id' => 1];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            $data
        );

        $job->handle();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Email sent successfully', \Mockery::on(function ($context) {
                return $context['template'] === 'appointment_confirmation'
                    && $context['to'] === 'test@example.com'
                    && $context['appointment_id'] === 1;
            }));
    }

    public function test_job_throws_exception_for_invalid_template(): void
    {
        Mail::fake();

        $job = new SendEmailNotification(
            'test@example.com',
            'invalid_template',
            []
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid template: invalid_template');

        $job->handle();
    }

    public function test_job_has_correct_retry_configuration(): void
    {
        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            []
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 300, 900], $job->backoff);
        $this->assertEquals(3, $job->maxExceptions);
        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_job_builds_correct_mailable_for_confirmation(): void
    {
        Mail::fake();

        $data = ['business_name' => 'Test Business'];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            $data
        );

        $job->handle();

        Mail::assertSent(AppointmentConfirmation::class, function ($mail) use ($data) {
            return $mail->data === $data;
        });
    }

    public function test_job_builds_correct_mailable_for_reminder(): void
    {
        Mail::fake();

        $data = ['business_name' => 'Test Business'];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_reminder',
            $data
        );

        $job->handle();

        Mail::assertSent(\App\Mail\AppointmentReminder::class);
    }

    public function test_job_builds_correct_mailable_for_cancelled(): void
    {
        Mail::fake();

        $data = ['business_name' => 'Test Business'];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_cancelled',
            $data
        );

        $job->handle();

        Mail::assertSent(\App\Mail\AppointmentCancelled::class);
    }

    public function test_job_builds_correct_mailable_for_rescheduled(): void
    {
        Mail::fake();

        $data = [
            'business_name' => 'Test Business',
            'old_scheduled_at' => 'January 1, 2025',
        ];

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_rescheduled',
            $data
        );

        $job->handle();

        Mail::assertSent(\App\Mail\AppointmentRescheduled::class);
    }

    public function test_job_logs_error_on_failure(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('Mail send failed'));
        Log::spy();

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            ['appointment_id' => 1]
        );

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected exception
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Email send failed', \Mockery::on(function ($context) {
                return $context['template'] === 'appointment_confirmation'
                    && $context['to'] === 'test@example.com'
                    && $context['error'] === 'Mail send failed';
            }));
    }

    public function test_failed_method_logs_permanent_failure(): void
    {
        Log::spy();

        $job = new SendEmailNotification(
            'test@example.com',
            'appointment_confirmation',
            []
        );

        $exception = new \Exception('Permanent failure');
        $job->failed($exception);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Email job failed permanently', \Mockery::on(function ($context) {
                return $context['template'] === 'appointment_confirmation'
                    && $context['to'] === 'test@example.com'
                    && $context['error'] === 'Permanent failure'
                    && isset($context['trace']);
            }));
    }
}
