<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
    /**
     * Determine whether the user can view any payroll periods.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->isBusinessAdmin() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view the payroll period.
     */
    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->isSuperAdmin() || ($user->isBusinessAdmin() && $user->business_id === $period->business_id);
    }

    /**
     * Determine whether the user can create payroll periods.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can update (approve/manage) the payroll period.
     * Requires the period to be open and the user to belong to the same business.
     */
    public function update(User $user, PayrollPeriod $period): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin()
            && $user->business_id === $period->business_id
            && $period->status === 'open';
    }

    /**
     * Determine whether the user can generate records for the payroll period.
     */
    public function generate(User $user, PayrollPeriod $period): bool
    {
        return $this->update($user, $period);
    }
}
