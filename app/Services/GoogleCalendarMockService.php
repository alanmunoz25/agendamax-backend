<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CalendarProviderInterface;
use App\Models\Appointment;
use App\Models\Employee;
use Carbon\CarbonInterface;

/**
 * Lightweight mock implementation for local demos without hitting Google APIs.
 */
class GoogleCalendarMockService implements CalendarProviderInterface
{
    public function listBusySlots(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $slotLength = 30; // minutes

        return [
            [
                'start' => $from->copy()->addMinutes(60),
                'end' => $from->copy()->addMinutes(60 + $slotLength),
            ],
            [
                'start' => $from->copy()->addMinutes(180),
                'end' => $from->copy()->addMinutes(180 + $slotLength),
            ],
            [
                'start' => $from->copy()->addMinutes(300),
                'end' => $from->copy()->addMinutes(300 + $slotLength),
            ],
        ];
    }

    public function createEvent(Appointment $appointment): ?string
    {
        return uniqid('mock_event_', true);
    }

    public function updateEvent(Appointment $appointment): bool
    {
        return true;
    }

    public function deleteEvent(Appointment $appointment): bool
    {
        return true;
    }
}
