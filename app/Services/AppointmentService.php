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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        private readonly CalendarProviderInterface $calendarProvider
    ) {}

    /**
     * Create a new appointment with full validation.
     * Supports both single-service (legacy) and multi-service formats.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \Exception
     */
    public function createAppointment(array $data): Appointment
    {
        // Normalize single-service format to multi-service format
        $serviceEntries = $this->normalizeServiceEntries($data);

        return $this->createMultiServiceAppointment($data, $serviceEntries);
    }

    /**
     * Normalize input data into a consistent array of service entries.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{service_id: int, employee_id: int|null}>
     */
    private function normalizeServiceEntries(array $data): array
    {
        if (isset($data['services']) && is_array($data['services'])) {
            return array_map(fn (array $entry) => [
                'service_id' => (int) $entry['service_id'],
                'employee_id' => isset($entry['employee_id']) ? (int) $entry['employee_id'] : null,
            ], $data['services']);
        }

        return [[
            'service_id' => (int) $data['service_id'],
            'employee_id' => isset($data['employee_id']) ? (int) $data['employee_id'] : null,
        ]];
    }

    /**
     * Create an appointment with one or more services.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{service_id: int, employee_id: int|null}>  $serviceEntries
     *
     * @throws \Exception
     */
    private function createMultiServiceAppointment(array $data, array $serviceEntries): Appointment
    {
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $maxDuration = 0;
        $businessId = null;

        // Validate each service-employee pair and determine duration
        $pivotData = [];
        foreach ($serviceEntries as $entry) {
            $service = Service::findOrFail($entry['service_id']);
            $businessId ??= $service->business_id;

            if ($entry['employee_id'] !== null) {
                $employee = Employee::findOrFail($entry['employee_id']);

                if (! $employee->services()->where('services.id', $service->id)->exists()) {
                    throw new \Exception("Employee cannot provide service: {$service->name}");
                }
            }

            $maxDuration = max($maxDuration, $service->duration);

            $pivotData[] = [
                'service' => $service,
                'employee_id' => $entry['employee_id'],
            ];
        }

        $scheduledUntil = $scheduledAt->copy()->addMinutes($maxDuration);

        // Check availability for each employee
        foreach ($pivotData as $entry) {
            if ($entry['employee_id'] !== null) {
                if (! $this->checkAvailability($entry['employee_id'], $scheduledAt, $scheduledUntil)) {
                    $employee = Employee::find($entry['employee_id']);
                    throw new \Exception("Time slot not available for employee: {$employee?->user?->name}");
                }
            }
        }

        // Use first entry for backward-compatible columns
        $firstEntry = $pivotData[0];

        return DB::transaction(function () use ($data, $scheduledAt, $scheduledUntil, $businessId, $firstEntry, $pivotData) {
            $appointment = Appointment::create([
                'business_id' => $businessId ?? Auth::user()->business_id,
                'service_id' => $firstEntry['service']->id,
                'employee_id' => $firstEntry['employee_id'],
                'client_id' => $data['client_id'],
                'scheduled_at' => $scheduledAt,
                'scheduled_until' => $scheduledUntil,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            // Attach services via pivot table
            foreach ($pivotData as $entry) {
                $appointment->services()->attach($entry['service']->id, [
                    'employee_id' => $entry['employee_id'],
                ]);
            }

            $appointment->load(['service', 'employee', 'client', 'services']);

            $this->syncCreate($appointment);

            return $appointment;
        });
    }

    /**
     * Check if an employee is available for a given time slot.
     */
    public function checkAvailability(int $employeeId, Carbon $startTime, Carbon $endTime): bool
    {
        $employee = Employee::find($employeeId);

        if (! $employee) {
            return false;
        }

        // Validate against the employee's weekly schedule (always enforced, regardless of environment)
        if (! $this->isWithinEmployeeSchedule($employee, $startTime, $endTime)) {
            Log::info('Outside employee schedule', [
                'employee_id' => $employeeId,
                'start' => $startTime,
                'end' => $endTime,
            ]);

            return false;
        }

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

        if (app()->environment('local')) {
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
     * Slots are derived from the employee's EmployeeSchedule records for that day.
     * If the employee has no schedule (or is marked unavailable), returns empty.
     *
     * @param  string  $date  Format: Y-m-d
     * @return Collection<int, string> Time strings in "HH:MM:SS" format
     */
    public function getAvailableSlots(int $employeeId, int $serviceId, string $date): Collection
    {
        $service = Service::findOrFail($serviceId);
        $employee = Employee::findOrFail($employeeId);

        // Validate employee can provide this service
        if (! $employee->services()->where('services.id', $service->id)->exists()) {
            return collect();
        }

        // Resolve the day-of-week for this date (0=Sunday … 6=Saturday, matching the migration comment)
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        // Load active schedule windows for this employee on this day
        $schedules = $employee->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->get();

        // Employee does not work on this day — return empty
        if ($schedules->isEmpty()) {
            return collect();
        }

        // Get existing appointments for this employee on this date
        $appointments = Appointment::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('scheduled_at', $date)
            ->orderBy('scheduled_at')
            ->get(['scheduled_at', 'scheduled_until']);

        $slotDuration = $service->duration;
        $availableSlots = collect();

        // Iterate every schedule window (supports split shifts, e.g. 09-12 + 14-18)
        foreach ($schedules as $schedule) {
            $windowStart = Carbon::parse($date.' '.$schedule->start_time.':00');
            $windowEnd = Carbon::parse($date.' '.$schedule->end_time.':00');

            $externalBusy = collect();

            if (! app()->environment('local')) {
                try {
                    $busySlots = $this->calendarProvider->listBusySlots($employee, $windowStart, $windowEnd);
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

            $currentTime = $windowStart->copy();

            while ($currentTime->copy()->addMinutes($slotDuration)->lte($windowEnd)) {
                $slotEnd = $currentTime->copy()->addMinutes($slotDuration);

                // Check if this slot conflicts with any appointment or external busy period
                $hasConflict = $appointments->contains(function ($appointment) use ($currentTime, $slotEnd) {
                    return $currentTime->lt($appointment->scheduled_until) && $slotEnd->gt($appointment->scheduled_at);
                }) || $externalBusy->contains(function (array $busy) use ($currentTime, $slotEnd) {
                    return $currentTime->lt($busy['end']) && $slotEnd->gt($busy['start']);
                });

                if (! $hasConflict) {
                    $availableSlots->push($currentTime->format('H:i:s'));
                }

                // Move to next slot (30-minute increments)
                $currentTime->addMinutes(30);
            }
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

        // Check availability at new time (skip if no employee assigned)
        if ($appointment->employee_id !== null && ! $this->checkAvailability($appointment->employee_id, $newStart, $newEnd)) {
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

    /**
     * Check whether a time window falls within at least one of the employee's
     * active schedule records for that day.
     *
     * The service window must be fully contained within a single schedule window
     * (no spanning across break periods or outside working hours).
     */
    private function isWithinEmployeeSchedule(Employee $employee, Carbon $startTime, Carbon $endTime): bool
    {
        $dayOfWeek = $startTime->dayOfWeek;

        $schedules = $employee->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->get();

        if ($schedules->isEmpty()) {
            return false;
        }

        $startHis = $startTime->format('H:i:s');
        $endHis = $endTime->format('H:i:s');

        return $schedules->contains(function ($schedule) use ($startHis, $endHis) {
            // Pad accessor values (HH:MM) to HH:MM:SS for consistent string comparison
            $scheduleStart = strlen($schedule->start_time) === 5 ? $schedule->start_time.':00' : $schedule->start_time;
            $scheduleEnd = strlen($schedule->end_time) === 5 ? $schedule->end_time.':00' : $schedule->end_time;

            return $scheduleStart <= $startHis && $scheduleEnd >= $endHis;
        });
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
