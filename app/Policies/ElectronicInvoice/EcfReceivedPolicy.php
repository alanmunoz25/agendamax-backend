<?php

declare(strict_types=1);

namespace App\Policies\ElectronicInvoice;

use App\Models\EcfReceived;
use App\Models\User;

/**
 * Authorization policy for EcfReceived (supplier e-CFs received by the business).
 *
 * Approving (ACECF) or rejecting received e-CFs has fiscal implications;
 * only business_admin may perform those actions.
 */
class EcfReceivedPolicy
{
    /**
     * Determine whether the user can list received e-CFs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isBusinessAdmin() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view a specific received e-CF.
     */
    public function view(User $user, EcfReceived $received): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $received->business_id;
    }

    /**
     * Determine whether the user can approve (send ACECF) a received e-CF.
     */
    public function approve(User $user, EcfReceived $received): bool
    {
        return $user->isBusinessAdmin() && $user->business_id === $received->business_id;
    }

    /**
     * Determine whether the user can reject a received e-CF.
     */
    public function reject(User $user, EcfReceived $received): bool
    {
        return $user->isBusinessAdmin() && $user->business_id === $received->business_id;
    }
}
