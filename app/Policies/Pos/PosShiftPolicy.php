<?php

declare(strict_types=1);

namespace App\Policies\Pos;

use App\Models\PosShift;
use App\Models\User;

/**
 * Authorization policy for POS shifts.
 *
 * A cashier (employee) may only close their own shift.
 * Business admins can view and close any shift for their business.
 */
class PosShiftPolicy
{
    /**
     * Determine whether the user can view a specific shift.
     */
    public function view(User $user, PosShift $shift): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id === $shift->business_id;
        }

        if ($user->isEmployee()) {
            // Employees can only view their own shift
            return $user->business_id === $shift->business_id
                && $user->id === $shift->cashier_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create (open) a shift.
     * Both employees and business admins may open a shift at the counter.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isBusinessAdmin()
            || $user->isEmployee();
    }

    /**
     * Determine whether the user can close (finalize) a shift.
     * Employees can only close their own shift; business admins can close any.
     */
    public function close(User $user, PosShift $shift): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id === $shift->business_id;
        }

        if ($user->isEmployee()) {
            return $user->business_id === $shift->business_id
                && $user->id === $shift->cashier_id;
        }

        return false;
    }
}
