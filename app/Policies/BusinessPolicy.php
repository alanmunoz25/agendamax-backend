<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Determine whether the user can view any businesses.
     */
    public function viewAny(User $user): bool
    {
        // Only super admin can view all businesses
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the business.
     */
    public function view(User $user, Business $business): bool
    {
        // Super admin can view any business
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Users can view their own business
        return $business->id === $user->business_id;
    }

    /**
     * Determine whether the user can create businesses.
     */
    public function create(User $user): bool
    {
        // Only super admin can create businesses
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the business.
     */
    public function update(User $user, Business $business): bool
    {
        // Super admin can update any business
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can update their own business
        if ($user->isBusinessAdmin()) {
            return $business->id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the business.
     */
    public function delete(User $user, Business $business): bool
    {
        // Only super admin can delete businesses
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the business.
     */
    public function restore(User $user, Business $business): bool
    {
        // Only super admin can restore businesses
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the business.
     */
    public function forceDelete(User $user, Business $business): bool
    {
        // Only super admin can force delete businesses
        return $user->isSuperAdmin();
    }
}
