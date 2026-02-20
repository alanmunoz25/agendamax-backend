<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Determine whether the user can view any services.
     */
    public function viewAny(User $user): bool
    {
        // Super admin can view all services
        if ($user->isSuperAdmin()) {
            return true;
        }

        // All authenticated users with a business can view services
        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the service.
     */
    public function view(User $user, Service $service): bool
    {
        // Super admin can view any service
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Users can view services in their business
        return $service->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create services.
     */
    public function create(User $user): bool
    {
        // Super admin can create services
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Only business admin can create services
        if ($user->isBusinessAdmin()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the service.
     */
    public function update(User $user, Service $service): bool
    {
        // Super admin can update any service
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can update services in their business
        if ($user->isBusinessAdmin()) {
            return $service->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the service.
     */
    public function delete(User $user, Service $service): bool
    {
        // Super admin can delete any service
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can delete services in their business
        if ($user->isBusinessAdmin()) {
            return $service->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the service.
     */
    public function restore(User $user, Service $service): bool
    {
        // Only super admin and business admin can restore
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $service->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the service.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        // Only super admin can force delete
        return $user->isSuperAdmin();
    }
}
