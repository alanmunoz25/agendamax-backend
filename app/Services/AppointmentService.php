<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CalendarProviderInterface;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        private readonly CalendarProviderInterface $calendarProvider
    ) {}

    /**
     * Create a new appointment with full validation.
     *
     * @param  array{service_id: int, employee_id: int, client_id: int, scheduled_at: string, notes?: string}  $data
     *
     * @throws \Exception
     */
    public function createAppointment(array $data): Appointment
    {
        $service = Service::findOrFail($data['service_id']);
        $employee = Employee::findOrFail($data['employee_id']);

        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $scheduledUntil = $scheduledAt->copy()->addMinutes($service->duration);

        // Validate employee can provide this service
        if (! $employee->services()->where('services.id', $service->id)->exists()) {
            throw new \Exception('Employee cannot provide this service');
        }

        // Check availability
        if (! $this->checkAvailability($employee->id, $scheduledAt, $scheduledUntil)) {
            throw new \Exception('Time slot not available');
        }

        // Create appointment
        $appointment = Appointment::create([
            'business_id' => $service->business_id ?? Auth::user()->business_id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'client_id' => $data['client_id'],
            'scheduled_at' => $scheduledAt,
            'scheduled_until' => $scheduledUntil,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $appointment->load(['service', 'employee', 'client']);

        $this->syncCreate($appointment);

        return $appointment;
    }

    /**
     * Check if an employee is available for a given time slot.
     */
    public function checkAvailability(int $employeeId, Carbon $startTime, Carbon $endTime): bool
    {
        // Check for conflicting appointments
        $hasConflict = Appointment::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('scheduled_at', [$startTime, $endTime])
                    ->orWhereBetween('scheduled_until', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('scheduled_at', '<=', $startTime)
                            ->where('scheduled_until', '>=', $endTime);
                    });
            })
            ->exists();

        if ($hasConflict) {
            Log::info('Local appointment conflict detected', [
                'employee_id' => $employeeId,
                'start' => $startTime,
                'end' => $endTime,
            ]);
            return false;
        }

        $employee = Employee::find($employeeId);

        if (! $employee || app()->environment('local')) {
            return true;
        }

        try {
            $externalBusy = $this->calendarProvider->listBusySlots($employee, $startTime, $endTime);

            foreach ($externalBusy as $slot) {
                if ($startTime->lt($slot['end']) && $endTime->gt($slot['start'])) {
                    Log::info('External busy slot prevents booking', [
                        'employee_id' => $employeeId,
                        'start' => $startTime,
                        'end' => $endTime,
                        'busy_start' => $slot['start'],
                        'busy_end' => $slot['end'],
                    ]);
                    return false;
                }
            }
        } catch (RuntimeException $exception) {
            Log::info('Skipping Google availability check', [
                'employee_id' => $employeeId,
                'reason' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Google availability lookup failed', [
                'employee_id' => $employeeId,
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Get available time slots for an employee on a given date.
     *
     * @param  string  $date  Format: Y-m-d
     * @return Collection<int, array{start: string, end: string}>
     */
    public function getAvailableSlots(int $employeeId, int $serviceId, string $date): Collection
    {
        $service = Service::findOrFail($serviceId);
        $employee = Employee::findOrFail($employeeId);

        // Validate employee can provide this service
        if (! $employee->services()->where('services.id', $service->id)->exists()) {
            return collect();
        }

        // Business hours (TODO: Make this configurable per business)
        $businessStart = Carbon::parse($date.' 09:00:00');
        $businessEnd = Carbon::parse($date.' 18:00:00');

        // Get existing appointments for this employee on this date
        $appointments = Appointment::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('scheduled_at', $date)
            ->orderBy('scheduled_at')
            ->get(['scheduled_at', 'scheduled_until']);

        $externalBusy = collect();

        if (! app()->environment('local')) {
            try {
                $busySlots = $this->calendarProvider->listBusySlots($employee, $businessStart, $businessEnd);
                $externalBusy = collect($busySlots);
            } catch (RuntimeException $exception) {
                Log::info('Skipping Google busy slots for availability', [
                    'employee_id' => $employeeId,
                    'reason' => $exception->getMessage(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Unable to fetch Google busy slots for availability', [
                    'employee_id' => $employeeId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $availableSlots = collect();
        $currentTime = $businessStart->copy();
        $slotDuration = $service->duration;

        while ($currentTime->copy()->addMinutes($slotDuration)->lte($businessEnd)) {
            $slotEnd = $currentTime->copy()->addMinutes($slotDuration);

            // Check if this slot conflicts with any appointment
            $hasConflict = $appointments->contains(function ($appointment) use ($currentTime, $slotEnd) {
                return $currentTime->lt($appointment->scheduled_until) && $slotEnd->gt($appointment->scheduled_at);
            }) || $externalBusy->contains(function (array $busy) use ($currentTime, $slotEnd) {
                return $currentTime->lt($busy['end']) && $slotEnd->gt($busy['start']);
            });

            if (! $hasConflict) {
                $availableSlots->push([
                    'start' => $currentTime->toIso8601String(),
                    'end' => $slotEnd->toIso8601String(),
                ]);
            }

            // Move to next slot (30-minute increments)
            $currentTime->addMinutes(30);
        }

        return $availableSlots;
    }

    /**
     * Cancel an appointment.
     *
     *
     * @throws \Exception
     */
    public function cancelAppointment(int $appointmentId, ?string $reason = null): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Can only cancel pending or confirmed appointments
        if (! in_array($appointment->status, ['pending', 'confirmed'])) {
            throw new \Exception('Cannot cancel appointment with status: '.$appointment->status);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        $appointment->refresh();
        $this->syncDelete($appointment);

        return $appointment;
    }

    /**
     * Reschedule an appointment to a new time.
     *
     * @param  string  $newScheduledAt  ISO 8601 format
     *
     * @throws \Exception
     */
    public function rescheduleAppointment(int $appointmentId, string $newScheduledAt): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Can only reschedule pending or confirmed appointments
        if (! in_array($appointment->status, ['pending', 'confirmed'])) {
            throw new \Exception('Cannot reschedule appointment with status: '.$appointment->status);
        }

        $newStart = Carbon::parse($newScheduledAt);
        $newEnd = $newStart->copy()->addMinutes($appointment->service->duration);

        // Check availability at new time
        if (! $this->checkAvailability($appointment->employee_id, $newStart, $newEnd)) {
            throw new \Exception('New time slot not available');
        }

        $appointment->update([
            'scheduled_at' => $newStart,
            'scheduled_until' => $newEnd,
        ]);

        $appointment->refresh();
        $this->syncUpdate($appointment);

        return $appointment;
    }

    private function syncCreate(Appointment $appointment): void
    {
        try {
            $this->calendarProvider->createEvent($appointment);
        } catch (RuntimeException $exception) {
            Log::info('Skipping Google event creation', [
                'appointment_id' => $appointment->id,
                'reason' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to create Google event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function syncUpdate(Appointment $appointment): void
    {
        try {
            $this->calendarProvider->updateEvent($appointment);
        } catch (RuntimeException $exception) {
            Log::info('Skipping Google event update', [
                'appointment_id' => $appointment->id,
                'reason' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to update Google event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function syncDelete(Appointment $appointment): void
    {
        try {
            $this->calendarProvider->deleteEvent($appointment);
        } catch (RuntimeException $exception) {
            Log::info('Skipping Google event deletion', [
                'appointment_id' => $appointment->id,
                'reason' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to delete Google event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
