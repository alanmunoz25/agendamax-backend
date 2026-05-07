<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Business;
use App\Services\CommissionService;
use App\Services\PayrollService;
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
     * CommissionService and PayrollService are resolved by the container at runtime.
     *
     * When the appointment is paid (ticket_id is set), the PosService::createTicket() already
     * called generateForAppointment() synchronously. CommissionService::generateForAppointment()
     * is idempotent (firstOrCreate) so calling it again is safe. After generating/confirming
     * commission records, the payroll auto-assign runs to ensure the PayrollRecord is up to date.
     */
    public function handle(CommissionService $commissionService, PayrollService $payrollService): void
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

        $commissionRecords = $commissionService->generateForAppointment($appointment);

        // If the appointment is paid (has a linked POS ticket), auto-assign commissions to
        // the open payroll period and upsert the employee's PayrollRecord immediately.
        // If not paid yet, commissions remain pending (payroll_period_id stays null) until
        // the ticket is created via PosService, which will trigger the upsert at that point.
        if ($appointment->ticket_id !== null && $commissionRecords->isNotEmpty()) {
            $business = Business::withoutGlobalScopes()->find($appointment->business_id);

            if ($business === null) {
                Log::warning('CalculateAppointmentCommission: business not found for payroll upsert', [
                    'appointment_id' => $this->appointmentId,
                    'business_id' => $appointment->business_id,
                ]);

                return;
            }

            try {
                $period = $payrollService->ensureOpenPeriodForToday($business);

                $commissionRecords->groupBy('employee_id')->each(
                    function (\Illuminate\Support\Collection $records, int $employeeId) use ($payrollService, $period): void {
                        $payrollService->upsertEmployeeRecord($employeeId, $period, $records);
                    }
                );
            } catch (\Throwable $e) {
                Log::error('CalculateAppointmentCommission: failed to auto-assign payroll', [
                    'appointment_id' => $this->appointmentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
