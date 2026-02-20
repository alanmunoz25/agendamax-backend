<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any employees.
     */
    public function viewAny(User $user): bool
    {
        // Super admin can view all employees
        if ($user->isSuperAdmin()) {
            return true;
        }

        // All authenticated users with a business can view employees
        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the employee.
     */
    public function view(User $user, Employee $employee): bool
    {
        // Super admin can view any employee
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Users can view employees in their business
        return $employee->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $user): bool
    {
        // Super admin can create employees
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Only business admin can create employees
        if ($user->isBusinessAdmin()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the employee.
     */
    public function update(User $user, Employee $employee): bool
    {
        // Super admin can update any employee
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can update employees in their business
        if ($user->isBusinessAdmin()) {
            return $employee->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the employee.
     */
    public function delete(User $user, Employee $employee): bool
    {
        // Super admin can delete any employee
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can delete employees in their business
        if ($user->isBusinessAdmin()) {
            return $employee->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the employee.
     */
    public function restore(User $user, Employee $employee): bool
    {
        // Only super admin and business admin can restore
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $employee->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the employee.
     */
    public function forceDelete(User $user, Employee $employee): bool
    {
        // Only super admin can force delete
        return $user->isSuperAdmin();
    }
}
