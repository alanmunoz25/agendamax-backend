<?php

declare(strict_types=1);

namespace App\Policies\ElectronicInvoice;

use App\Models\Ecf;
use App\Models\User;

/**
 * Authorization policy for emitted e-CFs (Comprobantes Fiscales Electrónicos).
 *
 * Employees can view the ECF history for their business but cannot create,
 * cancel, void, or resend e-CFs — those operations belong to business admins.
 */
class EcfPolicy
{
    /**
     * Determine whether the user can list e-CFs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isBusinessAdmin() && $user->business_id !== null)
            || ($user->isEmployee() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view a specific e-CF.
     */
    public function view(User $user, Ecf $ecf): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id === $ecf->business_id;
        }

        if ($user->isEmployee()) {
            return $user->business_id === $ecf->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create (emit) a new e-CF.
     * Employees are explicitly denied — only business admins can emit.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can cancel an e-CF.
     */
    public function cancel(User $user, Ecf $ecf): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ecf->business_id;
    }

    /**
     * Determine whether the user can void / issue a credit note for an e-CF.
     */
    public function voidIssue(User $user, Ecf $ecf): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ecf->business_id;
    }

    /**
     * Determine whether the user can resend an e-CF to DGII.
     */
    public function resend(User $user, Ecf $ecf): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ecf->business_id;
    }
}
