<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\EmployeeAvailabilityChanged;
use App\Models\Employee;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        // Broadcast when new employee is added
        EmployeeAvailabilityChanged::dispatch($employee);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        // Broadcast if is_active status changed or services were modified
        if ($employee->isDirty(['is_active'])) {
            EmployeeAvailabilityChanged::dispatch($employee);
        }
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        // Broadcast when employee is removed/deactivated
        EmployeeAvailabilityChanged::dispatch($employee);
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        // Broadcast when employee is restored
        EmployeeAvailabilityChanged::dispatch($employee);
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
