<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any appointments.
     */
    public function viewAny(User $user): bool
    {
        // Super admin can view all appointments
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin and employees can view appointments in their business
        if ($user->isBusinessAdmin() || $user->isEmployee()) {
            return $user->business_id !== null;
        }

        // Clients can view their own appointments
        if ($user->isClient()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can view the appointment.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        // Super admin can view any appointment
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can view appointments in their business
        if ($user->isBusinessAdmin()) {
            return $appointment->business_id === $user->business_id;
        }

        // Employee can only view appointments assigned to them
        if ($user->isEmployee()) {
            return $appointment->business_id === $user->business_id
                && $appointment->employee_id === $user->employee?->id;
        }

        // Client can only view their own appointments
        if ($user->isClient()) {
            return $appointment->client_id === $user->id
                && $appointment->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create appointments.
     *
     * When $business is provided (F3 multi-business), client access is gated
     * by enrollment + not blocked. For staff roles, the $business must match
     * the user's business. When $business is null (legacy context), staff and
     * client access falls back to checking user->business_id is set.
     */
    public function create(User $user, ?Business $business = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            if ($business !== null) {
                return $user->business_id === $business->id;
            }

            // Legacy fallback: web admin context without explicit Business model
            return $user->business_id !== null;
        }

        if ($user->isEmployee()) {
            if ($business !== null) {
                return $user->business_id === $business->id;
            }

            // Employees cannot create appointments in legacy context (no explicit Business passed)
            return false;
        }

        if ($user->isClient()) {
            if ($business !== null) {
                return $user->canBookIn($business);
            }

            // Legacy fallback: client with business_id set on user row
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the appointment.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        // Super admin can update any appointment
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can update appointments in their business
        if ($user->isBusinessAdmin()) {
            return $appointment->business_id === $user->business_id;
        }

        // Clients and employees cannot update appointments
        return false;
    }

    /**
     * Determine whether the user can delete (cancel) the appointment.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        // Super admin can delete any appointment
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can delete appointments in their business
        if ($user->isBusinessAdmin()) {
            return $appointment->business_id === $user->business_id;
        }

        // Client can cancel their own appointments
        if ($user->isClient()) {
            return $appointment->client_id === $user->id
                && $appointment->business_id === $user->business_id
                && $appointment->status !== 'completed'; // Cannot cancel completed appointments
        }

        // Employees cannot delete appointments
        return false;
    }

    /**
     * Determine whether the user can restore the appointment.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        // Only super admin and business admin can restore
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $appointment->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the appointment.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        // Only super admin can force delete
        return $user->isSuperAdmin();
    }
}
