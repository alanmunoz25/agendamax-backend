<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can manage users (role/business assignment).
     */
    public function manageUsers(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can view any clients.
     */
    public function viewAny(User $user): bool
    {
        // Super admin can view all clients
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin and employees can view clients in their business
        if ($user->isBusinessAdmin() || $user->isEmployee()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can view the client.
     */
    public function view(User $user, User $client): bool
    {
        // Super admin can view any client
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Clients can view their own profile
        if ($client->id === $user->id) {
            return true;
        }

        // Business admin and employees can view clients in their business
        if ($user->isBusinessAdmin() || $user->isEmployee()) {
            return $client->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create clients.
     */
    public function create(User $user): bool
    {
        // Super admin can create clients
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Only business admin can create clients
        if ($user->isBusinessAdmin()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the client.
     */
    public function update(User $user, User $client): bool
    {
        // Super admin can update any client
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Clients can update their own profile
        if ($client->id === $user->id) {
            return true;
        }

        // Business admin can update clients in their business
        if ($user->isBusinessAdmin()) {
            return $client->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the client.
     */
    public function delete(User $user, User $client): bool
    {
        // Super admin can delete any client
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Business admin can delete clients in their business
        if ($user->isBusinessAdmin()) {
            return $client->business_id === $user->business_id;
        }

        return false;
    }
}
