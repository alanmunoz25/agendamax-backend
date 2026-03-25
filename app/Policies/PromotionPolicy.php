<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    /**
     * Determine whether the user can view any promotions.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the promotion.
     */
    public function view(User $user, Promotion $promotion): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $promotion->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create promotions.
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
     * Determine whether the user can update the promotion.
     */
    public function update(User $user, Promotion $promotion): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $promotion->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the promotion.
     */
    public function delete(User $user, Promotion $promotion): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $promotion->business_id === $user->business_id;
        }

        return false;
    }
}
