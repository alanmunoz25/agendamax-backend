<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

class ServiceCategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, ServiceCategory $serviceCategory): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $serviceCategory->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ServiceCategory $serviceCategory): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $serviceCategory->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ServiceCategory $serviceCategory): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $serviceCategory->business_id === $user->business_id;
        }

        return false;
    }
}
