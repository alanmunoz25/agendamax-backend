<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\CommissionRule;
use App\Models\User;

/**
 * Authorization policy for CommissionRule.
 *
 * Commission rules define how earnings are calculated for employees.
 * Only business admins may modify them — employees and clients are denied.
 */
class CommissionRulePolicy
{
    /**
     * Determine whether the user can list commission rules for their business.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isBusinessAdmin() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view a specific commission rule.
     */
    public function view(User $user, CommissionRule $rule): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $rule->business_id;
    }

    /**
     * Determine whether the user can create a commission rule.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can update a commission rule.
     */
    public function update(User $user, CommissionRule $rule): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $rule->business_id;
    }

    /**
     * Determine whether the user can delete a commission rule.
     */
    public function delete(User $user, CommissionRule $rule): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $rule->business_id;
    }
}
