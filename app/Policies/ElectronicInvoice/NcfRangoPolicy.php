<?php

declare(strict_types=1);

namespace App\Policies\ElectronicInvoice;

use App\Models\NcfRango;
use App\Models\User;

/**
 * Authorization policy for NcfRango (DGII NCF ranges).
 *
 * Modifying NCF ranges is critical — incorrect changes break DGII compliance.
 * Only business_admin (owner of the range) may create/update/delete ranges.
 * Super admins may view ranges across businesses but cannot create them.
 */
class NcfRangoPolicy
{
    /**
     * Determine whether the user can list NCF ranges.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isBusinessAdmin() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view a specific NCF range.
     */
    public function view(User $user, NcfRango $ncfRango): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ncfRango->business_id;
    }

    /**
     * Determine whether the user can create a new NCF range.
     * Only business_admin of the owning business may add new ranges.
     */
    public function create(User $user): bool
    {
        return $user->isBusinessAdmin() && $user->business_id !== null;
    }

    /**
     * Determine whether the user can update (modify) an NCF range.
     */
    public function update(User $user, NcfRango $ncfRango): bool
    {
        return $user->isBusinessAdmin() && $user->business_id === $ncfRango->business_id;
    }

    /**
     * Determine whether the user can delete an NCF range.
     */
    public function delete(User $user, NcfRango $ncfRango): bool
    {
        return $user->isBusinessAdmin() && $user->business_id === $ncfRango->business_id;
    }
}
