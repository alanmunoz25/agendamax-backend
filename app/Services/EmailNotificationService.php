<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendEmailNotification;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Available email templates for appointments.
     */
    private const TEMPLATES = [
        'appointment_confirmation',
        'appointment_reminder',
        'appointment_cancelled',
        'appointment_rescheduled',
        'enrollment_confirmation',
        'payment_received',
    ];

    /**
     * Send a templated email notification.
     *
     * @param  string  $to  Email address
     * @param  string  $templateName  Template identifier
     * @param  array<string, mixed>  $data  Template data
     */
    public function sendTemplate(string $to, string $templateName, array $data): void
    {
        if (! in_array($templateName, self::TEMPLATES, true)) {
            Log::error('Invalid email template', [
                'template' => $templateName,
                'to' => $to,
            ]);

            throw new \InvalidArgumentException("Invalid email template: {$templateName}");
        }

        SendEmailNotification::dispatch($to, $templateName, $data);
    }

    /**
     * Send appointment confirmation email.
     */
    public function sendAppointmentConfirmation(Appointment $appointment): void
    {
        $this->sendTemplate(
            $appointment->client->email,
            'appointment_confirmation',
            $this->buildAppointmentData($appointment)
        );
    }

    /**
     * Send appointment reminder email.
     */
    public function sendAppointmentReminder(Appointment $appointment): void
    {
        $this->sendTemplate(
            $appointment->client->email,
            'appointment_reminder',
            $this->buildAppointmentData($appointment)
        );
    }

    /**
     * Send appointment cancellation email.
     */
    public function sendAppointmentCancelled(Appointment $appointment): void
    {
        $this->sendTemplate(
            $appointment->client->email,
            'appointment_cancelled',
            $this->buildAppointmentData($appointment)
        );
    }

    /**
     * Send appointment rescheduled email.
     *
     * @param  string  $oldScheduledAt  Previous scheduled datetime
     */
    public function sendAppointmentRescheduled(Appointment $appointment, string $oldScheduledAt): void
    {
        $data = $this->buildAppointmentData($appointment);
        $data['old_scheduled_at'] = $oldScheduledAt;

        $this->sendTemplate(
            $appointment->client->email,
            'appointment_rescheduled',
            $data
        );
    }

    /**
     * Build appointment data array for email templates.
     *
     * @return array<string, mixed>
     */
    private function buildAppointmentData(Appointment $appointment): array
    {
        $appointment->load(['client', 'service', 'employee.user', 'business']);

        return [
            'client_name' => $appointment->client->name,
            'client_email' => $appointment->client->email,
            'business_name' => $appointment->business->name,
            'business_email' => $appointment->business->email,
            'business_phone' => $appointment->business->phone,
            'business_address' => $appointment->business->address,
            'service_name' => $appointment->service?->name,
            'service_duration' => $appointment->service?->duration,
            'service_price' => $appointment->service?->price,
            'employee_name' => $appointment->employee?->user?->name ?? 'Any available',
            'scheduled_at' => $appointment->scheduled_at->format('F j, Y \a\t g:i A'),
            'scheduled_until' => $appointment->scheduled_until?->format('g:i A'),
            'appointment_id' => $appointment->id,
            'notes' => $appointment->notes,
        ];
    }

    /**
     * Get list of available email templates.
     *
     * @return array<int, string>
     */
    public function getAvailableTemplates(): array
    {
        return self::TEMPLATES;
    }
}
