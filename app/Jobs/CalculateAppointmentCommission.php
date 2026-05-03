<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\CommissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateAppointmentCommission implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job's uniqueness lock should be held.
     */
    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     * Stores only the ID to avoid serializing the model (queue safety).
     */
    public function __construct(public readonly int $appointmentId) {}

    /**
     * The unique ID of this job — prevents duplicate commission calculation for the same appointment.
     */
    public function uniqueId(): string
    {
        return (string) $this->appointmentId;
    }

    /**
     * Exponential-ish backoff: 10s, 30s, 60s between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        \Log::error('CalculateAppointmentCommission failed after retries', [
            'appointment_id' => $this->appointmentId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Execute the job.
     * CommissionService is resolved by the container at runtime — not injected in constructor.
     */
    public function handle(CommissionService $service): void
    {
        // Sin Auth en queue: bypass global scope para recuperar el appointment
        $appointment = Appointment::withoutGlobalScopes()->find($this->appointmentId);

        if ($appointment === null || $appointment->status !== 'completed') {
            Log::info('Skipping commission generation', [
                'appointment_id' => $this->appointmentId,
                'status' => $appointment?->status,
            ]);

            return;
        }

        $service->generateForAppointment($appointment);
    }
}
