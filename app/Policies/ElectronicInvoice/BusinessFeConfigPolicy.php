<?php

declare(strict_types=1);

namespace App\Policies\ElectronicInvoice;

use App\Models\BusinessFeConfig;
use App\Models\User;

/**
 * Authorization policy for BusinessFeConfig (FE module settings).
 *
 * Certificate upload is restricted to business_admin only — super_admin cannot
 * upload certificates on behalf of a business to prevent cross-tenant confusion.
 */
class BusinessFeConfigPolicy
{
    /**
     * Determine whether the user can view the FE configuration.
     */
    public function view(User $user, BusinessFeConfig $config): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $config->business_id;
    }

    /**
     * Determine whether the user can update the FE configuration.
     * Employees are never allowed to modify billing settings.
     */
    public function update(User $user, BusinessFeConfig $config): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $config->business_id;
    }

    /**
     * Determine whether the user can upload a digital certificate.
     * Restricted to the business_admin of the owning business only.
     */
    public function uploadCertificate(User $user, BusinessFeConfig $config): bool
    {
        return $user->isBusinessAdmin() && $user->business_id === $config->business_id;
    }
}
