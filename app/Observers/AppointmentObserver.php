<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Models\Appointment;
use App\Models\Stamp;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        private readonly EmailNotificationService $emailService
    ) {}

    /**
     * Handle the Appointment "created" event.
     * Send confirmation email when new appointment is created.
     */
    public function created(Appointment $appointment): void
    {
        try {
            $this->emailService->sendAppointmentConfirmation($appointment);

            Log::info('Appointment created and confirmation email queued', [
                'appointment_id' => $appointment->id,
                'business_id' => $appointment->business_id,
                'client_id' => $appointment->client_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue confirmation email for new appointment', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Broadcast real-time event
        AppointmentCreated::dispatch($appointment);
    }

    /**
     * Handle the Appointment "updated" event.
     * Detect status changes and trigger appropriate actions.
     */
    public function updated(Appointment $appointment): void
    {
        // Get the original values before the update
        $oldStatus = $appointment->getOriginal('status');
        $newStatus = $appointment->status;
        $oldScheduledAt = $appointment->getOriginal('scheduled_at');
        $newScheduledAt = $appointment->scheduled_at;

        try {
            // Handle completion - create loyalty stamp
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $this->handleAppointmentCompletion($appointment);
            }

            // Handle cancellation
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $this->emailService->sendAppointmentCancelled($appointment);

                Log::info('Appointment cancelled, notification queued', [
                    'appointment_id' => $appointment->id,
                    'old_status' => $oldStatus,
                ]);

                // Broadcast cancellation event
                AppointmentCancelled::dispatch($appointment);
            }

            // Handle rescheduling (when scheduled_at changes)
            if ($oldScheduledAt !== null && $newScheduledAt !== null && $oldScheduledAt != $newScheduledAt) {
                $this->emailService->sendAppointmentRescheduled(
                    $appointment,
                    $oldScheduledAt->format('F j, Y \a\t g:i A')
                );

                Log::info('Appointment rescheduled, notification queued', [
                    'appointment_id' => $appointment->id,
                    'old_time' => $oldScheduledAt,
                    'new_time' => $newScheduledAt,
                ]);
            }

            // Broadcast general update event if any changes occurred
            if ($appointment->isDirty()) {
                AppointmentUpdated::dispatch($appointment);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle appointment update', [
                'appointment_id' => $appointment->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Appointment "deleted" event.
     * Send cancellation email if appointment is soft deleted.
     */
    public function deleted(Appointment $appointment): void
    {
        // Only send cancellation if not already cancelled
        if ($appointment->status !== 'cancelled') {
            try {
                $this->emailService->sendAppointmentCancelled($appointment);

                Log::info('Appointment deleted, cancellation email queued', [
                    'appointment_id' => $appointment->id,
                    'status' => $appointment->status,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to queue cancellation email for deleted appointment', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the Appointment "restored" event.
     */
    public function restored(Appointment $appointment): void
    {
        // Optionally send a restoration notification
        Log::info('Appointment restored', [
            'appointment_id' => $appointment->id,
        ]);
    }

    /**
     * Handle the Appointment "force deleted" event.
     */
    public function forceDeleted(Appointment $appointment): void
    {
        Log::info('Appointment force deleted', [
            'appointment_id' => $appointment->id,
        ]);
    }

    /**
     * Handle appointment completion logic.
     * Create loyalty stamp for the client.
     */
    private function handleAppointmentCompletion(Appointment $appointment): void
    {
        // Create loyalty stamp
        Stamp::create([
            'client_id' => $appointment->client_id,
            'business_id' => $appointment->business_id,
            'appointment_id' => $appointment->id,
            'earned_at' => now(),
        ]);

        Log::info('Loyalty stamp created for completed appointment', [
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'business_id' => $appointment->business_id,
        ]);
    }
}
