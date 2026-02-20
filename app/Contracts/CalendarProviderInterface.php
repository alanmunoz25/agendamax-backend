<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Appointment;
use App\Models\Employee;
use Carbon\CarbonInterface;

/**
 * Contract for interacting with calendar providers (Google, mocks, etc.).
 */
interface CalendarProviderInterface
{
    /**
     * Retrieve the busy slots for an employee within a period.
     *
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface}>
     */
    public function listBusySlots(Employee $employee, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Create a calendar event for the given appointment.
     */
    public function createEvent(Appointment $appointment): ?string;

    /**
     * Update the event for the appointment.
     */
    public function updateEvent(Appointment $appointment): bool;

    /**
     * Delete the event for the appointment.
     */
    public function deleteEvent(Appointment $appointment): bool;
}
